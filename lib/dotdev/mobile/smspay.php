<?php
/*****
 * Version 1.2.2018-11-19
**/
namespace dotdev\mobile;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\nexus\service as nexus_service;
use \dotdev\traffic\event as traffic_event;

class smspay {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_mobile', [

			// SMSPay queries (using scalar operants for faster querying)
			's_smspay'				=> "SELECT
											s.*, m.operatorID, l.domainID, l.pageID,
											(SELECT COUNT(*) FROM `smspay_mo` WHERE `smspayID` = s.smspayID) as `mo`,
											(SELECT COUNT(*) FROM `smspay_mo` mo INNER JOIN `smspay_mt` mt ON mt.moID = mo.moID WHERE mt.smspayID = s.smspayID) as `mo_bound`,
											(SELECT COUNT(*) FROM `smspay_mt` WHERE `smspayID` = s.smspayID) as `mt`,
											(SELECT COUNT(*) FROM `smspay_mt` WHERE `smspayID` = s.smspayID AND `paidTime` != '0000-00-00 00:00:00') as `mt_paid`
										FROM `smspay` s
										INNER JOIN `mobile` m ON m.ID = s.mobileID
										LEFT JOIN `persistlink` l ON l.persistID = s.persistID
										WHERE s.smspayID = ?
										ORDER BY s.smspayID DESC
										LIMIT 1
										",
			's_smspay_unique'		=> ['s_smspay', ['s.smspayID = ?' => 's.mobileID = ? AND s.productID = ? AND s.persistID = ?']],
			'l_smspay_by_mobileID'	=> ['s_smspay', ['s.smspayID = ?' => 's.mobileID = ?', 'LIMIT 1' => '']],
			'l_smspay_by_mobprod'	=> ['s_smspay', ['s.smspayID = ?' => 's.mobileID = ? AND s.productID = ?', 'LIMIT 1' => '']],
			's_smspay_last_mobprod'	=> ['s_smspay', ['s.smspayID = ?' => 's.mobileID = ? AND s.productID = ?']],

			's_spnostat'			=> "SELECT
											s.*, m.operatorID
										FROM `smspay` s
										INNER JOIN `mobile` m ON m.ID = s.mobileID
										WHERE s.smspayID = ?
										ORDER BY s.smspayID DESC
										LIMIT 1
										",
			's_spnostat_unique'		=> ['s_spnostat', ['s.smspayID = ?' => 's.mobileID = ? AND s.productID = ? AND s.persistID = ?']],
			'l_spnostat_by_mobileID'=> ['s_spnostat', ['s.smspayID = ?' => 's.mobileID = ?', 'LIMIT 1' => '']],
			'l_spnostat_by_mobprod'	=> ['s_spnostat', ['s.smspayID = ?' => 's.mobileID = ? AND s.productID = ?', 'LIMIT 1' => '']],
			's_spnostat_last_mobprod'=> ['s_spnostat', ['s.smspayID = ?' => 's.mobileID = ? AND s.productID = ?']],

			's_smspay_mo_unbound'	=> "SELECT mo.moID, mo.createTime
										FROM `smspay_mo` mo
										INNER JOIN `smspay` s ON s.smspayID = mo.smspayID
										LEFT JOIN `smspay_mt` mt ON mt.moID = mo.moID
										WHERE mo.smspayID = ? AND s.stopTime = '0000-00-00 00:00:00' AND (s.restartTime = '0000-00-00 00:00:00' OR mo.createTime >= s.restartTime) AND mt.mtID IS NULL
										ORDER BY mo.createTime ASC
										",

			'i_smspay'				=> "INSERT INTO `smspay` (`mobileID`,`productID`,`persistID`,`createTime`) VALUES (?,?,?,?)",
			'u_smspay_restart'		=> "UPDATE `smspay` SET `stopTime` = ?, `restartTime` = ? WHERE `smspayID` = ?",
			'u_smspay_mobile_stop'	=> "UPDATE `smspay` SET `stopTime` = ? WHERE `mobileID` = ? AND `stopTime` = '0000-00-00 00:00:00'",
			'u_smspay_product_stop'	=> "UPDATE `smspay` SET `stopTime` = ? WHERE `mobileID` = ? AND `productID` = ? AND `stopTime` = '0000-00-00 00:00:00'",

			// SMSPay MO queries
			's_smspay_mo'			=> "SELECT * FROM `smspay_mo` WHERE `moID` = ? LIMIT 1",
			'i_smspay_mo'			=> "INSERT INTO `smspay_mo` (`smspayID`,`createTime`) VALUES (?,?)",

