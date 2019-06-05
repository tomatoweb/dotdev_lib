<?php
/*****
 * Version 1.0.2019-01-16
**/
namespace dotdev\app\datingportal;

use \tools\error as e;
use \tools\helper as h;

class test {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO/Redis Config */
	protected static function pdo_config(){

		return [self::_pdo_connection_switch(), [

			// queries: settings
			's_settings'				=> 'SELECT * FROM `settings` WHERE `id` = ? LIMIT 1',
			'l_settings_by_profile'		=> 'SELECT * FROM `settings` WHERE `profile_id` = ?',

			]];
		}

	protected static function redis_config(){
		return 'mt_nexus';
		}

	protected static function _pdo_connection_switch($to = null){

		// define cached PDO connection name
		static $connection = '';

		// if defined, switch connection
		if(is_string($to)) $connection = $to;

		// return connection
		return $connection;
		}

		/* Object: setting */
	public static function get_setting($req = []){

		// mandatory
		$mand = h::eX($req, [
			'db'			=> '~^(?:dp_leaffair_dev|dp_leaffair_beta|dp_leaffair_live|dp_yoomee_dev|dp_yoomee_beta|dp_yoomee_live)$',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'id'			=> '~1,2147483647/i',
			'profile_id'	=> '~1,2147483647/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// first set database
		self::_pdo_connection_switch($mand['db']);

		// param order 1: id
		if(isset($alt['id'])){

			// load entry from DB
			$entry = self::pdo('s_settings', $alt['id']);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: profile_id
		if(isset($alt['profile_id'])){

			// load list from DB
			$list = self::pdo('l_settings_by_profile', $alt['profile_id']);

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need id or profile_id parameter');
		}

	}