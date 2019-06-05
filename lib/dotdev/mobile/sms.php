<?php
/*****
 * Version 1.1.2018-06-11
**/
namespace dotdev\mobile;

use \tools\error as e;
use \tools\helper as h;

class sms {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_mobile', [
			's_sms'				=> "SELECT s.ID AS `smsID`, s.* FROM `sms` s WHERE s.ID = ? LIMIT 1",
			'l_sms'				=> "SELECT s.ID AS `smsID`, s.* FROM `sms` s WHERE s.mobileID = ?",

			'i_sms'				=> "INSERT INTO `sms` (`mobileID`,`serviceID`,`text`) VALUES (?,?,?)",

			'u_sms_sent'		=> "UPDATE `sms` SET `sendTime` = ? WHERE `ID` = ?",
			'u_sms_mobileasoc'	=> "UPDATE `sms` SET `mobileID` = ? WHERE `mobileID` = ?",
			]];
		}


	/* Object: sms */
	public static function get($req){

		// alternative
		$alt = h::eX($req, [
			'smsID'			=> '~1,16777215/i',
			'ID'			=> '~1,16777215/i', // DEPRECATED
			'mobileID'		=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// DEPRECATED
		if(isset($alt['ID']) and !isset($alt['smsID'])) $alt['smsID'] = $alt['ID'];

		// param order 1: smspayID
		if(isset($alt['smsID'])){

			// load entry
			$entry = self::pdo('s_sms', $alt['smsID']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// extend entry
			$entry->sent = ($entry->sendTime != '0000-00-00 00:00:00');

			// return result
			return self::response(200, $entry);
			}


		// param order 2: mobileID
		if(isset($alt['mobileID'])){

			// load list
			$list = self::pdo('l_sms', $alt['mobileID']);

			// on error
			if($list === false) return self::response(560);

			// extend entries in list
			foreach($list as $entry){
				$entry->sent = ($entry->sendTime != '0000-00-00 00:00:00');
				}

			// return success
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'Need at least one parameter: smsID|mobileID');
		}

	public static function create($req){

		// mandatory
		$mand = h::eX($req, [
			'mobileID'	=> '~1,4294967295/i',
			'serviceID'	=> '~1,65535/i',
			'text'		=> '~/s'
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// insert entry
		$smsID = self::pdo('i_sms', array_values($mand));

		// on error
		if(!$smsID) return self::response(560);

		// return success
		return self::response(201, (object)['smsID'=>$smsID, 'ID'=>$smsID]);
		}

	public static function sent($req){

		// alternative
		$alt = h::eX($req, [
			'smsID'			=> '~1,16777215/i',
			'ID'			=> '~1,16777215/i', // DEPRECATED
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'sendTime'		=> '~Y-m-d H:i:s/d',
			'overwrite'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(empty($alt)) return self::response(400, ['smsID']);

		// DEPRECATED
		if(isset($alt['ID']) and !isset($alt['smsID'])) $alt['smsID'] = $alt['ID'];

		// default
		$opt += [
			'sendTime'		=> h::dtstr('now'),
			'overwrite'		=> false,
			];

		// load entry
		$res = self::get([
			'smsID'	=> $alt['smsID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// if entry was already sent
		if($entry->sent and !$opt['overwrite']) return self::response(409);

		// update entry
		$upd = self::pdo('u_sms_sent', [$opt['sendTime'], $entry->smsID]);

		// on error
		if($upd === false) return self::response(560);

		// return success
		return self::response(204);
		}


	/* Helper */
	public static function migrate_mobile($req){

		// mandatory
		$mand = h::eX($req, [
			'from_mobileID'	=>	'~1,4294967295/i',
			'to_mobileID'	=>	'~1,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// update entries
		$upd = self::pdo('u_sms_mobileasoc', [$mand['to_mobileID'], $mand['from_mobileID']]);

		// on error
		if($upd === false) return self::response(560);

		// return success
		return self::response(204);
		}

	}
