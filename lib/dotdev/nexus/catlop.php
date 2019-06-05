<?php
/*****
 * Version 1.0.2019-03-11
**/
namespace dotdev\nexus;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class catlop {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_nexus', [

			// queries: apk
			'l_apk'					=> 'SELECT * FROM `apk`',
			's_apk'					=> 'SELECT * FROM `apk` WHERE `apkID` = ? LIMIT 1',
			's_apk_by_project'		=> 'SELECT * FROM `apk` WHERE `project` = ? LIMIT 1',
			'i_apk'					=> 'INSERT INTO `apk` (`project`,`createTime`,`status`,`name`,`adjust_app`,`apk_file`,`apk_date`,`apk_version`,`apk_build`,`apk_size`,`download_as`,`keystore_alias`,`keystore_file`,`storepass`,`keypass`,`config_to`,`config`,`info`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
			'u_apk'					=> 'UPDATE `apk`
										SET `project` = ?, `status` = ?, `name` = ?,
											`adjust_app` = ?,
											`apk_file` = ?, `apk_date` = ?, `apk_version` = ?, `apk_build` = ?, `apk_size` = ?,
											`download_as` = ?,
											`keystore_alias` = ?, `keystore_file` = ?, `storepass` = ?, `keypass` = ?,
											`config_to` = ?, `config` = ?,
											`info` = ?
										WHERE `apkID` = ?
										',

			// queries: catlop
			'l_catlop'				=> 'SELECT * FROM `catlop`',
			's_catlop'				=> 'SELECT * FROM `catlop` WHERE `key` = ? LIMIT 1',
			'i_catlop'				=> 'INSERT INTO `catlop` (`key`,`archive`,`fn_status`,`fn`,`fn_default_param`,`secfn`,`secfn_default_param`) VALUES (?,?,?,?,?,?,?)',
			'u_catlop'				=> 'UPDATE `catlop` SET `archive` = ?, `fn_status` = ?, `fn` = ?, `fn_default_param` = ?, `secfn` = ?, `secfn_default_param` = ? WHERE `key` = ?',

