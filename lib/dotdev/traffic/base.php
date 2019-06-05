<?php
/*****
 * Version 1.2.2018-11-19
**/
namespace dotdev\traffic;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \dotdev\nexus\device as nexus_device;

class base {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config() {
		return ['mt_traffic', [

			// queries: device
			's_device'						=> 'SELECT * FROM `device` WHERE `deviceID` = ? LIMIT 1',
			's_device_by_useragent'			=> 'SELECT * FROM `device` WHERE `useragent` = ? LIMIT 1',
			'i_device'						=> 'INSERT INTO `device` (`useragent`, `mobile`, `device_osID`, `device_vendorID`, `device_browserID`) VALUES (?, ?, ?, ?, ?)',
			'i_device_os'					=> 'INSERT INTO `device_os` (`name`, `major`, `minor`) VALUES (?, ?, ?)',
			'i_device_vendor'				=> 'INSERT INTO `device_vendor` (`name`) VALUES (?)',
			'i_device_browser'				=> 'INSERT INTO `device_browser` (`name`, `version`) VALUES (?, ?)',

			// queries: publisher affiliate
			's_affiliate'					=> "SELECT * FROM `publisher_affiliate` WHERE `publisher_affiliateID` = ? LIMIT 1",
			's_affiliate_by_key'			=> "SELECT * FROM `publisher_affiliate` WHERE `publisherID` = ? AND `affiliate_key` = ? LIMIT 1",
			'l_affiliate_by_publisher'		=> "SELECT * FROM `publisher_affiliate` WHERE `publisherID` = ?",
			'i_affiliate'					=> "INSERT INTO `publisher_affiliate` (`publisherID`,`affiliate_key`) VALUES (?,?)",
			]];

		}


	/* Redis */
	protected static function redis(){

		return redis::load_resource('mt_traffic');
		}


	/* lvl1 cache */
	protected static $lvl1_cache = [];



