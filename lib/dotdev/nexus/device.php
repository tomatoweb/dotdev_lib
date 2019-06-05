<?php
/*****
 * Version 1.2.2018-06-20
**/
namespace dotdev\nexus;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class device {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_nexus', [

			// device_browser
			's_device_browser'							=> 'SELECT * FROM `device_browser` WHERE `device_browserID` = ? LIMIT 1',
			's_device_browser_by_name'					=> 'SELECT * FROM `device_browser` WHERE `name` = ? LIMIT 1',
			'l_device_browser'							=> 'SELECT * FROM `device_browser`',

			'i_device_browser'							=> 'INSERT INTO `device_browser` (`name`, `regex`, `prio`) VALUES (?, ?, ?)',
			'u_device_browser'							=> 'UPDATE `device_browser` SET `name` = ?,`regex` = ?, `prio` = ? WHERE `device_browserID` = ?',
			'd_device_browser'							=> 'DELETE FROM `device_browser` WHERE `device_browserID` = ?',

			// device_os
			's_device_os'								=> 'SELECT * FROM `device_os` WHERE `device_osID` = ? LIMIT 1',
			's_device_os_by_name'						=> 'SELECT * FROM `device_os` WHERE `name` = ?',
			'l_device_os'								=> 'SELECT * FROM `device_os`',

			'i_device_os'								=> 'INSERT INTO `device_os` (`name`, `regex`, `major`, `minor`, `prio`) VALUES (?, ?, ?, ?, ?)',
			'u_device_os'								=> 'UPDATE `device_os` SET `name` = ?,`regex` = ?, `major` = ?, `minor` = ?, `prio` = ? WHERE `device_osID` = ?',
			'd_device_os'								=> 'DELETE FROM `device_os` WHERE `device_osID` = ?',

			// device_vendor
			's_device_vendor'							=> 'SELECT * FROM `device_vendor` WHERE `device_vendorID` = ? LIMIT 1',
			's_device_vendor_by_name'					=> 'SELECT * FROM `device_vendor` WHERE `name` = ? LIMIT 1',
			'l_device_vendor'							=> 'SELECT * FROM `device_vendor`',

