<?php
/*****
 * Version 1.0.2017-11-15
**/
namespace dotdev\app\mercatus;

use \tools\helper as h;
use \tools\error as e;
use \xadmin\redis;

class purorder{
	use \tools\pdo_trait,
		\tools\libcom_trait;

	protected static function pdo_config(){
		return ['app_mercatus', [

			// querries pur_order
			'l_order'							=> "SELECT * FROM `pur_order`",
			's_order'							=> "SELECT * FROM `pur_order` WHERE `customerID` = ? AND NOT `status` = 2",
			's_order_comp'						=> "SELECT 	o.customerID, o.order_date, a.articleID, a.quantity, a.article_price
													FROM `pur_order` o
													INNER JOIN `pur_order_article` a ON o.orderID = a.orderID
													WHERE o.orderID = ? AND NOT o.status = 2",
			'i_order'							=> "INSERT INTO `pur_order` (`customerID`, `order_date`, `status`) VALUES (?,?,?)",
			'u_order'							=> "UPDATE `pur_order` SET `status` = ? WHERE `orderID` = ?",

			// querries pur_order_article
			'l_order_art'						=> "SELECT * FROM `pur_order_article`",
			's_order_art'						=> "SELECT * FROM `pur_order_article` WHERE `orderID` = ? AND `articleID` = ? AND NOT `status` = 2 LIMIT 1 ",
			'i_order_art'						=> "INSERT INTO `pur_order_article` (`orderID`, `articleID`, `quantity`, `article_price`, `status`) VALUES (?,?,?,?,?)",
			'u_order_art'						=> "UPDATE `pur_order_article` SET `quantity` = ?, `status` = ? WHERE `orderID` = ? AND `articleID` = ?",
			]];
		}

	public static function get_order($req = []){

		// mandatory values check
		$mand = h::eX($req, [
			'orderID'		=>	'~1,255/i',
			'customerID'	=>	'~1,255/i',
			'articleID'		=>	'~1,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400,$error);

		// param order 1: articleID AND orderID
		if(isset($mand['articleID']) and isset($mand['orderID'])){
			// get entry
			$entry = self::pdo('s_order_art', [$mand['orderID'], $mand['articleID']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: orderID
		if(isset($mand['orderID'])){
			// get entry
			$entry = self::pdo('s_order_comp', $mand['orderID']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 3: customerID
		if(isset($mand['customerID'])){
			// get entry
			$entry = self::pdo('s_order', $mand['customerID']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 4: no param
		if(empty($req)){

			// get entrys
			$entry = self::pdo('l_order');

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// other request param invalid
		return self::response(400, 'need orderID/customerID or no parameter');
		}

	public static function create_order($req = []){

		// mandatory values check
		$mand = h::eX($req, [
			'customerID'	=>	'~1,255/i',
			'order_date'	=>	'~/d',
			'orderID'		=>	'~1,255/i',
			'articleID'		=>	'~1,255/i',
			'quantity'		=>	'~1,255/i',
			'article_price'	=>	'~1,255/i',
			'status'		=> 	'~0,4/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: orderID
		if(isset($mand['orderID'])){

			// insert new entry
			$ins = self::pdo('i_order_art', [$mand['orderID'], $mand['articleID'], $mand['quantity'], $mand['article_price'], 0]);

			// on error
			if($ins === false) return self::response(560);
			}

		// param order 2: customerID
		if(isset($mand['customerID'])){

			// insert new entry
			$ins = self::pdo('i_order', [$mand['customerID'], $mand['order_date'], $mand['status']]);

			// on error
			if($ins === false) return self::response(400, $error);
			}

		// on success
		return self::response(201);
		}

	public static function update_order($req = []){

		// mandatory values check
		$mand = h::eX($req,[
			'articleID'		=>	'~1,255/i',
			'customerID'	=>	'~1,255/i',
			'orderID'		=>	'~1,255/i',
			], $error, true);

		// optional values check
		$opt = h::eX($req,[
			'order_date'	=>	'~/d',
			'status'		=>	'~0,4/i',
			'quantity'		=>	'~1,255/i',
			'article_price'	=>	'~1,255/i',
			], $error, true);

		// setting default values
		$opt += [
			'order_date'	=>	'00.00.0000',
			'status'		=>	0,
			'quantity'		=>	0,
			'article_price'	=>	0,
			];

		// on error
		if($error) return self::response(400, $error);

		// param order 1: articleID AND orderID
		if(isset($mand['articleID']) and isset($mand['orderID'])){
			// get entry
			$entry = self::pdo('s_order_art', [$mand['orderID'], $mand['articleID']]);

			// check if entry is ok
			if($entry->status == 200){

				// take old data if no new data is applied
				if($opt['order_date'] == '00.00.0000'){
					$opt['order_date'] = $entry->data->order_date;
					}

				if($opt['status'] == 0){
					$opt['status'] = $entry->data->status;
					}

				if($opt['quantity'] == 0){
					$opt['quantity'] = $entry->data->quantity;
					}

				if($opt['article_price'] == 0){
					$opt['article_price'] = $entry->data->article_price;
					}

				// update entry
				$upt = self::pdo('u_order_art', [$mand['orderID'], $mand['articleID'], $opt['quantity'], $opt['status']]);

				// on error
				if($upt === false) return self::response(560);
			}
		}

		// on success
		return self::response(204);
	}
}

