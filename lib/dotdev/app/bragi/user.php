<?php
/*****
 * Version	 	1.0.2019-06-03
**/
namespace dotdev\app\bragi;

use \tools\error as e;
use \tools\helper as h;

// Authentication Class
class user {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	protected static function pdo_config(){

		return ['app_bragi', [

			's_user'=> "SELECT * FROM `user` WHERE `user` = ? AND `auth` = ? LIMIT 1",

			]];
		}


	public static function get($req){

		// mandatory
		$mand = h::eX($req, [
			'user'	=> '~^.{1,32}$',
			'auth'	=> '~^.*$',
			], $error);
		if($error) return self::response(400, $error);

		$res = self::pdo('s_user', [$mand['user'], $mand['auth']]);

		if(!$res) return self::response($res === false ? 560 : 404);

		return self::response(200, (object)['admin'=>$res->fulladmin, 'demo'=>$res->demo]);
		}


	}
