<?php
/*****
 * Version 1.2.2019-01-16
**/
namespace dotdev\nexus;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class base {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO/Redis Config */
	protected static function pdo_config(){
		return ['mt_nexus', [

			// queries: aggregator
			'l_aggregator'				=> 'SELECT * FROM `aggregator`',
			's_aggregator'				=> 'SELECT * FROM `aggregator` WHERE `aggregatorID` = ? LIMIT 1',
			'i_aggregator'				=> 'INSERT INTO `aggregator` (`name`) VALUES (?)',
			'u_aggregator'				=> 'UPDATE `aggregator` SET `name` = ? WHERE `aggregatorID` = ?',


			// queries: firm
			'l_firm'					=> 'SELECT * FROM `firm`',
			's_firm'					=> 'SELECT * FROM `firm` WHERE `firmID` = ? LIMIT 1',
			's_firm_by_mtservice_fqdn'	=> 'SELECT * FROM `firm` WHERE `mtservice_fqdn` = ? LIMIT 1',
			'i_firm'					=> 'INSERT INTO `firm` (`name`,`mtservice_fqdn`) VALUES (?,?)',
			'u_firm'					=> 'UPDATE `firm` SET `name` = ?, `mtservice_fqdn` = ? WHERE `firmID` = ?',

			// queries: event_type
			'l_event_type'				=> 'SELECT * FROM `event_type`',
			's_event_type'				=> 'SELECT * FROM `event_type` WHERE `event_typeID` = ? LIMIT 1',
			's_event_type_by_event_key'	=> 'SELECT * FROM `event_type` WHERE `event_key` = ? LIMIT 1',
			'i_event_type'				=> 'INSERT INTO `event_type` (`event_key`,`name`) VALUES (?,?)',
			'u_event_type'				=> 'UPDATE `event_type` SET `event_key` = ?, `name` = ? WHERE `event_typeID` = ?',


			// queries: app
			'l_app'						=> 'SELECT * FROM `app`',
			's_app'						=> 'SELECT * FROM `app` WHERE `appID` = ? LIMIT 1',
			'i_app'						=> 'INSERT INTO `app` (`name`,`projectname`) VALUES (?,?)',
			'u_app'						=> 'UPDATE `app` SET `name` = ?,`projectname` = ? WHERE `appID` = ?',


			// queries: country
			'l_country'					=> 'SELECT * FROM `country` c',
			's_country'					=> 'SELECT * FROM `country` c WHERE c.countryID = ? LIMIT 1',
			's_country_by_code'			=> 'SELECT * FROM `country` c WHERE c.code = ? LIMIT 1',
			's_country_by_prefix_int'	=> 'SELECT * FROM `country` c WHERE c.prefix_int = ? LIMIT 1',
			's_country_by_mcc'			=> 'SELECT * FROM `country` c WHERE c.mcc = ? LIMIT 1',

			'i_country'					=> 'INSERT INTO `country` (`name`,`code`,`prefix_nat`,`prefix_int`,`mcc`,`currency`) VALUES (?,?,?,?,?)',
			'u_country'					=> 'UPDATE `country` SET `name` = ?, `prefix_nat` = ?, `currency` = ? WHERE `countryID` = ?',


			// queries: operator
			's_operator'				=> 'SELECT o.*, c.code, c.prefix_nat, c.prefix_int
											FROM `operator` o
											INNER JOIN `country` c ON o.countryID = c.countryID
											WHERE o.operatorID = ?
											LIMIT 1
											',
			's_operator_by_hni'			=> 'SELECT o.*, c.code, c.prefix_nat, c.prefix_int
											FROM `operator_hni` i
											INNER JOIN `operator` o ON o.operatorID = i.operatorID
											INNER JOIN `country` c ON o.countryID = c.countryID
											WHERE i.hni = ?
											LIMIT 1
											',

			'l_operator'				=> ['s_operator', ['WHERE o.operatorID = ?' => 'ORDER BY o.countryID ASC, o.operatorID ASC', 'LIMIT 1'=>'']],
			'l_operator_by_countryID'	=> ['s_operator', ['WHERE o.operatorID = ?' => 'WHERE o.countryID = ?', 'LIMIT 1'=>'']],
			'l_operator_by_code'		=> ['s_operator', ['WHERE o.operatorID = ?' => 'WHERE c.code = ?', 'LIMIT 1'=>'']],
			'l_operator_by_prefix_int'	=> ['s_operator', ['WHERE o.operatorID = ?' => 'WHERE c.prefix_int = ?', 'LIMIT 1'=>'']],

			'i_operator'				=> 'INSERT INTO `operator` (`countryID`,`name`,`color`,`ignore`) VALUES (?,?,?,?)',
			'u_operator'				=> 'UPDATE `operator` SET `name` = ?, `color` = ?, `ignore` = ? WHERE `operatorID` = ?',


			// queries: operator hni
			's_op_hni'					=> 'SELECT i.*, c.code, c.prefix_nat, c.prefix_int
											FROM `operator_hni` i
											INNER JOIN `country` c ON c.countryID = i.countryID
											WHERE i.hni = ?
											LIMIT 1
											',
			'l_op_hni'					=> ['s_op_hni', ['WHERE i.hni = ?' => 'ORDER BY i.hni ASC', 'LIMIT 1'=>'']],
			'l_op_hni_by_countryID'		=> ['s_op_hni', ['WHERE i.hni = ?' => 'WHERE i.countryID = ?', 'LIMIT 1'=>'ORDER BY i.hni ASC']],
			'l_op_hni_by_operatorID'	=> ['s_op_hni', ['WHERE i.hni = ?' => 'WHERE i.operatorID = ?', 'LIMIT 1'=>'ORDER BY i.hni ASC']],

			'i_op_hni'					=> 'INSERT INTO `operator_hni` (`hni`,`countryID`,`operatorID`,`name`) VALUES (?,?,?,?)',
			'u_op_hni'					=> 'UPDATE `operator_hni` SET `operatorID` = ?, `name` = ? WHERE `hni` = ?',


			// queries: server
			'l_server'					=> 'SELECT * FROM `server`',
			'l_server_by_nexusbase'		=> 'SELECT * FROM `server` WHERE `nexusbase` = ?',
			'l_server_by_nexuscache'	=> 'SELECT * FROM `server` WHERE `nexusbase` = ?',
			'l_server_by_firmcache'		=> 'SELECT * FROM `server` WHERE `firmID` = ? AND `nexusbase` = ?',
			's_server'					=> 'SELECT * FROM `server` WHERE `serverID` = ? LIMIT 1',
			's_server_by_ipv4'			=> 'SELECT * FROM `server` WHERE `ipv4` = ? LIMIT 1',
			's_server_by_servercom_fqdn'=> 'SELECT * FROM `server` WHERE `ipv4` = ? LIMIT 1',
			'i_server'					=> 'INSERT INTO `server` (`name`,`ipv4`,`firmID`,`servercom_fqdn`,`status`,`createTime`,`nexusbase`,`nexuscache`,`firmcache`) VALUES (?,?,?,?,?,?,?,?,?)',
			'u_server'					=> 'UPDATE `server` SET `name` = ?, `ipv4` = ?, `firmID` = ?, `servercom_fqdn` = ?, `status` = ?, `nexusbase` = ?, `nexuscache` = ?, `firmcache` = ? WHERE `serverID` = ?',
			]];
		}

