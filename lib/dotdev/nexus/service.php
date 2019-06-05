<?php
/*****
 * Version 1.3.2018-10-29
**/
namespace dotdev\nexus;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class service {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_nexus', [

			// queries: service
			's_service'				=> 'SELECT
											s.*,
											f.name as `firm`,
											a.name as `aggregator`
										FROM `service` s
										LEFT JOIN `firm` f ON f.firmID = s.firmID
										LEFT JOIN `aggregator` a ON a.aggregatorID = s.aggregatorID
										WHERE `serviceID` = ?
										LIMIT 1
										',
			'l_service'				=> ['s_service', ['WHERE `serviceID` = ?' => '', 'LIMIT 1' => '']],

			'i_service'				=> 'INSERT INTO `service` (`name`,`firmID`,`aggregatorID`,`archiv`,`ns`,`param`) VALUES (?,?,?,?,?,?)',
			'u_service'				=> 'UPDATE `service` SET `name` = ?, `firmID` = ?, `aggregatorID` = ?, `archiv` = ?, `ns` = ?, `param` = ? WHERE `serviceID` = ?',


			// queries: product (abo)
			's_aboprd'				=> 'SELECT
											"abo" AS `type`, p.productID, p.name, p.serviceID, p.groupKey, p.countryID, p.archiv, p.price, p.currency, p.interval, p.charges, p.contingent, p.param AS `productParam`,
											s.name AS `service`, s.firmID, s.aggregatorID, s.ns AS `serviceNS`, s.param AS `serviceParam`,
											c.name AS `country`, c.code AS `country_code`,
											f.name as `firm`,
											a.name as `aggregator`
										FROM `service_product_abo` p
										LEFT JOIN `service` s ON s.serviceID = p.serviceID
										LEFT JOIN `country` c ON c.countryID = p.countryID
										LEFT JOIN `firm` f ON f.firmID = s.firmID
										LEFT JOIN `aggregator` a ON a.aggregatorID = s.aggregatorID
										WHERE p.productID = ?
										LIMIT 1
										',
			'l_aboprd'				=> ['s_aboprd', ['WHERE p.productID = ?' => '', 'LIMIT 1' => '']],
			'l_aboprd_by_serviceID'	=> ['s_aboprd', ['WHERE p.productID = ?' => 'WHERE p.serviceID = ?', 'LIMIT 1' => '']],
			'l_aboprd_by_countryID'	=> ['s_aboprd', ['WHERE p.productID = ?' => 'WHERE p.countryID = ?', 'LIMIT 1' => '']],
			'l_aboprd_by_groupKey'	=> ['s_aboprd', ['WHERE p.productID = ?' => 'WHERE p.groupKey LIKE ?', 'LIMIT 1' => '']],

			'i_aboprd'				=> 'INSERT INTO `service_product_abo` (`name`,`serviceID`,`groupKey`,`countryID`,`archiv`,`price`,`currency`,`interval`,`charges`,`contingent`,`param`) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
			'u_aboprd'				=> 'UPDATE `service_product_abo` SET `name` = ?, `serviceID` = ?, `groupKey` = ?, `countryID` = ?, `archiv` = ?, `price` = ?, `currency` = ?, `interval` = ?, `charges` = ?, `contingent` = ?, `param` = ? WHERE `productID` = ?',


			// queries: product (otp)
			's_otpprd'				=> 'SELECT
											"otp" AS `type`, `productID`, p.name, p.serviceID, p.countryID, p.archiv, p.price, p.currency, p.expire, p.contingent, p.param AS `productParam`,
											s.name AS `service`, s.firmID, s.aggregatorID, s.ns AS `serviceNS`, s.param AS `serviceParam`,
											c.name AS `country`, c.code AS `country_code`,
											f.name as `firm`,
											a.name as `aggregator`
										FROM `service_product_otp` p
										LEFT JOIN `service` s ON s.serviceID = p.serviceID
										LEFT JOIN `country` c ON c.countryID = p.countryID
										LEFT JOIN `firm` f ON f.firmID = s.firmID
										LEFT JOIN `aggregator` a ON a.aggregatorID = s.aggregatorID
										WHERE p.productID = ?
										LIMIT 1
										',
			'l_otpprd'				=> ['s_otpprd', ['WHERE p.productID = ?' => '', 'LIMIT 1' => '']],
			'l_otpprd_by_serviceID'	=> ['s_otpprd', ['WHERE p.productID = ?' => 'WHERE p.serviceID = ?', 'LIMIT 1' => '']],
			'l_otpprd_by_countryID'	=> ['s_otpprd', ['WHERE p.productID = ?' => 'WHERE p.countryID = ?', 'LIMIT 1' => '']],

