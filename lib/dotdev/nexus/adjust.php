<?php
/*****
 * Version 1.0.2018-12-07
**/
namespace dotdev\nexus;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\nexus\base as nexus_base;

class adjust {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO/Redis Config */
	protected static function pdo_config(){
		return ['mt_nexus', [

			// queries: adjust_app
			'l_adjust_app'				=> 'SELECT * FROM `adjust_app`',
			's_adjust_app'				=> 'SELECT * FROM `adjust_app` WHERE `adjust_app` = ? LIMIT 1',

			'i_adjust_app'				=> 'INSERT INTO `adjust_app` (`adjust_app`,`name`,`timezone`,`secret`,`callback_salt`) VALUES (?,?,?,?,?)',
			'u_adjust_app'				=> 'UPDATE `adjust_app` SET `name` = ?, `timezone` = ?, `secret` = ?, `callback_salt` = ? WHERE `adjust_app` = ?',


			// queries: adjust_event
			's_adjust_event_unique'		=> 'SELECT * FROM `adjust_event` WHERE `adjust_app` = ? AND `event_typeID` = ? LIMIT 1',
			'l_adjust_event'			=> 'SELECT * FROM `adjust_event`',
			'l_adjust_event_by_app'		=> 'SELECT * FROM `adjust_event` WHERE `adjust_app` = ?',

			'i_adjust_event'			=> 'INSERT INTO `adjust_event` (`adjust_app`,`event_typeID`,`adjust_event`) VALUES (?,?,?)',
			'u_adjust_event'			=> 'UPDATE `adjust_event` SET `adjust_event` = ? WHERE `adjust_app` = ? AND `event_typeID` = ?',
			'd_adjust_event'			=> 'DELETE FROM `adjust_event` WHERE `adjust_app` = ? AND `event_typeID` = ?',

			]];
		}

	protected static function redis_config(){
		return 'mt_nexus';
		}


	/* lvl1 cache */
	protected static $lvl1_cache = [];



