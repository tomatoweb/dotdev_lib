<?php
/*****
 * Version 1.0.2017-11-15
**/
namespace dotdev\app\mercatus;

use \tools\helper as h;
use \tools\error as e;
use \xadmin\redis;

class customer{
	use \tools\pdo_trait,
		\tools\libcom_trait;

	protected static $hash = 'mercatus';

	protected static function pdo_config(){
		return ['app_mercatus', [
			'l_cust'							=> "SELECT * FROM `customer`",
			's_cust'							=> "SELECT * FROM `customer` WHERE `customerID` = ? AND NOT `status` = 2 LIMIT 1",
			's_cust_nam'						=> "SELECT * FROM `customer` WHERE `customer_name` = ? AND NOT `status` = 2 LIMIT 1",
			'i_cust'							=> "INSERT INTO `customer` (`customer_name`, `password`, `email`, `city`, `postcode`, `street`, `country`, `status`)
													VALUES (?,?,?,?,?,?,?,?)",
			'u_cust_pw'							=> "UPDATE `customer` SET `password` = ? WHERE `customerID` = ?",
			'u_cust_stat'						=> "UPDATE `customer` SET `status` = ? WHERE `customerID` = ?",

			]];
		}

	public static function get_customer($req = []){

		// mandatory values check
		$mand = h::eX($req, [
			'customerID'	=>	'~1,255/i',
			'customer_name'	=>	'~1,255/s',
			], $error, true);

		// on error
		if($error) return self::response(400,$error);

		// param order 1: customerID
		if(isset($mand['customerID'])){

			// get entry
			$entry = self::pdo('s_cust', $mand['customerID']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: customer_name
		if(isset($mand['customer_name'])){

			// get entry
			$entry = self::pdo('s_cust_nam', $mand['customer_name']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 3: no param
		if(empty($req)){

			// get entrys
			$entry = self::pdo('l_cust');

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// other request param invalid
		return self::response(400, 'need customer_name/customerID or no parameter');
		}


	public static function create_customer($req = []){

		// mandatory values check
		$mand = h::eX($req, [
			'customer_name'			=> '~1,255/s',
			'password'				=> '~1,255/s',
			'email'					=> '~1,255/s',
			], $error);

		// optional values check
		$opt = h::eX($req, [
			'city'			=> '~1,255/s',
			'postcode'		=> '~1,255/s',
			'street'		=> '~1,255/s',
			'country'		=> '~1,255/s',
			'status'		=> '~0,3/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default values
		$opt += [
			'city'			=> '',
			'postcode'		=> '',
			'street'		=> '',
			'country'		=> '',
			'status' 		=> 0
			];

		// hash pw
		$mand['password'] = sha1(''.self::$hash.''.$mand['password']);

		// insert new entry
		$ins = self::pdo('i_cust', [$mand['customer_name'], $mand['password'], $mand['email'], $opt['city'], $opt['postcode'], $opt['street'], $opt['country'], $opt['status']]);

		// on error
		if($ins === false) return self::response(560);

		// on success
		return self::response(201);
		}

	public static function update_customer($req = []){

		// mandatory values check
		$mand = h::eX($req, [
			'customer_name'		=>	'~1,255/s',
			'password'			=>	'~1,255/s',
			'status'			=> 	'~0,4/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: password
		if(isset($mand['password'])){
			// hash password
			$mand['password'] = sha1(''.self::$hash.''.$mand['password']);

			// get right entry
			$res = self::get_customer('customer_name' => $mand['customer_name']);

			// check if entry is ok
			if($res->status == 200){
				// update entry
				$upt = self::pdo('u_cust_pw', [$mand['password'], $res->data->customerID]);

				//on error
				if($upt === false) return self::response(560);
				}
			}

		// param order 2: status
		if(isset($mand['status'])){
			// get right entry
			$res = self::get_customer('customer_name' => $mand['customer_name']);

			// check if entry is ok
			if($res->status == 200){
				// update entry
				$upt = self::pdo('u_cust_stat', [$mand['status'], $res->data->customerID]);

				// on error
				if($upt === false) return self::response(560);
				}
			}

		// on success
		return self::response(204);
		}
	}