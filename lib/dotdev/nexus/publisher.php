<?php
/*****
 * Version 1.0.2019-02-07
**/
namespace dotdev\nexus;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class publisher {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_nexus', [

			// queries: publisher
			's_publisher'				=> 'SELECT
												p.*,
												o.name as `owner`, o.color as `owner_color`
											FROM `publisher` p
											LEFT JOIN `publisher` o ON o.publisherID = p.ownerID
											WHERE p.publisherID = ?
											LIMIT 1
											',
			's_publisher_by_uncover_key'=> ['s_publisher', ['WHERE p.publisherID = ?' => 'WHERE p.ownerID = ? AND p.uncover_key = ?']],
			'l_publisher'				=> ['s_publisher', ['WHERE p.publisherID = ?' => '', 'LIMIT 1' => '']],

			'i_publisher'				=> 'INSERT INTO `publisher` (`createTime`,`name`,`status`,`color`,`ownerID`,`uncover_key`) VALUES (?,?,?,?,?,?)',
			'u_publisher'				=> 'UPDATE `publisher` SET `name` = ?, `status` = ?, `color` = ?, `ownerID` = ?, `uncover_key` = ? WHERE `publisherID` = ?',

			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_nexus');
		}


	/* lvl1 cache */
	protected static $lvl1_cache = [];


	/* Object: publisher */
	public static function get_publisher($req = []){

		// alternative
		$alt = h::eX($req, [
			'publisherID'		=> '~1,65535/i',
			'ownerID'			=> '~1,65535/i',
			'uncover_key'		=> '~^[a-zA-Z0-9\-\_]{1,64}$',
			'load_top_owner'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: publisherID
		if(isset($alt['publisherID'])){

			// define cache key
			$cache_key = 'publisher:by_publisherID:'.$alt['publisherID'];

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// if load_top_owner is defined and found publisher has an owner
				if(!empty($alt['load_top_owner']) and self::$lvl1_cache[$cache_key]->ownerID){

					// reload with ownerID
					return self::get_publisher([
						'publisherID'	=> self::$lvl1_cache[$cache_key]->ownerID,
						'load_top_owner'=> true,
						]);
					}

				// return entry
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
				$entry = self::pdo('s_publisher', [$alt['publisherID']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache entry in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// if load_top_owner is defined and found publisher has an owner
			if(!empty($alt['load_top_owner']) and $entry->ownerID){

				// load and return owner instead
				return self::get_publisher([
					'publisherID'	=> $entry->ownerID,
					'load_top_owner'=> true,
					]);
				}

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: ownerID + uncover_key
		if(isset($alt['ownerID']) and isset($alt['uncover_key'])){

			// define cache key
			$cache_key = 'publisher:by_uncover_key:'.$alt['ownerID'].':'.$alt['uncover_key'];

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return entry
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
				$entry = self::pdo('s_publisher_by_uncover_key', [$alt['ownerID'], $alt['uncover_key']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					$redis->set('publisher:by_publisherID:'.$entry->publisherID, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache entry in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;
			self::$lvl1_cache['publisher:by_publisherID:'.$entry->publisherID] = clone $entry;

			// return entry
			return self::response(200, $entry);
			}

		// param order 3: no param
		if(empty($req)){

			// load publisher list
			$list = self::pdo('l_publisher');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need publisherID (+ load_top_owner), ownerID + uncover_key or no parameter');
		}

	public static function create_publisher($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'			=> '~1,120/s',
			], $error);

		// optional
		$opt = h::eX($req, [

			// base data
			'createTime'	=> '~Y-m-d H:i:s/d',
			'status'		=> '~^(?:enabled|disabled|archive)$',
			'color'			=> '~^#[a-f0-9]{6}$',
			'ownerID'		=> '~1,65535/i',
			'uncover_key'	=> '~^[a-zA-Z0-9\-\_]{1,64}$',

			// option
			'locked_insert'	=> '~/b',
			], $error, true);

		// additional check
		if(isset($opt['uncover_key']) and !isset($opt['ownerID']) and !in_array('ownerID', $error)) $error[] = 'ownerID';

		// on error
		if($error) return self::response(400, $error);

		// define default
		$opt += [
			'createTime'	=> h::dtstr('now'),
			'status'		=> 'enabled',
			'color'			=> '#cccccc',
			'ownerID'		=> 0,
			'uncover_key'	=> null,
			'locked_insert'	=> false,
			];

		// if entry should inserted normally
		if(!$opt['locked_insert']){

			// if this is an entry with uncover_key
			if($opt['uncover_key']){

				// check if publisher with ownerID+uncover_key does not exist
				$res = self::get_publisher([
					'ownerID'		=> $opt['ownerID'],
					'uncover_key'	=> $opt['uncover_key'],
					]);

				// on error or found
				if($res->status != 404){

					// on error
					if($res->status != 200) return self::response(570, $res);

					// return concurrent process already finished creation
					return self::response(409);
					}
				}

			// insert entry
			$publisherID = self::pdo('i_publisher', [$opt['createTime'], $mand['name'], $opt['status'], $opt['color'], $opt['ownerID'], $opt['uncover_key']]);

			// on error
			if($publisherID === false) return self::response(560);

			// return result
			return self::response(201, (object)['publisherID'=>$publisherID]);
			}

		// else here starts locked insert method

		// init redis
		$redis = self::redis();

		// define lock key
		$lock_key = 'publisher_uncover_insertion:'.$opt['ownerID'].':'.$opt['uncover_key'];

		// try to get priority (reload status every 0.4 seconds, but timeout after 1.6 seconds)
		$lock_status = redis::lock_process($redis, $lock_key, ['timeout_ms'=>2000, 'retry_ms'=>500]);

		// if concurrent process already finished creation
		if($lock_status == 200) return self::response(409);

		// on unexpected error
		if($lock_status != 100) return self::response(500, 'Creating publisher with uncover_key '.h::encode_php($opt['uncover_key']).' for ownerID '.$opt['ownerID'].' ends in lock status: '.$lock_status);

		// if this is an entry with uncover_key
		if($opt['uncover_key']){

			// check if publisher with ownerID+uncover_key does not exist
			$res = self::get_publisher([
				'ownerID'		=> $opt['ownerID'],
				'uncover_key'	=> $opt['uncover_key'],
				]);

			// on error or found
			if($res->status != 404){

				// set lock_status to 200 und expire lock_key after 2 minutes
				$lock_status = redis::unlock_process($redis, $lock_key, ['ttl'=>120]);

				// on error
				if($res->status != 200) return self::response(570, $res);

				// return concurrent process already finished creation
				return self::response(409);
				}
			}

		// create entry
		$entry = (object)[
			'publisherID'	=> null,
			'createTime'	=> $opt['createTime'],
			'name'			=> $mand['name'],
			'status'		=> $opt['status'],
			'color'			=> $opt['color'],
			'ownerID'		=> $opt['ownerID'],
			'uncover_key'	=> $opt['uncover_key'],
			];

		// insert entry
		$entry->publisherID = self::pdo('i_publisher', [$opt['createTime'], $mand['name'], $opt['status'], $opt['color'], $opt['ownerID'], $opt['uncover_key']]);

		// on error
		if($entry->publisherID === false){

			// set lock_status to 200 und expire lock_key after 2 minutes
			$lock_status = redis::unlock_process($redis, $lock_key, ['ttl'=>120]);

			// return error
			return self::response(560);
			}

		// define cache key
		$cache_key = 'publisher:by_publisherID:'.$entry->publisherID;

		// cache it in lvl1 cache
		self::$lvl1_cache[$cache_key] = clone $entry;

		// cache entry
		$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours

		// if this is an entry with uncover_key
		if($opt['uncover_key']){

			// define cache key
			$cache_key = 'publisher:by_uncover_key:'.$entry->ownerID.':'.$entry->uncover_key;

			// cache it in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// cache entry
			$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
			}

		// set lock_status to 200 und expire lock_key after 2 minutes
		$lock_status = redis::unlock_process($redis, $lock_key, ['ttl'=>120]);

		// return success
		return self::response(201, (object)['publisherID'=>$entry->publisherID]);
		}

	public static function update_publisher($req = []){

		// mandatory
		$mand = h::eX($req, [
			'publisherID'	=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~1,120/s',
			'status'		=> '~^(?:enabled|disabled|archive)$',
			'color'			=> '~^#[a-f0-9]{6}$',
			'ownerID'		=> '~1,65535/i',
			'uncover_key'	=> '~^[a-zA-Z0-9\-\_]{1,64}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_publisher([
			'publisherID'	=> $mand['publisherID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// define cache keys (using previous state for uncover_key)
		$cache_key = 'publisher:by_publisherID:'.$entry->publisherID;
		$cache_key_uncover = $entry->uncover_key ? 'publisher:by_uncover_key:'.$entry->publisherID.':'.$entry->uncover_key : null;

		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_publisher', [$entry->name, $entry->status, $entry->color, $entry->ownerID, $entry->uncover_key, $entry->publisherID]);

		// on error
		if($upd === false) return self::response(560);

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);
		if($cache_key_uncover) unset(self::$lvl1_cache[$cache_key_uncover]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire entries
			$redis->setTimeout($cache_key, 0);
			if($cache_key_uncover) $redis->setTimeout($cache_key_uncover, 0);
			}

		// return success
		return self::response(204);
		}

	}
