<?php
/*****
 * Version 1.0.2017-11-15
**/
namespace dotdev\app\mercatus;

use \tools\helper as h;
use \tools\error as e;
use \xadmin\redis;

class cart{
	use \tools\pdo_trait,
		\tools\libcom_trait;

	protected static function pdo_config(){
		return ['app_mercatus', [

			// querries cart
			'l_cart'							=> "SELECT * FROM `cart`",
			's_cart'							=> "SELECT * FROM `cart` WHERE `cartID` = ? AND NOT `status` = 2 LIMIT 1",
			's_cart_id'							=> "SELECT * FROM `cart` WHERE `customerID` = ? AND NOT `status` = 2 LIMIT 1",
			'i_cart'							=> "INSERT INTO `cart` (`customerID`, `status`) VALUES (?,?)",
			'u_cart_stat'						=> "UPDATE `cart` SET `status` = ? WHERE `cartID` = ?",

			// querries cart-article
			'l_cart_art'						=> "SELECT * FROM `cart_article`",
			's_cart_art'						=> "SELECT * FROM `cart_article` WHERE `cartID` = ? AND NOT `status` = 2",
			's_cart_art_entry'					=> "SELECT * FROM `cart_article` WHERE `cartID` = ? AND `articleID` = ? AND NOT `status` = 2 LIMIT 1 ",
			'i_cart_art'						=> "INSERT INTO `cart_article` (`cartID`, `articleID`, `quantity`, `status`) VALUES (?,?,?,?)",
			'u_cart_art'						=> "UPDATE `cart_article` SET `quantity` = ?, `status` = ? WHERE `cartID` = ? AND `articleID` = ?",

			's_cart_full'						=> "SELECT `cart.cartID`, `cart.customerID`, `cart_article.articleID`, `cart_article.quantity`
													FROM `cart`
													LEFT JOIN `cart.cartID` ON `cart_article.cartID` = `cart.cartID`
													WHERE cartID = ?",

			]];
		}


	protected static function get_cart($req = []){

		// check input
		$mand = h::eX($req, [
			'cartID'	=>	'~1,255/i',
			'articleID'	=>	'~1,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400,$error);

		// param order 1: articleID && cartID
		if(isset($mand['articleID']) and isset($mand['cartID'])){

			// get entry
			$entry = self::pdo('s_cart_art_entry', [$mand['cartID'], $mand['articleID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: articleID
		if(isset($mand['articleID'])){

			// get entry
			$entry = self::pdo('s_cart_art', $mand['articleID']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 3: cartID
		if(isset($mand['cartID'])){

			// get entry
			$entry = self::pdo('s_cart', $mand['cartID']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 4: no param
		if(empty($req)){

			// get entrys
			$entry = self::pdo('l_cart');

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// other request param invalid
		return self::response(400, 'need articleID/cartID or no parameter');
		}

	public static function create_cart($req = []){

		// mandatory values check
		$mand = h::eX($req, [
			'articleID'		=>	'~1,255/i',
			'quantity'		=>	'~1,255/i',
			'status'		=>	'~0,255/i',
			], $error);

		// optional values check
		$opt = h::eX($req,[
			'cartID'		=>	'~1,255/i',
			'customerID'	=>	'~1,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// set default values
		$opt += [
			'cartID'		=>	0,
			'customerID'	=>	0
			];

		// param order 1: customerID
		if(isset($opt['customerID']) and $opt['customerID'] != 0){

			// insert new entry
			$ins = self::pdo('i_cart', [$opt['customerID'], 0]);

			// on error
			if($ins === false) return self::response(560);
			}

		// param order 2: articleID
		if(isset($mand['articleID'])){

			// insert new entry
			$ins = self::pdo('i_cart_art', [ $opt['cartID'], $mand['articleID'], $mand['quantity'], $mand['status']]);

			// on error
			if($ins === false) return self::response(400, $error);
			}

		return self::response(201);
		}

	/*protected static function update_cart($req = []){
		// mandatory values check
		$mand = h::eX($req, [
			'cartID'	=>	'~1,255/i',
			'articleID'	=>	'~1,255/i',
			'quantity'	=>	'~1,255/i',
			'status'	=>	'~1,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: cartID AND ArticleID
		if(isset($mand['cartID']) && isset($mand['articleID'])){
			// get entry
			$back = self::get_cart('s_cart_entry', [$mand['cartID'], $mand['articleID']]);

			// check entry
			if($back->status == 200){

				if($mand)

				// update entry
				$upt = self::pdo('u_cart_art', [$mand['cartID'], $mand['articleID'], $opt['quantity'], $opt['status']]);
				}
			}
		}*/
	}