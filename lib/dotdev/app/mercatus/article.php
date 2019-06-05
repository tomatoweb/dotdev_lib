<?php
/*****
 * Version 1.0.2017-11-15
**/
namespace dotdev\app\mercatus;

use \tools\helper as h;
use \tools\error as e;
use \xadmin\redis;

class article{
	use \tools\pdo_trait,
		\tools\libcom_trait;

	protected static function pdo_config(){
		return ['app_mercatus', [
			'l_art'								=> "SELECT * FROM `article`",
			's_art'								=> "SELECT * FROM `article` WHERE `articleID` = ? AND NOT `status` = 2 LIMIT 1",
			's_art_nam'							=> "SELECT * FROM `article` WHERE `art_name` = ? AND NOT `status` = 2 LIMIT 1",
			'i_art'								=> "INSERT INTO `article` (`art_name`, `gingle`, `length`, `reach`, `weight`, `descripton`, `price`, `status`)
													VALUES (?,?,?,?,?,?,?,?)",
			'u_art_attr'						=> "UPDATE `article`
													SET `art_name` = ?, `gingle` = ?, `length` = ?, `reach` = ?, `weight` = ?, `descripton` = ?, `price` = ? WHERE `articleID` = ?",
			'u_art_stat'						=> "UPDATE `article` SET `status` = ? WHERE `articleID` = ?",

			]];
		}

	public static function get_article($req = []){
		// mandatory values check
		$mand = h::eX($req, [
			'articleID'	=>	'~1,255/i',
			'art_name'	=>	'~1,255/s',
			], $error, true);

		// on error
		if($error) return self::response(400,$error);

		// param order 1: articleID
		if(isset($mand['articleID'])){
			// get entry
			$entry = self::pdo('s_art', $mand['articleID']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: article_name
		if(isset($mand['art_name'])){
			// get entry
			$entry = self::pdo('s_art_nam', $mand['art_name']);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 3: no param
		if(empty($req)){

			// get entrys
			$entry = self::pdo('l_art');

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// other request param invalid
		return self::response(400, 'need article_name/articleID or no parameter');

		}

	public static function create_article($req = []){

		// mandatory values check
		$mand = h::eX($req, [
			'art_name'					=> '~1,255/s',
			'gingle'					=> '~1,255/s',
			'length'					=> '~1,255/s',
			'reach'						=> '~1,255/s',
			'weight'					=> '~1,255/s',
			'descripton'				=> '~/s',
			'price'						=> '~/i',
			'status'					=> '~0,4/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// insert new entry
		$ins = self::pdo('i_art', [$mand['art_name'], $mand['gingle'], $mand['length'], $mand['reach'], $mand['weight'], $mand['descripton'], $mand['price'], $mand['status']]);

		// on error
		if($ins === false) return self::response(560);

		// on success
		return self::response(201);
		}

	public static function update_article($req = []){

		// mandatory values check
		$mand = h::eX($req, [
			'art_name'			=>	'~1,255/s',
			'price'				=>	'~/i',
			'status'			=>	'~1,4/i',
			], $error, true);

		// optional values check
		$opt = h::eX($req, [
			'gingle'				=>	'~1,255/s',
			'length'				=> 	'~0,4/i',
			'reach'					=>	'~1,255/s',
			'weight'				=>	'~1,255/s',
			'descripton'			=>	'~/s',
		    ]);

		// on error
		if($error) return self::response(400, $error);

		// setting default values
		$opt +=[
			'gingle'		=> '',
			'length'		=> '',
			'reach'			=> '',
			'weight'		=> '',
			'descripton'	=> '',
			];

		// param order 1: status
		if(isset($mand['status'])){
			// get right entry
			$res = self::get_customer('art_name' => $mand['art_name']);

			// check if entry is ok
			if($res->status == 200){
				// update entry
				$upt = self::pdo('u_art_stat', [$mand['status'], $res->data->articleID]);

				// on error
				if($upt === false) return self::response(560);
				}
			}

		// param order 2: price
		if(isset($mand['price'])){
			// get right entry
			$res = self::get_article('art_name' => $mand['art_name']);

			// check if entry is ok
			if($res->status == 200){

				if($opt['gingle'] == ''){
					$opt['gingle'] = $res->data->gingle;
					}

				if($opt['length'] == ''){
					$opt['length'] = $res->data->length;
					}

				if($opt['reach'] == ''){
					$opt['reach'] = $res->data->reach;
					}

				if($opt['weight'] == ''){
					$opt['weight'] = $res->data->weight;
					}

				if($opt['descripton'] == ''){
					$opt['descripton'] = $res->data->descri;
					}

				// update entry
				$upt = self::pdo('u_art_attr', [$mand['art_name'], $opt['gingle'], $opt['length'], $opt['reach'], $opt['weight'], $opt['descripton'], $mand['price'], $res->data->articleID]);

				// on error
				if($upt === false) return self::response(560);
				}
			}

		// on success
		return self::response(204);
		}
	}