	protected static function redis_config(){
		return 'mt_nexus';
		}

	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_nexus');
		}

	/* lvl1 cache */
	protected static $lvl1_cache = [];


	/* Service URL */
	public static function get_mtservice_url($req = []){

		// define static for service url
		static $service_url = null;

		// load service url
		if(!$service_url){
			$service_url = include($_SERVER['ENV_PATH'].'/config/service/mtservice/server.php');
			}

		// return service url
		return self::response(200, (object)['url' => $service_url]);
		}


	/* Object: aggregator */
	public static function get_aggregator($req = []){

		// alternative
		$alt = h::eX($req, [
			'aggregatorID'	=> '~1,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: aggregatorID
		if(isset($alt['aggregatorID'])){

			// define cache key
			$cache_key = 'aggregator:by_aggregatorID:'.$alt['aggregatorID'];

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return entry
				return self::response(200, clone self::$lvl1_cache[$cache_key]);
				}

			// init redis
			$redis = self::redis();

			// define entry
			$entry = null;

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// load entry
				$entry = $redis->get($cache_key);
				}

			// if entry is not set
			if(!$entry){

				// load entry from DB
				$entry = self::pdo('s_aggregator', [$alt['aggregatorID']]);

				// on error or not found
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache entry in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: no param
		if(empty($req)){

			// load list from DB
			$list = self::pdo('l_aggregator');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need aggregatorID or no parameter');
		}

	public static function create_aggregator($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'		=> '~1,120/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// create entry
		$aggregatorID = self::pdo('i_aggregator', [$mand['name']]);

		// on error
		if($aggregatorID === false) return self::response(560);

		// return success
		return self::response(201, (object)['aggregatorID' => $aggregatorID]);
		}

	public static function update_aggregator($req = []){

		// mandatory
		$mand = h::eX($req, [
			'aggregatorID'	=> '~1,255/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'		=> '~1,120/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_aggregator([
			'aggregatorID'	=> $mand['aggregatorID'],
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
		$upd = self::pdo('u_aggregator', [$entry->name, $entry->aggregatorID]);

		// on error
		if($upd === false) return self::response(560);

		// define cache key
		$cache_key = 'aggregator:by_aggregatorID:'.$entry->aggregatorID;

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire entry
			$redis->setTimeout($cache_key, 0);
			}

		// return success
		return self::response(204);
		}



	/* Object: firm */
	public static function get_firm($req = []){

		// alternative
		$alt = h::eX($req, [
			'firmID'		=> '~1,255/i',
			'mtservice_fqdn'=> '~1,60/s',
			'self'			=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1-2: firmID, mtservice_fqdn
		foreach(['firmID', 'mtservice_fqdn'] as $key){

			// check key
			if(isset($alt[$key])){

				// define cache key
				$cache_key = 'firm:by_'.$key.':'.$alt[$key];

				// check lvl1-cache
				if(isset(self::$lvl1_cache[$cache_key])){

					// return entry
					return self::response(200, clone self::$lvl1_cache[$cache_key]);
					}

				// init redis
				$redis = self::redis();

				// define entry
				$entry = null;

				// if redis accessable and entry exists
				if($redis and $redis->exists($cache_key)){

					// load entry
					$entry = $redis->get($cache_key);
					}

				// if entry is not set
				if(!$entry){

					// load entry from DB
					$entry = self::pdo(($key == 'firmID') ? 's_firm' : 's_firm_by_'.$key, [$alt[$key]]);

					// on error or not found
					if(!$entry) return self::response($entry === false ? 560 : 404);

					// if redis accessable, cache entry
					if($redis) $redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}

				// define other cache key
				$other_cache_key = ($key == 'firmID') ? ($entry->mtservice_fqdn ? 'firm:by_mtservice_fqdn:'.$entry->mtservice_fqdn : null) : 'firm:by_firmID:'.$entry->firmID;

				// cache entry in lvl1 cache
				self::$lvl1_cache[$cache_key] = clone $entry;
				if($other_cache_key) self::$lvl1_cache[$other_cache_key] = clone $entry;

				// return entry
				return self::response(200, $entry);
				}
			}

		// param order 3: self
		if(!empty($alt['self'])){

			// load service url
			$res = self::get_mtservice_url();

			// on error
			if($res->status != 200) return self::response(500, 'Cannot load mtservice_url: '.$res->status);

			// take fqdn
			$mtservice_fqdn = str_replace(['http://','https://'], '', $res->data->url);

			// reload with fqdn
			return self::get_firm([
				'mtservice_fqdn'=> $mtservice_fqdn,
				]);
			}

		// param order 4: no param
		if(empty($req)){

			// load list from DB
			$list = self::pdo('l_firm');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need firmID, mtservice_fqdn, self (as true) or no parameter');
		}

	public static function create_firm($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'			=> '~1,120/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'mtservice_fqdn'=> '~0,60/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define default
		$opt += [
			'mtservice_fqdn'=> null,
			];

		// convert empty mtservice_fqdn to null
		if($opt['mtservice_fqdn'] == '') $opt['mtservice_fqdn'] = null;

		// if mtservice_fqdn is given
		if($opt['mtservice_fqdn']){

			// try to load with mtservice_fqdn
			$res = self::get_firm([
				'mtservice_fqdn'	=> $opt['mtservice_fqdn'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return $res;

			// if already exists, return conflict
			if($res->status == 200) return self::response(409);
			}

		// create entry
		$firmID = self::pdo('i_firm', [$mand['name'], $opt['mtservice_fqdn']]);

		// on error
		if($firmID === false) return self::response(560);

		// return success
		return self::response(201, (object)['firmID' => $firmID]);
		}

	public static function update_firm($req = []){

		// mandatory
		$mand = h::eX($req, [
			'firmID'		=> '~1,255/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~1,120/s',
			'mtservice_fqdn'=> '~0,60/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_firm([
			'firmID'	=> $mand['firmID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// special check (different mtservice_fqdn)
		if(!empty($opt['mtservice_fqdn']) and $entry->mtservice_fqdn != $opt['mtservice_fqdn']){

			// try to load with new mtservice_fqdn
			$res = self::get_firm([
				'mtservice_fqdn'	=> $opt['mtservice_fqdn'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return $res;

			// if already exists, return conflict
			if($res->status == 200) return self::response(409);
			}

		// convert empty mtservice_fqdn to null
		if(isset($opt['mtservice_fqdn']) and $opt['mtservice_fqdn'] == '') $opt['mtservice_fqdn'] = null;

		// define cache key
		$cache_key = 'firm:by_firmID:'.$entry->firmID;
		$cache_key_mtservice = !empty($entry->mtservice_fqdn) ? 'firm:by_mtservice_fqdn:'.$entry->mtservice_fqdn : null;

		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_firm', [$entry->name, $entry->mtservice_fqdn, $entry->firmID]);

		// on error
		if($upd === false) return self::response(560);

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);
		if($cache_key_mtservice) unset(self::$lvl1_cache[$cache_key_mtservice]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire entry
			$redis->setTimeout($cache_key, 0);
			if($cache_key_mtservice) $redis->setTimeout($cache_key_mtservice, 0);
			}

		// return success
		return self::response(204);
		}



	/* Object: event */
	public static function get_event_type($req = []){

		// alternative
		$alt = h::eX($req, [
			'event_typeID'	=> '~1,65535/i',
			'event_key'		=> '~^[a-z0-9\_]{1,32}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1-2: event_typeID, event_key
		foreach(['event_typeID', 'event_key'] as $key){

			// check key
			if(isset($alt[$key])){

				// define cache key
				$cache_key = 'event_type:by_'.$key.':'.$alt[$key];

				// check lvl1-cache
				if(isset(self::$lvl1_cache[$cache_key])){

					// return entry
					return self::response(200, clone self::$lvl1_cache[$cache_key]);
					}

				// init redis
				$redis = self::redis();

				// define entry
				$entry = null;

				// if redis accessable and entry exists
				if($redis and $redis->exists($cache_key)){

					// load entry
					$entry = $redis->get($cache_key);
					}

				// if entry is not set
				if(!$entry){

					// load entry from DB
					$entry = self::pdo(($key == 'event_typeID') ? 's_event_type' : 's_event_type_by_'.$key, [$alt[$key]]);

					// on error or not found
					if(!$entry) return self::response($entry === false ? 560 : 404);

					// if redis accessable, cache entry
					if($redis) $redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}

				// define other cache key
				$other_cache_key = ($key == 'event_typeID') ? ($entry->event_key ? 'event_type:by_event_key:'.$entry->event_key : null) : 'event_type:by_event_typeID:'.$entry->event_typeID;

				// cache entry in lvl1 cache
				self::$lvl1_cache[$cache_key] = clone $entry;
				if($other_cache_key) self::$lvl1_cache[$other_cache_key] = clone $entry;

				// return entry
				return self::response(200, $entry);
				}
			}

		// param order 3: no param
		if(empty($req)){

			// load list from DB
			$list = self::pdo('l_event_type');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need event_typeID, event_key or no parameter');
		}

	public static function create_event_type($req = []){

		// mandatory
		$mand = h::eX($req, [
			'event_key'		=> '~^[a-z0-9\_]{1,32}$',
			'name'			=> '~1,60/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// try to load with event_key
		$res = self::get_event_type([
			'event_key'		=> $mand['event_key'],
			]);

		// on error
		if(!in_array($res->status, [200, 404])) return $res;

		// if already exists, return conflict
		if($res->status == 200) return self::response(409);

		// create entry
		$event_typeID = self::pdo('i_event_type', [$mand['event_key'], $mand['name']]);

		// on error
		if($event_typeID === false) return self::response(560);

		// return success
		return self::response(201, (object)['event_typeID' => $event_typeID]);
		}

	public static function update_event_type($req = []){

		// mandatory
		$mand = h::eX($req, [
			'event_typeID'	=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'event_key'		=> '~^[a-z0-9\_]{1,32}$',
			'name'			=> '~1,60/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_event_type([
			'event_typeID'	=> $mand['event_typeID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// special check (different event_key)
		if(isset($opt['event_key']) and $entry->event_key != $opt['event_key']){

			// try to load with new event_key
			$res = self::get_event_type([
				'event_key'	=> $opt['event_key'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return $res;

			// if already exists, return conflict
			if($res->status == 200) return self::response(409);
			}

		// define cache key
		$cache_key = 'event_type:by_event_typeID:'.$entry->event_typeID;
		$cache_key_event_key = $entry->event_key ? 'event_type:by_event_key:'.$entry->event_key : null;

		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_event_type', [$entry->event_key, $entry->name, $entry->event_typeID]);

		// on error
		if($upd === false) return self::response(560);

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);
		if($cache_key_event_key) unset(self::$lvl1_cache[$cache_key_event_key]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire entry
			$redis->setTimeout($cache_key, 0);
			if($cache_key_event_key) $redis->setTimeout($cache_key_event_key, 0);
			}

		// return success
		return self::response(204);
		}



	/* Object: app */
	public static function get_app($req = []){

		// alternative
		$alt = h::eX($req, [
			'appID'		=> '~1,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: appID
		if(isset($alt['appID'])){

			// define cache key
			$cache_key = 'app:by_appID:'.$alt['appID'];

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return entry
				return self::response(200, clone self::$lvl1_cache[$cache_key]);
				}

			// init redis
			$redis = self::redis();

			// define entry
			$entry = null;

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// load entry
				$entry = $redis->get($cache_key);
				}

			// if entry is not set
			if(!$entry){

				// load entry from DB
				$entry = self::pdo('s_app', [$alt['appID']]);

				// on error or not found
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache entry in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: no param
		if(empty($req)){

			// load list from DB
			$list = self::pdo('l_app');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need appID or no parameter');
		}

	public static function create_app($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'			=> '~1,120/s',
			'projectname'	=> '~1,32/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// create entry
		$appID = self::pdo('i_app', [$mand['name'], $mand['projectname']]);

		// on error
		if($appID === false) return self::response(560);

		// return success
		return self::response(201, (object)['appID' => $appID]);
		}

	public static function update_app($req = []){

		// mandatory
		$mand = h::eX($req, [
			'appID'			=> '~1,255/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~1,120/s',
			'projectname'	=> '~1,32/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_app([
			'appID'			=> $mand['appID'],
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
		$upd = self::pdo('u_app', [$entry->name, $entry->projectname, $entry->appID]);

		// on error
		if($upd === false) return self::response(560);

		// define cache key
		$cache_key = 'app:by_appID:'.$entry->appID;

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire entry
			$redis->setTimeout($cache_key, 0);
			}

		// return success
		return self::response(204);
		}



	/* Object: country */
	public static function get_country($req = []){

		// alternativ
		$alt = h::eX($req, [
			'countryID'		=> '~1,255/i',
			'code'			=> '~^[a-zA-Z]{2}$',
			'prefix_int'	=> '~^[1-9]{1}[0-9]{1,2}$',
			'mcc'			=> '~200,799/i',
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'imsi'			=> '~^([1-9]{1}[0-9]{2})([0-9]{2,13})$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1-4: countryID, country code, international prefix, mcc
		foreach(['countryID', 'code', 'prefix_int', 'mcc'] as $key){

			// check key
			if(isset($alt[$key])){

				// lowercase country code
				if($key == 'code'){
					$alt[$key] = strtolower($alt[$key]);
					}

				// define cache key
				$cache_key = 'country:by_'.$key.':'.$alt[$key];

				// check lvl1-cache
				if(isset(self::$lvl1_cache[$cache_key])){

					// return entry
					return self::response(200, clone self::$lvl1_cache[$cache_key]);
					}

				// init redis
				$redis = self::redis();

				// define entry
				$entry = null;

				// if redis accessable and entry exists
				if($redis and $redis->exists($cache_key)){

					// load entry
					$entry = $redis->get($cache_key);
					}

				// if entry is not set
				if(!$entry){

					// load entry from DB
					$entry = self::pdo(($key == 'countryID') ? 's_country' : 's_country_by_'.$key, [$alt[$key]]);

					// on error or not found
					if(!$entry) return self::response($entry === false ? 560 : 404);

					// if redis accessable, cache entry
					if($redis) $redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}

				// cache entry in lvl1 cache
				self::$lvl1_cache[$cache_key] = clone $entry;

				// return entry
				return self::response(200, $entry);
				}
			}


		// param order 5: msisdn
		if(isset($alt['msisdn'])){

			// first tree digits, then first two digits
			foreach([3,2] as $i){

				// reload with international prefix
				$res = self::get_country([
					'prefix_int' => substr((string) $alt['msisdn'][0], 0, $i),
					]);

				// if status is anything else than 404, return it
				if($res->status != 404) return $res;
				}

			// return not found
			return self::response(404);
			}

		// param order 6: imsi
		if(isset($alt['imsi'])){

			// reload with mcc param
			return self::get_country([
				'mcc' => $alt['imsi'][0],
				]);
			}

		// param order 7: no param
		if(empty($req)){

			// load list from DB
			$list = self::pdo('l_country');

			// on error
			if($list === false) return self::response(560);

			// return success
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need countryID, code, prefix_int, mcc, msisdn, imsi or no parameter');
		}

	public static function create_country($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'			=> '~1,30/s',
			'code'			=> '~^[a-zA-Z]{2}$',
			'prefix_int'	=> '~^[1-9]{1}[0-9]{1,2}$',
			'mcc'			=> '~200,799/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'prefix_nat'	=> '~^[0-9]{0,4}$',
			'currency'		=> '~^[a-zA-Z]{0,8}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define default
		$opt += [
			'prefix_nat'	=> '',
			'currency'		=> '',
			];

		// convert to lowerstring code
		$mand['code'] = strtolower($mand['code']);

		// create entry
		$countryID = self::pdo('i_country', [$mand['name'], $mand['code'], $opt['prefix_nat'], $mand['prefix_int'], $mand['mcc'], $opt['currency']]);

		// on error
		if($countryID === false) return self::response(560);

		// return success
		return self::response(201, (object)['countryID' => $countryID]);
		}

	public static function update_country($req = []){

		// mandatory
		$mand = h::eX($req, [
			'countryID'		=> '~1,255/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~1,30/s',
			'prefix_nat'	=> '~^[0-9]{0,4}$',
			'currency'		=> '~^[a-zA-Z]{0,8}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_country([
			'countryID'		=> $mand['countryID'],
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
		$upd = self::pdo('u_country', [$entry->name, $entry->prefix_nat, $entry->currency, $entry->countryID]);

		// on error
		if($upd === false) return self::response(560);

		// init redis
		$redis = self::redis();

		// for all searchable keys in cache
		foreach(['countryID', 'code', 'prefix_int'] as $key){

			// define cache key
			$cache_key = 'country:by_'.$key.':'.$entry->{$key};

			// unset in lvl1-cache
			unset(self::$lvl1_cache[$cache_key]);

			// if redis accessable
			if($redis){

				// expire entry
				$redis->setTimeout($cache_key, 0);
				}
			}

		// return success
		return self::response(204);
		}



	/* Object: operator */
	public static function get_operator($req = []){

		// alternativ
		$alt = h::eX($req, [
			'operatorID'	=> '~1,65535/i',
			'hni'			=> '~20000,79999/i',
			'countryID'		=> '~1,255/i',
			'code'			=> '~^[a-zA-Z]{2}$',
			'prefix_int'	=> '~^[1-9]{1}[0-9]{1,2}$',
			'imsi'			=> '~^([2-7]{1}[0-9]{4})[0-9]{1,11}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1-2: operatorID, hni (mcc+mnc)
		foreach(['operatorID', 'hni'] as $key){

			// check key
			if(isset($alt[$key])){

				// cache key
				$cache_key = 'operator:by_'.$key.':'.$alt[$key];

				// check lvl1-cache
				if(isset(self::$lvl1_cache[$cache_key])){

					// return entry
					return self::response(200, clone self::$lvl1_cache[$cache_key]);
					}

				// init redis
				$redis = self::redis();

				// define entry
				$entry = null;

				// if redis accessable and entry exists
				if($redis and $redis->exists($cache_key)){

					// load entry
					$entry = $redis->get($cache_key);
					}

				// if entry is not set
				if(!$entry){

					// search in DB
					$entry = self::pdo(($key == 'operatorID') ? 's_operator' : 's_operator_by_'.$key, [$alt[$key]]);

					// on error or not found
					if(!$entry) return self::response($entry === false ? 560 : 404);

					// if redis accessable, cache entry
					if($redis) $redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}

				// cache entry in lvl1 cache
				self::$lvl1_cache[$cache_key] = clone $entry;

				// return entry
				return self::response(200, $entry);
				}
			}

		// param order 2-4: countryID, country code or international prefix
		foreach(['countryID', 'code', 'prefix_int'] as $key){

			// check key
			if(isset($alt[$key])){

				// lowercase country code
				if($key == 'code'){
					$alt[$key] = strtolower($alt[$key]);
					}

				// search in DB
				$list = self::pdo('l_operator_by_'.$key, $alt[$key]);

				// on error
				if($list === false) return self::response(560);

				// return success
				return self::response(200, $list);
				}
			}

		// param order 5: imsi
		if(isset($alt['imsi'])){

			// reload with international prefix
			return self::get_operator([
				'hni' => $alt['imsi'][0],
				]);
			}

		// param order 6: no param
		if(empty($req)){

			// load list from DB
			$list = self::pdo('l_operator');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need operatorID, hni, countryID, code, prefix_int, imsi or no parameter');
		}

	public static function create_operator($req = []){

		// mandatory
		$mand = h::eX($req, [
			'countryID'		=> '~1,255/i',
			'name'			=> '~1,20/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'color'			=> '~^#[a-f0-9]{6}$',
			'ignore'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define default
		$opt += [
			'color'		=> '#cccccc',
			'ignore'	=> false,
			];

		// create entry
		$operatorID = self::pdo('i_operator', [$mand['countryID'], $mand['name'], $opt['color'], $opt['ignore'] ? 1 : 0]);

		// on error
		if($operatorID === false) return self::response(560);

		// return success
		return self::response(201, (object)['operatorID' => $operatorID]);
		}

	public static function update_operator($req = []){

		// mandatory
		$mand = h::eX($req, [
			'operatorID'	=> '~1,255/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~1,20/s',
			'color'			=> '~^#[a-f0-9]{6}$',
			'ignore'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_operator([
			'operatorID'	=> $mand['operatorID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// tale
		$entry = $res->data;

		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_operator', [$entry->name, $entry->color, $entry->ignore ? 1 : 0, $entry->operatorID]);

		// on error
		if($upd === false) return self::response(560);

		// define cache key
		$cache_key = 'operator:by_operatorID:'.$entry->operatorID;

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire entry
			$redis->setTimeout($cache_key, 0);
			}

		// return success
		return self::response(204);
		}



	/* Object: operator_hni */
	public static function get_operator_hni($req = []){

		// alternativ
		$alt = h::eX($req, [
			'hni'			=> '~20000,79999/i',
			'countryID'		=> '~1,255/i',
			'operatorID'	=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: hni
		if(isset($alt['hni'])){

			// define cache key
			$cache_key = 'operator_hni:by_hni:'.$alt['hni'];

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return entry
				return self::response(200, clone self::$lvl1_cache[$cache_key]);
				}

			// init redis
			$redis = self::redis();

			// define entry
			$entry = null;

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// load entry
				$entry = $redis->get($cache_key);
				}

			// if entry is not set
			if(!$entry){

				// load entry from DB
				$entry = self::pdo('s_op_hni', [$alt['hni']]);

				// on error or not found
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache entry in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: countryID
		if(isset($alt['countryID'])){

			// define cache key
			$cache_key = 'op_imsi:by_countryID:'.$alt['countryID'];

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return list
				return self::response(200, self::$lvl1_cache[$cache_key]);
				}

			// init redis
			$redis = self::redis();

			// define list
			$list = null;

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// load entry
				$list = $redis->get($cache_key);
				}

			// if list could not be loaded
			if(!is_array($list)){

				// search in DB
				$list = self::pdo('l_op_hni_by_countryID', [$alt['countryID']]);

				// on error or not found
				if(!$list) return self::response($list === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $list, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache entry in lvl1 cache
			self::$lvl1_cache[$cache_key] = $list;

			// return list
			return self::response(200, $list);
			}

		// param order 3: operatorID
		if(isset($alt['operatorID'])){

			// load list from DB
			$list = self::pdo('l_op_hni_by_operatorID', [$alt['operatorID']]);

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// param order 4: no param
		if(empty($req)){

			// load list from DB
			$list = self::pdo('l_op_hni');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need hni, countryID, operatorID or no parameter');
		}

	public static function create_operator_hni($req = []){

		// mandatory
		$mand = h::eX($req, [
			'hni'			=> '~20000,79999/i',
			'countryID'		=> '~1,255/i',
			'operatorID'	=> '~1,65535/i',
			'name'			=> '~1,60/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// create entry
		$ins = self::pdo('i_op_hni', [$mand['hni'], $mand['countryID'], $mand['operatorID'], $mand['name']]);

		// on error
		if($ins === false) return self::response(560);

		// init redis
		$redis = self::redis();

		// if redis accessable, expire countryID list
		if($redis) $redis->setTimeout('operator_hni:by_countryID:'.$mand['countryID'], 0);

		// return success
		return self::response(201, (object)['hni' => $mand['hni']]);
		}

	public static function update_operator_hni($req = []){

		// mandatory
		$mand = h::eX($req, [
			'hni'			=> '~20000,79999/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~1,60/s',
			'operatorID'	=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_operator_hni([
			'hni'	=> $mand['hni'],
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
		$upd = self::pdo('u_op_hni', [$entry->operatorID, $entry->name, $entry->hni]);

		// on error
		if($upd === false) return self::response(560);

		// init redis
		$redis = self::redis();

		// for all searchable keys in cache
		foreach(['hni', 'countryID'] as $key){

			// define cache key
			$cache_key = 'operator_hni:by_'.$key.':'.$entry->{$key};

			// unset in lvl1-cache
			unset(self::$lvl1_cache[$cache_key]);

			// if redis accessable
			if($redis){

				// expire entry
				$redis->setTimeout($cache_key, 0);
				}
			}

		// return success
		return self::response(204);
		}



	/* Object: server */
	public static function get_server($req = []){

		// alternative
		$alt = h::eX($req, [
			'serverID'		=> '~1,255/i',
			'ipv4'			=> '~^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$', // close enough check
			'servercom_fqdn'=> '~1,60/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1-3: serverID, ipv4, servercom_fqdn
		foreach(['serverID', 'ipv4', 'servercom_fqdn'] as $key){

			// check key
			if(isset($alt[$key])){

				// cache key
				$cache_key = 'server:by_'.$key.':'.$alt[$key];

				// check lvl1-cache
				if(isset(self::$lvl1_cache[$cache_key])){

					// return entry
					return self::response(200, clone self::$lvl1_cache[$cache_key]);
					}

				// init redis
				$redis = self::redis();

				// define entry
				$entry = null;

				// if redis accessable and entry exists
				if($redis and $redis->exists($cache_key)){

					// load entry
					$entry = $redis->get($cache_key);
					}

				// if entry is not set
				if(!$entry){

					// load entry from DB
					$entry = self::pdo(($key == 'serverID') ? 's_server' : 's_server_by_'.$key, [$alt[$key]]);

					// on error or not found
					if(!$entry) return self::response($entry === false ? 560 : 404);

					// if redis accessable, cache entry
					if($redis) $redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}

				// cache entry in lvl1 cache
				self::$lvl1_cache[$cache_key] = clone $entry;

				// return entry
				return self::response(200, $entry);
				}
			}

		// param order 2: no param
		if(empty($req)){

			// load list from DB
			$list = self::pdo('l_server');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need serverID or no parameter');
		}

	public static function create_server($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'			=> '~1,120/s',
			'ipv4'			=> '~^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$', // close enough check
			], $error);

		// optional
		$opt = h::eX($req, [
			'firmID'		=> '~0,255/i',
			'servercom_fqdn'=> '~0,60/s',
			'status'		=> '~^(?:online|maintenance|offline|archive)$',
			'createTime'	=> '~Y-m-d H:i:s/d',
			'nexusbase'		=> '~/b',
			'nexuscache'	=> '~/b',
			'firmcache'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'firmID'		=> 0,
			'servercom_fqdn'=> null,
			'status'		=> 'maintenance',
			'createTime'	=> h::dtstr('now'),
			'nexusbase'		=> false,
			'nexuscache'	=> false,
			'firmcache'		=> false,
			];

		// try to load with ipv4
		$res = self::get_server([
			'ipv4'		=> $mand['ipv4'],
			]);

		// on error
		if(!in_array($res->status, [200, 404])) return $res;

		// if already exists, return conflict
		if($res->status == 200) return self::response(409);

		// if servercom_fqdn is defined
		if($opt['servercom_fqdn']){

			// try to load with servercom_fqdn
			$res = self::get_server([
				'servercom_fqdn'	=> $opt['servercom_fqdn'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return $res;

			// if already exists, return conflict
			if($res->status == 200) return self::response(409);
			}

		// create entry
		$serverID = self::pdo('i_server', [$mand['name'], $mand['ipv4'], $opt['firmID'], $opt['servercom_fqdn'] ?: null, $opt['status'], $opt['createTime'], $opt['nexusbase'] ? 1 : 0, $opt['nexuscache'] ? 1 : 0, $opt['firmcache'] ? 1 : 0]);

		// on error
		if($serverID === false) return self::response(560);

		// return success
		return self::response(201, (object)['serverID' => $serverID]);
		}

	public static function update_server($req = []){

		// mandatory
		$mand = h::eX($req, [
			'serverID'		=> '~1,255/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~1,120/s',
			'ipv4'			=> '~^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$', // close enough check
			'firmID'		=> '~0,255/i',
			'servercom_fqdn'=> '~0,60/s',
			'status'		=> '~^(?:online|maintenance|offline|archive)$',
			'nexusbase'		=> '~/b',
			'nexuscache'	=> '~/b',
			'firmcache'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_server([
			'serverID'		=> $mand['serverID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// for each unique key
		foreach(['ipv4', 'servercom_fqdn'] as $unique_key){

			// if unique key is given (!empty) and different
			if(!empty($opt[$unique_key]) and $entry->{$unique_key} != $opt[$unique_key]){

				// try to load with new unique key
				$res = self::get_server([
					$unique_key	=> $opt[$unique_key],
					]);

				// on error
				if(!in_array($res->status, [200, 404])) return $res;

				// if already exists, return conflict
				if($res->status == 200) return self::response(409);
				}
			}

		// define cache key
		$cache_key = 'server:by_serverID:'.$entry->serverID;
		$cache_key_ipv4 = 'server:by_ipv4:'.$entry->ipv4;
		$cache_key_servercom_fqdn = $entry->servercom_fqdn ? 'server:by_servercom_fqdn:'.$entry->servercom_fqdn : null;

		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_server', [$entry->name, $entry->ipv4, $entry->firmID, $entry->servercom_fqdn ?: null, $entry->status, $entry->nexusbase ? 1 : 0, $entry->nexuscache ? 1 : 0, $entry->firmcache ? 1 : 0, $entry->serverID]);

		// on error
		if($upd === false) return self::response(560);

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);
		unset(self::$lvl1_cache[$cache_key_ipv4]);
		if($entry->servercom_fqdn) unset(self::$lvl1_cache[$cache_key_servercom_fqdn]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire entry
			$redis->setTimeout($cache_key, 0);
			$redis->setTimeout($cache_key_ipv4, 0);
			if($entry->servercom_fqdn) $redis->setTimeout($cache_key_servercom_fqdn, 0);
			}

		// return success
		return self::response(204);
		}



	// DEPRECATED, use get_operator_hni
	public static function get_operator_imsi($req = []){

		// alternativ
		$alt = h::eX($req, [
			'hni'			=> '~20000,79999/i',
			'prefix'		=> '~^[1-9]{1}[0-9]{4,5}$', // DEPRECATED
			'countryID'		=> '~1,255/i',
			'operatorID'	=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// fix DEPRECATED
		if(isset($alt['prefix'])){
			if(!isset($alt['hni'])) $alt['hni'] = (int) $alt['prefix'];
			unset($alt['prefix']);
			}

		// load response from actual function
		return self::get_operator_hni($alt);
		}

	}