			// SMSPay MT queries
			's_smspay_mt'			=> "SELECT * FROM `smspay_mt` WHERE `mtID` = ? LIMIT 1",
			'i_smspay_mt'			=> "INSERT INTO `smspay_mt` (`smspayID`,`createTime`,`moID`,`paidTime`,`paytries`) VALUES (?,?,?,?,?)",
			'u_smspay_mt'			=> "UPDATE `smspay_mt` SET `paidTime` = ?, `paytries` = ? WHERE `mtID` = ?",
			'u_smspay_mt_paid'		=> "UPDATE `smspay_mt` SET `paidTime` = ?, `paytries` = `paytries` + 1 WHERE `mtID` = ?",
			'u_smspay_mt_failed'	=> "UPDATE `smspay_mt` SET `paytries` = `paytries` + 1 WHERE `mtID` = ?",


			// dummy_upgrade queries
			'u_smspay_mobileID'		=> "UPDATE `smspay` SET `mobileID` = ? WHERE `mobileID` = ?",
			]];
		}


	/* Main */
	public static function get_smspay($req = []){

		// alternative
		$alt = h::eX($req, [
			'smspayID'			=> '~1,4294967295/i',
			'moID'				=> '~1,4294967295/i',
			'mtID'				=> '~1,4294967295/i',
			'mobileID'			=> '~1,4294967295/i',
			'persistID'			=> '~0,18446744073709551615/i',
			'productID'			=> '~1,65535/i',
			'productID_list'	=> '~!empty/a',
			'last_only'			=> '~/b',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'with_unbound_mo'	=> '~/b',
			'no_stat'			=> '~/b',
			], $error, true);

		// check productID_list
		if(isset($alt['productID_list'])){
			foreach($alt['productID_list'] as $x){
				if(!h::is($x, '~1,65535/i')){
					$error[] = 'productID_list';
					break;
					}
				}
			}

		// on error
		if($error) return self::response(400, $error);


		// define function to alter result on success
		$on_success_alter = function($result) use ($alt, $opt){

			// make list (of objects)
			$list = is_array($result) ? $result : [$result];

			// for each entry
			foreach($list as $entry){

				// add stopped status
				$entry->stopped = ($entry->stopTime !== '0000-00-00 00:00:00');
				}

			// if open MTs should not added to result
			if(!empty($opt['with_unbound_mo'])){

				// for each entry
				foreach($list as $entry){

					// extend entry
					$entry->unbound_mo = self::pdo('s_smspay_mo_unbound', [$entry->smspayID]);

					// on error
					if($entry->unbound_mo === false) return self::response(560);
					}
				}

			// if billing stats could be added
			if(empty($opt['no_stat'])){

				// for each entry
				foreach($list as $entry){

					// load product
					$res = nexus_service::get_product([
						'type'		=> 'smspay',
						'productID'	=> $entry->productID,
						]);

					// on error
					if($res->status != 200) return self::response(570, $res);

					// take product
					$product = $res->data;

					// define billing type
					$billing_type = in_array($entry->operatorID, h::gX($product->param, 'mt_billing_operator') ?: []) ? 'mt' : 'mo';

					// calculate billings
					$entry->billing = ($billing_type == 'mt') ? $entry->mt_paid : $entry->mo;
					}
				}

			// return result
			return self::response(200, $result);
			};

		// define base query type
		$base_query = !empty($opt['no_stat']) ? 'spnostat' : 'smspay';


		// param order 1: smspayID
		if(isset($alt['smspayID'])){

			// load entry
			$entry = self::pdo('s_'.$base_query, [$alt['smspayID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return $on_success_alter($entry);
			}


		// param order 2: mobileID + productID + persistID
		if(isset($alt['mobileID']) and isset($alt['productID']) and isset($alt['persistID'])){

			// load entry
			$entry = self::pdo('s_'.$base_query.'_unique', [$alt['mobileID'], $alt['productID'], $alt['persistID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return $on_success_alter($entry);
			}


		// param order 3: mobileID (+productID(+last_only)|productID_list)
		if(isset($alt['mobileID'])){

			// param order 3-1: with productID+last_only
			if(isset($alt['productID']) and isset($alt['last_only'])){

				// load entry
				$entry = self::pdo('s_'.$base_query.'_last_mobprod', [$alt['mobileID'], $alt['productID']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// return entry
				return $on_success_alter($entry);
				}

			// param order 3-2: with productID
			if(isset($alt['productID'])){

				// load list
				$list = self::pdo('l_'.$base_query.'_by_mobprod', [$alt['mobileID'], $alt['productID']]);

				// on error
				if($list === false) return self::response(560);

				// return list
				return $on_success_alter($list);
				}

			// param order 3-2|3: only mobileID (with productID_list)
			$list = self::pdo('l_'.$base_query.'_by_mobileID', [$alt['mobileID']]);

			// on error
			if($list === false) return self::response(560);

			// param order 3-2: with productID_list
			if(isset($alt['productID_list'])){

				// filter matching productID in productID_list
				foreach($list as $k => $entry){
					if(!in_array($abo->productID, $filter_product)) unset($list[$k]);
					}
				}

			// return list
			return $on_success_alter($list);
			}


		// param order 4: moID or mtID
		if(isset($alt['moID']) or isset($alt['mtID'])){

			// load entry
			$entry = isset($alt['moID'])
				? self::pdo('s_smspay_mo', [$alt['moID']])
				: self::pdo('s_smspay_mt', [$alt['mtID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// restart request with new information
			return self::get_smspay(['smspayID' => $entry->smspayID]);
			}

		// other request param invalid
		return self::response(400, 'need smspayID|mobileID+productID+persistID|mobileID+productID(+lastonly)|mobileID(+productID_list) param');
		}

	public static function stop_smspay($req = []){

		// mandatory
		$mand = h::eX($req, [
			'mobileID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'productID'		=> '~0,65535/i',
			'stopTime'		=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default
		$opt += [
			'productID'		=> 0,
			'createTime'	=> h::dtstr('now'),
			];

		// update
		$upd = $opt['productID']
			? self::pdo('u_smspay_product_stop', [$opt['createTime'], $mand['mobileID'], $opt['productID']])
			: self::pdo('u_smspay_mobile_stop', [$opt['createTime'], $mand['mobileID']]);

		// on error
		if($upd === false) return self::response(560);

		// return success
		return self::response(204);
		}

	public static function create_mo($req = []){

		// mandatory
		$mand = h::eX($req, [
			'mobileID'		=> '~1,4294967295/i',
			'productID'		=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'persistID'		=> '~0,18446744073709551615/i',
			'createTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// define defaults
		$opt += [
			'persistID'		=> 0,
			'createTime'	=> h::dtstr('now'),
			];


		// find existing
		$res = self::get_smspay([
			'mobileID'		=> $mand['mobileID'],
			'productID'		=> $mand['productID'],
			'persistID'		=> $opt['persistID'],
			]);

		// if not found (or already stopped)
		if($res->status == 404){

			// insert entry
			$new_smspayID = self::pdo('i_smspay', [$mand['mobileID'], $mand['productID'], $opt['persistID'], $opt['createTime']]);

			// on error
			if(!$new_smspayID) return self::response(560);

			// load created
			$res = self::get_smspay([
				'smspayID'	=> $new_smspayID,
				]);
			}

		// on error
		if($res->status != 200) return $res;

		// take entry
		$smspay = $res->data;

		// check if already stopped
		if($smspay->stopped){

			// set new smspay status
			$smspay->stopTime = '0000-00-00 00:00:00';
			$smspay->stopped = false;
			$smspay->restartTime = $opt['createTime'];

			// update entry
			$upd = self::pdo('u_smspay_restart', [$smspay->stopTime, $smspay->restartTime, $smspay->smspayID]);

			// on error
			if($upd === false) return self::response(560);
			}

		// insert mo
		$moID = self::pdo('i_smspay_mo', [$smspay->smspayID, $opt['createTime']]);

		// on error
		if(!$moID) return self::response(560);

		// trigger/update smspay event
		$res = self::trigger_smspay_event([
			'smspayID'	=> $smspay->smspayID,
			'createTime'=> $opt['createTime'],
			'trigger'	=> 'mo',
			]);

		// return success
		return self::response(201, (object)['smspayID'=>$smspay->smspayID, 'moID'=>$moID]);
		}

	public static function create_mt($req = []){

		// mandatory
		$mand = h::eX($req, [
			'smspayID'		=> '~1,4294967295/i',
			'moID'			=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'	=> '~Y-m-d H:i:s/d',
			'paidTime'		=> '~Y-m-d H:i:s/d',
			'paytries'		=> '~0,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'createTime'	=> h::dtstr('now'),
			'paidTime'		=> '0000-00-00 00:00:00',
			'paytries'		=> 0,
			];

		// insert mt
		$mtID = self::pdo('i_smspay_mt', [$mand['smspayID'], $opt['createTime'], $mand['moID'], $opt['paidTime'], $opt['paytries']]);

		// on error
		if(!$mtID) return self::response(560);

		// return success
		return self::response(201, (object)['mtID'=>$mtID]);
		}

	public static function update_mt($req = []){

		// mandatory
		$mand = h::eX($req, [
			'mtID'			=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'status'		=> '~^(?:paid|failed)$',
			'smspayID'		=> '~1,4294967295/i',
			'paidTime'		=> '~Y-m-d H:i:s/d',
			'paytries'		=> '~0,65535/i',
			], $error, true);

		// special check for status update
		if(isset($opt['status'])){
			if(!isset($opt['smspayID']) and !in_array('smspayID', $error)) $error[] = 'smspayID';
			if(isset($opt['paytries'])) $error[] = 'paytries not allowed';
			}

		// on error
		if($error) return self::response(400, $error);

		// param order 1: status update
		if(isset($opt['status'])){

			// if paid
			if($opt['status'] == 'paid'){

				// define default
				$opt += [
					'paidTime'	=> h::dtstr('now'),
					];
				}

			// update
			$upd = ($opt['status'] == 'paid')
				? self::pdo('u_smspay_mt_paid', [$opt['paidTime'], $mand['mtID']])
				: self::pdo('u_smspay_mt_failed', [$mand['mtID']]);

			// on error
			if($upd === false) return self::response(560);

			// trigger/update smspay event
			$res = self::trigger_smspay_event([
				'smspayID'	=> $opt['smspayID'],
				'createTime'=> $opt['paidTime'] ?? h::dtstr('now'),
				'trigger'	=> ($opt['status'] == 'paid') ? 'paid_mt' : 'mt',
				]);
			}

		// param order 2: standard update
		elseif($opt){

			// seach in DB
			$entry = self::pdo('s_smspay_mt', [$mand['mtID']]);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// replace params
			foreach($opt as $k => $v){
				$entry->{$k} = $v;
				}

			// update entry
			$upd = self::pdo('u_smspay_mt', [$entry->paidTime, $entry->paytries, $entry->mtID]);

			// on error
			if($upd === false) return self::response(560);
			}

		// return success
		return self::response(204);
		}


	/* Helper */
	public static function trigger_smspay_event($req = []){

		// mandatory
		$mand = h::eX($req, [
			'smspayID'	=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'trigger'	=> '~^(?:mo|mt|paid_mt)$',
			'createTime'=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'trigger'	=> null,
			'createTime'=> null,
			];


		// load smspay
		$res = self::get_smspay([
			'smspayID'	=> $mand['smspayID'],
			]);

		// on error
		if($res->status == 404) return $res;
		if($res->status != 200) return self::response(570, $res);

		// take entry
		$smspay = $res->data;

		// if no persistID set, return done
		if(!$smspay->persistID) return self::response(204);


		// load product
		$res = nexus_service::get_product([
			'type'		=> 'smspay',
			'productID'	=> $smspay->productID,
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take product
		$product = $res->data;

		// define billing type
		$billing_type = in_array($smspay->operatorID, h::gX($product->param, 'mt_billing_operator') ?: []) ? 'mt' : 'mo';

		// define update
		$update = (object)[
			'type'				=> 'smspay',
			'subevent'			=> $billing_type,
			'status'			=> (($billing_type == 'mo' and $opt['trigger'] == 'mo') or ($billing_type == 'mt' and $opt['trigger'] == 'paid_mt')) ? 'paid' : 'update',
			'createTime'		=> $opt['createTime'] ?: $smspay->createTime,
			'persistID' 		=> $smspay->persistID,
			'smspayID' 			=> $smspay->smspayID,
			'productID'			=> $smspay->productID,
			'mo' 				=> $smspay->mo,
			'mt' 				=> $smspay->mt,
			'billing' 			=> ($billing_type == 'mt') ? $smspay->mt_paid : $smspay->mo,
			'income'			=> null,
			'income_increase'	=> null,
			];


		// load payout
		$res = nexus_service::get_service_payout([
			'operatorID'			=> $smspay->operatorID,
			'productID'				=> $smspay->productID,
			'product_type'			=> 'smspay',
			'fallback_operatorID'	=> 0,
			]);

		// on success
		if($res->status == 200){

			// calculate income
			$update->income = round($update->billing * $res->data->payout, 2);
			$update->income_increase = ($update->status == 'paid') ? round($res->data->payout, 2) : null;
			}


		// return result of adding redisjob for event_trigger
		return traffic_event::delayed_event_trigger($update);
		}

	public static function migrate_mobile($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from_mobileID'	=>	'~1,4294967295/i',
			'to_mobileID'	=>	'~1,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// update entries
		$upd = self::pdo('u_smspay_mobileID', [$mand['to_mobileID'], $mand['from_mobileID']]);

		// on error
		if($upd === false) return self::response(560);

		// return
		return self::response(204);
		}


	// DEPRECATED
	public static function get($req = []){

		return self::get_smspay($req);
		}
	}
