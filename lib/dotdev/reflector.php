<?php
/*****
 * Version 1.4.2018-12-11
**/
namespace dotdev;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \tools\http;

class reflector {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_tool', [
			's_reflector'		=> "SELECT `ID` as `reflectorID`, `createTime`
									FROM `reflector`
									WHERE `ID` = ?
									LIMIT 1
									",
			's_stack_list'		=> "SELECT `ID` as `stackID`, `createMtime`, `passMtime`, `url`
									FROM `reflector_stack`
									WHERE `reflectorID` = ?
									ORDER BY `createMtime` ASC
									",

			'i_reflector'		=> "INSERT INTO `reflector` (`createTime`) VALUES (?)",
			'i_stack'			=> "INSERT INTO `reflector_stack` (`reflectorID`,`createMtime`,`url`) VALUES (?,?,?)",

			'u_stack_pass'		=> "UPDATE `reflector_stack` SET `passMtime` = ? WHERE `ID` = ?",
			'u_reflector_pass'	=> "UPDATE `reflector_stack` SET `passMtime` = ? WHERE `reflectorID` = ?",
			'u_stack_modify'	=> "UPDATE `reflector_stack` SET `url` = ? WHERE `ID` = ?",

			'd_reflector'		=> "DELETE FROM `reflector` WHERE `ID` = ?",
			'd_stack'			=> "DELETE FROM `reflector_stack` WHERE `ID` = ?"
			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_service');
		}


	/* base functionality */
	public static function create(){

		// create new entry
		$new = (object)[
			'reflectorID'	=> 0,
			'createTime'	=> h::dtstr('now'),
			'stack'			=> [],
			];

		// insert reflector
		$new->reflectorID = self::pdo('i_reflector', $new->createTime);
		if(!$new->reflectorID) return self::response(560);

		// init redis
		$redis = self::redis();
		$cache_key = 'reflector:'.$new->reflectorID;

		// if redis accessable, cache entry
		if($redis){

			$redis->set($cache_key, $new, ['ex'=>3600, 'nx']); // 1 hour
			}

		// return inserted reflectorID
		return self::response(201, (object)['reflectorID'=>$new->reflectorID]);
		}

	public static function get($req){

		// mandatory
		$mand = h::eX($req, [
			'reflectorID'	=> '~1,4294967295/i',
			], $error);
		if($error) return self::response(400, $error);

		// var
		$reflector = null;

		// init redis
		$redis = self::redis();
		$cache_key = 'reflector:'.$mand['reflectorID'];

		// if redis accessable, search for entry
		if($redis and $redis->exists($cache_key)){

			$reflector = $redis->get($cache_key);
			}

		// if not or not found
		if(!$reflector){

			// load entry from db
			$reflector = self::pdo('s_reflector', $mand['reflectorID']);
			if(!$reflector) return self::response($reflector === false ? 560 : 404);

			// and load stack list from db
			$stack = self::pdo('s_stack_list', $mand['reflectorID']);
			if($stack === false) return self::response(560);
			$reflector->stack = $stack;

			// if redis accessable, cache entry
			if($redis){

				$redis->set($cache_key, $reflector, ['ex'=>3600, 'nx']); // 1 hour
				}
			}

		// expand reflector
		$reflector->passed = null;
		foreach($reflector->stack as $s){
			$s->passed = ($s->passMtime != 0);
			$reflector->passed = ($reflector->passed !== false and $s->passed);
			}

		return self::response(200, $reflector);
		}

	public static function stack($req){

		// load reflector
		$res = self::get($req);
		if($res->status != 200) return $res;
		$reflector = $res->data;

		// mandatory
		$mand = h::eX($req, [
			'url'	=> '~^http(?:s|)\:\/\/[a-z0-9\-\.]+(?:\/.*|\?.*|)$',
			], $error);
		if($error) return self::response(400, $error);

		// create new entry
		$new = (object)[
			'stackID'		=> 0,
			'createMtime'	=> round(microtime(true), 4),
			'passMtime'		=> 0,
			'url'			=> $mand['url'],
			];

		// add new entry to db
		$new->stackID = self::pdo('i_stack', [$reflector->reflectorID, $new->createMtime, $new->url]);
		if(!$new->stackID) return self::response(560);

		// init redis
		$redis = self::redis();
		$cache_key = 'reflector:'.$reflector->reflectorID;

		// if redis is accessable
		if($redis and $redis->exists($cache_key)){

			// add entry to reflector stack array
			$reflector->stack[] = $new;

			// and save it
			$redis->set($cache_key, $reflector);
			}

		// return inserted stackID
		return self::response(201, (object)['stackID'=>$new->stackID, 'ID'=>$new->stackID]);
		}


	/* helper to get and update next stack */
	public static function run_stack($req){

		// load reflector
		$res = self::get($req);
		if($res->status != 200) return $res;
		$reflector = $res->data;

		// optional
		$opt = h::eX($req, [
			'stackID'=> '~1,4294967295/i',
			], $error, true);
		if($error) return self::response(400, $error);

		// if there aren't stack in there
		if(!$reflector->stack) return self::response(406);

		// redirecting
		$stack_key = null;

		foreach($reflector->stack as $key => $entry){


			// if stackID given, take this
			if(isset($opt['stackID']) and $entry->stackID == $opt['stackID']){
				$stack_key = $key;
				break;
				}

			// if stackID not given, take each
			if(!isset($opt['stackID'])){
				$stack_key = $key;

				// and break if it wasn't passed before
				if($entry->passMtime == 0) break;
				}

			}

		// now use_stack is the wanted or at least the last stack
		// update it, if it has no passMtime
		if(!$reflector->stack[$stack_key]->passMtime){

			// update time
			$reflector->stack[$stack_key]->passMtime = round(microtime(true), 4);

			// save it to db
			$upd = self::pdo('u_stack_pass', [$reflector->stack[$stack_key]->passMtime, $reflector->stack[$stack_key]->stackID]);
			if($upd === false) return self::response(560);

			// init redis
			$redis = self::redis();
			$cache_key = 'reflector:'.$reflector->reflectorID;

			// if redis is accessable
			if($redis and $redis->exists($cache_key)){

				// save it
				$redis->set($cache_key, $reflector);
				}
			}


		// return the wanted stack
		return self::response(200, $reflector->stack[$stack_key]);
		}


	}