	/* Object: adjust_app */
	public static function get_adjust_app($req = []){

		// alternative
		$alt = h::eX($req, [
			'adjust_app'	=> '~^[a-z0-9]{12}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: adjust_app
		if(isset($alt['adjust_app'])){

			// define cache key
			$cache_key = 'adjust_app:'.$alt['adjust_app'];

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
				$entry = self::pdo('s_adjust_app', [$alt['adjust_app']]);

				// on error or not found
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// decode config
			$entry->secret = $entry->secret ? json_decode($entry->secret, true) : [];

			// cache entry in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: no param
		if(empty($req)){

			// load list from DB
			$list = self::pdo('l_adjust_app');

			// on error
			if($list === false) return self::response(560);

			// decode config
			foreach($list as $entry){
				$entry->secret = $entry->secret ? json_decode($entry->secret, true) : [];
				}

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need adjust_app or no parameter');
		}

	public static function create_adjust_app($req = []){

		// mandatory
		$mand = h::eX($req, [
			'adjust_app'	=> '~^[a-z0-9]{12}$',
			'name'			=> '~1,120/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'timezone'		=> '~^[\+\-][0-9]{4}$',
			'secret'		=> '~/l',
			'callback_salt'	=> '~1,16/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define default
		$opt += [
			'timezone'		=> '+0000',
			'secret'		=> [],
			'callback_salt'	=> h::rand_str(16),
			];

		// convert param to json
		$opt['secret'] = json_encode($opt['secret']);

		// try to load adjust_app
		$res = self::get_adjust_app([
			'adjust_app'	=> $mand['adjust_app'],
			]);

		// if entry already exists, return conflict
		if($res->status == 200) return self::response(409);

		// on unexpected error
		if($res->status != 404) return self::response(570, $res);

		// create entry
		$ins = self::pdo('i_adjust_app', [$mand['adjust_app'], $mand['name'], $opt['timezone'], $opt['secret'], $opt['callback_salt']]);

		// on error
		if($ins === false) return self::response(560);

		// return success
		return self::response(201, (object)['adjust_app' => $mand['adjust_app']]);
		}

	public static function update_adjust_app($req = []){

		// mandatory
		$mand = h::eX($req, [
			'adjust_app'	=> '~^[a-z0-9]{12}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~1,120/s',
			'timezone'		=> '~^[\+\-][0-9]{4}$',
			'secret'		=> '~/l',
			'callback_salt'	=> '~1,16/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert secret to json
		if(isset($opt['secret'])) $opt['secret'] = json_encode($opt['secret']);

		// load entry
		$res = self::get_adjust_app([
			'adjust_app'	=> $mand['adjust_app'],
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
		$upd = self::pdo('u_adjust_app', [$entry->name, $entry->timezone, $entry->secret, $entry->callback_salt, $entry->adjust_app]);

		// on error
		if($upd === false) return self::response(560);

		// define cache key
		$cache_key = 'adjust_app:'.$entry->adjust_app;

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



	/* Object: adjust_event */
	public static function get_adjust_event($req = []){

		// alternative
		$alt = h::eX($req, [
			'adjust_app'	=> '~^[a-z0-9]{12}$',
			'event_typeID'	=> '~1,65535/i',
			'event_key'		=> '~^[a-z0-9\_]{1,32}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: adjust_app + (event_typeID or event_key)
		if(isset($alt['adjust_app']) and (isset($alt['event_typeID']) or isset($alt['event_key']))){

			// if event_typeID is not set
			if(!isset($alt['event_typeID'])){

				// use event_key to load event_typeID
				$res = nexus_base::get_event_type([
					'event_key'	=> $alt['event_key'],
					]);

				// on unexpected error
				if(!in_array($res->status, [200, 404])) return self::response(570, $res);

				// if event does not exist, return 404
				if($res->status == 404) return self::response(404);

				// take event_typeID
				$alt['event_typeID'] = $res->data->event_typeID;
				}

			// define cache key
			$cache_key = 'adjust_app:'.$alt['adjust_app'].':event:'.$alt['event_typeID'];

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
				$entry = self::pdo('s_adjust_event_unique', [$alt['adjust_app'], $alt['event_typeID']]);

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

		// param order 2: adjust_app
		if(isset($alt['adjust_app'])){

			// load list from DB
			$list = self::pdo('l_adjust_event_by_app', [$alt['adjust_app']]);

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// param order 3: no param
		if(empty($req)){

			// load list from DB
			$list = self::pdo('l_adjust_event');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need adjust_event or no parameter');
		}

	public static function create_adjust_event($req = []){

		// mandatory
		$mand = h::eX($req, [
			'adjust_app'	=> '~^[a-z0-9]{12}$',
			'event_typeID'	=> '~1,65535/i',
			'adjust_event'	=> '~^[a-z0-9]{6}$',
			], $error);

		// on error
		if($error) return self::response(400, $error);


		// try to load adjust_app with event_typeID
		$res = self::get_adjust_event([
			'adjust_app'	=> $mand['adjust_app'],
			'event_typeID'	=> $mand['event_typeID'],
			]);

		// if entry already exists, return conflict
		if($res->status == 200) return self::response(409);

		// on unexpected error
		if($res->status != 404) return self::response(570, $res);


		// create entry
		$ins = self::pdo('i_adjust_event', [$mand['adjust_app'], $mand['event_typeID'], $mand['adjust_event']]);

		// on error
		if($ins === false) return self::response(560);

		// return success
		return self::response(201, (object)['adjust_app' => $mand['adjust_app'], 'event_typeID' => $mand['event_typeID']]);
		}

	public static function update_adjust_event($req = []){

		// mandatory
		$mand = h::eX($req, [
			'adjust_app'	=> '~^[a-z0-9]{12}$',
			'event_typeID'	=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'adjust_event'	=> '~^[a-z0-9]{6}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// load entry
		$res = self::get_adjust_event([
			'adjust_app'	=> $mand['adjust_app'],
			'event_typeID'	=> $mand['event_typeID'],
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
		$upd = self::pdo('u_adjust_event', [$entry->adjust_event, $entry->adjust_app, $entry->event_typeID]);

		// on error
		if($upd === false) return self::response(560);


		// define cache key
		$cache_key = 'adjust_app:'.$entry->adjust_app.':event:'.$entry->event_typeID;

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

	public static function delete_adjust_event($req = []){

		// mandatory
		$mand = h::eX($req, [
			'adjust_app'	=> '~^[a-z0-9]{12}$',
			'event_typeID'	=> '~1,65535/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);


		// load entry
		$res = self::get_adjust_event([
			'adjust_app'	=> $mand['adjust_app'],
			'event_typeID'	=> $mand['event_typeID'],
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if event was not found, return gone
		if($res->status == 404) return self::response(410);

		// take entry
		$entry = $res->data;


		// delete
		$del = self::pdo('d_adjust_event', [$entry->adjust_app, $entry->event_typeID]);

		// on error
		if($del === false) return self::response(560);


		// define cache key
		$cache_key = 'adjust_app:'.$entry->adjust_app.':event:'.$entry->event_typeID;

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

	}