			// queries: sslcert
			'l_sslcert'				=> 'SELECT * FROM `sslcert`',
			'l_sslcert_by_firmID'	=> 'SELECT * FROM `sslcert` WHERE `firmID` = ?',
			's_sslcert'				=> 'SELECT * FROM `sslcert` WHERE `sslcertID` = ? LIMIT 1',
			's_sslcert_def_firmID'	=> 'SELECT * FROM `sslcert` WHERE `firmID` = ? AND `default` = 1 LIMIT 1',
			'i_sslcert'				=> 'INSERT INTO `sslcert` (`firmID`,`default`,`createTime`,`public_key`,`private_key`) VALUES (?,?,?,?,?)',
			'u_sslcert'				=> 'UPDATE `sslcert` SET `default` = ?, `public_key` = ?, `private_key` = ? WHERE `sslcertID` = ?',
			'u_sslcert_firm_default'=> 'UPDATE `sslcert` SET `default` = ? WHERE `firmID` = ?',

			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_nexus');
		}


	/* lvl1 cache */
	protected static $lvl1_cache = [];


	/* Object: apk */
	public static function get_apk($req = []){

		// alternative
		$alt = h::eX($req, [
			'apkID'		=> '~1,255/i',
			'project'	=> '~^[a-z0-9_]{1,32}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: apkID
		if(isset($alt['apkID'])){

			// define cache key
			$cache_key = 'apk:by_apkID:'.$alt['apkID'];

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

				// seach in DB
				$entry = self::pdo('s_apk', [$alt['apkID']]);

				// on error or not found
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// decode config
			$entry->config = $entry->config ? json_decode($entry->config, true) : [];

			// cache entry in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: project
		if(isset($alt['project'])){

			// define cache key
			$cache_key = 'apk:by_project:'.$alt['project'];

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

				// seach in DB
				$entry = self::pdo('s_apk_by_project', [$alt['project']]);

				// on error or not found
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// decode config
			$entry->config = $entry->config ? json_decode($entry->config, true) : [];

			// cache entry in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return entry
			return self::response(200, $entry);
			}

		// param order 3: no param
		if(empty($req)){

			// load list from DB
			$list = self::pdo('l_apk');

			// on error
			if($list === false) return self::response(560);

			// decode config
			foreach($list as $entry){
				$entry->config = $entry->config ? json_decode($entry->config, true) : [];
				}

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need apkID, project or no parameter');
		}

	public static function create_apk($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'name'			=> '~1,60/s',
			'apk_file'		=> '~1,255/s',
			'download_as'	=> '~1,32/s',
			'keystore_alias'=> '~1,64/s',
			'keystore_file'	=> '~1,255/s',
			'storepass'		=> '~1,64/s',
			'keypass'		=> '~1,64/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'	=> '~Y-m-d H:i:s/d',
			'status'		=> '~^(?:active|maintenance|dev|archive)$',
			'adjust_app'	=> '~^(?:[a-z0-9]{12}|)$',
			'config_to'		=> '~/s',
			'apk_date'		=> '~Y-m-d/d',
			'apk_version'	=> '~0,16/s',
			'apk_build'		=> '~0,16777215/i',
			'apk_size'		=> '~0,16/s',
			'config_to'		=> '~0,255/s',
			'config'		=> '~/l',
			'info'			=> '~/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// set default
		$opt += [
			'createTime'	=> h::dtstr('now'),
			'status'		=> 'maintenance',
			'adjust_app'	=> null,
			'apk_date'		=> h::dtstr('now', 'Y-m-d'),
			'apk_version'	=> '',
			'apk_build'		=> 0,
			'apk_size'		=> '',
			'config_to'		=> '',
			'config'		=> [],
			'info'			=> '',
			];

		// convert config to json
		$opt['config'] = json_encode($opt['config']);

		// convert empty adjust app to null value
		if(!$opt['adjust_app']) $opt['adjust_app'] = null;

		// try to load with project name
		$res = self::get_apk([
			'project'		=> $mand['project'],
			]);

		// on error
		if(!in_array($res->status, [200, 404])) return $res;

		// if already exists, return conflict
		if($res->status == 200) return self::response(409);

		// create entry
		$apkID = self::pdo('i_apk', [$mand['project'], $opt['createTime'], $opt['status'], $mand['name'], $opt['adjust_app'], $mand['apk_file'], $opt['apk_date'], $opt['apk_version'], $opt['apk_build'], $opt['apk_size'], $mand['download_as'], $mand['keystore_alias'], $mand['keystore_file'], $mand['storepass'], $mand['keypass'], $opt['config_to'], $opt['config'], $opt['info']]);

		// on error
		if($apkID === false) return self::response(560);

		// return success
		return self::response(201, (object)['apkID' => $apkID]);
		}

	public static function update_apk($req = []){

		// mandatory
		$mand = h::eX($req, [
			'apkID'		=> '~1,255/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'status'		=> '~^(?:active|maintenance|dev|archive)$',
			'name'			=> '~1,60/s',
			'adjust_app'	=> '~^(?:[a-z0-9]{12}|)$',
			'apk_file'		=> '~1,255/s',
			'apk_date'		=> '~Y-m-d/d',
			'apk_version'	=> '~0,16/s',
			'apk_build'		=> '~0,16777215/i',
			'apk_size'		=> '~0,16/s',
			'download_as'	=> '~1,32/s',
			'keystore_alias'=> '~1,64/s',
			'keystore_file'	=> '~1,255/s',
			'storepass'		=> '~1,64/s',
			'keypass'		=> '~1,64/s',
			'config_to'		=> '~/s',
			'config'		=> '~/l',
			'info'			=> '~/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert config to json
		if(isset($opt['config'])) $opt['config'] = json_encode($opt['config']);

		// convert empty adjust app to null value
		if(isset($opt['adjust_app']) and !$opt['adjust_app']) $opt['adjust_app'] = null;

		// load entry
		$res = self::get_apk([
			'apkID'	=> $mand['apkID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// special check (different project name)
		if(isset($opt['project']) and $entry->project != $opt['project']){

			// try to load with new project name
			$res = self::get_apk([
				'project'	=> $opt['project'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return $res;

			// if already exists, return conflict
			if($res->status == 200) return self::response(409);
			}

		// define cache key
		$cache_key = 'apk:by_apkID:'.$entry->apkID;
		$cache_key_project = 'apk:by_project:'.$entry->project;
		$cache_key_rendered_config = 'apk:rendered_config_by_project:'.$entry->project;

		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_apk', [$entry->project, $entry->status, $entry->name, $entry->adjust_app, $entry->apk_file, $entry->apk_date, $entry->apk_version, $entry->apk_build, $entry->apk_size, $entry->download_as, $entry->keystore_alias, $entry->keystore_file, $entry->storepass, $entry->keypass, $entry->config_to, $entry->config, $entry->info, $entry->apkID]);

		// on error
		if($upd === false) return self::response(560);

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);
		unset(self::$lvl1_cache[$cache_key_project]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire entry
			$redis->setTimeout($cache_key, 0);
			$redis->setTimeout($cache_key_project, 0);
			$redis->setTimeout($cache_key_rendered_config, 0);
			}

		// return success
		return self::response(204);
		}


	/* Object: catlop */
	public static function get_catlop($req = []){

		// alternativ
		$alt = h::eX($req, [
			'key'	=> '~^[a-z0-9_\.]{1,32}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: key
		if(isset($alt['key'])){

			// cache key
			$cache_key = 'catlop:'.$alt['key'];

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return result
				return self::response(200, clone self::$lvl1_cache[$cache_key]);
				}

			// load redis
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

				// load entry
				$entry = self::pdo('s_catlop', [$alt['key']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable, cache entry
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// decode param
			$entry->fn_default_param = $entry->fn_default_param ? json_decode($entry->fn_default_param, true) : [];
			$entry->secfn_default_param = $entry->secfn_default_param ? json_decode($entry->secfn_default_param, true) : [];

			// cache it in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: no param
		if(empty($req)){

			// get list
			$list = self::pdo('l_catlop');

			// on error
			if($list === false) return self::response(560);

			// decode param
			foreach($list as $entry){
				$entry->fn_default_param = $entry->fn_default_param ? json_decode($entry->fn_default_param, true) : [];
				$entry->secfn_default_param = $entry->secfn_default_param ? json_decode($entry->secfn_default_param, true) : [];
				}

			// return success
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need key or no parameter');
		}

	public static function create_catlop($req = []){

		// mandatory
		$mand = h::eX($req, [
			'key'					=> '~^[a-z0-9_\.]{1,32}$',
			'fn'					=> '~^\\\\[a-z0-9\\\\:_]{1,254}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'archive'				=> '~/b',
			'fn_status'				=> '~0,999/i',
			'fn_default_param'		=> '~/l',
			'secfn'					=> '~^(?:\\\\[a-z0-9\\\\:_]{1,254}|)$',
			'secfn_default_param'	=> '~/l',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// set default
		$opt += [
			'archive'				=> false,
			'fn_status'				=> 0,
			'fn_default_param'		=> [],
			'secfn'					=> '',
			'secfn_default_param'	=> [],
			];

		// convert param to json
		$opt['fn_default_param'] = json_encode($opt['fn_default_param']);
		$opt['secfn_default_param'] = json_encode($opt['secfn_default_param']);

		// create entry
		$ins = self::pdo('i_catlop', [$mand['key'], $opt['archive'] ? 1 : 0, $opt['fn_status'], $mand['fn'], $opt['fn_default_param'], $opt['secfn'], $opt['secfn_default_param']]);

		// on error
		if($ins === false) return self::response(560);

		// return success
		return self::response(201, (object)['key'=>$mand['key']]);
		}

	public static function update_catlop($req = []){

		// mandatory
		$mand = h::eX($req, [
			'key'					=> '~^[a-z0-9_\.]{1,32}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'fn'					=> '~^\\\\[a-z0-9\\\\:_]{1,254}$',
			'archive'				=> '~/b',
			'fn_status'				=> '~0,999/i',
			'fn_default_param'		=> '~/l',
			'secfn'					=> '~^(?:\\\\[a-z0-9\\\\:_]{1,254}|)$',
			'secfn_default_param'	=> '~/l',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert param to json
		if(isset($opt['fn_default_param'])) $opt['fn_default_param'] = json_encode($opt['fn_default_param']);
		if(isset($opt['secfn_default_param'])) $opt['secfn_default_param'] = json_encode($opt['secfn_default_param']);

		// check
		$res = self::get_catlop(['key'=>$mand['key']]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_catlop', [$entry->archive ? 1 : 0, $entry->fn_status, $entry->fn, $entry->fn_default_param, $entry->secfn, $entry->secfn_default_param, $entry->key]);

		// on error
		if($upd === false) return self::response(560);

		// cache key
		$cache_key = 'catlop:'.$entry->key;

		// load redis
		$redis = self::redis();

		// expire redis and unset lvl1 cache
		if($redis) $redis->setTimeout($cache_key, 0);
		unset(self::$lvl1_cache[$cache_key]);

		// return success
		return self::response(204);
		}


	/* Object: sslcert */
	public static function get_sslcert($req = []){

		// alternative
		$alt = h::eX($req, [
			'sslcertID'	=> '~1,255/i',
			'firmID'	=> '~1,255/i',
			'default'	=> '~/b',
			], $error, true);

		// additional check: only true is allowed for default
		if(isset($opt['default']) and !$opt['default']) $error[] = 'default';

		// on error
		if($error) return self::response(400, $error);


		// param order 1: sslcertID
		if(isset($alt['sslcertID'])){

			// define cache key
			$cache_key = 'sslcert:by_sslcertID:'.$alt['sslcertID'];

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

				// seach in DB
				$entry = self::pdo('s_sslcert', [$alt['sslcertID']]);

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

		// param order 2: firmID + default
		if(isset($alt['firmID']) and !empty($alt['default'])){

			// define cache key
			$cache_key = 'sslcert:default_by_firmID:'.$alt['firmID'];

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

				// seach in DB
				$entry = self::pdo('s_sslcert_def_firmID', [$alt['firmID']]);

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

		// param order 3: firmID only
		if(isset($alt['firmID'])){

			// load list from DB
			$list = self::pdo('l_sslcert_by_firmID', $alt['firmID']);

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// param order 4: no param
		if(empty($req)){

			// load list from DB
			$list = self::pdo('l_sslcert');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}


		// other request param invalid
		return self::response(400, 'need sslcertID, firmID(+default) or no parameter');
		}

	public static function create_sslcert($req = []){

		// mandatory
		$mand = h::eX($req, [
			'firmID'		=> '~1,255/i',
			'public_key'	=> '~400,10000/s',
			'private_key'	=> '~400,10000/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'	=> '~Y-m-d H:i:s/d',
			'default'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// set default
		$opt += [
			'createTime'	=> h::dtstr('now'),
			'default'		=> false,
			];

		// create entry
		$sslcertID = self::pdo('i_sslcert', [$mand['firmID'], $opt['default'] ? 1 : 0, $opt['createTime'], $mand['public_key'], $mand['private_key']]);

		// on error
		if($sslcertID === false) return self::response(560);

		// return success
		return self::response(201, (object)['sslcertID' => $sslcertID]);
		}

	public static function update_sslcert($req = []){

		// mandatory
		$mand = h::eX($req, [
			'sslcertID'		=> '~1,255/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'default'		=> '~/b',
			'public_key'	=> '~400,10000/s',
			'private_key'	=> '~400,10000/s',
			], $error, true);

		// additional check: only true is allowed for default
		if(isset($opt['default']) and !$opt['default']) $error[] = 'default';

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_sslcert([
			'sslcertID'		=> $mand['sslcertID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// define if default is reseted
		$default_reseted = false;

		// check if we have a new firm default
		if(isset($opt['default']) and !$entry->default and $entry->firmID){

			// update
			$upd = self::pdo('u_sslcert_firm_default', [0, $entry->firmID]);

			// on error
			if($upd === false) return self::response(560);

			// define default is reseted
			$default_reseted = true;
			}

		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_sslcert', [$entry->default ? 1 : 0, $entry->public_key, $entry->private_key, $entry->sslcertID]);

		// on error
		if($upd === false) return self::response(560);

		// if default was reseted
		if($default_reseted){

			// unset lvl1 cache
			self::$lvl1_cache = [];

			// unset all related redis cache keys
			$res = self::redis_unset(['search'=>'sslcert:*']);
			}

		// else normal update
		else{

			// define cache key
			$cache_key = 'sslcert:by_sslcertID:'.$entry->sslcertID;
			$cache_key_firm = 'sslcert:default_by_firmID:'.$entry->firmID;

			// unset lvl1 cache
			unset(self::$lvl1_cache[$cache_key]);
			unset(self::$lvl1_cache[$cache_key_firm]);

			// init redis
			$redis = self::redis();

			// if redis is accessable
			if($redis){

				// expire entry
				$redis->setTimeout($cache_key, 0);
				$redis->setTimeout($cache_key_firm, 0);
				}
			}

		// return success
		return self::response(204);
		}

	}