<?php
/*****
 * Version 1.0.2018-12-07

		Name				appID	firmID	pubID	domID	pageID

		def					-		-		-		-		-		// pure defaults --> no implementation yet
		def-inheriting		-		-		-		-		-		// set-> Pseudo-Level for inheriting values --> no implementation yet

		firm				-		x		-		-		-		// get|set -> firm defaults

		app					x		-		-		-		-		// get|set -> app defaults
		app-inheriting		x		-		-		-		-		// set-> Pseudo-Level for inheriting dom&page values

		pub					-		-		x		-		-		// get|set -> publisher defaults
		pub-inheriting		-		-		x		-		-		// set-> Pseudo-Level for inheriting dom&page values

		dom-app				x		-		-		x		-		// get|set -> domain values for app
		dom-app-inherited	x		-		-		x		-		// get -> inherited for administration
		dom-app-inheriting	x		-		-		x		-		// set-> Pseudo-Level for inheriting page values

		page-app			x		-		-		a		x		// get|set -> page values for app
		page-app-inherited	x		-		-		a		x		// get -> inherited for administration

		dom-pub				-		-		x		x		-		// get|set -> domain values for publisher
		dom-pub-inherited	-		-		x		x		-		// get -> inherited for administration
		dom-pub-inheriting	-		-		x		x		-		// set-> Pseudo-Level for inheriting page values

		page-pub			-		-		x		a		x		// get|set -> page values for publisher
		page-pub-inherited	-		-		x		a		x		// get -> inherited for administration

		user-inherited		a		a		a/o		a		x		// get -> inherited for user

		// x = function needs value, a = function calculates value automatically, o = param could be overwritten

**/
namespace dotdev\nexus;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \dotdev\nexus\domain;

class levelconfig {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_nexus', [


			// queries: Levelconfig
			'l_levelconfig_by_level'		=> 'SELECT `keyname`, `val` FROM `levelconfig` WHERE `appID` = ? AND `firmID` = ? AND `publisherID` = ? AND `domainID` = ? AND `pageID` = ?',

