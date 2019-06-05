<?php
/*****
 * Version 1.0.2017-10-04
**/
namespace dotdev;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class livestat {
	use \tools\redis_trait,
		\tools\libcom_trait;

	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_livestat');
		}

	/* Object: simple day|hour counter */
	public static function get_dh_counter($req = []){

		// mandatory
		$mand = h::eX($req, [
			'datetime'	=> '~Y-m-d H:i:s/d',
			], $error);

		// optional
		$opt = h::eX($req, [
			'group'		=> '~^[a-zA-Z0-9\-\_]{1,32}$',
			'inhours'	=> '~/b',
			'hour'		=> '~0,23/i',
			'lasthours'	=> '~1,99/i',
			'name'		=> '~^[a-z]{1,8}$',
			'names'		=> '~!empty/a',
			], $error, true);

		// subcheck for names
		if(isset($opt['names'])){

			// for each name
			foreach($opt['names'] as $name){

				// if value seems invalid
				if(!h::is($name, '~^[a-z]{1,8}$')){

					// set error
					$error[] = 'names';
					break;
					}
				}
			}

		// only one of these params are allowed, or none
		if((!empty($opt['inhours']) + isset($opt['hour']) + isset($opt['lasthours'])) > 1) $error[] = 'inhours|hour|lasthours';

		// on error
		if($error) return self::response(400, $error);

		// define day value
		$mand['day'] = substr($mand['datetime'], 0, 10); // 2017-10-04
		$mand['dayhour'] = substr($mand['datetime'], 0, 13).':00:00';  // 2017-10-04 15:00:00


		// init redis
		$redis = self::redis();

		// on error
		if(!$redis) return self::response(500, 'Redis is not accessable');

		// define defaults
		$opt += [
			'group'		=> '_default',
			];

		// define result
		$result = [];

		// if result should be seperated in hours
		if(!empty($opt['inhours'])){

			// for each hour a day
			for($hour = 0; $hour < 24; $hour++){

				// create set for result
				$result[] = (object)[
					'hash'		=> 'dh_counter:'.$opt['group'].':'.$mand['day'].':'.($hour < 10 ? '0' : '').$hour,
					'timepos'	=> h::dtstr($mand['day'].' +'.$hour.' hour'),
					'sum'		=> [],
					];
				}
			}

		// if result should be seperated in specific count of hours back from now
		elseif(!empty($opt['lasthours'])){

			// for each hour a day
			for($hour = $opt['lasthours']-1; $hour >= 0; $hour--){

				// define timepos
				$timepos = h::dtstr($mand['dayhour'].' -'.$hour.' hour');

				// create set for result
				$result[] = (object)[
					'hash'		=> 'dh_counter:'.$opt['group'].':'.h::dtstr($timepos, 'Y-m-d\:H'),
					'timepos'	=> $timepos,
					'sum'		=> [],
					];
				}
			}

		// or if it should only for the day or one hour of the day
		else{

			// create set for result
			$result[] = (object)[
				'hash'		=> 'dh_counter:'.$opt['group'].':'.$mand['day'].(isset($opt['hour']) ? ':'.($opt['hour'] < 10 ? '0' : '').$opt['hour'] : ''),
				'timepos'	=> isset($opt['hour']) ? h::dtstr($mand['day'].' +'.$opt['hour'].' hour') : $mand['day'],
				'sum'		=> []
				];
			}


		// for each set in result
		foreach($result as $set){

			// take and remove hash from result
			$hash = $set->hash;
			unset($set->hash);

			// param order 1: name
			if(isset($opt['name'])){

				// convert to param order 2: names
				$opt['names'] = [$opt['name']];
				}

			// param order 2: names
			if(isset($opt['names'])){

				// load list
				$set->sum = $redis->hMGet($hash, $opt['names']);

				// for each wanted name
				foreach($opt['names'] as $name){

					// add if not exists
					if(!isset($set->sum[$name])) $set->sum[$name] = 0;
					}
				}

			// param order 3: no subselect param
			else{

				// load list
				$set->sum = $redis->hGetAll($hash);
				}

			// for each entry
			foreach($set->sum as $name => $value){

				// convert values for keys not found by redis to 0
				if($value === false) $set->sum[$name] = 0;

				// convert strings to integer
				if(is_string($value)) $set->sum[$name] = (int) $value;
				}

			// convert result to object
			$set->sum = (object) $set->sum;
			}

		// return result one entry (when param produces it) or list of entries
		return self::response(200, count($result) == 1 ? $result[0] : $result);
		}

	public static function incr_dh_counter($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'		=> '~^[a-z]{1,8}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'group'		=> '~^[a-zA-Z0-9\-\_]{1,32}$',
			'by'		=> '~1,9999999/i',
			'datetime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// init redis
		$redis = self::redis();

		// on error
		if(!$redis) return self::response(500, 'Redis is not accessable');

		// define defaults
		$opt += [
			'by'		=> 1,
			'datetime'	=> h::dtstr('now'),
			'group'		=> '_default',
			];

		// define day, hour and expire time
		$day = h::dtstr($opt['datetime'], 'Y-m-d');
		$hour = h::dtstr($opt['datetime'], 'H');
		$expire = h::date($day, '+3 day', 'u');

		// define hashes and ttl
		$hashes = [
			'dh_counter:'.$opt['group'].':'.$day => $expire,
			'dh_counter:'.$opt['group'].':'.$day.':'.$hour => $expire,
			];

		// incrementing with serialization does not work, so we have to disable it temporary (see: https://github.com/phpredis/phpredis/issues/670)
		$opt_serializer = $redis->getOption(\Redis::OPT_SERIALIZER);
		if($opt_serializer != \Redis::SERIALIZER_NONE) $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

		// for each hash
		foreach($hashes as $hash => $expire){

			// if hash does not exists
			if(!$redis->exists($hash)){

				// safely create and set lifetime
				$redis->hSetNx($hash, $mand['name'], 0);
				$redis->expireAt($hash, $expire);
				}

			// finally increment value
			$redis->hIncrBy($hash, $mand['name'], $opt['by']);
			}

		// reenable serialization
		if($opt_serializer != \Redis::SERIALIZER_NONE) $redis->setOption(\Redis::OPT_SERIALIZER, $opt_serializer);

		// return success
		return self::response(204);
		}

	}
