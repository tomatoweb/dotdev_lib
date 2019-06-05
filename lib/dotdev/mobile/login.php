<?php
/*****
 * Version 1.1.2019-01-11
**/
namespace dotdev\mobile;

use \tools\error as e;
use \tools\helper as h;

class login {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_mobile', [
			's_login_by_loginstr'				=> 'SELECT l.*, m.msisdn, m.operatorID, m.confirmTime
													FROM `mobile_login` l
													INNER JOIN `mobile` m ON m.ID = l.mobileID
													WHERE l.loginstr = ?
													LIMIT 1',
			'l_login_by_mobileID'				=> 'SELECT l.*, m.msisdn, m.operatorID, m.confirmTime
													FROM `mobile_login` l
													INNER JOIN `mobile` m ON m.ID = l.mobileID
													WHERE l.mobileID = ?
													ORDER BY l.expireTime ASC
													',
			'i_login'							=> 'INSERT INTO `mobile_login` (`mobileID`,`loginstr`,`expireTime`) VALUES (?,?,?)',
			]];
		}


	/* Object: login */
	public static function get_login($req = []){

		// alternativ
		$alt = h::eX($req, [
			'loginstr'	=> '~^[a-zA-Z0-9]{12}$',
			'mobileID'	=> '~1,4294967295/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'expired'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'expired'	=> false,
			];

		// param order 1: loginstr
		if(isset($alt['loginstr'])){

			// load login entry
			$entry = self::pdo('s_login_by_loginstr', $alt['loginstr']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// extend entry
			$entry->expired = ($entry->expireTime != '0000-00-00 00:00:00' and h::date($entry->expireTime) < h::date('now'));

			// if expired entries are not allowed, return not found
			if($entry->expired and !$opt['expired']) return self::response(404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: mobileID
		if(isset($alt['mobileID'])){

			//  load all mobileID from DB
			$list = self::pdo('l_login_by_mobileID', $alt['mobileID']);

			// on error or not found
			if(!$list) return self::response($list === false ? 560 : 404);

			// if expired keys should be removed
			if(!$opt['expired']){

				// define new list
				$filtered_list = [];

				// define now
				$now = h::date('now');

				// for each entry
				foreach($list as $entry){

					// if entry is expired, skip it
					if($entry->expireTime != '0000-00-00 00:00:00' and h::date($entry->expireTime) < $now) continue;

					// add entry
					$filtered_list[] = $entry;
					}

				// save new list
				$list = $filtered_list;
				}

			// return result
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need loginstr or mobileID (+expired)');
		}

	public static function create_login($req = []){

		// mandatory
		$mand = h::eX($req, [
			'mobileID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'expireTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// set default
		$opt += [
			'expireTime'	=> '0000-00-00 00:00:00',
			];

		// repeatly create loginstr till it is unique
		do{
			// create random string (containing only chars and numbers)
			$loginstr = h::rand_str(12);

			// check existing entries not expired
			$entry = self::pdo('s_login_by_loginstr', $loginstr);

			// on error
			if($entry === false) return self::response(560);

			// finish if do not found one
			} while($entry);

		// insert entry
		$loginID = self::pdo('i_login', [$mand['mobileID'], $loginstr, $opt['expireTime']]);

		// on error
		if($loginID === false) return self::response(560);

		// return success
		return self::response(201, (object)['loginID'=>$loginID, 'loginstr'=>$loginstr]);
		}

	}