			'i_otpprd'				=> 'INSERT INTO `service_product_otp` (`name`,`serviceID`,`countryID`,`archiv`,`price`,`currency`,`expire`,`contingent`,`param`) VALUES (?,?,?,?,?,?,?,?,?)',
			'u_otpprd'				=> 'UPDATE `service_product_otp` SET `name` = ?, `serviceID` = ?, `countryID` = ?, `archiv` = ?, `price` = ?, `currency` = ?, `expire` = ?, `contingent` = ?, `param` = ? WHERE `productID` = ?',


			// queries: smsgate
			's_smsgate'				=> 'SELECT
											g.*,
											s.name AS `service`, s.firmID, s.aggregatorID,
											c.name AS `country`, c.code AS `country_code`,
											f.name as `firm`,
											a.name as `aggregator`
										FROM `smsgate` g
										LEFT JOIN `service` s ON s.serviceID = g.serviceID
										LEFT JOIN `country` c ON c.countryID = g.countryID
										LEFT JOIN `firm` f ON f.firmID = s.firmID
										LEFT JOIN `aggregator` a ON a.aggregatorID = s.aggregatorID
										WHERE g.smsgateID = ?
										LIMIT 1
										',
			'l_smsgate'				=> ['s_smsgate', ['WHERE g.smsgateID = ?' => '', 'LIMIT 1' => '']],
			'l_smsgate_by_number'	=> ['s_smsgate', ['WHERE g.smsgateID = ?' => 'WHERE g.number = ?', 'LIMIT 1' => '']],
			'l_smsgate_by_serviceID'=> ['s_smsgate', ['WHERE g.smsgateID = ?' => 'WHERE g.serviceID = ?', 'LIMIT 1' => '']],
			'l_smsgate_by_countryID'=> ['s_smsgate', ['WHERE g.smsgateID = ?' => 'WHERE g.countryID = ?', 'LIMIT 1' => '']],

			'i_smsgate'				=> 'INSERT INTO `smsgate` (`number`,`keyword`,`operatorID`,`countryID`,`serviceID`,`type`,`productID`,`archiv`,`param`) VALUES (?,?,?,?,?,?,?,?,?)',
			'u_smsgate'				=> 'UPDATE `smsgate` SET `countryID` = ?, `serviceID` = ?, `type` = ?, `productID` = ?, `archiv` = ?, `param` = ? WHERE `smsgateID` = ?',


			// Queries: service_payout
			's_serpay'				=> 'SELECT * FROM `service_payout` WHERE `operatorID` = ? AND `productID` = ? AND `product_type` = ? LIMIT 1',

			'l_serpay'				=> 'SELECT * FROM `service_payout` ORDER BY `productID` ASC, `operatorID` ASC',
			'l_serpay_by_serviceID'	=> 'SELECT * FROM `service_payout` WHERE `serviceID` = ? ORDER BY `productID` ASC, `operatorID` ASC',