			'i_device_vendor'							=> 'INSERT INTO `device_vendor` (`name`, `regex`) VALUES (?, ?)',
			'u_device_vendor'							=> 'UPDATE `device_vendor` SET `name` = ?,`regex` = ? WHERE `device_vendorID` = ?',
			'd_device_vendor'							=> 'DELETE FROM `device_vendor` WHERE `device_vendorID` = ?',
			]];

		}


	/* Redis */
	protected static function redis(){

		return redis::load_resource('mt_nexus');
		}


	/* lvl1 cache */
	protected static $lvl1_cache = [];



	/* Object: device_browser */
	public static function get_device_browser($req = []){

		// alternative
		$alt = h::eX($req, [
			'device_browserID'	=> '~1,65536/i',
			'name'				=> '~1,32/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: device_browserID
		if(isset($alt['device_browserID'])) {

			// search in DB
			$entry = self::pdo('s_device_browser', [$alt['device_browserID']]);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return result
			return self::response(200, $entry);
			}

		// param order 2: name
		if(isset($alt['name'])) {

			// search in DB
			$entry = self::pdo('s_device_browser_by_name', [$alt['name']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);


			// return entry
			return self::response(200, $entry);
			}

		// param order 3: no param
		if(empty($req)) {

			// define cache key
			$cache_key = 'device_browser:list';

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return deep-copy list
				return self::response(200, array_map(function($entry){
					return clone $entry;
					}, self::$lvl1_cache[$cache_key]));
				}

			// init redis
			$redis = self::redis();

			// if redis accessable and list exists
			if($redis and $redis->exists($cache_key)){

				// load list
				$list = $redis->get($cache_key);
				}

			// else
			else{

				// load from DB
				$list = self::pdo('l_device_browser');

				// on error
				if($list === false) return self::response(560);

				// if redis accessable
				if($redis){

					// cache list
					$redis->set($cache_key, $list, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache a deep-copy list in lvl1 cache
			self::$lvl1_cache[$cache_key] = array_map(function($entry){
				return clone $entry;
				}, $list);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need device_browserID, name or no parameter');
		}

	public static function create_device_browser($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'			=> '~1,32/s',
			'regex'			=> '~/s',
			'prio'			=> '~1,255/i'
			], $error, true);

		// additional check
		if(isset($mand['regex']) and @preg_match('~'.$mand['regex'].'~', null) === false) $error[] = 'regex';

		// on error
		if($error) return self::response(400, $error);

		// insert entry
		$device_browserID = self::pdo('i_device_browser', [$mand['name'], $mand['regex'], $mand['prio']]);

		// on error
		if($device_browserID === false) return self::response(560);

		// define cache key
		$cache_key = 'device_browser:list';

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire cache entries
			$redis->setTimeout($cache_key, 0);
			}

		// return success
		return self::response(201, (object)['device_browserID' => $device_browserID]);
		}

	public static function update_device_browser($req = []){

		// mandatory
		$mand = h::eX($req, [
			'device_browserID'	=> '~1,65536/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'				=> '~1,32/s',
			'regex'				=> '~/s',
			'prio'				=> '~1,255/i'
			], $error, true);

		// additional check
		if(isset($opt['regex']) and @preg_match('~'.$opt['regex'].'~', null) === false) $error[] = 'regex';

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_device_browser([
			'device_browserID' => $mand['device_browserID'],
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
		$upd = self::pdo('u_device_browser', [$entry->name, $entry->regex, $entry->prio, $entry->device_browserID]);

		// on error
		if($upd === false) return self::response(560);

		// define cache keys
		$cache_key = 'device_browser:list';

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire cache entries
			$redis->setTimeout($cache_key, 0);
			}

		// return success
		return self::response(204);
		}

	public static function delete_device_browser($req = []){

		// mandatory
		$mand = h::eX($req, [
			'device_browserID'	=> '~1,65536/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// delete entry
		$del = self::pdo('d_device_browser', [$mand['device_browserID']]);

		// on error
		if($del === false) return self::response(560);

		// define cache keys
		$cache_key = 'device_browser:list';

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire cache entries
			$redis->setTimeout($cache_key, 0);
			}

		// return success
		return self::response(204);
		}



	/* Object: device_os */
	public static function get_device_os($req = []){

		// alternative
		$alt = h::eX($req, [
			'device_osID'		=> '~1,65536/i',
			'name'				=> '~1,32/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: device_osID
		if(isset($alt['device_osID'])) {

			// search in DB
			$entry = self::pdo('s_device_os', [$alt['device_osID']]);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: name
		if(isset($alt['name'])) {

			// search in DB
			$list = self::pdo('s_device_os_by_name', [$alt['name']]);

			// on error or not found
			if(!$list) return self::response($list === false ? 560 : 404);

			// return entry
			return self::response(200, $list);
			}

		// param order 3: no param
		if(empty($req)) {

			// define cache key
			$cache_key = 'device_os:list';

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return deep-copy list
				return self::response(200, array_map(function($entry){
					return clone $entry;
					}, self::$lvl1_cache[$cache_key]));
				}

			// init redis
			$redis = self::redis();

			// if redis accessable and lists exists
			if($redis and $redis->exists($cache_key)){

				// load list
				$list = $redis->get($cache_key);
				}

			// else
			else{

				// load from DB
				$list = self::pdo('l_device_os');

				// on error
				if($list === false) return self::response(560);

				// if redis accessable
				if($redis){

					// cache list
					$redis->set($cache_key, $list, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache a deep-copy list in lvl1 cache
			self::$lvl1_cache[$cache_key] = array_map(function($entry){
				return clone $entry;
				}, $list);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need device_osID, name or no parameter');
		}

	public static function create_device_os($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'			=> '~1,32/s',
			'regex'			=> '~/s',
			'prio'			=> '~1,255/i'
			], $error);

		// optional
		$opt = h::eX($req, [
			'major'			=> '~0,8/s',
			'minor'			=> '~0,8/s',
			], $error, true);

		// additional check
		if(isset($mand['regex']) and @preg_match('~'.$mand['regex'].'~', null) === false) $error[] = 'regex';

		// on error
		if($error) return self::response(400, $error);

		// insert entry
		$device_osID = self::pdo('i_device_os', [$mand['name'], $mand['regex'], $opt['major'], $opt['minor'], $mand['prio']]);

		// on error
		if($device_osID === false) return self::response(560);

		// define cache key
		$cache_key = 'device_os:list';

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire cache entries
			$redis->setTimeout($cache_key, 0);
			}

		// return success
		return self::response(201, (object)['device_osID' => $device_osID]);
		}

	public static function update_device_os($req = []){

		// mandatory
		$mand = h::eX($req, [
			'device_osID'	=> '~1,65536/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~1,32/s',
			'regex'			=> '~/s',
			'major'			=> '~0,8/s',
			'minor'			=> '~0,8/s',
			'prio'			=> '~1,255/i'
			], $error, true);

		// additional check
		if(isset($opt['regex']) and @preg_match('~'.$opt['regex'].'~', null) === false) $error[] = 'regex';

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_device_os([
			'device_osID'	=> $mand['device_osID'],
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
		$upd = self::pdo('u_device_os', [$entry->name, $entry->regex, $entry->major, $entry->minor, $entry->prio, $entry->device_osID]);

		// on error
		if($upd === false) return self::response(560);

		// define cache keys
		$cache_key = 'device_os:list';

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire cache entries
			$redis->setTimeout($cache_key, 0);
			}

		// return success
		return self::response(204);
		}

	public static function delete_device_os($req = []){

		// mandatory
		$mand = h::eX($req, [
			'device_osID'	=> '~1,65536/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// delete entry
		$del = self::pdo('d_device_os', [$mand['device_osID']]);

		// on error
		if($del === false) return self::response(560);

		// define cache keys
		$cache_key = 'device_os:list';

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire cache entries
			$redis->setTimeout($cache_key, 0);
			}

		// return success
		return self::response(204);
		}



	/* Object: device_vendor */
	public static function get_device_vendor($req = []){

		// alternative
		$alt = h::eX($req, [
			'device_vendorID'	=> '~1,65536/i',
			'name'				=> '~1,32/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: device_osID
		if(isset($alt['device_vendorID'])) {

			// search in DB
			$entry = self::pdo('s_device_vendor', [$alt['device_vendorID']]);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return result
			return self::response(200, $entry);
			}

		// param order 2: name
		if(isset($alt['name'])) {

			// search in DB
			$entry = self::pdo('s_device_vendor_by_name', [$alt['name']]);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return result
			return self::response(200, $entry);
			}

		// param order 3: no param
		if(empty($req)) {

			// define cache key
			$cache_key = 'device_vendor:list';

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return deep-copy list
				return self::response(200, array_map(function($entry){
					return clone $entry;
					}, self::$lvl1_cache[$cache_key]));
				}

			// init redis
			$redis = self::redis();

			// if redis accessable and list exists
			if($redis and $redis->exists($cache_key)){

				// load list
				$list = $redis->get($cache_key);
				}

			// else
			else{

				// load from DB
				$list = self::pdo('l_device_vendor');

				// on error
				if($list === false) return self::response(560);

				// if redis accessable
				if($redis){

					// cache list
					$redis->set($cache_key, $list, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache a deep-copy list in lvl1 cache
			self::$lvl1_cache[$cache_key] = array_map(function($entry){
				return clone $entry;
				}, $list);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need device_osID, name or no parameter');
		}

	public static function create_device_vendor($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'			=> '~1,32/s',
			'regex'			=> '~/s'
			], $error, true);

		// additional check
		if(isset($mand['regex']) and @preg_match('~'.$mand['regex'].'~', null) === false) $error[] = 'regex';

		// on error
		if($error) return self::response(400, $error);

		// insert entry
		$device_vendorID = self::pdo('i_device_vendor', [$mand['name'], $mand['regex']]);

		// on error
		if($device_vendorID === false) return self::response(560);

		// define cache key
		$cache_key = 'device_vendor:list';

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire cache entries
			$redis->setTimeout($cache_key, 0);
			}

		// return success
		return self::response(201, (object)['device_vendorID' => $device_vendorID]);
		}

	public static function update_device_vendor($req = []){

		// mandatory
		$mand = h::eX($req, [
			'device_vendorID'	=> '~1,65536/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'				=> '~1,32/s',
			'regex'				=> '~/s'
			], $error, true);

		// additional check
		if(isset($opt['regex']) and @preg_match('~'.$opt['regex'].'~', null) === false) $error[] = 'regex';

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_device_vendor([
			'device_vendorID'	=> $mand['device_vendorID'],
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
		$upd = self::pdo('u_device_vendor', [$entry->name, $entry->regex, $entry->device_vendorID]);

		// on error
		if($upd === false) return self::response(560);

		// define cache keys
		$cache_key = 'device_vendor:list';

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire cache entries
			$redis->setTimeout($cache_key, 0);
			}

		// return success
		return self::response(204);
		}

	public static function delete_device_vendor($req = []){

		// mandatory
		$mand = h::eX($req, [
			'device_vendorID'	=> '~1,65536/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// delete entry
		$del = self::pdo('d_device_vendor', [$mand['device_vendorID']]);

		// on error
		if($del === false) return self::response(560);

		// define cache keys
		$cache_key = 'device_vendor:list';

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire cache entries
			$redis->setTimeout($cache_key, 0);
			}

		// return success
		return self::response(204);
		}



	/* Helper */
	public static function parse_useragent($req = []){

		// mandatory
		$mand = h::eX($req, [
			'useragent'		=> '~1,255/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// define result
		$result = (object) [
			'useragent'			=> $mand['useragent'],
			'valid'				=> false,
			'mobile'			=> false,
			'device_browserID'	=> 0,
			'browser_name'		=> '',
			'device_osID'		=> 0,
			'os_name'			=> '',
			'os_major'			=> '',
			'os_minor'			=> '',
			'device_vendorID'	=> 0,
			'vendor_name'		=> '',
			];

		// load device browser list
		$res = self::get_device_browser();

		// on error
		if($res->status != 200) return $res;

		// sort by prio ASC
		usort($res->data, function($a, $b){
			return ($a->prio < $b->prio) ? -1 : ($a->prio == $b->prio ? 0 : 1);
			});

		// run each regex to match device browser
		foreach($res->data as $entry){
			if(preg_match('~'.$entry->regex.'~', $mand['useragent'], $match)) {
				$result->device_browserID = $entry->device_browserID;
				$result->browser_name = $entry->name;
				break;
				}
			}


		// load device os list
		$res = self::get_device_os();

		// on error
		if($res->status != 200) return $res;

		// sort by prio ASC
		usort($res->data, function($a, $b){
			return ($a->prio < $b->prio) ? -1 : ($a->prio == $b->prio ? 0 : 1);
			});

		// run each regex to match device os
		foreach($res->data as $entry){
			if(preg_match('~'.$entry->regex.'~', $mand['useragent'], $match)){
				$result->device_osID = $entry->device_osID;
				$result->os_name = $entry->name;
				$result->os_major = $entry->major;
				$result->os_minor = $entry->minor;
				break;
				}
			}


		// load device vendor list
		$res = self::get_device_vendor();

		// on error
		if($res->status != 200) return $res;

		// run each regex to match device os
		foreach($res->data as $entry){
			if(preg_match('~'.$entry->regex.'~', $mand['useragent'], $match)){
				$result->device_vendorID = $entry->device_vendorID;
				$result->vendor_name = $entry->name;
				break;
				}
			}

		// Check for smartphone
		if(preg_match('/(?i)(Mobile)/', $mand['useragent'], $match_phone)){
			$result->mobile = true;
			}

		// set valid if something is detected
		if($result->device_osID or $result->device_vendorID or $result->device_browserID) {
			$result->valid = true;
			}

		// return success and the parsed object
		return self::response(200, $result);
		}

	}