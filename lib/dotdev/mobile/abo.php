<?php
/*****
 * Version 1.8.2018-10-16
**/
namespace dotdev\mobile;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\nexus\service as nexus_service;
use \dotdev\traffic\event as traffic_event;

class abo {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_mobile', [

			// queries: abo
			's_abo_by_aboID'		=> 'SELECT
											a.ID AS aboID, a.*,
											m.operatorID,
											l.domainID, l.pageID,
											IF(i.aboID IS NOT NULL, 1, 0) as `abo_info`, COALESCE(i.refundTime, "0000-00-00 00:00:00") AS `refundTime`, COALESCE(i.refundedCharges, 0) AS `refundedCharges`
										FROM `abo` a
										INNER JOIN `mobile` m ON m.ID = a.mobileID
										LEFT JOIN `abo_info` i ON i.aboID = a.ID
										LEFT JOIN `persistlink` l ON l.persistID = a.persistID
										WHERE a.ID = ?
										LIMIT 1
										',
			's_abo_by_chargeID'		=> ['s_abo_by_aboID', ['FROM `abo` a' => 'FROM `abo_charge` c INNER JOIN `abo` a ON a.ID = c.aboID', 'a.ID = ?' => 'c.ID = ?']],
			'l_abo_by_mobileID'		=> ['s_abo_by_aboID', ['a.ID = ?' => 'a.mobileID = ? AND a.confirmTime != "0000-00-00 00:00:00"', 'LIMIT 1'=>'']],
			'l_abo_by_persistID'	=> ['s_abo_by_aboID', ['a.ID = ?' => 'a.persistID = ? AND a.confirmTime != "0000-00-00 00:00:00"', 'LIMIT 1'=>'']],

			'l_rechargable'			=> 'SELECT a.ID AS aboID, m.operatorID
										FROM `abo` a
										INNER JOIN `mobile` m ON m.ID = a.mobileID
										WHERE a.productID = ? AND a.confirmTime != "0000-00-00 00:00:00" AND (a.endTime = "0000-00-00 00:00:00" OR a.endTime > ?)
										',
			'l_rechargable_op'		=> ['l_rechargable', ['a.productID = ?' => 'a.productID = ? AND m.operatorID = ?']],

			'u_abo'					=> 'UPDATE `abo` SET WHERE `ID` = ?',

			'i_abo'					=> 'INSERT INTO `abo` (`createTime`,`mobileID`,`productID`,`persistID`,`confirmTime`) VALUES (?,?,?,?,?)',
			'i_abo_info'			=> 'INSERT INTO `abo_info` (`aboID`,`refundTime`,`refundedCharges`) VALUES (?,?,?)',
			'u_abo_info'			=> 'UPDATE `abo_info` SET `refundTime` = ?, `refundedCharges` = ? WHERE `aboID` = ?',

			// queries: charge
			's_charge'				=> 'SELECT c.ID AS chargeID, c.* FROM `abo_charge` c WHERE c.ID = ? LIMIT 1',
			'l_charge_by_aboID'		=> 'SELECT c.ID AS chargeID, c.* FROM `abo_charge` c WHERE c.aboID = ? ORDER BY c.startTime ASC',

			'i_charge'				=> 'INSERT INTO `abo_charge` (`aboID`,`contingent`,`startTime`,`endTime`) VALUES (?,?,?,?)',

			'u_charge_status'		=> 'UPDATE `abo_charge` SET `paidTime` = ?, `paytries` = `paytries`+1 WHERE `ID` = ?',
			'u_charge_contingent'	=> 'UPDATE `abo_charge` SET `contingent` = ? WHERE `ID` = ?',

