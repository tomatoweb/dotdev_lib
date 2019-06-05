<?php
/*****
 * Version 1.0.2018-09-14
**/
namespace dotdev\bragi;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class profile {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;


	// !!!!! THIS CLASS IS STILL UNFINISHED - DO NOT USE !!!!!

	/* PDO Config */
	protected static function pdo_config(){
		return ['app_bragi', [

			// queries: profile
			's_profile'						=> 'SELECT * FROM `profile` p WHERE p.profileID = ? LIMIT 1',
			's_profile_by_mobileID'			=> 'SELECT p.* FROM `profile_mobile` pm INNER JOIN `profile` p ON pm.profileID = p.profileID WHERE pm.mobileID = ? LIMIT 1',
			's_profile_by_persistID'		=> 'SELECT p.* FROM `profile_persist` pp INNER JOIN `profile` p ON pp.profileID = p.profileID WHERE pp.persistID = ? LIMIT 1',
			'l_user_profile_by_countryID'	=> 'SELECT * FROM `profile` p WHERE p.countryID = ? AND p.poolID = 0',
			'l_pool_profile'				=> 'SELECT * FROM `profile` p WHERE p.poolID = ?',
			'l_pool_profile_nothidden'		=> 'SELECT * FROM `profile` p WHERE p.poolID = ? AND p.hidden = 0',

			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('app_bragi');
		}


	public static function get_profile($req = []){

		// alternative
		$alt = h::eX($req, [
			'profileID'		=> '~1,16777215/i',
			'poolID'		=> '~1,16777215/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'gender'		=> '~^[MF]$',
			'orientation'	=> '~^[MFB]$',
			'hidden'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: profileID
		if(isset($alt['profileID'])){

			// load list from DB
			$entry = self::pdo('s_profile', $alt['profileID']);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return list
			return self::response(200, $entry);
			}

		// param order 2: poolID + hidden
		if(isset($alt['poolID']) and !empty($opt['hidden'])){

			// load list from DB
			$list = self::pdo('l_image_by_profileID', $alt['profileID']);

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// param order 3: poolID
		if(isset($alt['poolID'])){

			// init redis
			$redis = self::redis();

			// define cache key
			$cache_key = 'pool_profile_list:'.$alt['poolID'];

			// define list
			$list = null;

			// if redis accessable and list exists
			if($redis and $redis->exists($cache_key)){

				// take list
				$list = $redis->get($cache_key);
				}

			// if no list is loaded
			if(!is_array($list)){

				// load list from DB
				$list = self::pdo('l_image_by_profileID', $alt['profileID']);

				// on error
				if($list === false) return self::response(560);

				// if redis is accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $list, ['ex'=>7200, 'nx']); // 2 hours
					}
				}

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need persistID or profileID (+moderator, +fsk) parameter');
		}

	}