			'iu_levelconfig'				=> 'INSERT INTO `levelconfig` (`appID`, `firmID`, `publisherID`, `domainID`, `pageID`, `keyname`, `val`) VALUES (?,?,?,?,?,?,?)
												ON DUPLICATE KEY UPDATE
													`appID` = VALUES(`appID`),
													`firmID` = VALUES(`firmID`),
													`publisherID` = VALUES(`publisherID`),
													`domainID` = VALUES(`domainID`),
													`pageID` = VALUES(`pageID`),
													`keyname` = VALUES(`keyname`),
													`val` = VALUES(`val`)
												',
			'd_levelconfig_by_level_keys'	=> 'DELETE FROM `levelconfig` WHERE `keyname` IN (?) AND `appID` = ? AND `firmID` = ? AND `publisherID` = ? AND `domainID` = ? AND `pageID` = ?',

			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_nexus');
		}


	/* Object: levelconfig */
	public static function get_levelconfig($req = []){

		// cacheKey strings
		$ckey_strings = [ // level:appID-firmID-publisherID-domainID-pageID
			'firm'					=> '0-firmID-0-0-0',
			'app'					=> 'appID-0-0-0-0',
			'pub'					=> '0-0-publisherID-0-0',
			'dom-app'				=> 'appID-0-0-domainID-0',
			'dom-app-inherited'		=> 'appID-0-0-domainID-0',
			'page-app'				=> 'appID-0-0-domainID-pageID',
			'page-app-inherited'	=> 'appID-0-0-domainID-pageID',
			'dom-pub'				=> '0-0-publisherID-domainID-0',
			'dom-pub-inherited'		=> '0-0-publisherID-domainID-0',
			'page-pub'				=> '0-0-publisherID-domainID-pageID',
			'page-pub-inherited'	=> '0-0-publisherID-domainID-pageID',
			'user-inherited'		=> 'appID-firmID-publisherID-domainID-pageID',
			];

		// inherited levels (from right to left)
		$inherited_levels = [
			'dom-app-inherited'		=> ['dom-app', 'app'],
			'page-app-inherited'	=> ['page-app', 'dom-app', 'app'],
			'dom-pub-inherited'		=> ['dom-pub', 'pub'],
			'page-pub-inherited'	=> ['page-pub', 'dom-pub', 'pub'],
			'user-inherited'		=> ['page-pub', 'page-app', 'dom-pub', 'dom-app', 'pub', 'app', 'firm'],
			];

		// mandatory check for level
		$mand = h::eX($req, [
			'level'					=> '~^'.str_replace('-', '\-', implode('$|^', array_keys($ckey_strings))).'$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// define array for required keys
		$require = [];

		// add keys for specific level
		if(h::is($mand['level'], '~firm')) $require['firmID'] = '~1,255/i';
		if(h::is($mand['level'], '~app')) $require['appID'] = '~1,255/i';
		if(h::is($mand['level'], '~pub')) $require['publisherID'] = '~1,65535/i';
		if(h::is($mand['level'], '~dom')) $require['domainID'] = '~1,65535/i';
		if(h::is($mand['level'], '~page|user')) $require['pageID'] = '~1,65535/i';

		// if keys are required
		if($require){

			// mandatory
			$mand += h::eX($req, $require, $error);

			// on error
			if($error) return self::response(400, $error);
			}

		// optional
		$opt = h::eX($req, [

			// specific keys only
			'keys'			=> '~!empty/a',

			// returnage format
			'format'		=> '~^(?:flat|nested)$',

			// optional key for overwriting publisher
			'publisherID'	=> '~1,65535/i',
			], $error, true);

		// additional check
		if(isset($opt['keys'])){
			$opt['keys'] = array_values($opt['keys']);
			foreach($opt['keys'] as $keyname){
				if(!h::is($keyname, '~^[a-zA-Z0-9\_]{1,40}\:[a-zA-Z0-9\_]{1,80}$')) $error[] = 'keys['.$keyname.']';
				}
			}

		// on error
		if($error) return self::response(400, $error);

		// set default format
		if(!isset($opt['format'])) $opt['format'] = 'flat';

		// if pageID exists
		if(isset($mand['pageID'])){

			// load advertised target
			$res = domain::get_adtarget([
				'pageID'	=> $mand['pageID'],
				]);

			// on error
			if($res->status != 200) return $res;

			// add domainID
			$mand['domainID'] = $res->data->domainID;

			// if level is user-inherited
			if($mand['level'] == 'user-inherited'){

				// add missing IDs
				$mand['firmID'] = $res->data->firmID;
				$mand['appID'] = $res->data->appID;

				// prefer given publisherID over adtargets publisherID
				$mand['publisherID'] = $opt['publisherID'] ?? $res->data->publisherID;
				}
			}


		// cacheKey-Array: level:appID-firmID-publisherID-domainID-pageID
		$ckey = [];

		// add all needed CacheKeys
		if(isset($inherited_levels[$mand['level']])){
			foreach(array_reverse($inherited_levels[$mand['level']]) as $level){
				$ckey[$level] = $ckey_strings[$level];
				}
			}
		$ckey[$mand['level']] = $ckey_strings[$mand['level']];

		// replace IDs in Level-Strings to create cachekey-string (level:appID-firmID-publisherID-domainID-pageID)
		foreach($ckey as $level => $basestr){
			$ckey[$level] = $level.':'.str_replace(array_keys($mand), array_values($mand), $basestr);
			}

		// some variables
		$result = [];
		$filtered = false;
		$lock_status = 0;

		// if redis
		if($redis = self::redis()){

			// status 200, if data exists, or exists after locked process
			$lock_status = $redis->exists('levelconfig:'.$ckey[$mand['level']]) ? 200 : redis::lock_process($redis, 'levelconfig:'.$ckey[$mand['level']], ['timeout_ms'=>4000, 'retry_ms'=>400]);

			// check if redis cache fulfill the request
			if($lock_status == 200){

				// if only a set of keys is defined
				if(isset($opt['keys'])){

					// load set of HashKeys
					$result[$ckey[$mand['level']]] = $redis->hMGet('levelconfig:'.$ckey[$mand['level']], $opt['keys']);

					// for each key
					foreach($result[$ckey[$mand['level']]] as $key => $val){

						// unset key, if value is false
						if($val === false) unset($result[$ckey[$mand['level']]][$key]);
						}

					// define result is filtered
					$filtered = true;
					}

				// else
				else{

					// load all HashKeys
					$result[$ckey[$mand['level']]] = $redis->hGetAll('levelconfig:'.$ckey[$mand['level']]);
					}
				}
			}


		// load data from DB
		if(!isset($result[$ckey[$mand['level']]])){

			// load each needed level
			foreach($ckey as $level => $key){

				// skip inherited Keys
				if(strpos($key, 'inherited') !== false) continue;

				// check if redis has this level
				if($redis = self::redis() and $redis->exists('levelconfig:'.$key)){
					$result[$ckey[$level]] = $redis->hGetAll('levelconfig:'.$key);
					continue;
					}

				// create param array from cachekey-string -> [appID, firmID, publisherID, domainID, pageID]
				$param = explode('-', str_replace($level.':', '', $key));

				// load from DB
				$list = self::pdo('l_levelconfig_by_level', $param);


				// on error
				if($list === false) return self::response(560);

				// take each entry
				$result[$key] = [];
				foreach($list as $entry){ // keyname, val
					$result[$key][$entry->keyname] = $entry->val;
					}

				// save Memory
				unset($list);

				// cache in Redis
				if($redis = self::redis() and !$redis->exists('levelconfig:'.$key)){
					$redis->hMSet('levelconfig:'.$key, $result[$key]);

					// set cache expire to 6 hours
					$redis->setTimeout('levelconfig:'.$key, 21600);
					}
				}


			// create inherited Level
			if(isset($inherited_levels[$mand['level']])){

				$result[$ckey[$mand['level']]] = [];

				// inherite
				foreach($inherited_levels[$mand['level']] as $level){

					if(!isset($result[$ckey[$level]])) return self::response(500, 'Function running inconsistent');

					$result[$ckey[$mand['level']]] += $result[$ckey[$level]];
					}

				// cache in redis
				if($redis = self::redis() and !$redis->exists('levelconfig:'.$ckey[$mand['level']])){
					$redis->hMSet('levelconfig:'.$ckey[$mand['level']], $result[$ckey[$mand['level']]]);

					// set cache expire to 6 hours
					$redis->setTimeout('levelconfig:'.$ckey[$mand['level']], 21600);
					}
				}
			}

		// redis unlock
		if($redis = self::redis() and $lock_status == 100){
			$lock_status = redis::unlock_process($redis, 'levelconfig:'.$ckey[$mand['level']], ['ttl'=>120]);
			}

		// filter keys from result array (if needed and loaded from DB)
		if(isset($opt['keys']) and !$filtered){
			foreach($result[$ckey[$mand['level']]] as $key => $var){
				if(!in_array($key, $opt['keys'])) unset($result[$ckey[$mand['level']]][$key]);
				}
			}

		// convert to nested
		if($opt['format'] == 'nested'){

			// create temporary array
			$nested_result = [];

			// convert to nested
			foreach($result[$ckey[$mand['level']]] as $kset => $val){
				$link = &$nested_result;

				foreach(explode(':', $kset) as $k){
					if(is_array($link)){
						if(!isset($link[$k])) $link[$k] = [];
						$link = &$link[$k];
						continue;
						}
					return self::response(500 , 'Failed to convert levelconfig');
					}

				$link = $val;
				}

			// save to result
			$result[$ckey[$mand['level']]] = $nested_result;
			}

		// return result
		return self::response(200, $result[$ckey[$mand['level']]]);
		}

	public static function set_levelconfig($req = []){

		// mandatory check for level
		$mand = h::eX($req, [
			'level'		=> '~^firm$|^app$|^pub$|^dom\-app$|^dom\-pub$|^page\-app$|^page\-pub$|^app\-inheriting$|^pub\-inheriting$|^dom\-app\-inheriting$|^dom\-pub\-inheriting$',
			'keys'
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// mandatory for level
		$require = [];
		if(h::is($mand['level'], '~firm')) $require['firmID'] = '~1,255/i';
		if(h::is($mand['level'], '~app')) $require['appID'] = '~1,255/i';
		if(h::is($mand['level'], '~pub')) $require['publisherID'] = '~1,65535/i';
		if(h::is($mand['level'], '~dom')) $require['domainID'] = '~1,65535/i';
		if(h::is($mand['level'], '~page')) $require['pageID'] = '~1,65535/i';

		if($require){
			$mand += h::eX($req, $require, $error);

			// on error
			if($error) return self::response(400, $error);
			}

		// mandatory specific keys
		if(empty($mand['keys']) or (!is_array($mand['keys']) and !is_object($mand['keys']))){
			$error[] = 'keys';
			}
		else{
			if(is_object($mand['keys'])){
				$mand['keys'] = (array) $mand['keys'];
				}
			foreach($mand['keys'] as $keyname => $v){
				if(!h::is($keyname, '~^[a-zA-Z0-9\_]{1,40}\:[a-zA-Z0-9\_]{1,80}$')){
					$error[] = 'keys['.$keyname.']';
					}
				}
			}

		// on error
		if($error) return self::response(400, $error);

		// if pageID exists
		if(isset($mand['pageID'])){

			// load advertised target
			$res = domain::get_adtarget([
				'pageID'	=> $mand['pageID'],
				]);

			// on error
			if($res->status != 200) return $res;

			// set domainID
			$mand['domainID'] = $res->data->domainID;
			}

		// define if inheriting update
		$inherite_update = strpos($mand['level'], '-inheriting') !== false;

		// sort keys for deleting and updating
		$delete_keys = [];
		$update_keys = [];
		foreach($mand['keys'] as $key => $value){

			// NULL means deleting, inherite deletes also
			if($value === null or $inherite_update) $delete_keys[] = $key;

			// insert/Update each key with a value
			if($value !== null) $update_keys[] = $key;
			}

		// first delete Keys
		if($delete_keys){

			// take query and expand replaceables for keynames
			$query = h::replace_in_str(self::pdo_extract('d_levelconfig_by_level_keys'), [
				'`keyname` IN (?)' => '`keyname` IN (?'.str_repeat(',?', count($delete_keys)-1).')'
				]);

			// create query param with delete keys
			$query_param = $delete_keys;

			// define IDs for query or remove it for inherite update
			foreach(['appID','firmID','publisherID','domainID','pageID'] as $IDname){
				if($inherite_update){
					if(isset($mand[$IDname])){
						$query_param[] = $mand[$IDname];
						}
					else{
						$query = str_replace('AND `'.$IDname.'` = ?', '', $query);
						}
					}
				else{
					$query_param[] = isset($mand[$IDname]) ? $mand[$IDname] : 0;
					}
				}

			// run query
			$delete = self::pdo($query, $query_param);

			// on error
			if($delete === false) return self::response(560);
			}

		// then insert/update keys
		if($update_keys){

			// take query and expand replaceables for keynames
			$query = h::replace_in_str(self::pdo_extract('iu_levelconfig'), [
				'VALUES (?,?,?,?,?,?,?)' => 'VALUES (?,?,?,?,?,?,?)'.str_repeat(',(?,?,?,?,?,?,?)', count($update_keys)-1)
				]);

			// create level param: `appID`, `firmID`, `publisherID`, `domainID`, `pageID`
			$level_param = [];
			foreach(['appID','firmID','publisherID','domainID','pageID'] as $IDname){
				$level_param[] = isset($mand[$IDname]) ? $mand[$IDname] : 0;
				}

			// create query param with repeatly adding level param, `keyname` and `val`
			$query_param = [];
			foreach($update_keys as $key){
				$query_param = array_merge($query_param, $level_param, [$key, $mand['keys'][$key]]);
				}

			// run query
			$insupd = self::pdo($query, $query_param);

			// on error
			if($insupd === false) return self::response(560);
			}

		// if redis
		if($redis = self::redis()){

			// create string to search for redis keys, which may be invalid through db-update
			$searchstr = h::replace_in_str('levelconfig:*:appID-firmID-publisherID-domainID-pageID', [
				'appID'			=> isset($mand['appID']) ? $mand['appID'] : '*',
				'firmID'		=> isset($mand['firmID']) ? $mand['firmID'] : '*',
				'publisherID'	=> isset($mand['publisherID']) ? $mand['publisherID'] : '*',
				'domainID'		=> isset($mand['domainID']) ? $mand['domainID'] : '*',
				'pageID'		=> isset($mand['pageID']) ? $mand['pageID'] : '*',
				]);

			// use redis_unset to delete all matched keys + all lock processes
			$res = self::redis_unset([
				'search'	=> '*'.$searchstr.'*',
				]);

			// if pageID given
			if(isset($mand['pageID'])){

				// force adtarget update (which resets cache adtarget)
				$res = domain::update_adtarget([
					'pageID'	=> $mand['pageID'],
					]);
				}
			}

		// return success
		return self::response(204);
		}


	// DEPRECATED
	public static function get($req = []){

		return self::get_levelconfig($req);
		}

	public static function set($req = []){

		return self::set_levelconfig($req);
		}

	}