			'd_charge'				=> 'DELETE FROM `abo_charge` WHERE `ID` = ? LIMIT 1',

			]];
		}


	/* Object: abo */
	public static function get_abo($req = []){

		// one is mandatory
		$alt = h::eX($req, [
			'aboID'			=> '~1,4294967295/i',
			'chargeID'		=> '~1,4294967295/i',
			'mobileID'		=> '~1,4294967295/i',
			'persistID'		=> '~1,18446744073709551615/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'productID'		=> '~1,65535/i',
			'productID_list'=> '~/a',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// define expand function
		$on_success_alter = function($result){

			// make list (of objects)
			$list = is_array($result) ? $result : [$result];

			// for each entry
			foreach($list as $abo){

				// load charge list
				$abo->charges = self::pdo('l_charge_by_aboID', $abo->aboID);

				// on error
				if($abo->charges === false) return self::response(560);

				// define now
				$now = h::date('now');

				// define boolean
				$abo->confirmed = ($abo->confirmTime != '0000-00-00 00:00:00');
				$abo->terminated = ($abo->terminateTime != '0000-00-00 00:00:00');
				$abo->ended = ($abo->endTime != '0000-00-00 00:00:00' and h::date($abo->endTime) < h::date('now'));
				$abo->refunded = ($abo->refundTime != '0000-00-00 00:00:00');
				$abo->paid = null;

				// if abo is confirmed
				if($abo->confirmed){

					// load product
					$res = nexus_service::get_product([
						'type'		=> 'abo',
						'productID'	=> $abo->productID,
						]);

					// on unexpected error
					if($res->status !== 200) return self::response(570, $res);

					// take product
					$product = $res->data;

					// calc interval in sec (using a date not falling in daylight saving time, which could result in wrong hour calculation)
					$intervalPerChargeSec = round((INT) h::date(0, '+'.$product->interval, 'U') / $product->charges);

					// calc last time
					$till = $abo->terminated ? h::date($abo->endTime) : clone $now;

					// define charge position time
					$chargePosTime = substr($abo->createTime, 0, 10).' 00:00:00';

					// define run position time
					$runPosTime = h::date(substr($abo->createTime, 0, 10).' 00:00:00');

					// define if charge list should be reloaded
					$reloadChargeList = false;

					// define charge range array
					$ccarr = [];

					// for each charge
					foreach($abo->charges as $k => $charge){

						// if range already exist in previous charge
						if(!empty($ccarr[$charge->startTime.'-'.$charge->endTime])){

							// delete charge
							$del = self::pdo('d_charge', $charge->chargeID);

							// on error
							if($del === false) return self::response(560);

							// and remove charge from list
							unset($abo->charges[$k]);
							}

						// add range
						$ccarr[$charge->startTime.'-'.$charge->endTime] = true;
						}

					// if charge was deleted, this will reorder the array keys
					$abo->charges = array_values($abo->charges);


					// run each possible interval
					while($runPosTime < $till){

						// Interval Contingent Variable
						$chcontModulo = $product->contingent % $product->charges; // = maximal $product->charges-1
						$chcont = ($product->contingent - $chcontModulo) / $product->charges;

						// Für jeden Charge innerhalb des Intervals
						for($i = 1; $i <= $product->charges; $i++){

							// Charge Contingent
							$contingent = $chcont;
							if($chcontModulo > 0){
								$chcontModulo--;
								$contingent++;
								}

							// Startzeit ist immer die aktuelle Zeit
							$startTime = $chargePosTime;

							// Bei Abo mit mehr als 1 Charge per Interval und für jeden ausser dem letzen wird hier ein Charge mittels $intervalPerChargeSec berechnet
							if($i < $product->charges){
								$chargePosTime = h::date($chargePosTime, '+'.($intervalPerChargeSec-1).' sec', 'Y-m-d H:i:s');
								}

							// Beim einzigen, oder letzten Charge hingegen die Sekunde vor dem nächsten Interval
							else{
								$chargePosTime = h::date($runPosTime, '+'.$product->interval.' -1 sec', 'Y-m-d H:i:s');
								}

							$endTime = $chargePosTime;

							// Wenn dieser Charge noch nicht existiert, dann entsprechend erstellen
							if(empty($ccarr[$startTime.'-'.$endTime])){

								$chargeID = self::pdo('i_charge', [$abo->aboID, $contingent, $startTime, $endTime]);
								if(!$chargeID) return 560;

								$ccarr[$startTime.'-'.$endTime] = true;
								$reloadChargeList = true;
								}

							$chargePosTime = h::date($chargePosTime, '+1 sec', 'Y-m-d H:i:s');
							}

						$chargePosTime = h::date($runPosTime, '+1 sec', 'Y-m-d H:i:s');
						}

					// if reload is defined
					if($reloadChargeList){

						// load charge list
						$abo->charges = self::pdo('l_charge_by_aboID', $abo->aboID);

						// on error
						if($abo->charges === false) return self::response(560);

						// again, check for doubled charge times
						$ccarr = [];

						// for each charge
						foreach($abo->charges as $k => $charge){

							// if range already exist in previous charge
							if(!empty($ccarr[$charge->startTime.'-'.$charge->endTime])){

								// delete charge
								$del = self::pdo('d_charge', $charge->chargeID);

								// on error
								if($del === false) return self::response(560);

								// and remove charge from list
								unset($abo->charges[$k]);
								}

							// add range
							$ccarr[$charge->startTime.'-'.$charge->endTime] = true;
							}

						// if charge was deleted, this will reorder the array keys
						$abo->charges = array_values($abo->charges);
						}

					// define is abo was never paid
					$neverpaid = true;

					// for each charge
					foreach($abo->charges as $charge){

						// define charge status
						$charge->paid = ($charge->paidTime != '0000-00-00 00:00:00') ? true : ($charge->paytries ? false : null);

						// if at least one charge was paid, set neverpaid to false
						if($charge->paid) $neverpaid = false;

						// Aktuell gültigen Charge gefunden
						if(!$neverpaid and h::date($charge->startTime) <= $now and h::date($charge->endTime) >= $now) $abo->paid = $charge->paid;
						}

					if(($abo->paid === null and $abo->terminated) or $neverpaid) $abo->paid = false;
					}

				}

			// return result
			return self::response(200, $result);
			};


		// param order 1-2: aboID or chargeID
		if(isset($alt['aboID']) or isset($alt['chargeID'])){

			// define key for searching
			$key = isset($alt['aboID']) ? 'aboID' : 'chargeID';

			// load entry from DB
			$entry = self::pdo('s_abo_by_'.$key, $alt[$key]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return altered result
			return $on_success_alter($entry);
			}

		// param order 3-4: mobileID or persistID (+ productID(list))
		if(isset($alt['mobileID']) or isset($alt['persistID'])){

			// define key for searching
			$key = isset($alt['mobileID']) ? 'mobileID' : 'persistID';

			// define product filter
			$filter_product = [];

			// if single productID is given
			if(isset($opt['productID'])){

				// take it for filter
				$filter_product[] = $opt['productID'];
				}

			// if productID list is given
			elseif(!empty($opt['productID_list'])){

				// for each entry
				foreach($opt['productID_list'] as $entry){

					// if entry is not an productID, return error
					if(!h::is($entry, '~1,65535/i')) return self::response(400, ['productID_list']);

					// take entry as productID
					$filter_product[] = $entry;
					}
				}

			// load list from DB
			$list = self::pdo('l_abo_by_'.$key, $alt[$key]);

			// on error
			if($list === false) return self::response(560);

			// for each entry in list
			foreach($list as $k => $abo){

				// if product filter does not match
				if(!empty($filter_product) and !in_array($abo->productID, $filter_product)){

					// unset entry and continue
					unset($list[$k]);
					continue;
					}
				}

			// reorder list
			$list = array_values($list);

			// return altered result
			return $on_success_alter($list);
			}

		// on error
		return self::response(400, 'Need at least aboID, chargeID, mobileID or persistID');
		}

	public static function create_abo($req = []){

		// mandatory
		$mand = h::eX($req, [
			'mobileID'		=> '~1,4294967295/i',
			'productID'		=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'persistID'		=> '~0,4294967295/i',
			'createTime'	=> '~Y-m-d H:i:s/d',
			'confirmed'		=> '~/b',
			'confirmTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default for optional keys
		$opt = $opt + [
			'persistID'		=> 0,
			'createTime'	=> date('Y-m-d H:i:s'),
			'confirmTime'	=> !empty($opt['confirmed']) ? date('Y-m-d H:i:s') : '0000-00-00 00:00:00',
			];

		// create abo
		$aboID = self::pdo('i_abo', [$opt['createTime'], $mand['mobileID'], $mand['productID'], $opt['persistID'], $opt['confirmTime']]);

		// on error
		if(!$aboID) return self::response(560);

		// if abo already confirmed
		if($opt['confirmTime'] != '0000-00-00 00:00:00'){

			// trigger/update event
			$res = self::trigger_abo_event([
				'aboID'		=> $aboID,
				'subevent'	=> 'abo',
				'trigger'	=> 'confirm',
				'createTime'=> $opt['confirmTime'] ?? $opt['createTime'] ?? null,
				]);
			}

		// return success
		return self::response(201, (object)['aboID'=>$aboID]);
		}

	public static function update_abo($req = []){

		// mandatory
		$mand = h::eX($req, [
			'aboID'		=> '~1,4294967295/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [

			// options
			'confirm'		=> '~/b',
			'terminate'		=> '~/b',
			'refund'		=> '~1,65535/i',
			'reopen'		=> '~/b',
			'force'			=> '~/b',
			'setTime'		=> '~Y-m-d H:i:s/d',

			// values
			'mobileID'		=> '~1,4294967295/i',
			'productID'		=> '~1,65535/i',
			'persistID'		=> '~0,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_abo([
			'aboID'		=> $mand['aboID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take entry
		$abo = $res->data;

		// define trigger status
		$trigger = 'update';

		// define update
		$update = [];
		$info_update = [];

		// if abo should be confirmed
		if(!empty($opt['confirm'])){

			// if abo is already confirmed, return conflict
			if($abo->confirmed and empty($opt['force'])) return self::response(409);

			// set confirmTime
			$opt['confirmTime'] = $opt['setTime'] ?? h::dtstr('now');

			// set trigger
			$trigger = 'confirm';
			}

		// if abo should be terminated
		if(!empty($opt['terminate'])){

			// if abo is already terminated, return conflict
			if($abo->terminated and empty($opt['force'])) return self::response(409);

			// set confirmTime
			$opt['terminateTime'] = $opt['setTime'] ?? h::dtstr('now');

			// set trigger
			$trigger = 'terminate';
			}

		// if abo should be refund
		if(!empty($opt['refund'])){

			// if abo is already refunded, return conflict
			if($abo->refunded and empty($opt['force'])) return self::response(409);

			// set refundTime and refundedCharges
			$info_update['refundTime'] = $opt['setTime'] ?? h::dtstr('now');
			$info_update['refundedCharges'] = $opt['refund'];

			// if abo not terminated yet, set terminateTime too
			if(!$abo->terminated) $opt['terminateTime'] = $info_update['refundTime'];

			// set trigger
			$trigger = 'refund';
			}

		// if abo should be reopened
		if(!empty($opt['reopen'])){

			// if abo is not already terminated, return conflict
			if(!$abo->terminated and empty($opt['force'])) return self::response(409);

			// set terminateTime
			$opt['terminateTime'] = '0000-00-00 00:00:00';
			$opt['endTime'] = '0000-00-00 00:00:00';

			// set trigger
			$trigger = 'update';
			}

		// if terminateTime is set
		if(isset($opt['terminateTime']) and $opt['terminateTime'] != '0000-00-00 00:00:00'){

			// load product
			$res = nexus_service::get_product([
				'type'		=> 'abo',
				'productID'	=> $abo->productID
				]);

			// on error
			if($res->status !== 200) return self::response(570, $res);

			// take product
			$product = $res->data;

			// calculate endTime
			$opt['endTime'] = !empty($abo->charges)
				? end($abo->charges)->endTime
				: h::date(substr($abo->createTime, 0, 10), '+'.$product->interval.' -1 sec', 'Y-m-d H:i:s');
			}

		// if force param not set
		if(empty($opt['force'])){

			// prevent updating these values
			unset($opt['mobileID']);
			unset($opt['productID']);

			// prevent only if persistID was set before
			if($abo->persistID) unset($opt['persistID']);
			}



		// for each entry key
		foreach(['mobileID','productID','persistID','confirmTime','terminateTime','endTime'] as $key){

			// if no update value given or it's the same
			if(!isset($opt[$key]) or $opt[$key] == $abo->{$key}) continue;

			// take value
			$update[$key] = $opt[$key];
			}

		// if abo needs to be updated
		if($update){

			// get update query
			$query = self::pdo_extract('u_abo', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

			// update
			$upd = self::pdo($query, array_merge(array_values($update), [$abo->aboID]));

			// on error
			if($upd === false) return self::response(560);
			}

		// if abo_info needs to be updated
		if($info_update){

			// insert or update
			$upd = $abo->abo_info
				? self::pdo('u_abo_info', [$info_update['refundTime'], $info_update['refundedCharges'], $abo->aboID])
				: self::pdo('i_abo_info', [$abo->aboID, $info_update['refundTime'], $info_update['refundedCharges']]);

			// on error
			if($upd === false) return self::response(560);
			}

		// if abo is confirmed
		if($abo->confirmed or isset($opt['confirmTime'])){

			// trigger/update event
			$res = self::trigger_abo_event([
				'aboID'		=> $abo->aboID,
				'subevent'	=> 'abo',
				'trigger'	=> $trigger,
				'createTime'=> $opt['confirmTime'] ?? $opt['createTime'] ?? null,
				]);
			}

		// return success
		return self::response(204);
		}


	/* Helper */
	public static function update_charge_status($req = []){

		// mandatory
		$mand = h::eX($req, [
			'chargeID'	=> '~1,4294967295/i',
			'status'	=> '~^(?:paid|failed)$',
			], $error);

		// optional
		$opt =  h::eX($req, [
			'paidTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load charge
		$charge = self::pdo('s_charge', $mand['chargeID']);

		// on error
		if(!$charge) return self::response($charge === false ? 560 : 404);

		// if charge is already paid, return conflict
		if($charge->paidTime != '0000-00-00 00:00:00') return self::response(409);

		// if status is paid
		if($mand['status'] == 'paid'){

			// define paidTime
			$charge->paidTime = isset($opt['paidTime']) ? $opt['paidTime'] : date('Y-m-d H:i:s');
			}

		// update entry
		$upd = self::pdo('u_charge_status', [$charge->paidTime, $charge->ID]);

		// on error
		if(!$upd) return self::response(560);

		// trigger/update event
		$res = self::trigger_abo_event([
			'aboID'		=> $charge->aboID,
			'subevent'	=> 'charge',
			'trigger'	=> ($mand['status'] == 'paid') ? 'paid' : 'update',
			'createTime'=> ($mand['status'] == 'paid') ? $charge->paidTime : null,
			]);

		// return success
		return self::response(204);
		}

	public static function update_charge_contingent($req = []){

		// mandatory
		$mand = h::eX($req, [
			'chargeID'	=> '~1,4294967295/i',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'by'		=> '~-16000000,16000000/i',
			'to'		=> '~0,16777215/i',
			'up_to'		=> '~-16000000,-1/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		elseif(empty($alt)) return self::response(400, 'Need by, to or up_to parameter');

		// load charge
		$charge = self::pdo('s_charge', $mand['chargeID']);

		// on error
		if(!$charge) return self::response($charge === false ? 560 : 404);

		// define missing
		$missing = 0;

		// if normal decrement
		if(isset($alt['by']) or isset($alt['to'])){

			// calculate new contingent
			$new_contingent = isset($alt['by']) ? $charge->contingent + $alt['by'] : $alt['to'];

			// if new contingent is invalid
			if($new_contingent < 0 or $new_contingent > 16777215){

				// log error
				e::logtrigger('DEBUG: Cannot change contingent of chargeID '.$charge->chargeID.' from '.h::encode_php($charge->contingent).' to '.h::encode_php($new_contingent));

				// return not acceptable
				return self::response(406);
				}
			}

		// for up_to decrement
		else{

			// calc missing difference
			$missing = $charge->contingent + $alt['up_to'];
			$missing = ($missing < 0) ? $missing * -1 : 0;

			// calculate new contingent
			$new_contingent = $missing ? 0 : $charge->contingent + $alt['up_to'];
			}

		// if new contingent exeeded range, return error
		if($new_contingent < 0 or $new_contingent > 16777215) return self::response(500, 'New contingent exeeded range: '.$charge->contingent.' => '.$new_contingent.' ('.h::encode_php($mand+$alt).')');

		// update charge
		$upd = self::pdo('u_charge_contingent', [$new_contingent, $charge->ID]);

		// on error
		if(!$upd) return self::response(560);

		// return success
		return isset($alt['up_to']) ? self::response(204, (object)['missing' => $missing]) : self::response(204);
		}

	public static function trigger_abo_event($req = []){

		// mandatory
		$mand = h::eX($req, [
			'aboID'		=> '~1,4294967295/i',
			'subevent'	=> '~^(?:abo|charge)$',
			'trigger'	=> '~^(?:create|confirm|terminate|refund|paid|update)$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'createTime'=> null,
			];


		// load abo
		$res = self::get_abo([
			'aboID'	=> $mand['aboID'],
			]);

		// on error
		if($res->status == 404) return $res;
		if($res->status != 200) return self::response(570, $res);

		// take entry
		$abo = $res->data;

		// if no persistID set, return done
		if(!$abo->persistID) return self::response(204);


		// define charges paid and first paid charge
		$charges_paid = 0;
		$first_paid_charge = null;

		// for each charge
		foreach($abo->charges as $charge){

			// skip unpaid charges
			if(!$charge->paid) continue;

			// count
			$charges_paid++;

			// save first paid charge
			if(!$first_paid_charge) $first_paid_charge = $charge;
			}


		// define update
		$update = (object)[
			'type'				=> 'abo',
			'subevent'			=> ($mand['trigger'] == 'paid') ? 'charge' : 'abo',
			'status'			=> $mand['trigger'],
			'createTime'		=> $opt['createTime'] ?: ($abo->confirmTime != '0000-00-00 00:00:00' ? $abo->confirmTime : $abo->createTime),
			'persistID' 		=> $abo->persistID,
			'aboID' 			=> $abo->aboID,
			'productID'			=> $abo->productID,
			'income'			=> null,
			'income_increase'	=> null,
			'charges'			=> $charges_paid,
			'charges_max'		=> count($abo->charges),
			'paidafter'			=> $first_paid_charge ? max(h::dtstr($first_paid_charge->paidTime, 'U') - h::dtstr($abo->confirmTime, 'U'), 0) : null,
			'terminated'		=> $abo->terminated,
			'terminateTime'		=> $abo->terminated ? $abo->terminateTime : null,
			'livetime'			=> $abo->terminated ? max(h::dtstr($abo->terminateTime, 'U') - h::dtstr($abo->confirmTime, 'U'), 0) : 0,
			'charges_refunded'	=> $abo->refundedCharges,
			];


		// load payout
		$res = nexus_service::get_service_payout([
			'operatorID'			=> $abo->operatorID,
			'productID'				=> $abo->productID,
			'product_type'			=> 'abo',
			'fallback_operatorID'	=> 0,
			]);

		// on success
		if($res->status == 200){

			// calculate income
			$update->income = round($update->charges * $res->data->payout, 2);
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
		$upd = self::pdo('u_abo_mobileID', [$mand['to_mobileID'], $mand['from_mobileID']]);

		// on error
		if($upd === false) return self::response(560);

		// return success
		return self::response(204);
		}

	public static function get_rechargeable($req = []){

		// mandatory
		$mand = h::eX($req, [
			'productID'	=>	'~1,65535/i'
			], $error);

		// optional
		$opt = h::eX($req, [
			'operatorID'=>	'~1,65535/i'
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load list
		$list = isset($opt['operatorID'])
			? self::pdo('l_rechargable_op', [$mand['productID'], $opt['operatorID'], date('Y-m-d')])
			: self::pdo('l_rechargable', [$mand['productID'], date('Y-m-d')]);

		// on error
		if($list === false) return self::response(560);

		// return result
		return self::response(200, $list);
		}


	/* DEPRECATED */
	public static function get($req = []){

		// convert req
		if(is_object($req)) $req = (array) $req;

		// DEPRECATED
		if(is_array($req) and isset($req['ID']) and !isset($req['aboID'])) $req['aboID'] = $req['ID'];

		return self::get_abo($req);
		}

	public static function create($req = []){

		// create abo
		$res = self::create_abo($req);

		// add deprecated ID
		if($res->status == 201) $res->data->ID = $res->data->aboID;

		// return result
		return $res;
		}

	public static function confirm($req = []){

		// one is mandatory
		$alt = h::eX($req, [
			'aboID'		=> '~1,4294967295/i',
			'ID'		=> '~1,4294967295/i', // DEPRECATED
			], $error, true);

		// optional
		$opt =  h::eX($req, [
			'confirmTime'=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		elseif(empty($alt)) return self::response(400, ['aboID']);

		// DEPRECATED
		if(isset($alt['ID']) and !isset($alt['aboID'])) $alt['aboID'] = $alt['ID'];

		// return forwarded result
		return self::update_abo([
			'aboID'		=> $alt['aboID'],
			'confirm'	=> true,
			'setTime'	=> $opt['confirmTime'] ?? null,
			]);
		}

	public static function terminate($req = []){

		// on is mandatory
		$alt = h::eX($req, [
			'aboID'		=> '~1,4294967295/i',
			'ID'		=> '~1,4294967295/i', // DEPRECATED
			], $error, true);

		// optional
		$opt =  h::eX($req, [
			'terminateTime'=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		elseif(empty($alt)) return self::response(400, ['aboID']);

		// DEPRECATED
		if(isset($alt['ID']) and !isset($alt['aboID'])) $alt['aboID'] = $alt['ID'];

		// return forwarded result
		return self::update_abo([
			'aboID'		=> $alt['aboID'],
			'terminate'	=> true,
			'setTime'	=> $opt['terminateTime'] ?? null,
			]);
		}

	public static function refund($req = []){

		// mandatory
		$mand = h::eX($req, [
			'aboID'				=> '~1,4294967295/i',
			], $error, true);

		// optional
		$opt =  h::eX($req, [
			'refundTime'		=> '~Y-m-d H:i:s/d',
			'refundedCharges'	=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// return forwarded result
		return self::update_abo([
			'aboID'		=> $mand['aboID'],
			'refund'	=> $opt['refundedCharges'] ?? 1,
			'setTime'	=> $opt['refundTime'] ?? null,
			]);
		}

	}