			'i_serpay'				=> 'INSERT INTO `service_payout` (`serviceID`,`productID`,`product_type`,`operatorID`,`payout`,`info`) VALUES (?,?,?,?,?,?)',
			'u_serpay'				=> 'UPDATE `service_payout` SET `payout` = ?, `info` = ? WHERE `productID` = ? AND `product_type` = ? AND `operatorID` = ?',
			'd_serpay'				=> 'DELETE FROM `service_payout` WHERE `productID` = ? AND `product_type` = ? AND `operatorID` = ? LIMIT 1',

			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_nexus');
		}


	/* lvl1 cache */
	protected static $lvl1_cache = [];



	/* Object: service */
	public static function get_service($req = []){

		// alternativ
		$alt = h::eX($req, [
			'serviceID'	=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: serviceID
		if(isset($alt['serviceID'])){

			// cache key
			$cache_key = 'service:by_serviceID:'.$alt['serviceID'];

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return result
				return self::response(200, clone self::$lvl1_cache[$cache_key]);
				}

			// load redis
			$redis = self::redis();

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// load entry
				$entry = $redis->get($cache_key);
				}

			// else search in DB
			else{

				// load entry
				$entry = self::pdo('s_service', [$alt['serviceID']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable, cache entry
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// decode param
			$entry->param = $entry->param ? json_decode($entry->param, true) : [];

			// cache it in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: no param
		if(empty($req)){

			// get list
			$list = self::pdo('l_service');

			// on error
			if($list === false) return self::response(560);

			// decode param
			foreach($list as $entry){
				$entry->param = $entry->param ? json_decode($entry->param, true) : [];
				}

			// return success
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need serviceID or no parameter');
		}

	public static function get_service_fn($req){

		// mandatory
		$mand = h::eX($req, [
			'serviceID'	=> '~1,65535/i',
			'fn'		=> '~^[a-z\_]{1,64}$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load service
		$res = self::get_service([
			'serviceID'	=> $mand['serviceID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take entry
		$service = $res->data;

		// if service function does not exists
		if(!is_callable($service->ns.'::'.$mand['fn'])){

			// return error
			return self::response(501, 'Service method '.$service->ns.'::'.$mand['fn'].' unavailable');
			}

		// return result
		return self::response(200, (object)['fn'=>$service->ns.'::'.$mand['fn']]);
		}

	public static function create_service($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'			=> '~1,120/s',
			'ns'			=> '~^\\\\[a-z0-9\\\\]{1,159}$',
			'param'			=> '~/l',
			], $error);

		// optional
		$opt = h::eX($req, [
			'firmID'		=> '~0,255/i',
			'aggregatorID'	=> '~0,255/i',
			'archiv'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'firmID'		=> 0,
			'aggregatorID'	=> 0,
			'archiv'		=> false,
			];

		// convert param to json
		$mand['param'] = json_encode($mand['param']);

		// create entry
		$serviceID = self::pdo('i_service', [$mand['name'], $opt['firmID'], $opt['aggregatorID'], $opt['archiv'] ? 1 : 0, $mand['ns'], $mand['param']]);

		// on error
		if($serviceID === false) return self::response(560);

		// return success
		return self::response(201, (object)['serviceID'=>$serviceID]);
		}

	public static function update_service($req = []){

		// mandatory
		$mand = h::eX($req, [
			'serviceID'		=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~1,120/s',
			'firmID'		=> '~0,255/i',
			'aggregatorID'	=> '~0,255/i',
			'archiv'		=> '~/b',
			'ns'			=> '~^\\\\[a-z0-9\\\\]{1,159}$',
			'param'			=> '~/l',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert param to json
		if(isset($opt['param'])) $opt['param'] = json_encode($opt['param']);

		// check
		$res = self::get_service(['serviceID'=>$mand['serviceID']]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_service', [$entry->name, $entry->firmID, $entry->aggregatorID, $entry->archiv ? 1 : 0, $entry->ns, $entry->param, $entry->serviceID]);

		// on error
		if($upd === false) return self::response(560);

		// cache key
		$cache_key = 'service:by_serviceID:'.$entry->serviceID;

		// load redis
		$redis = self::redis();

		// expire redis and unset lvl1 cache
		if($redis) $redis->setTimeout($cache_key, 0);
		unset(self::$lvl1_cache[$cache_key]);

		// for each product type
		foreach(['abo','otp'] as $type){

			// load product using this service
			$res = self::get_product([
				'type'		=> $type,
				'serviceID'	=> $entry->serviceID,
				]);

			// on success
			if($res->status == 200){

				// and expire cache entries
				foreach($res->data as $p){

					// cache key
					$cache_key = 'product:by_'.$type.'_productID:'.$p->productID;

					// expire entry
					if($redis) $redis->setTimeout($cache_key, 0);
					unset(self::$lvl1_cache[$cache_key]);
					}
				}

			// on error
			else{
				e::logtrigger('Error '.$res->status.' while loading '.$type.'_products for  serviceID '.$entry->serviceID.' for cache reseting');
				}
			}

		// return success
		return self::response(204);
		}



	/* Object: product */
	public static function get_product($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'				=> '~^(?:abo|smsabo|otp|smspay)$',
			], $error);

		// alternativ
		$alt = h::eX($req, [
			'productID'			=> '~1,65535/i',
			'productID_list'	=> '~/a',
			'serviceID'			=> '~1,65535/i',
			'countryID'			=> '~1,255/i',
			'groupKey'			=> '~^[a-zA-Z0-9 \-\%\_]{1,32}$',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'no_combined_param'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert type
		if($mand['type'] == 'smsabo') $mand['type'] = 'abo';
		elseif($mand['type'] == 'smspay') $mand['type'] = 'otp';

		// entry function
		$convert = function($entry) use ($opt){

			// decode param
			$entry->productParam = $entry->productParam ? json_decode($entry->productParam, true) : [];
			$entry->serviceParam = $entry->serviceParam ? json_decode($entry->serviceParam, true) : [];

			// combine param
			if(empty($opt['no_combined_param'])){
				$entry->param = $entry->productParam + $entry->serviceParam;
				unset($entry->serviceParam);
				unset($entry->productParam);
				}

			// return converted entry
			return $entry;
			};

		// param order 1: productID
		if(isset($alt['productID'])){

			// cache key
			$cache_key = 'product:by_'.$mand['type'].'_productID:'.$alt['productID'];

			// check lvl1-cache
			if(isset(self::$lvl1_cache[$cache_key]) and empty($opt['no_combined_param'])){

				// and return entry
				return self::response(200, clone self::$lvl1_cache[$cache_key]);
				}

			// load redis
			$redis = self::redis();

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// load entry
				$entry = $redis->get($cache_key);
				}

			// else search in DB
			else{

				// load entry
				$entry = self::pdo('s_'.$mand['type'].'prd', [$alt['productID']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// convert entry
			$entry = $convert($entry);

			// cache combined entry in lvl1 cache
			if(empty($opt['no_combined_param'])){

				// cache entry
				self::$lvl1_cache[$cache_key] = clone $entry;
				}

			// return result
			return self::response(200, $entry);
			}

		// param order 2: productID_list
		if(isset($alt['productID_list'])){

			// prepare list
			$list = [];

			// run each productID in list
			foreach($alt['productID_list'] as $productID){

				// skip double entries
				if(isset($list[$productID])) continue;

				// load product
				$res = self::get_product([
					'type'				=> $mand['type'],
					'productID'			=> $productID,
					'no_combined_param'	=> !empty($opt['no_combined_param']),
					]);

				// if found, apped to list
				if($res->status == 200) $list[$productID] = $res->data;

				// if not found, skip
				elseif($res->status === 404) continue;

				// or return error
				else return $res;
				}

			// return result
			return self::response(200, $list);
			}

		// param order 3: serviceID or countryID
		if(isset($alt['serviceID']) or isset($alt['countryID'])){

			// define the ID
			$keyname = isset($alt['serviceID']) ? 'serviceID' : 'countryID';

			// load list
			$list = self::pdo('l_'.$mand['type'].'prd_by_'.$keyname, $alt[$keyname]);

			// on error
			if($list === false) return self::response(560);

			// for each entry
			foreach($list as $entry){

				// convert entry
				$entry = $convert($entry);
				}

			// return result
			return self::response(200, $list);
			}

		// param order 4: groupKey
		if(isset($alt['groupKey'])){

			// special condition
			if($mand['type'] != 'abo') return self::response(400, 'groupKey only allowed with type=abo');

			// load
			$list = self::pdo('l_aboprd_by_groupKey', $alt['groupKey']);

			// on error
			if($list === false) return self::response(560);

			// for each entry
			foreach($list as $entry){

				// convert entry
				$entry = $convert($entry);
				}

			// return result
			return self::response(200, $list);
			}

		// param order 4: no param
		if(empty($alt)){

			// load
			$list = self::pdo('l_'.$mand['type'].'prd');

			// on error
			if($list === false) return self::response(560);

			// for each entry
			foreach($list as $entry){

				// convert entry
				$entry = $convert($entry);
				}

			// return result
			return self::response(200, $list);
			}


		// other request param invalid
		return self::response(400, 'need productID or productID_list or serviceID or countryID or no parameter');
		}

	public static function get_product_fn($req){

		// mandatory
		$mand = h::eX($req, [
			'type'		=> '~^(?:abo|otp)$',
			'productID'	=> '~1,65535/i',
			'fn'		=> '~^[a-z\_]{1,64}$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load product
		$res = self::get_product([
			'type'		=> $mand['type'],
			'productID'	=> $mand['productID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take entry
		$product = $res->data;

		// if service function is not callable
		if(!is_callable($product->serviceNS.'::'.$mand['fn'])){

			// return error
			return self::response(501, 'Service method '.$product->serviceNS.'::'.$mand['fn'].' unavailable');
			}

		// return function
		return self::response(200, (object)['fn'=>$product->serviceNS.'::'.$mand['fn']]);
		}

	public static function create_product($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'		=> '~^(?:abo|otp)$',
			'name'		=> '~1,120/s',
			'serviceID'	=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'groupKey'	=> '~^[a-zA-Z0-9 \-\%\_]{0,32}$',
			'countryID'	=> '~0,255/i',
			'archiv'	=> '~/b',
			'price'		=> '~0,99999/f',
			'currency'	=> '~^[a-zA-Z]{1,8}$',
			'contingent'=> '~0,65535/i',
			'interval'	=> '~1,10/s',
			'expire'	=> '~0,10/s',
			'charges'	=> '~0,255/i',
			'param'		=> '~/l',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'groupKey'	=> '',
			'countryID'	=> 0,
			'archiv'	=> false,
			'price'		=> 0,
			'currency'	=> '',
			'interval'	=> '1 week',
			'charges'	=> 1,
			'expire'	=> '',
			'contingent'=> 0,
			'param'		=> [],
			];

		// convert param to json
		$opt['param'] = json_encode($opt['param']);

		// insert entry
		$productID = ($mand['type'] == 'abo')
			? self::pdo('i_aboprd', [$mand['name'], $mand['serviceID'], $opt['groupKey'], $opt['countryID'], $opt['archiv'] ? 1 : 0, $opt['price'], $opt['currency'], $opt['interval'], $opt['charges'], $opt['contingent'], $opt['param']])
			: self::pdo('i_otpprd', [$mand['name'], $mand['serviceID'], $opt['countryID'], $opt['archiv'] ? 1 : 0, $opt['price'], $opt['currency'], $opt['expire'], $opt['contingent'], $opt['param']]);

		// on error
		if($productID === false) return self::response(560);

		// return success
		return self::response(201, (object)['type'=>$mand['type'], 'productID'=>$productID]);
		}

	public static function update_product($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'		=> '~^(?:abo|otp)$',
			'productID'	=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'		=> '~1,120/s',
			'serviceID'	=> '~1,65535/i',
			'groupKey'	=> '~^[a-zA-Z0-9 \-\%\_]{0,32}$',
			'countryID'	=> '~0,255/i',
			'archiv'	=> '~/b',
			'price'		=> '~0,99999/f',
			'currency'	=> '~^[a-zA-Z]{1,8}$',
			'interval'	=> '~1,10/s',
			'charges'	=> '~0,255/i',
			'expire'	=> '~0,10/s',
			'contingent'=> '~0,65535/i',
			'param'		=> '~/l',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert param to json
		if(isset($opt['param'])) $opt['param'] = json_encode($opt['param']);

		// load entry
		$res = self::get_product([
			'type'		=> $mand['type'],
			'productID'	=> $mand['productID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// replace params
		foreach($opt as $k => $v){

			// skip specific keys
			if($mand['type'] != 'abo' and in_array($k, ['groupKey','charges','expire'])) continue;
			if($mand['type'] != 'otp' and in_array($k, ['expire'])) continue;

			// replace key
			$entry->{$k} = $v;
			}

		// update abo or otp entry
		$upd = ($mand['type'] == 'abo')
			? self::pdo('u_aboprd', [$entry->name, $entry->serviceID, $entry->groupKey, $entry->countryID, $entry->archiv ? 1 : 0, $entry->price, $entry->currency, $entry->interval, $entry->charges, $entry->contingent, $entry->param, $entry->productID])
			: self::pdo('u_otpprd', [$entry->name, $entry->serviceID, $entry->countryID, $entry->archiv ? 1 : 0, $entry->price, $entry->currency, $entry->expire, $entry->contingent, $entry->param, $entry->productID]);

		// on error
		if($upd === false) return self::response(560);

		// cache key
		$cache_key = 'product:by_'.$mand['type'].'_productID:'.$entry->productID;

		// load redis
		$redis = self::redis();

		// expire redis and unset lvl1 cache
		if($redis) $redis->setTimeout($cache_key, 0);
		unset(self::$lvl1_cache[$cache_key]);

		// return success
		return self::response(204);
		}



	/* Object: smsgate */
	public static function get_smsgate($req = []){

		// alternativ
		$alt = h::eX($req, [
			'smsgateID'		=> '~1,65535/i',
			'number'		=> '~^(?:\+|00|)([0-9]{3,15})$',
			'keyword'		=> '~^(?:|\*|[A-Za-z0-9]{1,32})$',
			'operatorID'	=> '~0,65535/i',
			'serviceID'		=> '~1,65535/i',
			'countryID'		=> '~1,255/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'ignore_archive'		=> '~/b',
			'fallback_keyword'		=> '~^(?:|\*|[A-Za-z0-9]{1,32})$',
			'fallback_operatorID'	=> '~0,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert number
		if(isset($alt['number'])) $alt['number'] = $alt['number'][0];

		// convert keyword
		if(isset($alt['keyword'])) $alt['keyword'] = ($alt['keyword'] == '*') ? '' : strtoupper($alt['keyword']);

		// convert fallback_keyword
		if(isset($opt['fallback_keyword'])) $opt['fallback_keyword'] = ($opt['fallback_keyword'] == '*') ? '' : strtoupper($opt['fallback_keyword']);

		// define defaults
		$opt += [
			'ignore_archive'=> false,
			];


		// param order 1: smsgateID
		if(isset($alt['smsgateID'])){

			// cache key
			$cache_key = 'smsgate:by_smsgateID:'.$alt['smsgateID'];

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return result
				return self::response(200, clone self::$lvl1_cache[$cache_key]);
				}

			// load redis
			$redis = self::redis();

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// load entry
				$entry = $redis->get($cache_key);
				}

			// else
			else{

				// search in DB
				$entry = self::pdo('s_smsgate', [$alt['smsgateID']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable, cache entry
				if($redis){

					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// decode param
			$entry->param = $entry->param ? json_decode($entry->param, true) : [];

			// cache it in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return result
			return self::response(200, $entry);
			}

		// param order 2: number+keyword
		if(isset($alt['number']) and isset($alt['keyword']) and isset($alt['operatorID'])){

			// cache key
			$cache_key = 'smsgate:by_number:'.$alt['number'];

			// hash key
			$hash_key = $alt['keyword'].'-'.$alt['operatorID'];

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key][$hash_key])){

				// return result
				return self::response(200, clone self::$lvl1_cache[$cache_key][$hash_key]);
				}

			// load redis
			$redis = self::redis();

			// if redis accessable and hash list exists
			if($redis and $redis->exists($cache_key)){

				// define entry (if exists)
				$entry = $redis->hExists($cache_key, $hash_key) ? $redis->hGet($cache_key, $hash_key) : null;
				}

			// else search in DB
			else{

				// get list
				$list = self::pdo('l_smsgate_by_number', [$alt['number']]);

				// on error
				if($list === false) return self::response(560);

				// create hash list
				$hash_list = [];

				// run list
				foreach($list as $e){

					// set hash entry
					$hash_list[$e->keyword.'-'.$e->operatorID] = $e;
					}

				// if redis accessable and hash list not empty
				if($redis and $hash_list){

					// cache hash list
					$redis->hMSet($cache_key, $hash_list);
					$redis->expire($cache_key, 21600); // 6 hours
					}

				// take entry (if exists)
				$entry = isset($hash_list[$hash_key]) ? $hash_list[$hash_key] : null;

				// if redis accessable and entry exists
				if($redis and $entry){

					// cache entry
					$redis->set('smsgate:by_smsgateID:'.$entry->smsgateID, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// if entry was not found
			if(!$entry){

				// if fallback_operatorID is defined and operatorID is different
				if(isset($opt['fallback_operatorID']) and $opt['fallback_operatorID'] != $alt['operatorID']){

					// try again with fallback_operatorID
					$res = self::get_smsgate([
						'number'		=> $alt['number'],
						'keyword'		=> $alt['keyword'],
						'operatorID'	=> $opt['fallback_operatorID'],
						'ignore_archive'=> $opt['ignore_archive'],
						]);

					// if found or on unexpected error, return result
					if($res->status != 404) return $res;
					}

				// if fallback_keyword is defined and keyword is different
				if(isset($opt['fallback_keyword']) and $opt['fallback_keyword'] != $alt['keyword']){

					// try again with fallback_keyword
					$res = self::get_smsgate([
						'number'		=> $alt['number'],
						'keyword'		=> $opt['fallback_keyword'],
						'operatorID'	=> $alt['operatorID'],
						'ignore_archive'=> $opt['ignore_archive'],
						]);

					// if found or on unexpected error, return result
					if($res->status != 404) return $res;
					}

				// if both fallback_operatorID and fallback_keyword are defined and different
				if(isset($opt['fallback_operatorID']) and isset($opt['fallback_keyword']) and $opt['fallback_operatorID'] != $alt['operatorID'] and $opt['fallback_keyword'] != $alt['keyword']){

					// try again with fallback_operatorID and fallback_keyword
					$res = self::get_smsgate([
						'number'		=> $alt['number'],
						'keyword'		=> $opt['fallback_keyword'],
						'operatorID'	=> $opt['fallback_operatorID'],
						'ignore_archive'=> $opt['ignore_archive'],
						]);

					// if found or on unexpected error, return result
					if($res->status != 404) return $res;
					}

				// return not found
				return self::response(404);
				}

			// decode param
			$entry->param = $entry->param ? json_decode($entry->param, true) : [];

			// cache entry in lvl1 cache
			self::$lvl1_cache[$cache_key][$hash_key] = clone $entry;
			self::$lvl1_cache['smsgate:by_smsgateID:'.$entry->smsgateID] = clone $entry;

			// check if archivated entry should be ignored
			if($opt['ignore_archive'] and $entry->archiv) return self::response(404);

			// return result
			return self::response(200, $entry);
			}

		// param order 3: number
		if(isset($alt['number'])){

			// cache key
			$cache_key = 'smsgate:by_number:'.$alt['number'];

			// load redis
			$redis = self::redis();

			// if redis accessable and hash list exists
			if($redis and $redis->exists($cache_key)){

				// get sequential list
				$list = $redis->hVals($cache_key);
				}

			// else search in DB
			else{

				// get list
				$list = self::pdo('l_smsgate_by_number', [$alt['number']]);

				// on error
				if($list === false) return self::response(560);

				// create hash list
				$hash_list = [];

				// run list
				foreach($list as $e){

					// set hash entry
					$hash_list[$e->keyword.'-'.$e->operatorID] = $e;
					}

				// if redis accessable and hash list not empty
				if($redis and $hash_list){

					// cache hash list
					$redis->hMSet($cache_key, $hash_list);
					$redis->expire($cache_key, 21600); // 6 hours
					}
				}

			// for each entry
			foreach($list as $k => $entry){

				// check if archivated entry should be ignored
				if($opt['ignore_archive'] and $entry->archiv) unset($list[$k]);

				// decode param
				$entry->param = $entry->param ? json_decode($entry->param, true) : [];
				}

			// return result
			return self::response(200, array_values($list));
			}

		// param order 4: serviceID or countryID
		if(isset($alt['serviceID']) or isset($alt['countryID'])){

			// define the ID
			$keyname = isset($alt['serviceID']) ? 'serviceID' : 'countryID';

			// load
			$list = self::pdo('l_smsgate_by_'.$keyname, $alt[$keyname]);

			// on error
			if($list === false) return self::response(560);

			// for each entry
			foreach($list as $k => $entry){

				// check if archivated entry should be ignored
				if($opt['ignore_archive'] and $entry->archiv) unset($list[$k]);

				// decode param
				$entry->param = $entry->param ? json_decode($entry->param, true) : [];
				}

			// return result
			return self::response(200, array_values($list));
			}

		// param order 5: no param
		if(empty($req)){

			// get list
			$list = self::pdo('l_smsgate');

			// on error
			if($list === false) return self::response(560);

			// for each entry
			foreach($list as $k => $entry){

				// check if archivated entry should be ignored
				if($opt['ignore_archive'] and $entry->archiv) unset($list[$k]);

				// decode param
				$entry->param = $entry->param ? json_decode($entry->param, true) : [];
				}

			// return result
			return self::response(200, array_values($list));
			}

		// other request param invalid
		return self::response(400, 'need smsgateID, number, number+keyword+operatorID or no parameter');
		}

	public static function create_smsgate($req = []){

		// mandatory
		$mand = h::eX($req, [
			'number'		=> '~^(?:\+|00|)([0-9]{3,15})$',
			'keyword'		=> '~^(?:|[A-Z0-9]{1,32})$',
			'operatorID'	=> '~0,65535/i',
			'serviceID'		=> '~1,65535/i',
			'countryID'		=> '~1,255/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'type'			=> '~^(?:smspay|smsabo|otp|)$',
			'productID'		=> '~0,65535/i',
			'archiv'		=> '~/b',
			'param'			=> '~/l',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert number
		$mand['number'] = $mand['number'][0];

		// define default
		$opt += [
			'type'			=> '',
			'productID'		=> 0,
			'archiv'		=> false,
			'param'			=> [],
			];

		// convert param to json
		$opt['param'] = json_encode($opt['param']);

		// check existance of number+keyword+operatorID combination
		$res = self::get_smsgate([
			'number'	=> $mand['number'],
			'keyword'	=> $mand['keyword'],
			'operatorID'=> $mand['operatorID'],
			]);

		// when found or error, return conflict or error
		if($res->status != 404) return ($res->status == 200) ? self::response(409) : $res;

		// create entry
		$smsgateID = self::pdo('i_smsgate', [$mand['number'], $mand['keyword'], $mand['operatorID'], $mand['countryID'], $mand['serviceID'], $opt['type'], $opt['productID'], $opt['archiv'] ? 1 : 0, $opt['param']]);

		// on error
		if($smsgateID === false) return self::response(560);

		// cache key
		$cache_key = 'smsgate:by_number:'.$mand['number'];

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire entry
			$redis->setTimeout($cache_key, 0);
			}

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);

		// return success
		return self::response(201, (object)['smsgateID'=>$smsgateID]);
		}

	public static function update_smsgate($req = []){

		// mandatory
		$mand = h::eX($req, [
			'smsgateID'		=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'serviceID'		=> '~1,65535/i',
			'countryID'		=> '~1,255/i',
			'type'			=> '~^(?:smspay|smsabo|otp|)$',
			'productID'		=> '~0,65535/i',
			'archiv'		=> '~/b',
			'param'			=> '~/l',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert param to json
		if(isset($opt['param'])) $opt['param'] = json_encode($opt['param']);

		// check
		$res = self::get_smsgate([
			'smsgateID'		=> $mand['smsgateID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_smsgate', [$entry->countryID, $entry->serviceID, $entry->type, $entry->productID, $entry->archiv ? 1 : 0, $entry->param, $entry->smsgateID]);

		// on error
		if($upd === false) return self::response(560);

		// cache key
		$cache_key_direct = 'smsgate:by_smsgateID:'.$entry->smsgateID;
		$cache_key_nk = 'smsgate:by_number:'.$entry->number;

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire entries
			$redis->setTimeout($cache_key_direct, 0);
			$redis->setTimeout($cache_key_nk, 0);
			}

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key_direct]);
		unset(self::$lvl1_cache[$cache_key_nk]);

		// return success
		return self::response(204);
		}



	/* Object: service_payout */
	public static function get_service_payout($req = []){

		// alternative
		$alt = h::eX($req, [
			'serviceID'		=> '~1,255/i',
			'operatorID'	=> '~0,65535/i',
			'productID'		=> '~1,65535/i',
			'product_type'	=> '~^(?:abo|otp|smspay|smsabo)$',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'fallback_operatorID'	=> '~0,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if product type is defined
		if(isset($alt['product_type'])){

			// convert subtypes to maintype
			if($alt['product_type'] == 'smspay') $alt['product_type'] = 'otp';
			if($alt['product_type'] == 'smsabo') $alt['product_type'] = 'abo';
			}


		// param order 1: operatorID + productID + product_type
		if(isset($alt['operatorID']) and isset($alt['productID']) and isset($alt['product_type'])){

			// define cache key
			$cache_key = 'service_payout:'.$alt['product_type'].':'.$alt['productID'].':'.$alt['operatorID'];

			// check lvl1-cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return entry
				return self::response(200, clone self::$lvl1_cache[$cache_key]);
				}

			// init redis
			$redis = self::redis();

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// load entry
				$entry = $redis->get($cache_key);
				}

			// else
			else{

				// search in DB
				$entry = self::pdo('s_serpay', [$alt['operatorID'], $alt['productID'], $alt['product_type']]);

				// on error
				if($entry === false) return self::response(560);

				// if redis accessable
				if($redis){

					// cache entry (save false if entry is null)
					$redis->set($cache_key, $entry ?: false, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// if entry was not found
			if(!$entry){

				// if fallback_operatorID is defined and operatorID is different
				if(isset($opt['fallback_operatorID']) and $opt['fallback_operatorID'] != $alt['operatorID']){

					// try again with fallback_operatorID
					$res = self::get_service_payout([
						'operatorID'	=> $opt['fallback_operatorID'],
						'productID'		=> $alt['productID'],
						'product_type'	=> $alt['product_type'],
						]);

					// if found or on unexpected error, return result
					if($res->status != 404) return $res;
					}

				// return not found
				return self::response(404);
				}

			// convert decimal
			$entry->payout = (float) $entry->payout;

			// cache it in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return entry
			return self::response(200, $entry);
			}


		// param order 2: serviceID or no param
		if((count($alt) == 1 and isset($alt['serviceID'])) or empty($alt)){

			// search for serviceID or for all entries
			$list = isset($alt['serviceID'])
				? self::pdo('l_serpay_by_serviceID', [$alt['serviceID']])
				: self::pdo('l_serpay');

			// on error
			if($list === false) return self::response(560);

			// for each entry
			foreach($list as $entry){

				// convert decimal
				$entry->payout = (float) $entry->payout;
				}

			// return list
			return self::response(200, $list);
			}


		// other request param invalid
		return self::response(400, 'only operatorID+productID+product_type or serviceID or no param allowed');
		}

	public static function set_service_payout($req = []){

		// mandatory
		$mand = h::eX($req, [
			'serviceID'		=> '~1,255/i',
			'productID'		=> '~1,65535/i',
			'product_type'	=> '~^(?:abo|otp|smspay|smsabo)$',
			'operatorID'	=> '~0,65535/i',
			'payout'		=> '~0,9999/f',
			'info'			=> '~/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// convert subtypes to maintype
		if($mand['product_type'] == 'smspay') $mand['product_type'] = 'otp';
		if($mand['product_type'] == 'smsabo') $mand['product_type'] = 'abo';

		// search for entry
		$entry = self::pdo('s_serpay', [$mand['operatorID'], $mand['productID'], $mand['product_type']]);

		// on error
		if($entry === false) return self::response(560);

		// if entry is found
		if($entry){

			// update entry
			$upd = self::pdo('u_serpay', [$mand['payout'], $mand['info'], $mand['productID'], $mand['product_type'], $mand['operatorID']]);

			// on error
			if($upd === false) return self::response(560);
			}

		// else
		else{

			// create entry
			$ins = self::pdo('i_serpay', [$mand['serviceID'], $mand['productID'], $mand['product_type'], $mand['operatorID'], $mand['payout'], $mand['info']]);

			// on error
			if($ins === false) return self::response(560);
			}

		// load redis and define cachekey
		$redis = self::redis();
		$cache_key = 'service_payout:'.$mand['product_type'].':'.$mand['productID'].':'.$mand['operatorID'];

		// unset in lvl1-cache
		unset(self::$lvl1_cache[$cache_key]);

		// if redis accessable and cachekey exists
		if($redis and $redis->exists($cache_key)){

			// expire entry
			$redis->setTimeout($cache_key, 0);
			}

		// return success (as created entry, if new, or updated entry)
		return !$entry ? self::response(201, (object) $mand) : self::response(204);
		}

	public static function unset_service_payout($req = []){

		// mandatory
		$mand = h::eX($req, [
			'productID'		=> '~1,65535/i',
			'product_type'	=> '~^(?:abo|otp|smspay|smsabo)$',
			'operatorID'	=> '~0,65535/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// convert subtypes to maintype
		if($mand['product_type'] == 'smspay') $mand['product_type'] = 'otp';
		if($mand['product_type'] == 'smsabo') $mand['product_type'] = 'abo';

		// search for config
		$del = self::pdo('d_serpay', [$mand['productID'], $mand['product_type'], $mand['operatorID']]);

		// on error
		if($del === false) return self::response(560);

		// load redis
		$redis = self::redis();

		// define cachekey
		$cache_key = 'service_payout:'.$mand['product_type'].':'.$mand['productID'].':'.$mand['operatorID'];

		// unset in lvl1-cache
		unset(self::$lvl1_cache[$cache_key]);

		// if redis accessable and cachekey exists
		if($redis and $redis->exists($cache_key)){

			// expire entry
			$redis->setTimeout($cache_key, 0);
			}

		// return success
		return self::response(204);
		}

	}