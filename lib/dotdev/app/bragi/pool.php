<?php
/*****
 * Version	 	1.0.2018-03-06
**/
namespace dotdev\app\bragi;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class pool {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){

		return ['app_bragi', [

			// queries: pool
			'l_pool'					=> "SELECT * FROM `pool`",
			's_pool'					=> "SELECT * FROM `pool` WHERE `poolID` = ? LIMIT 1",
			'i_pool'					=> "INSERT INTO `pool` (`name`, `countryID`, `scheme`, `portal_domain`) VALUES (?,?,?,?)",
			'u_pool'					=> "UPDATE `pool` SET `name` = ?, `countryID` = ?, `scheme` = ?, `portal_domain` = ? WHERE `poolID` = ?",

			]];
		}

	/* Redis */
	public static function redis(){

		return redis::load_resource('app_bragi');
		}

	/* Object: pool */
	public static function get($req = []){

		// alternative
		$alt = h::eX($req, [
			'poolID'	=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: poolID
		if(isset($alt['poolID'])){

			// init redis
			$redis = self::redis();

			// define cache key
			$cache_key = 'pool:by_poolID:'.$alt['poolID'];

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// load entry
				$entry = $redis->get($cache_key);
				}

			// else
			else{

				// seach in DB
				$entry = self::pdo('s_pool', [$alt['poolID']]);

				// on error or not found
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable, cache entry
				if($redis) $redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours

				}

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: no param
		if(empty($req)){

			// load list from DB
			$list = self::pdo('l_pool');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, ['need poolID or no parameter']);
		}

	public static function create($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'			=> '~1,120/s',
			'countryID'		=> '~1,65535/i',
			'scheme'		=> '~1,120/s',
			'portal_domain'	=> '~1,120/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// create entry
		$poolID = self::pdo('i_pool', [$mand['name'], $mand['countryID'], $mand['scheme'], $mand['portal_domain']]);

		// on error
		if($poolID === false) return self::response(560);

		// return success
		return self::response(201, (object)['poolID' => $poolID]);
		}

	public static function update($req = []){

		// mandatory
		$mand = h::eX($req, [
			'poolID'	=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~1,120/s',
			'countryID'		=> '~1,65535/i',
			'scheme'		=> '~1,120/s',
			'portal_domain'	=> '~1,120/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get([
			'poolID'	=> $mand['poolID'],
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
		$upd = self::pdo('u_pool', [$entry->name, $entry->countryID, $entry->scheme, $entry->portal_domain, $entry->poolID]);

		// on error
		if($upd === false) return self::response(560);

		// init redis
		$redis = self::redis();

		// if redis accessable, expire entry
		if($redis) $redis->setTimeout('pool:by_poolID:'.$entry->poolID, 0);

		// return success
		return self::response(204);
		}

	public static function copy($req = []){

		// mandatory
		$mand = h::eX($req, [
			'destPoolId'	=> '~1,65535/i',
			'srcPoolId'		=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'countryID'	=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// Check if destination pool exists
		$res = self::get([
			'poolID'	=> $mand['destPoolId'],
			]);

		// on error
		if($res->status != 200) return $res;

		// Check if source pool exists
		$res = self::get([
			'poolID'	=> $mand['srcPoolId'],
			]);

		// on error
		if($res->status != 200) return $res;


		// Check pool if exists
		$res = self::get([
			'poolID'	=> $mand['destPoolId'],
			]);

		// on error
		if($res->status != 200) return $res;


		// load profiles from source pool
		$res = profile::get_list(['poolID' => $mand['srcPoolId']]);

		// on error
		if($res->status != 200) return $res;

		// loop profiles
		foreach($res->data as $p){

			// check if profile name allready exists in destination pool
			$res = profile::get(['name' => $p->profileName, 'poolID' => $mand['destPoolId']]);

			// on error
			if(!in_array($res->status, [200, 404])) return $res;

			// check ok
			elseif($res->status == 404){

				// copy profile
				$result = profile::copy(['profileID' => $p->profileID, 'poolID' => $mand['destPoolId']]);

				}
			}

		// return success
		return self::response(201);
		}



	}
