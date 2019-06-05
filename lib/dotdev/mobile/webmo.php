<?php
/*****
 * Version 1.0.2018-09-03
**/
namespace dotdev\mobile;

use \tools\error as e;
use \tools\helper as h;

class webmo {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_mobile', [

			// webmo
			's_webmo'					=> "SELECT * FROM `webmo` WHERE `webmoID` = ? LIMIT 1",
			'l_webmo_by_mobileID'		=> "SELECT * FROM `webmo` WHERE `mobileID` = ? ORDER BY `createTime` ASC",

			'i_webmo' 					=> "INSERT INTO `webmo` (`createTime`,`apkID`,`operatorID`,`smsgateID`,`status`,`mobileID`,`persistID`,`message`) VALUES (?,?,?,?,?,?,?,?)",
			'u_webmo'					=> "UPDATE `webmo` SET `apkID` = ?, `operatorID` = ?, `smsgateID` = ?, `status` = ?, `persistID` = ? WHERE `webmoID` = ?",
			]];
		}


	/* Object: webmo */
	public static function get_webmo($req){

		// alternative
		$alt = h::eX($req, [
			'webmoID'		=> '~1,4294967295/i',
			'mobileID'		=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// expand message entries
		$expand = function($list){

			// define if single entry
			$single = !is_array($list);

			// convert single entry to list
			if($single) $list = [$list];

			// for each entry
			foreach($list as $entry){

				// define special keys
				$entry->orderID = $entry->webmoID;
				$entry->mlstate = $entry->status;
				}

			// return result as single entry or list
			return $single ? reset($list) : $list;
			};


		// param order 1: webmoID
		if(isset($alt['webmoID'])){

			// load entry from DB
			$entry = self::pdo('s_webmo', $alt['webmoID']);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $expand($entry));
			}

		// param order 2: mobileID
		if(isset($alt['mobileID'])){

			// load list
			$list = self::pdo('l_webmo_by_mobileID', $alt['mobileID']);

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $expand($list));
			}

		// other request param invalid
		return self::response(400, 'need webmoID|mobileID parameter');
		}

	public static function create_webmo($req){

		// mandatory
		$mand = h::eX($req, [
			'mobileID'		=> '~1,4294967295/i',
			'message'		=> '~/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'apkID'			=> '~0,255/i',
			'operatorID'	=> '~0,65535/i',
			'smsgateID'		=> '~0,65535/i',
			'status'		=> '~0,999/i',
			'createTime'	=> '~Y-m-d H:i:s/d',
			'persistID'		=> '~0,18446744073709551615/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'createTime'	=> h::dtstr('now'),
			'apkID'			=> 0,
			'operatorID'	=> 0,
			'smsgateID'		=> 0,
			'status'		=> null,
			'persistID'		=> 0,
			];

		// insert entry
		$webmoID = self::pdo('i_webmo', [$opt['createTime'], $opt['apkID'], $opt['operatorID'], $opt['smsgateID'], $opt['status'], $mand['mobileID'], $opt['persistID'], $mand['message']]);

		// on error
		if($webmoID === false) return self::response(560);

		// return success
		return self::response(201, (object)['webmoID'=>$webmoID]);
		}

	public static function update_webmo($req){

		// mandatory
		$mand = h::eX($req, [
			'webmoID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'apkID'			=> '~0,255/i',
			'operatorID'	=> '~0,65535/i',
			'smsgateID'		=> '~0,65535/i',
			'status'		=> '~0,999/i',
			'persistID'		=> '~0,18446744073709551615/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_webmo([
			'webmoID'		=> $mand['webmoID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_webmo', [$entry->apkID, $entry->operatorID, $entry->smsgateID, $entry->status, $entry->persistID, $entry->webmoID]);

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
