<?php
/*****
 * Version 1.0.2019-03-15
**/
namespace dotdev\app\datingportal;

use \tools\error as e;
use \tools\helper as h;

class debug {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){

		return [self::_pdo_connection_switch(), [

			]];
		}

	protected static function _pdo_connection_switch($to = null){

		// define cached PDO connection name
		static $connection = '';

		// if defined, switch connection
		if(is_string($to)) $connection = $to;

		// return connection
		return $connection;
		}


	/* Redis Config */
	protected static function redis_config(){

		return self::_redis_connection_switch();
		}

	protected static function _redis_connection_switch($to = null){

		// define cached PDO connection name
		static $connection = '';

		// if defined, switch connection
		if(is_string($to)) $connection = $to;

		// return connection
		return $connection;
		}




	/* get unserialized redis data */
	public static function get_redis_data($req = []){

		// mandatory
		$mand = h::eX($req, [
			'for'				=> '~^(?:leaffair|yoomee)$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'search'			=> '~1,120/s',
			'disable_serializer'=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// first set redis database
		self::_redis_connection_switch('app_'.$mand['for']);

		// if serializer should be disabled
		if(!empty($opt['disable_serializer'])){

			// init redis
			$redis = self::redis();

			// set redis option for no serialization
			$redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
			}

		// return found strings
		return self::redis_get([
			'search'	=> $opt['search'] ?? null,
			]);
		}

	public static function get_push_notifications($req = []){

		// mandatory
		$mand = h::eX($req, [
			'for'	=> '~^(?:leaffair|yoomee)$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// first set redis database
		self::_redis_connection_switch('app_'.$mand['for']);

		// init redis
		$redis = self::redis();

		// load list
		$list = $redis->lRange('push_notifications', 0, -1);

		// if nothing found, return empty list
		if(!$list) return self::response(200, []);

		// foreach entry
		foreach($list as $key => $entry){

			// check for json and decode if
			if(is_string($entry) and $entry[0] == '{') $list[$key] = json_decode($entry);
			}

		// return list
		return self::response(200, $list);
		}

	public static function get_socket_stream($req = []){

		// mandatory
		$mand = h::eX($req, [
			'for'	=> '~^(?:leaffair|yoomee)$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// first set redis database
		self::_redis_connection_switch('app_'.$mand['for']);

		// result
		$list = [];

		// search event entries
		$res = self::redis_get([
			'search'	=> $mand['for'].'_event*',
			]);

		// append to list
		if($res->status == 200) $list += $res->data;

		// init redis
		$redis = self::redis();

		// load list
		$list['notifications'] = $redis->lRange('notifications', 0, -1);

		// foreach entry
		foreach($list['notifications'] as $key => $entry){

			// check for json and decode if
			if(is_string($entry) and $entry[0] == '{') $list['notifications'][$key] = json_decode($entry);
			}

		// return result
		return self::response(200, $list);
		}

	}