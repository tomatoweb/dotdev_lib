<?php
/*****
 * Version 1.0.2018-06-18
**/
namespace dotdev\nexus;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \dotdev\nexus\base;
use \dotdev\nexus\publisher;

class domain {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_nexus', [

			// queries: domain
			'l_domain'					=> 'SELECT
												d.domainID, d.domain, d.fqdn, d.appID, d.firmID, d.countryID, d.status, d.createTime,
												lc.val AS `color`, lc.val AS `domain_color`
											FROM `domain` d
											LEFT JOIN `levelconfig` lc ON lc.appID = d.appID AND lc.firmID = 0 AND lc.publisherID = 0 AND lc.domainID = d.domainID AND lc.pageID = 0 AND lc.keyname = "domain:color"
											WHERE 1
											',
			'l_domain_by_domain'		=> ['l_domain', ['WHERE 1' => 'WHERE d.domain = ?']],
			's_domain'					=> ['l_domain', ['WHERE 1' => 'WHERE d.domainID = ? LIMIT 1']],
			's_domain_by_fqdn'			=> ['l_domain', ['WHERE 1' => 'WHERE d.fqdn = ? LIMIT 1']],
			'i_domain'					=> 'INSERT INTO `domain` (`domain`,`fqdn`,`appID`,`firmID`,`countryID`,`status`) VALUES (?,?,?,?,?,?)',
			'u_domain'					=> 'UPDATE `domain` SET `appID` = ?, `firmID` = ?, `countryID` = ?, `status` = ? WHERE `domainID` = ?',


