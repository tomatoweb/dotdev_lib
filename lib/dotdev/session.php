<?php
/*****
 * Version 1.0.2016-05-18
**/
namespace dotdev;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class session {
	use \tools\libcom_trait,
		\tools\redis_trait;

	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_session');
		}

	/* Object: session */
	public static function create($req = []){

		// optional
		$opt = h::eX($req, [
			'bind_ip'		=> '~^.{1,255}$',
			'bind_useragent'=> '~^.{1,2048}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// check redis connection
		$redis = self::redis();
		if(!$redis){
			return self::response(500, 'Connection to redis DB mt_session failed');
			}

		// generate ID
		$loop_stop = 0;
		do{
			$loop_stop++;
			$tryID = h::rand_str(24);
			}
		while($redis->exists($tryID) and $loop_stop < 100);

		// on error
		if(!$loop_stop >= 100){
			return self::response(500, 'Cannot generate SessionID (loop stop)');
			}

		// set createtime and define session timeout
		$redis->hSet($tryID, 'session_createtime', microtime(true));
		$redis->setTimeout($tryID, 1200); // 20 minutes

		// check additional param
		foreach($opt as $key => $val){
			$redis->hSet($tryID, 'session_'.$key, $val);
			}

		// return sessionID and handle
		return self::response(200, (object)['sessionID'=>$tryID, 'handle'=>$redis]);
		}

	public static function open($req){

		// mandatory
		$mand = h::eX($req, [
			'sessionID'		=> '~^.{24}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'reset'			=> '~/b',
			'no_check'		=> '~/b',
			], $error, true);

		// optional 2
		$bind_opt = h::eX($req, [
			'bind_ip'		=> '~^.{1,255}$',
			'bind_useragent'=> '~^.{1,2048}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// check redis connection
		$redis = self::redis();
		if(!$redis){
			return self::response(500, 'Connection to redis DB mt_session failed');
			}

		// check existance of session
		if(!$redis->exists($mand['sessionID'])){
			return self::response(404);
			}

		// opt: check bind values
		if(empty($opt['no_check'])){

			foreach($bind_opt as $key => $val){
				$given = $redis->hGet($mand['sessionID'], 'session_'.$key);
				if($given and $given != $val){
					e::logtrigger('DEBUG: session_'.$key.' failed: '.h::encode_php($given).' != '.h::encode_php($val).'');
					return self::response(403);
					}
				}
			}

		// opt: reset session
		if(!empty($opt['reset'])){

			// delete session data
			$redis->delete($mand['sessionID']);

			// recreate createtime and define session timoeut
			$redis->hSet($mand['sessionID'], 'session_createtime', microtime(true));

			// reattach session param
			foreach($bind_opt as $key => $val){
				$redis->hSet($mand['sessionID'], 'session_'.$key, $val);
				}
			}

		// refresh timeout
		$redis->setTimeout($mand['sessionID'], 1200); // 20 minutes

		// return sessionID and handle
		return self::response(200, (object)['sessionID'=>$mand['sessionID'], 'handle'=>$redis]);
		}

	}
