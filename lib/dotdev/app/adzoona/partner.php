<?php
/*****
 * Version 1.1.2018-01-17
**/
namespace dotdev\app\adzoona;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;


class partner {
	use	\tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['app_adzoona:partner', [

			// partner
			'l_partner'							=> "SELECT *  FROM `partner`",
			's_partner'							=> "SELECT * FROM `partner` WHERE `partnerID` = ? LIMIT 1",
			's_partner_by_email'				=> "SELECT * FROM `partner` WHERE `email` = ? LIMIT 1",
			'i_partner'							=> "INSERT INTO `partner` (`name`,`email`,`auth`,`data`) VALUES (?,?,?,?)",
			'u_partner'							=> "UPDATE `partner` SET `name` = ?, `email` = ?, `auth` = ?, `data` = ? WHERE `partnerID` = ?",
			'd_partner'							=> "DELETE FROM `partner` WHERE `partnerID` = ?",

			// partner_access
			'l_partner_access'					=> 'SELECT p.partnerID, p.publisherID, p.domainID, p.pageID, p.status, p.partner_accessID
													FROM `partner_access` p
													WHERE 1',

			'l_partner_access_by_partnerID'		=> ['l_partner_access', ['WHERE 1' => 'WHERE p.partnerID = ?']],
			'l_partner_access_by_publisherID'	=> ['l_partner_access', ['WHERE 1' => 'WHERE p.publisherID = ?']],
			'l_partner_access_by_domainID'		=> ['l_partner_access', ['WHERE 1' => 'WHERE p.domainID = ?']],
			'l_partner_access_by_pageID'		=> ['l_partner_access', ['WHERE 1' => 'WHERE p.pageID = ?']],
			'l_partner_access_by_status'		=> ['l_partner_access', ['WHERE 1' => 'WHERE p.status = ?']],
			's_partner_access_by_all'			=> ['l_partner_access', ['WHERE 1' => 'WHERE p.partnerID = ? AND p.publisherID = ? AND p.domainID = ? AND p.pageID = ? LIMIT 1']],

			'i_partner_access'					=> 'INSERT INTO `partner_access` (`partnerID`, `publisherID`, `domainID`, `pageID`, `status`)
													VALUES (?, ?, ?, ?, ?)
													ON DUPLICATE KEY UPDATE `partnerID`=VALUES(`partnerID`), `publisherID`=VALUES(`publisherID`), `domainID`=VALUES(`domainID`), `pageID`=VALUES(`pageID`), `status`=VALUES(`status`)
													',

			'u_partner_access_status'			=> 'UPDATE `partner_access` SET `status` = ? WHERE `partnerID` = ? AND `publisherID` = ? AND `domainID` = ? AND `pageID` = ? LIMIT 1',

			'd_partner_access'					=> 'DELETE FROM `partner_access` WHERE `partnerID` = ?',
			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('app_adzoona');
		}


	/* Object: partner */
	public static function get_partner($req = []){

		// alternativ
		$alt = h::eX($req, [
			'email'		=> '~^[a-z0-9\-\.]+@[a-z0-9\-]+\.[a-z]{2,5}$',
			'partnerID'	=> '~1,65535/i',
			], $error, true);
		if($error) return self::response(400, $error);


		// param order 1: partnerID
		if(isset($alt['partnerID'])){

			// init redis
			$redis     = self::redis();
			$cache_key = 'adzoona:partner:'.$alt['partnerID'];

			// if redis accessable, search for entry
			if($redis and $redis->exists($cache_key)){

				$entry = $redis->get($cache_key);
				}

			// else search in DB
			else{

				$entry = self::pdo('s_partner', $alt['partnerID']);
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable, cache entry
				if($redis){

					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// decode data
			$entry->data = $entry->data ? json_decode($entry->data, true) : [];

			return self::response(200, $entry);
			}

		// param order 2: email
		if(isset($alt['email'])){

			// init redis
			$redis     = self::redis();
			$cache_key = 'adzoona:email:'.$alt['email'];

			// if redis accessable, search for entry
			if($redis and $redis->exists($cache_key)){

				return self::get_partner(['partnerID'=>$redis->get($cache_key)]);
				}

			// else search in DB
			else{

				$entry = self::pdo('s_partner_by_email', $alt['email']);
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable, cache partnerID of entry
				if($redis){

					$redis->set($cache_key, $entry->partnerID, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// decode data
			$entry->data = $entry->data ? json_decode($entry->data, true) : [];

			return self::response(200, $entry);
			}

		// param order 2: no param
		if(empty($req)){

			$list = self::pdo('l_partner');
			if($list === false) return self::response(560);

			// decode data
			foreach($list as $entry){
				$entry->data = $entry->data ? json_decode($entry->data, true) : [];
				}

			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need partnerID, email or no parameter');
		}

	public static function create_partner($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'	=> '~^.{1,120}$',
			'email'	=> '~^[a-z0-9\-\.]+@[a-z0-9\-]+\.[a-z]{2,5}$',
			'auth'	=> '~1,255/s',
			'data'	=> '~/l',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// convert data to json
		$mand['data'] = json_encode($mand['data']);

		// create entry
		$partnerID = self::pdo('i_partner', [$mand['name'], $mand['email'], $mand['auth'], $mand['data']]);
		if($partnerID === false) return self::response(560);

		return self::response(201, (object)['partnerID'=>$partnerID]);
		}

	public static function update_partner($req = []){

		// mandatory
		$mand = h::eX($req, [
			'partnerID'	=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~^.{1,120}$',
			'email'			=> '~^[a-z0-9\-\.]+@[a-z0-9\-]+\.[a-z]{2,5}$',
			'auth'			=> '~1,255/s',
			'data'			=> '~/l',
			], $error, true);
		if($error) return self::response(400, $error);

		// convert param to json
		if(isset($opt['data'])) $opt['data'] = json_encode($opt['data']);

		// load entry
		$res = self::get_partner(['partnerID'=>$mand['partnerID']]);
		if($res->status != 200) return $res;
		$entry = $res->data;

		// convert param to json
		if(isset($entry->data)) $entry->data = json_encode($entry->data);

		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_partner', [$entry->name, $entry->email, $entry->auth, $entry->data, $entry->partnerID]);
		if($upd === false) return self::response(560);

		// if redis accessable, expire entry
		$redis = self::redis();
		if($redis){
			$redis->setTimeout('adzoona:partner:'.$entry->partnerID, 0);
			}

		return self::response(204);
		}

	public static function delete_partner($req = []){

		// mandatory
		$mand = h::eX($req, [
			'partnerID'	=> '~1,65535/i',
			], $error);
		if($error) return self::response(400, $error);

		// check partner
		$res = self::get_partner(['partnerID'=>$mand['partnerID']]);
		if($res->status == 404) return self::response(406);
		elseif($res->status != 200) return $res;

		// delete partner
		$delete = self::pdo('d_partner', [$mand['partnerID']]);
		if($delete === false) return self::response(560);

		// use redis_unset to expire cached partnerright
		self::redis_unset(['search'=>'adzoona:partner*:'.$mand['partnerID']]);

		return self::response(200);
		}


	public static function get_partner_access($req = []) {

		// alternative
		$alt = h::eX($req, [
			'partnerID'		=> '~1,65535/i',
			'publisherID'	=> '~1,65535/i',
			'domainID'		=> '~0,65535/i',
			'pageID'		=> '~0,65535/i',
			'status'		=> '~1,32/s'
			], $error, true);
		if($error) return self::response(400, $error);

		// param order 1: partnerID, publisherID, domainID, pageID
		if(isset($alt['partnerID']) and isset($alt['publisherID']) and isset($alt['domainID']) and isset($alt['pageID'])) {

			// load res
			$res = self::pdo('s_partner_access_by_all', [$alt['partnerID'], $alt['publisherID'], $alt['domainID'], $alt['pageID']]);
			if(!$res) return self::response($res === false ? 560 : 404);

			// return success
			return self::response(200, $res);
			}

		// param order 2: partnerID
		if(isset($alt['partnerID'])){

			// load list
			$list = self::pdo('l_partner_access_by_partnerID', [$alt['partnerID']]);
			if(!$list) return self::response($list === false ? 560 : 404);

			// return success
			return self::response(200, $list);
			}

		// param order 3: publisherID
		if(isset($alt['publisherID'])){

			// load list
			$list = self::pdo('l_partner_access_by_publisherID', [$alt['publisherID']]);
			if(!$list) return self::response($list === false ? 560 : 404);

			// return success
			return self::response(200, $list);
			}

		// param order 4: domainID
		if(isset($alt['domainID'])){

			// load list
			$list = self::pdo('l_partner_access_by_domainID', [$alt['domainID']]);
			if(!$list) return self::response($list === false ? 560 : 404);

			// return success
			return self::response(200, $list);
			}

		// param order 5: pageID
		if(isset($alt['pageID'])){

			// load list
			$list = self::pdo('l_partner_access_by_pageID', [$alt['pageID']]);
			if(!$list) return self::response($list === false ? 560 : 404);

			// return success
			return self::response(200, $list);
			}

		// param order 6: status
		if(isset($alt['status'])){

			// load list
			$list = self::pdo('l_partner_access_by_status', [$alt['status']]);
			if(!$list) return self::response($list === false ? 560 : 404);

			// return success
			return self::response(200, $list);
			}

		// param order 7: empty
		if(empty($req)) {

			// load list
			$list = self::pdo('l_partner_access');
			if(!$list) return self::response($list === false ? 560 : 404);

			// return success
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need partnerID|publisherID|domainID|pageID|status or no parameter');

		}

	public static function create_partner_access($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'partnerID'		=> '~1,65535/i',
			'publisherID'	=> '~1,65535/i',
			'domainID'		=> '~0,65535/i',
			'pageID'		=> '~0,65535/i',
			'status'		=> '~1,32/s'
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// create entry
		$res = self::pdo('i_partner_access', [$mand['partnerID'], $mand['publisherID'], $mand['domainID'], $mand['pageID'], $mand['status']]);
		if($res === false) return self::response(560);

		return self::response(201);
		}

	public static function delete_partner_access($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'partnerID'	=> '~1,65535/i',
			], $error);
		if($error) return self::response(400, $error);

		// check partner_access
		$res = self::get_partner_access(['partnerID'=>$mand['partnerID']]);
		if($res->status == 404) return self::response(406);
		elseif($res->status != 200) return $res;

		// delete partner_access
		$delete = self::pdo('d_partner_access', [$mand['partnerID']]);
		if($delete === false) return self::response(560);

		return self::response(200);
		}

	public static function update_partner_access_status($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'partnerID'		=> '~1,65535/i',
			'publisherID'	=> '~1,65535/i',
			'domainID'		=> '~0,65535/i',
			'pageID'		=> '~0,65535/i',
			'status'		=> '~1,32/s'
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_partner_access(['partnerID' => $mand['partnerID'], 'publisherID' => $mand['publisherID'], 'domainID' => $mand['domainID'], 'pageID' => $mand['pageID']]);
		if($res->status != 200) return $res;
		$entry = $res->data;

		// replace params
		foreach($mand as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_partner_access_status', [$entry->status, $entry->partnerID, $entry->publisherID, $entry->domainID, $entry->pageID]);
		if($upd === false) return self::response(560);

		// return success
		return self::response(204);
		}
	}
