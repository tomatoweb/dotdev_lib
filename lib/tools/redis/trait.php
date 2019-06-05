<?php
/*****
 * Version 1.0.2015-11-16
**/
namespace tools;

use \tools\helper as h;
use \tools\error as e;
use \tools\redis;

trait redis_trait {

	public static function redis(){
		e::trigger('Function redis() must be defined in class which uses redis_trait');
		return null;
		}

	public static function redis_get($req = []){

		// optional
		$opt = h::eX($req, [
			'search' => '~^.{0,512}$',
			], $error, true);
		if($error) return self::response(400, $error);
		$opt += ['search' => '*'];

		// Redis laden
		$redis = self::redis();
		if(!$redis) return self::response(503);

		$prefix = $redis->getOption(\Redis::OPT_PREFIX);
		$prefix_len = strlen($prefix);

		$list = [];

		foreach($redis->keys($opt['search']) as $key){
			if(substr($key, 0, $prefix_len) == $prefix){
				$key = substr($key, $prefix_len);
				$type = $redis->type($key);
				if($type == \Redis::REDIS_STRING) $list[$key] = $redis->get($key);
				elseif($type == \Redis::REDIS_SET) $list[$key] = 'REDIS_SET';
				elseif($type == \Redis::REDIS_LIST) $list[$key] = 'REDIS_LIST';
				elseif($type == \Redis::REDIS_ZSET) $list[$key] = 'REDIS_ZSET';
				elseif($type == \Redis::REDIS_HASH) $list[$key] = $redis->hGetAll($key);
				elseif($type == \Redis::REDIS_NOT_FOUND) $list[$key] = 'REDIS_NOT_FOUND';
				else $list[$key] = 'REDIS_TYPE_NOT_IMPLEMENTED';
				}
			}

		return self::response(200, $list);
		}

	public static function redis_unset($req = []){

		// optional
		$opt = h::eX($req, [
			'search' => '~^.{0,512}$',
			], $error, true);
		if($error) return self::response(400, $error);
		$opt += ['search' => '*'];

		// Redis laden
		$redis = self::redis();
		if(!$redis) return self::response(503);

		$prefix = $redis->getOption(\Redis::OPT_PREFIX);
		$prefix_len = strlen($prefix);

		foreach($redis->keys($opt['search']) as $key){
			if(substr($key, 0, $prefix_len) == $prefix){
				$redis->setTimeout(substr($key, $prefix_len), 0);
				}
			}

		return self::response(204);
		}

	}
