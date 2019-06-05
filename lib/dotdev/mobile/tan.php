<?php
/*****
 * Version 1.1.2018-11-19
**/
namespace dotdev\mobile;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class tan {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_mobile', [
			's_tan_last'			=> "SELECT * FROM `tan` WHERE `mobileID` = ? ORDER BY `createTime` DESC LIMIT 1",
			'l_tan_by_mobileID'		=> "SELECT * FROM `tan` WHERE `mobileID` = ? ORDER BY `createTime` ASC",
			'l_tan_by_persistID'	=> "SELECT * FROM `tan` WHERE `persistID` = ? ORDER BY `createTime` ASC",
			'i_tan'					=> "INSERT INTO `tan` (`mobileID`,`persistID`,`createTime`,`tan`,`retry`) VALUES (?,?,?,?,?)",
			'u_tan'					=> "UPDATE `tan` SET `retry` = ? WHERE `tanID` = ?",
			]];
		}

	/* Redis */
	protected static function redis(){

		return redis::load_resource('mt_mobile');
		}


	/* Object: TAN */
	public static function get_tan($req = []){

		// alternative
		$alt = h::eX($req, [
			'mobileID'		=> '~1,4294967295/i',
			'persistID'		=> '~1,4294967295/i',
			'last_only'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: mobileID + last_only
		if(isset($alt['mobileID']) and !empty($alt['last_only'])){

			// init redis
			$redis = self::redis();
			$cache_key = 'tan:last_by_mobileID:'.$alt['mobileID'];

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// take entry
				$entry = $redis->get($cache_key);
				}

			// else
			else{

				// load list from DB
				$entry = self::pdo('s_tan_last', $alt['mobileID']);

				// on error or not found
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis is accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>7200, 'nx']); // 2 hours
					}
				}

			// return list
			return self::response(200, $entry);
			}

		// param order 2: mobileID
		if(isset($alt['mobileID'])){

			// load list from DB
			$list = self::pdo('l_tan_by_mobileID', $alt['mobileID']);

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// param order 3: persistID
		if(isset($alt['persistID'])){

			// load list from DB
			$list = self::pdo('l_tan_by_persistID', $alt['persistID']);

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need mobileID(+last_only) or persistID parameter');
		}

	public static function create_tan($req = []){

		// mandatory
		$mand = h::eX($req, [
			'mobileID'		=> '~1,4294967295/i',
			'persistID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'	=> '~Y-m-d H:i:s/d',
			'tan'			=> '~4,12/s',
			'tan_length'	=> '~4,12/i',
			'tan_char'		=> '~^[a-zA-Z0-9]*$',
			'retry'			=> '~0,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define default
		$opt += [
			'createTime'	=> h::dtstr('now'),
			'tan_length'	=> 6,
			'tan_char'		=> 'abcdefghjkmnopqrstuwxyz0123456789',
			'retry'			=> 0,
			];

		// if no tan is given
		if(!isset($opt['tan'])){

			// create one
			$opt['tan'] = h::rand_str($opt['tan_length'], '', $opt['tan_char']);
			}

		// insert tan
		$tanID = self::pdo('i_tan', [$mand['mobileID'], $mand['persistID'], $opt['createTime'], $opt['tan'], $opt['retry']]);

		// on error
		if(!$tanID) return self::response(560);

		// define entry
		$entry = (object)[
			'tanID'		=> $tanID,
			'mobileID'	=> $mand['mobileID'],
			'persistID'	=> $mand['persistID'],
			'createTime'=> $opt['createTime'],
			'tan'		=> $opt['tan'],
			'retry'		=> $opt['retry'],
			];

		// init redis
		$redis = self::redis();
		$cache_key = 'tan:last_by_mobileID:'.$mand['mobileID'];

		// if redis accessable
		if($redis){

			// cache entry
			$redis->set($cache_key, $entry, ['ex'=>7200]); // 2 hours
			}

		// return result
		return self::response(201, $entry);
		}

	public static function check_tan($req = []){

		// mandatory
		$mand = h::eX($req, [
			'mobileID'		=> '~1,4294967295/i',
			'tan'			=> '~4,12/s',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'expire'		=> '~^[0-9]{1,2} (?:month|week|day|hour|min)$',
			'retry_expires'	=> '~/b',
			'match_expires'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// init redis
		$redis = self::redis();
		$cache_key = 'tan:last_by_mobileID:'.$mand['mobileID'];

		// define entry loaded from
		$entry_loaded_from = false;

		// define expiration
		$expired = false;

		// if redis accessable and entry exists
		if($redis and $redis->exists($cache_key)){

			// take entry
			$entry = $redis->get($cache_key);

			// define redis loaded
			$entry_loaded_from = 'redis';
			}

		// else
		else{

			// search in DB
			$entry = self::pdo('s_tan_last', [$mand['mobileID']]);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// define DB loaded
			$entry_loaded_from = 'db';
			}

		// if expire is defined and if tan is expired
		if(isset($opt['expire']) and h::date($entry->createTime) < h::date('-'.$opt['expire'])){

			// define tan is expired
			$expired = true;
			}

		// else if retry_expires or match_expires is set to true
		elseif(!empty($opt['retry_expires']) or !empty($opt['match_expires'])){

			// if retries left
			if($entry->retry > 0){

				// set retry value, if tan matches and match expires tan after, or decrement only
				$entry->retry = ($entry->tan == $mand['tan'] and !empty($opt['match_expires'])) ? 0 : $entry->retry - 1;

				// update tan
				$upd = self::pdo('u_tan', [$entry->retry, $entry->tanID]);

				// on error
				if($upd === false) return self::response(560);

				// if entry was loaded from redis
				if($entry_loaded_from == 'redis' and $redis){

					// update cached entry
					$redis->set($cache_key, $entry, ['ex'=>7200]);
					}
				}

			// else
			else{

				// define tan is expired
				$expired = true;
				}
			}


		// if entry was loaded from DB and redis is accessable
		if($entry_loaded_from == 'db' and $redis){

			// cache entry
			$redis->set($cache_key, $entry, ['ex'=>7200, 'nx']); // 2 hours
			}

		// return result
		return self::response(200, (object)[
			'expired'	=> $expired,
			'valid'		=> (!$expired and $entry->tan == $mand['tan']),
			]);
		}


	// DEPRECATED
	public static function list_by($req = []){

		return self::get_tan($req);
		}

	public static function create($req = []){

		return self::create_tan($req);
		}

	}
