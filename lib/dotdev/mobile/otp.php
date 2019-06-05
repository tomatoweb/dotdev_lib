<?php
/*****
 * Version 1.2.2018-08-15
**/
namespace dotdev\mobile;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\nexus\service as nexus_service;
use \dotdev\traffic\event as traffic_event;

class otp {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_mobile', [
			's_otp'					=> "SELECT o.ID AS otpID, o.*, m.operatorID, l.domainID, l.pageID
										FROM `otp` o
										INNER JOIN `mobile` m ON m.ID = o.mobileID
										LEFT JOIN `persistlink` l ON l.persistID = o.persistID
										WHERE o.ID = ?
										LIMIT 1
										",

			'i_otp'					=> "INSERT INTO `otp` (`createTime`,`mobileID`,`productID`,`persistID`,`paidTime`,`expireTime`,`contingent`) VALUES (?,?,?,?,?,?,?)",

			'u_otp_paid'			=> "UPDATE `otp` SET `paidTime` = ?, `expireTime` = ? WHERE `ID` = ?",
			'u_otp_refundTime'		=> "UPDATE `otp` SET `refundTime` = ? WHERE `ID` = ?",
			'u_otp_contingent'		=> "UPDATE `otp` SET `contingent` = ? WHERE `ID` = ?",
			'u_otp_mobileID'		=> "UPDATE `otp` SET `mobileID` = ? WHERE `mobileID` = ?",
			]];
		}


	/* object: otp */
	public static function get($req = []){

		// alternative
		$alt = h::eX($req, [
			'otpID'			=> '~1,4294967295/i',
			'ID'			=> '~1,4294967295/i', // DEPRECATED
			'mobileID'		=> '~1,4294967295/i',
			'persistID'		=> '~1,4294967295/i'
			], $error, true);

		// Optional
		$opt = h::eX($req, [
			'productID'		=> '~1,65535/i',
			'productID_list'=> '~!empty/a',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		elseif(empty($alt)) return self::response(400, 'Need at least one parameter: otpID|mobileID|persistID');

		// DEPRECATED
		if(isset($alt['ID']) and !isset($alt['otpID'])) $alt['otpID'] = $alt['ID'];

		// param order 1: otpID
		if(isset($alt['otpID'])){

			// load entry
			$otp = self::pdo('s_otp', $alt['otpID']);

			// on error
			if(!$otp) return self::response($otp === false ? 560 : 404);

			// take to list
			$list = [$otp];
			}

		// param order 2-3: mobileID or persistID
		else{

			// define product filter
			$filter_product = [];
			if(isset($opt['productID'])){
				$filter_product[] = $opt['productID'];
				}
			elseif(isset($opt['productID_list'])){
				foreach($opt['productID_list'] as $entry){
					if(!h::is($entry, '~1,65535/i')) return self::response(400, ['productID_list']);
					$filter_product[] = $entry;
					}
				}

			// define query
			$query = h::replace_in_str(self::pdo_extract('s_otp'), [
				'o.ID = ?'	=> isset($alt['mobileID']) ? 'o.mobileID = ?' : 'o.persistID = ?',
				'LIMIT 1'	=> $filter_product ? 'AND o.productID IN ('.implode(',', $filter_product).')' : '',
				]);

			// load list
			$list = self::pdo($query, isset($alt['mobileID']) ? $alt['mobileID'] : $alt['persistID']);

			// on error
			if($list === false) return self::response(560);
			}

		// extend OTP object
		foreach($list as $e){
			$e->paid = ($e->paidTime !== '0000-00-00 00:00:00');
			$e->refunded = ($e->refundTime !== '0000-00-00 00:00:00');
			$e->expired =  ($e->expireTime !== '0000-00-00 00:00:00' and h::date($e->expireTime) <= h::date('now'));
			}

		// return result
		return self::response(200, isset($alt['otpID']) ? $otp : $list);
		}

	public static function create($req = []){

		// mandatory
		$mand = h::eX($req, [
			'mobileID'		=> '~1,4294967295/i',
			'productID'		=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'persistID'		=> '~0,4294967295/i',
			'createTime'	=> '~Y-m-d H:i:s/d',
			'paidTime'		=> '~Y-m-d H:i:s/d',
			'paid'			=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load product
		$res = nexus_service::get_product([
			'type'		=> 'otp',
			'productID'	=> $mand['productID'],
			]);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// take product
		$product = $res->data;

		// define defaults
		$now = h::dtstr('now');
		$opt = $opt + [
			'persistID'		=> 0,
			'createTime'	=> $now,
			'paidTime'		=> !empty($opt['paid']) ? $now : '0000-00-00 00:00:00',
			'expireTime'	=> '0000-00-00 00:00:00',
			'contingent'	=> $product->contingent,
			];

		// define expireTime
		if($opt['paidTime'] != '0000-00-00 00:00:00' and $product->expire){
			$opt['expireTime'] = h::date($opt['paidTime'], '+'.$product->expire, 'Y-m-d H:i:s');
			}

		// create DB entry
		$otpID = self::pdo('i_otp', [$opt['createTime'], $mand['mobileID'], $mand['productID'], $opt['persistID'], $opt['paidTime'], $opt['expireTime'], $opt['contingent']]);

		// on error
		if(!$otpID) return self::response(560);

		// if otp is paid and persistID is given
		if($opt['paidTime'] != '0000-00-00 00:00:00' and !empty($opt['persistID'])){

			// define income (null = no update)
			$income = null;
			$income_increase = null;

			// load otp
			$res = self::get([
				'otpID'	=> $otpID,
				]);

			// on success
			if($res->status == 200){

				// take operatorID
				$operatorID = $res->data->operatorID;

				// load payout
				$res = nexus_service::get_service_payout([
					'operatorID'			=> $operatorID,
					'productID'				=> $mand['productID'],
					'product_type'			=> 'otp',
					'fallback_operatorID'	=> 0,
					]);

				// on success
				if($res->status == 200){

					// take payout
					$payout = $res->data;

					// calculate new income
					$income = round($payout->payout, 2);
					$income_increase = round($payout->payout, 2);
					}
				}

			// add redisjob for event
			$res = traffic_event::delayed_event_trigger([
				'type'				=> 'otp',
				'subEvent'			=> 'otp',
				'status'			=> 'paid',
				'createTime'		=> $opt['paidTime'],
				'persistID' 		=> $opt['persistID'],
				'otpID' 			=> $otpID,
				'productID'			=> $mand['productID'],
				'income'			=> $income,
				'income_increase'	=> $income_increase,
				]);
			}

		// return
		return self::response(201, (object)['otpID'=>$otpID, 'ID'=>$otpID]);
		}

	public static function pay($req = []){

		// alternative
		$alt = h::eX($req, [
			'otpID'		=> '~1,4294967295/i',
			'ID'		=> '~1,4294967295/i', // DEPRECATED
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'paidTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		elseif(!isset($alt['otpID']) and !isset($alt['ID'])) return self::response(400, ['otpID']);

		// DEPRECATED
		if(isset($alt['ID']) and !isset($alt['otpID'])) $alt['otpID'] = $alt['ID'];

		// load entry
		$res = self::get([
			'otpID'		=> $alt['otpID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take entry
		$otp = $res->data;

		// if already paid, return conflict
		if($otp->paid) return self::response(409);

		// load product
		$res = nexus_service::get_product([
			'type'		=> 'otp',
			'productID'	=> $otp->productID,
			]);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// take product
		$product = $res->data;

		// define paidTime
		if(!isset($opt['paidTime'])) $opt['paidTime'] = h::dtstr('now');

		// define expireTime
		$opt['expireTime'] = $product->expire ? h::date($opt['paidTime'], '+'.$product->expire, 'Y-m-d H:i:s') : '0000-00-00 00:00:00';

		// update entry
		$upd = self::pdo('u_otp_paid', [$opt['paidTime'], $opt['expireTime'], $otp->otpID]);

		// on error
		if(!$upd) return self::response(560);

		// if persistID is given
		if($otp->persistID){

			// define income (null = no update)
			$income = null;
			$income_increase = null;

			// load payout
			$res = nexus_service::get_service_payout([
				'operatorID'			=> $otp->operatorID,
				'productID'				=> $otp->productID,
				'product_type'			=> 'otp',
				'fallback_operatorID'	=> 0,
				]);

			// on success
			if($res->status == 200){

				// take payout
				$payout = $res->data;

				// calculate new income
				$income = round($payout->payout, 2);
				$income_increase = round($payout->payout, 2);
				}

			// add redisjob for event
			$res = traffic_event::delayed_event_trigger([
				'type'				=> 'otp',
				'subevent'			=> 'otp',
				'status'			=> 'paid',
				'createTime'		=> $opt['paidTime'],
				'persistID' 		=> $otp->persistID,
				'otpID' 			=> $otp->otpID,
				'productID'			=> $otp->productID,
				'income'			=> $income,
				'income_increase'	=> $income_increase,
				]);
			}

		// return success
		return self::response(204);
		}

	public static function refund($req = []){

		// alternative
		$alt = h::eX($req, [
			'otpID'		=> '~1,4294967295/i',
			'ID'		=> '~1,4294967295/i', // DEPRECATED
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'refundTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		elseif(!isset($alt['otpID']) and !isset($alt['ID'])) return self::response(400, ['otpID']);

		// DEPRECATED
		if(isset($alt['ID']) and !isset($alt['otpID'])) $alt['otpID'] = $alt['ID'];

		// define defaults
		$opt += [
			'refundTime'	=> h::dtstr('now'),
			];

		// load otp
		$res = self::get([
			'otpID'		=> $alt['otpID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take otp
		$otp = $res->data;

		// if otp wasn't paid or already refunded, return forbidden
		if(!$otp->paid or $otp->refunded) return self::response(403);

		// update otp
		$upd = self::pdo('u_otp_refundTime', [$opt['refundTime'], $otp->otpID]);

		// on error
		if(!$upd) return self::response(560);

		// return success
		return self::response(204);
		}

	public static function update_contingent($req = []){

		// alternative
		$alt = h::eX($req, [
			'otpID'			=> '~1,4294967295/i',
			'ID'			=> '~1,4294967295/i', // DEPRECATED
			'by'			=> '~-16000000,16000000/i',
			'to'			=> '~0,16777215/i',
			'up_to'			=> '~-16000000,-1/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		elseif(!isset($alt['otpID']) and !isset($alt['ID'])) return self::response(400, ['otpID']);
		elseif(!isset($alt['by']) and !isset($alt['to']) and !isset($alt['up_to'])) return self::response(400, 'Need by, to or up_to parameter');

		// DEPRECATED
		if(isset($alt['ID']) and !isset($alt['otpID'])) $alt['otpID'] = $alt['ID'];

		// load otp
		$res = self::get([
			'otpID'		=> $alt['otpID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take otp
		$otp = $res->data;

		// if otp is not paid, or already expired or refunded, return forbidden
		if(!$otp->paid or $otp->refunded or $otp->expired) return self::response(403);

		// define missing
		$missing = 0;

		// if normal decrement
		if(isset($alt['by']) or isset($alt['to'])){

			// calculate new contingent
			$new_contingent = isset($alt['by']) ? $otp->contingent + $alt['by'] : $alt['to'];

			// if new contingent is invalid
			if($new_contingent < 0 or $new_contingent > 16777215){

				// log error
				e::logtrigger('DEBUG: Cannot change contingent of otpID '.$otp->otpID.' from '.h::encode_php($otp->contingent).' to '.h::encode_php($new_contingent));

				// return not acceptable
				return self::response(406);
				}
			}

		// for up_to decrement
		else{

			// calc missing difference
			$missing = $otp->contingent + $alt['up_to'];
			$missing = ($missing < 0) ? $missing * -1 : 0;

			// calculate new contingent
			$new_contingent = $missing ? 0 : $otp->contingent + $alt['up_to'];
			}

		// if new contingent exeeded range, return error
		if($new_contingent < 0 or $new_contingent > 16777215) return self::response(500, 'New contingent exeeded range: '.$otp->contingent.' => '.$new_contingent.' ('.h::encode_php($alt).')');

		// update otp
		$upd = self::pdo('u_otp_contingent', [$new_contingent, $otp->otpID]);

		// on error
		if(!$upd) return self::response(560);

		// return success
		return isset($alt['up_to']) ? self::response(204, (object)['missing' => $missing]) : self::response(204);
		}


	/* helper */
	public static function migrate_mobile($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from_mobileID'	=>	'~1,4294967295/i',
			'to_mobileID'	=>	'~1,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// update entries
		$upd = self::pdo('u_otp_mobileID', [$mand['to_mobileID'], $mand['from_mobileID']]);

		// on error
		if($upd === false) return self::response(560);

		// return success
		return self::response(204);
		}

	}