	/* Object: device */
	public static function get_device($req = []) {

		// alternative
		$alt = h::eX($req, [
			'useragent'		=> '~1,255/s',
			'deviceID'		=> '~1,16777215/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: deviceID
		if(isset($alt['deviceID'])) {

			// search in DB
			$entry = self::pdo('s_device', [$alt['deviceID']]);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: useragent
		if(isset($alt['useragent'])) {

			// cache key
			$cache_key = 'device:by_useragent:'.$alt['useragent'];

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return result
				return self::response(200, clone self::$lvl1_cache[$cache_key]);
				}

			// init redis
			$redis = self::redis();

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// load entry
				$entry = $redis->get($cache_key);
				}

			// else search in DB
			else{

				// load entry
				$entry = self::pdo('s_device_by_useragent', [$alt['useragent']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable, cache entry
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache it in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return entry
			return self::response(200, $entry);
			}

		// other request param invalid
		return self::response(400, 'need deviceID or useragent');
		}

	public static function create_device($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'useragent'		=> '~1,255/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// parse useragent
		$res = nexus_device::parse_useragent([
			'useragent'		=> $mand['useragent'],
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take parsed data
		$parsed = $res->data;

		// if useragent is not valid, return error
		if($parsed->valid == false) return self::response(406);

		// init redis
		$redis = self::redis();

		// define lock key
		$lock_key = 'create_device_preloading:'.sha1($mand['useragent']);

		// try to get priority (reload status every 0.4 seconds, but timeout after 1.6 seconds)
		$lock_status = redis::lock_process($redis, $lock_key, ['timeout_ms'=>2000, 'retry_ms'=>500]);

		// if concurrent process already finished creation
		if($lock_status == 200) return self::response(409);

		// on unexpected error
		if($lock_status != 100) return self::response(500, 'Creating device ends in lock status: '.$lock_status);

		// insert device
		$deviceID = self::pdo('i_device', [$parsed->useragent, $parsed->mobile ? 1 : 0, $parsed->device_osID, $parsed->device_vendorID, $parsed->device_browserID]);

		// on error
		if($deviceID === false) return self::response(560);

		// cache key
		$cache_key = 'device:by_useragent:'.$mand['useragent'];

		// cache it in lvl1 cache
		self::$lvl1_cache[$cache_key] = (object)[
			'deviceID'			=> $deviceID,
			'useragent'			=> $mand['useragent'],
			'mobile'			=> $parsed->mobile ? 1 : 0,
			'device_osID'		=> $parsed->device_osID,
			'device_vendorID'	=> $parsed->device_vendorID,
			'device_browserID'	=> $parsed->device_browserID,
			];

		// if redis accessable, cache entry
		if($redis){

			// cache entry
			$redis->set($cache_key, self::$lvl1_cache[$cache_key], ['ex'=>21600, 'nx']); // 6 hours
			}

		// set lock_status to 200 und expire lock_key after 2 minutes
		$lock_status = redis::unlock_process($redis, $lock_key, ['ttl'=>120]);

		// return success
		return self::response(201, (object)['deviceID' => $deviceID]);
		}



	/* Object: publisher_affiliate */
	public static function get_publisher_affiliate($req = []){

		// alternative
		$alt = h::eX($req, [
			'publisher_affiliateID'	=> '~1,65535/i',
			'publisherID'			=> '~1,65535/i',
			'affiliate_key'			=> '~^[a-zA-Z0-9\-\_\:\.]{1,255}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: publisher_affiliateID
		if(isset($alt['publisher_affiliateID'])){

			// search in DB
			$entry = self::pdo('s_affiliate', [$alt['publisher_affiliateID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: publisherID + affiliate_key
		if(isset($alt['publisherID']) and isset($alt['affiliate_key'])){

			// define cache key
			$cache_key = 'publisher_affiliate_key:'.$alt['publisherID'].':'.$alt['affiliate_key'];

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return result
				return self::response(200, clone self::$lvl1_cache[$cache_key]);
				}

			// init redis
			$redis = self::redis();

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// load entry
				$entry = $redis->get($cache_key);
				}

			// else
			else{

				// search in DB
				$entry = self::pdo('s_affiliate_by_key', [$alt['publisherID'], $alt['affiliate_key']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache it in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return entry
			return self::response(200, $entry);
			}

		// param order 3: publisherID only
		if(isset($alt['publisherID'])){

			// load from DB
			$list = self::pdo('l_affiliate_by_publisher', [$alt['publisherID']]);

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need publisher_affiliateID or publisherID(+affiliate_key)');
		}

	public static function create_publisher_affiliate($req = []){

		// mandatory
		$mand = h::eX($req, [
			'publisherID'	=> '~1,65535/i',
			'affiliate_key'	=> '~^[a-zA-Z0-9\-\_\:\.]{1,255}$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// init redis
		$redis = self::redis();

		// define lock key
		$lock_key = 'publisher_affiliate_insertion:'.$mand['publisherID'].':'.$mand['affiliate_key'];

		// try to get priority (reload status every 0.4 seconds, but timeout after 1.6 seconds)
		$lock_status = redis::lock_process($redis, $lock_key, ['timeout_ms'=>2000, 'retry_ms'=>500]);

		// if concurrent process already finished creation
		if($lock_status == 200) return self::response(409);

		// on unexpected error
		if($lock_status != 100) return self::response(500, 'Creating publisher_affiliate ends in lock status: '.$lock_status);

		// insert entry
		$publisher_affiliateID = self::pdo('i_affiliate', [$mand['publisherID'], $mand['affiliate_key']]);

		// on error
		if($publisher_affiliateID === false) return self::response(560);

		// cache key
		$cache_key = 'publisher_affiliate_key:'.$mand['publisherID'].':'.$mand['affiliate_key'];

		// cache it in lvl1 cache
		self::$lvl1_cache[$cache_key] = (object)[
			'publisher_affiliateID'		=> $publisher_affiliateID,
			'publisherID'				=> $mand['publisherID'],
			'affiliate_key'				=> $mand['affiliate_key'],
			];

		// if redis accessable, cache entry
		if($redis){

			// cache entry
			$redis->set($cache_key, self::$lvl1_cache[$cache_key], ['ex'=>21600, 'nx']); // 6 hours
			}

		// set lock_status to 200 und expire lock_key after 2 minutes
		$lock_status = redis::unlock_process($redis, $lock_key, ['ttl'=>120]);

		// return success
		return self::response(201, (object)['publisher_affiliateID'=>$publisher_affiliateID]);
		}

	}