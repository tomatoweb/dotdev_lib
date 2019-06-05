<?php
/*****
 * Version 1.1.2018-06-29

		Name				partID	pubID	domID	pageID

		def					-		-		-		-		// pure defaults --> no implementation yet
		def-inheriting		-		-		-		-		// set-> Pseudo-Level for inheriting values --> no implementation yet

		part				x		-		-		-		// get|set -> partner defaults

		pub-part			x		x		-		-		// get|set -> partner values for publisher
		pub-part-inherited	x		x		-		-		// get -> inherited for administration
		pub-part-inheriting	x		x		-		-		// set-> Pseudo-Level for inheriting page values

		dom-part			x		x		x		-		// get|set -> publisher values for domain
		dom-part-inherited	x		x		x		-		// get -> inherited for administration

		page-part			x		x		x		x		// get|set -> domain values for page
		page-part-inherited	x		x		x		x		// get -> inherited for administration

		// x = function needs value, a = function calculates value automatically

**/
namespace dotdev\app\adzoona;

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
		return ['app_adzoona:levelconfig', [


			// queries: Levelconfig
			'l_config_by_level'				=> "SELECT `keyname`, `val` FROM `levelconfig` WHERE `partnerID` = ? AND `publisherID` = ? AND `domainID` = ? AND `pageID` = ?",

			'iu_config'						=> "INSERT INTO `levelconfig` (`partnerID`, `publisherID`, `domainID`, `pageID`, `keyname`, `val`)
												VALUES (?,?,?,?,?,?)
												ON DUPLICATE KEY UPDATE `partnerID`=VALUES(`partnerID`), `publisherID`=VALUES(`publisherID`), `domainID`=VALUES(`domainID`), `pageID`=VALUES(`pageID`), `keyname`=VALUES(`keyname`), `val`=VALUES(`val`)
												",

			'd_config_level_keys'			=> "DELETE FROM `levelconfig` WHERE `keyname` IN (?) AND `partnerID` = ? AND `publisherID` = ? AND `domainID` = ? AND `pageID` = ?",

			'd_config_level_by_partnerID'	=> 'DELETE FROM `levelconfig` WHERE `partnerID` = ?',
			'l_config_level_by_partnerID'	=> 'SELECT * FROM `levelconfig` WHERE `partnerID` = ?',
			'l_config_level_by_keyname'		=> 'SELECT * FROM `levelconfig` WHERE `keyname` = ?',
			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('app_adzoona');
		}


	/* Object: levelconfig */
	public static function get($req = []){

		// cacheKey strings
		$ckey_strings = [ // level:partnerID-publisherID-domainID-pageID
			'part'					=> 'partnerID-0-0-0',
			'pub-part'				=> 'partnerID-publisherID-0-0',
			'pub-part-inherited'	=> 'partnerID-publisherID-0-0',
			'dom-part'				=> 'partnerID-publisherID-domainID-0',
			'dom-part-inherited'	=> 'partnerID-publisherID-domainID-0',
			'page-part'				=> 'partnerID-publisherID-domainID-pageID',
			'page-part-inherited'	=> 'partnerID-publisherID-domainID-pageID',
			// 'user-inherited'		=> 'appID-firmID-publisherID-domainID-pageID',
			];

		// inherited levels (from right to left)
		$inherited_levels = [
			'pub-part-inherited'	=> ['pub-part', 'part'],
			'dom-part-inherited'	=> ['dom-part',	'pub-part', 'part'],
			'page-part-inherited'	=> ['page-part', 'dom-part', 'pub-part', 'part'],
			// 'user-inherited'		=> ['pub-part', 'page-app', 'dom-pub', 'dom-app', 'pub', 'app', 'firm'],
			];

		// mandatory check for level
		$mand = h::eX($req, [
			'level'		=> '~^'.str_replace('-', '\-', implode('$|^', array_keys($ckey_strings))).'$',
			], $error);
		if($error) return self::response(400, $error);

		// mandatory IDs for level
		$require = [];

		if(h::is($mand['level'], '~part')) $require['partnerID'] = '~1,255/i';
		if(h::is($mand['level'], '~pub')) $require['publisherID'] = '~1,65535/i';
		if(h::is($mand['level'], '~dom')) $require['domainID'] = '~1,65535/i' and $require['publisherID'] = '~1,65535/i';
		if(h::is($mand['level'], '~page')) $require['pageID'] = '~1,65535/i' and $require['publisherID'] = '~1,65535/i' and $require['domainID'] = '~1,65535/i';

		if($require){
			$mand += h::eX($req, $require, $error);
			if($error) return self::response(400, $error);
			}

		// optional specific keys
		$opt = h::eX($req, [
			'keys'		=> '~!empty/a',
			'format'	=> '~^(?:flat|nested)$',
			], $error, true);
		if(isset($opt['keys'])){
			$opt['keys'] = array_values($opt['keys']);
			foreach($opt['keys'] as $keyname){
				if(!h::is($keyname, '~^[a-zA-Z0-9\_\-]{1,40}\:[a-zA-Z0-9\_\-]{1,80}$')) $error[] = 'keys['.$keyname.']';
				}
			}
		if($error) return self::response(400, $error);

		// set default format (nested)
		if(!isset($opt['format'])) $opt['format'] = 'flat';

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
				$list = self::pdo('l_config_by_level', $param);
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

	public static function set($req = []){

		// mandatory check for level
		$mand = h::eX($req, [
			'level'		=> '~^part$|^pub\-part$|^dom\-part$|^page\-part$',
			'keys'
			], $error);
		if($error) return self::response(400, $error);

		// mandatory for level
		$require = [];
		if(h::is($mand['level'], '~part')) $require['partnerID'] = '~1,255/i';
		if(h::is($mand['level'], '~pub')) $require['publisherID'] = '~1,65535/i';
		if(h::is($mand['level'], '~dom')) $require['domainID'] = '~1,65535/i' and $require['publisherID'] = '~1,65535/i';
		if(h::is($mand['level'], '~page')) $require['pageID'] = '~1,65535/i' and $require['publisherID'] = '~1,65535/i' and $require['domainID'] = '~1,65535/i';

		if($require){
			$mand += h::eX($req, $require, $error);
			if($error) return self::response(400, $error);
			}

		// mandatory specific keys
		if(empty($mand['keys']) or (!is_array($mand['keys']) and !is_object($mand['keys']))) $error[] = 'keys';
		else{
			if(is_object($mand['keys'])) $mand['keys'] = (array) $mand['keys'];
			foreach($mand['keys'] as $keyname => $v){
				if(!h::is($keyname, '~^[a-zA-Z0-9\_\]{1,40}\:[a-zA-Z0-9\_\-]{1,80}$')) $error[] = 'keys['.$keyname.']';
				}
			}
		if($error) return self::response(400, $error);

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
			$query = h::replace_in_str(self::pdo_extract('d_config_level_keys'), [
				'`keyname` IN (?)' => '`keyname` IN (?'.str_repeat(',?', count($delete_keys)-1).')'
				]);

			// create query param with delete keys
			$query_param = $delete_keys;

			// define IDs for query or remove it for inherite update
			foreach(['partnerID', 'publisherID', 'domainID', 'pageID'] as $IDname){
				if($inherite_update){
					if(isset($mand[$IDname])) $query_param[] = $mand[$IDname];
					else $query = str_replace('AND `'.$IDname.'` = ?', '', $query);
					}
				else{
					$query_param[] = isset($mand[$IDname]) ? $mand[$IDname] : 0;
					}
				}

			// run query
			$delete = self::pdo($query, $query_param);
			if($delete === false) return self::response(560);
			}

		// then insert/update keys
		if($update_keys){

			// take query and expand replaceables for keynames
			$query = h::replace_in_str(self::pdo_extract('iu_config'), [
				'VALUES (?,?,?,?,?,?)' => 'VALUES (?,?,?,?,?,?)'.str_repeat(',(?,?,?,?,?,?)', count($update_keys)-1)
				]);

			// create level param: `appID`, `firmID`, `publisherID`, `domainID`, `pageID`
			$level_param = [];
			foreach(['partnerID', 'publisherID', 'domainID', 'pageID'] as $IDname){
				$level_param[] = isset($mand[$IDname]) ? $mand[$IDname] : 0;
				}

			// create query param with repeatly adding level param, `keyname` and `val`
			$query_param = [];
			foreach($update_keys as $key){
				$query_param = array_merge($query_param, $level_param, [$key, $mand['keys'][$key]]);
				}

			// run query
			$insupd = self::pdo($query, $query_param);
			if($insupd === false) return self::response(560);
			}

		// if redis
		if($redis = self::redis()){

			// create string to search for redis keys, which may be invalid through db-update
			$searchstr = h::replace_in_str('levelconfig:*:partnerID-publisherID-domainID-pageID', [
				'partnerID'			=> isset($mand['partnerID']) ? $mand['partnerID'] : '*',
				'publisherID'		=> isset($mand['publisherID']) ? $mand['publisherID'] : '*',
				'domainID'			=> isset($mand['domainID']) ? $mand['domainID'] : '*',
				'pageID'			=> isset($mand['pageID']) ? $mand['pageID'] : '*',
				]);

			// use redis_unset to delete all matched keys + all lock processes
			$res = self::redis_unset(['search'=>'*'.$searchstr.'*']);

			}

		return self::response(204);
		}

	public static function delete($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'partnerID'	=> '~1,65535/i',
			], $error);
		if($error) return self::response(400, $error);

		// check levelconfig
		$res = self::pdo('l_config_level_by_partnerID', [$mand['partnerID']]);
		if(!$res) return self::response($res === false ? 560 : 404);

		// delete levelconfig
		$delete = self::pdo('d_config_level_by_partnerID', [$mand['partnerID']]);
		if($delete === false) return self::response(560);

		// update specefic key (accounter:partnerIDs) == remove partnerID
		$res = self::pdo('l_config_level_by_keyname', ['accounter:partnerIDs']);

		if(!empty($res)) {
			foreach($res as $key => $entry) {
				if(isset(json_decode($entry->val)->{$mand['partnerID']})) {
					$object = json_decode($entry->val);

					unset($object->{$mand['partnerID']});

					$res[$key]->val = json_encode($object);
					}
				}

			foreach($res as $entry) {
				self::set(['level' => 'part', 'partnerID' => $entry->partnerID, 'keys' => ['accounter:partnerIDs' => $entry->val]]);
				}
			}

		return self::response(200);

		}

	}
