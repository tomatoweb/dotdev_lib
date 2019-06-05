<?php
/*****
 * Version 1.0.2017-03-28
**/
namespace dotdev;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class persist {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_tool', [
			's_persist'			=> "SELECT `ID` as `persistID`, `createTime` FROM `persist` WHERE `ID` = ? LIMIT 1",
			'i_persist'			=> "INSERT INTO `persist` (`createTime`) VALUES (?)"
			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_service');
		}


	/* Cache */
	protected static $_lvl1_cache = [];


	/* Object: persist */
	public static function create($req = []){

		// optional
		$opt = h::eX($req, [
			'createTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// set default
		$opt += [
			'createTime'	=> date('Y-m-d H:i:s'),
			];

		// insert
		$persistID = self::pdo('i_persist', $opt['createTime']);
		if(!$persistID) return self::response(560);

		// lvl1 cache
		self::$_lvl1_cache[$persistID] = $opt['createTime'];

		// redis cache
		$redis = self::redis();
		if($redis){
			$redis->set('persistID:'.$persistID, $opt['createTime'], ['ex'=>1200, 'nx']);
			}

		// return result
		return self::response(200, (object)[
			'persistID'	=> (int) $persistID,
			'createTime'=> $opt['createTime'],
			]);
		}

	public static function get($req){

		// mandatory
		$mand = h::eX($req, [
			'persistID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// check lvl1 cache is not set
		if(!isset(self::$_lvl1_cache[$mand['persistID']])){

			// init redis
			$redis = self::redis();

			// check redis cache
			if($redis and $redis->exists('persistID:'.$mand['persistID'])){

				// lvl1 cache
				self::$_lvl1_cache[$mand['persistID']] = $redis->get('persistID:'.$mand['persistID']);
				}

			// or check DB
			else{

				// load
				$entry = self::pdo('s_persist', $mand['persistID']);
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// save in redis
				if($redis){
					$redis->set('persistID:'.$mand['persistID'], $entry->createTime, ['ex'=>1200, 'nx']);
					}

				// lvl1 cache
				self::$_lvl1_cache[$mand['persistID']] = $entry->createTime;
				}
			}

		// return lvl1 result
		return self::response(200, (object)[
			'persistID'	=> $mand['persistID'],
			'createTime'=> self::$_lvl1_cache[$mand['persistID']],
			]);
		}

	}