			// queries: domain adtarget
			'l_adtarget'				=> 'SELECT
												t.pageID, t.domainID, t.publisherID, t.hash, t.status, t.createTime, d.fqdn,
												d.domain, d.appID, d.firmID, d.countryID, d.status AS `domain_status`,
												lca2.val as `adtarget_videoID`, lca3.val as `adtarget_game`, lca4.val as `adtarget_ckey`, lca5.val as `adtarget_prelp`,
												a.projectname,
												p.name as `publisher`, p.color as `pub_color`
											FROM `domain_adtarget` t
											INNER JOIN `domain` d ON d.domainID = t.domainID
											LEFT JOIN `app` a ON a.appID = d.appID
											LEFT JOIN `publisher` p ON p.publisherID = t.publisherID
											LEFT JOIN `levelconfig` lca2 ON lca2.appID = d.appID AND lca2.firmID = 0 AND lca2.publisherID = 0 AND lca2.domainID = t.domainID AND lca2.pageID = t.pageID AND lca2.keyname = "adtarget:videoID"
											LEFT JOIN `levelconfig` lca3 ON lca3.appID = d.appID AND lca3.firmID = 0 AND lca3.publisherID = 0 AND lca3.domainID = t.domainID AND lca3.pageID = t.pageID AND lca3.keyname = "adtarget:game"
											LEFT JOIN `levelconfig` lca4 ON lca4.appID = d.appID AND lca4.firmID = 0 AND lca4.publisherID = 0 AND lca4.domainID = t.domainID AND lca4.pageID = t.pageID AND lca4.keyname = "adtarget:ckey"
											LEFT JOIN `levelconfig` lca5 ON lca5.appID = d.appID AND lca5.firmID = 0 AND lca5.publisherID = 0 AND lca5.domainID = t.domainID AND lca5.pageID = t.pageID AND lca5.keyname = "adtarget:prelp"
											WHERE 1
											',
			'l_adtarget_by_domainID'	=> ['l_adtarget', ['WHERE 1' => 'WHERE t.domainID = ?']],
			'l_adtarget_by_publisherID'	=> ['l_adtarget', ['WHERE 1' => 'WHERE t.publisherID = ?']],
			'l_adtarget_by_fqdn'		=> ['l_adtarget', ['WHERE 1' => 'WHERE d.fqdn = ?']],
			's_adtarget'				=> ['l_adtarget', ['WHERE 1' => 'WHERE t.pageID = ? LIMIT 1']],
			's_adtarget_by_fqdn_hash'	=> ['l_adtarget', ['WHERE 1' => 'WHERE d.fqdn = ? AND t.hash = ? LIMIT 1']],
			'c_adtarget_hash'			=> 'SELECT `pageID` FROM `domain_adtarget` WHERE `domainID` = ? AND `hash` = ? LIMIT 1',
			'i_adtarget'				=> 'INSERT INTO `domain_adtarget` (`domainID`,`publisherID`,`hash`,`status`) VALUES (?,?,?,?)',
			'u_adtarget'				=> 'UPDATE `domain_adtarget` SET `publisherID` = ?, `status` = ? WHERE `pageID` = ?',
			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_nexus');
		}


	/* lvl1 cache */
	protected static $lvl1_cache = [];



	/* Object: domain */
	public static function get_domain($req = []){

		// alternative
		$alt = h::eX($req, [
			'domainID'	=> '~1,65535/i',
			'domain'	=> '~^[a-z0-9\-]{3,74}\.[a-z0-9]{2,5}$',
			'fqdn'		=> '~^(?:[a-z0-9\-\.]{1,174}\.|)[a-z0-9\-]{3,74}\.[a-z0-9]{2,5}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: domainID
		if(isset($alt['domainID'])){

			// define cache key
			$cache_key = 'domain:by_domainID:'.$alt['domainID'];

			// check lvl1 cache
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

			// else search in DB
			else{

				// load entry
				$entry = self::pdo('s_domain', [$alt['domainID']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache entry in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return success
			return self::response(200, $entry);
			}

		// param order 2: fqdn
		if(isset($alt['fqdn'])){

			// define cache key
			$cache_key = 'domain:by_fqdn:'.$alt['fqdn'];

			// check lvl1 cache
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

			// else search in DB
			else{

				// load entry
				$entry = self::pdo('s_domain_by_fqdn', [$alt['fqdn']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache entry in lvl1 cache
			self::$lvl1_cache['domain:by_domainID:'.$entry->domainID] = clone $entry;
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return success
			return self::response(200, $entry);
			}

		// param order 3: domain
		if(isset($alt['domain'])){

			// load list
			$list = self::pdo('l_domain_by_domain', [$alt['domain']]);

			// on error
			if($list === false) return self::response(560);

			// return success
			return self::response(200, $list);
			}

		// param order 4: no param (at all)
		if(empty($req)){

			// load list
			$list = self::pdo('l_domain');

			// on error
			if($list === false) return self::response(560);

			// return success
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need domainID or fqdn or domain or no parameter');
		}

	public static function create_domain($req = []){

		// mandatory
		$mand = h::eX($req, [
			'fqdn'		=> '~^([a-z0-9\-\.]{1,174}\.|)([a-z0-9\-]{3,74}\.[a-z0-9]{2,5})$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'appID'		=> '~0,255/i',
			'firmID'	=> '~0,255/i',
			'countryID'	=> '~0,255/i',
			'status'	=> '~^(?:online|maintenance|archive|dev|ignore)$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default params
		$opt += [
			'appID'		=> 0,
			'firmID'	=> 0,
			'countryID'	=> 0,
			'status'	=> 'online',
			'domain'	=> $mand['fqdn'][1],
			];

		// combine fqdn to string
		$mand['fqdn'] = $mand['fqdn'][0].$mand['fqdn'][1];

		// check fqdn
		$res = self::get_domain([
			'fqdn'	=>	$mand['fqdn'],
			]);

		// on error
		if($res->status == 200) return self::response(409);
		elseif($res->status != 404) return $res;

		// if appID is given
		if($opt['appID']){

			// check app
			$res = base::get_app([
				'appID'		=> $opt['appID'],
				]);

			// on error
			if($res->status == 404) return self::response(406);
			elseif($res->status != 200) return $res;
			}

		// if firmID is given
		if($opt['firmID']){

			// check firm
			$res = base::get_firm([
				'firmID'	=> $opt['firmID'],
				]);

			// on error
			if($res->status == 404) return self::response(406);
			elseif($res->status != 200) return $res;
			}

		// if countryID is given
		if($opt['countryID']){

			// check country
			$res = base::get_country([
				'countryID'	=> $opt['countryID'],
				]);

			// on error
			if($res->status == 404) return self::response(406);
			elseif($res->status != 200) return $res;
			}

		// create entry
		$domainID = self::pdo('i_domain', [$opt['domain'], $mand['fqdn'], $opt['appID'], $opt['firmID'], $opt['countryID'], $opt['status']]);

		// on error
		if($domainID === false) return self::response(560);

		// create non-hash-adtarget
		$pageID = self::pdo('i_adtarget', [$domainID, 0, '', 'inherit']);

		// on error
		if($pageID === false) return self::response(560);

		// return success
		return self::response(201, (object)['domainID'=>$domainID, 'pageID'=>$pageID]);
		}

	public static function update_domain($req = []){

		// mandatory
		$mand = h::eX($req, [
			'domainID'		=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'appID'			=> '~0,255/i',
			'firmID'		=> '~0,255/i',
			'countryID'		=> '~0,255/i',
			'status'		=> '~^(?:online|maintenance|archive|dev|ignore)$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load domain
		$res = self::get_domain([
			'domainID'	=> $mand['domainID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// if appID is given
		if(!empty($opt['appID'])){

			// check app
			$res = base::get_app([
				'appID'		=> $opt['appID'],
				]);

			// on error
			if($res->status == 404) return self::response(406);
			elseif($res->status != 200) return $res;
			}

		// if firmID is given
		if(!empty($opt['firmID'])){

			// check firm
			$res = base::get_firm([
				'firmID'	=> $opt['firmID'],
				]);

			// on error
			if($res->status == 404) return self::response(406);
			elseif($res->status != 200) return $res;
			}

		// if countryID is given
		if(!empty($opt['countryID'])){

			// check country
			$res = base::get_country([
				'countryID'	=> $opt['countryID'],
				]);

			// on error
			if($res->status == 404) return self::response(406);
			elseif($res->status != 200) return $res;
			}

		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_domain', [$entry->appID, $entry->firmID, $entry->countryID, $entry->status, $entry->domainID]);

		// on error
		if($upd === false) return self::response(560);

		// completly unset lvl1 cache
		self::$lvl1_cache = [];

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire entries
			$redis->setTimeout('domain:by_domainID:'.$entry->domainID, 0);
			$redis->setTimeout('domain:by_fqdn:'.$entry->fqdn, 0);

			// reload adtargets of this domain
			$res = self::get_adtarget([
				'domainID'	=> $entry->domainID,
				]);

			// on success
			if($res->status == 200){

				// and expire cache entries
				foreach($res->data as $adtarget){
					$redis->setTimeout('adtarget:by_pageID:'.$adtarget->pageID, 0);
					$redis->setTimeout('adtarget:by_fqdnhash:'.$adtarget->fqdn.':'.$adtarget->hash, 0);
					}
				}

			// on error
			else{
				e::logtrigger('Error loading domainID '.$entry->domainID.' for associated adtargets: '.$res->status);
				}
			}

		// return success
		return self::response(204);
		}



	/* Object: adtarget (page) */
	public static function get_adtarget($req = []){

		// alternative
		$alt = h::eX($req, [
			'pageID'		=> '~1,65535/i',
			'domainID'		=> '~1,65535/i',
			'publisherID'	=> '~0,65535/i',
			'fqdn'			=> '~^(?:[a-z0-9\-\.]{1,174}\.|)[a-z0-9\-]{3,74}\.[a-z0-9]{2,5}$',
			'hash'			=> '~^[a-zA-Z0-9]{48}$|^$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: pageID
		if(isset($alt['pageID'])){

			// define cache key
			$cache_key = 'adtarget:by_pageID:'.$alt['pageID'];

			// check lvl1 cache
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

			// else search in DB
			else{

				// load entry
				$entry = self::pdo('s_adtarget', [$alt['pageID']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache entry in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return success
			return self::response(200, $entry);
			}

		// param order 2: fqdn and hash
		if(isset($alt['fqdn']) and isset($alt['hash'])){

			// define cache key
			$cache_key = 'adtarget:by_fqdnhash:'.$alt['fqdn'].':'.$alt['hash'];

			// init redis
			$redis = self::redis();

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// load entry
				$entry = $redis->get($cache_key);
				}

			// else search in DB
			else{

				// load entry
				$entry = self::pdo('s_adtarget_by_fqdn_hash', [$alt['fqdn'], $alt['hash']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache entry for pageID access in lvl1 cache
			self::$lvl1_cache['adtarget:by_pageID:'.$entry->pageID] = clone $entry;

			// return success
			return self::response(200, $entry);
			}

		// param order 3: domainID
		if(isset($alt['domainID'])){

			// load list
			$list = self::pdo('l_adtarget_by_domainID', [$alt['domainID']]);

			// on error
			if($list === false) return self::response(560);

			// return success
			return self::response(200, $list);
			}

		// param order 4: fqdn only
		if(isset($alt['fqdn'])){

			// load list
			$list = self::pdo('l_adtarget_by_fqdn', [$alt['fqdn']]);

			// on error
			if($list === false) return self::response(560);

			// return success
			return self::response(200, $list);
			}

		// param order 5: publisherID
		if(isset($alt['publisherID'])){

			// load list
			$list = self::pdo('l_adtarget_by_publisherID', [$alt['publisherID']]);

			// on error
			if($list === false) return self::response(560);

			// return success
			return self::response(200, $list);
			}

		// param order 6: no param (at all)
		if(empty($req)){

			// load list
			$list = self::pdo('l_adtarget');

			// on error
			if($list === false) return self::response(560);

			// return success
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need pageID or domainID or fqdn (and hash) or no parameter');
		}

	public static function create_adtarget($req = []){

		// mandatory
		$mand = h::eX($req, [
			'domainID'		=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'hash'			=> '~^[a-zA-Z0-9]{48}$',
			'publisherID'	=> '~0,65535/i',
			'status'		=> '~^(?:inherit|online|maintenance|archive|dev|ignore|usemp)$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// create hash if not given
		if(empty($opt['hash'])){

			// repeat creating hash
			while($opt['hash'] = h::rand_str(48, '', 'abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789')){

				// check entry
				$search = self::pdo('c_adtarget_hash', [$mand['domainID'], $opt['hash']]);

				// on error
				if($search === false) return self::response(560);
				elseif(!$search) break;
				}
			}

		// or try given hash
		else{

			// check entry
			$search = self::pdo('c_adtarget_hash', [$mand['domainID'], $opt['hash']]);

			// on error
			if($search === false) return self::response(560);
			elseif($search) return self::response(409);
			}


		// check domain
		$res = self::get_domain([
			'domainID'	=> $mand['domainID'],
			]);

		// on error
		if($res->status == 404) return self::response(406);
		elseif($res->status != 200) return $res;

		// default params
		$opt += ['publisherID'=>0, 'status'=>'inherit'];

		// create entry
		$pageID = self::pdo('i_adtarget', [$mand['domainID'], $opt['publisherID'], $opt['hash'], $opt['status']]);

		// on error
		if($pageID === false) return self::response(560);

		// return success
		return self::response(201, (object)['pageID'=>$pageID, 'hash'=>$opt['hash']]);
		}

	public static function update_adtarget($req = []){

		// mandatory
		$mand = h::eX($req, [
			'pageID'		=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'publisherID'	=> '~0,65535/i',
			'status'		=> '~^(?:inherit|online|maintenance|archive|dev|ignore|usemp)$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// check
		$res = self::get_adtarget([
			'pageID'	=> $mand['pageID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// if publisherID is given
		if(!empty($opt['publisherID'])){

			// check publisher
			$res = publisher::get_publisher([
				'publisherID'	=> $opt['publisherID'],
				]);

			// on error
			if($res->status == 404) return self::response(406);
			elseif($res->status != 200) return $res;
			}

		// define need to update
		$sql_update = false;

		// replace params
		foreach($opt as $k => $v){
			$sql_update = true;
			$entry->{$k} = $v;
			}

		// if there is something to update
		if($sql_update){

			// update
			$upd = self::pdo('u_adtarget', [$entry->publisherID, $entry->status, $entry->pageID]);

			// on error
			if($upd === false) return self::response(560);
			}

		// define cache keys
		$cache_key = 'adtarget:by_pageID:'.$entry->pageID;
		$cache_key_fqdn = 'adtarget:by_fqdnhash:'.$entry->fqdn.':'.$entry->hash;

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire entries
			$redis->setTimeout($cache_key, 0);
			$redis->setTimeout($cache_key_fqdn, 0);
			}

		// return success
		return self::response(204);
		}

	}
