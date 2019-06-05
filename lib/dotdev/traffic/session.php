<?php
/*****
 * Version 1.6.2019-02-07
**/
namespace dotdev\traffic;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \dotdev\cronjob;
use \dotdev\nexus\base as nexus_base;
use \dotdev\nexus\domain as nexus_domain;
use \dotdev\nexus\publisher as nexus_publisher;
use \dotdev\nexus\ipv4range as nexus_ipv4range;
use \dotdev\traffic\base as traffic_base;
use \dotdev\traffic\service as traffic_service;

class session {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO/Redis Config */
	protected static function pdo_config(){
		return ['mt_traffic', [

			// queries: session
			's_session'						=> 'SELECT * FROM `session` WHERE `persistID` = ? LIMIT 1',
			's_session_with_unique'			=> 'SELECT s.*,
													IF(u.persistID, 1, 0) as `unique`, u.hash, u.createTime as `hash_createTime`, u.device as `hash_device`
												FROM `session` s
												LEFT JOIN `session_unique` u ON u.persistID = s.persistID
												WHERE s.persistID = ?
												LIMIT 1
												',
			's_session_with_lastopen'		=> 'SELECT s.*,
													lo.apkID, lo.createTime as `last_openTime`, lo.apk_build as `last_apk_build`
												FROM `session` s
												LEFT JOIN `session_open` lo ON lo.persistID = s.persistID
												LEFT JOIN `session_open` lo2 ON lo2.persistID = lo.persistID AND lo2.createTime > lo.createTime
												WHERE s.persistID = ? AND lo2.persistID IS NULL
												LIMIT 1
												',
			's_session_with_uniqlink'		=> 'SELECT s.*,
													IF(l.persistID, 1, 0) as `link`, l.usID, l.ipv4, l.ipv6, l.hostname, l.referer,
													IF(u.persistID, 1, 0) as `unique`, u.hash, u.createTime as `hash_createTime`, u.device as `hash_device`
												FROM `session` s
												LEFT JOIN `session_link` l ON l.persistID = s.persistID
												LEFT JOIN `session_unique` u ON u.persistID = s.persistID
												WHERE s.persistID = ?
												LIMIT 1
												',
			's_session_with_all'			=> 'SELECT s.*,
													IF(l.persistID, 1, 0) as `link`, l.usID, l.ipv4, l.ipv6, l.hostname, l.referer,
													IF(u.persistID, 1, 0) as `unique`, u.hash, u.createTime as `hash_createTime`, u.device as `hash_device`,
													a.affiliate_key,
													d.useragent, d.mobile, d.device_osID, d.device_vendorID, d.device_browserID,
													o.createTime as `openTime`, o.apkID, o.apk_build,
													lo.createTime as `last_openTime`, lo.apk_build as `last_apk_build`,
													b.persistID as `blocked_persistID`, b.createTime as `blocked_createTime`, b.apkID as `blocked_apkID`, b.apk_build as `blocked_apk_build`, b.status as `blocked_status`, b.data as `blocked_data`
												FROM `session` s
												LEFT JOIN `session_link` l ON l.persistID = s.persistID
												LEFT JOIN `session_unique` u ON u.persistID = s.persistID
												LEFT JOIN `publisher_affiliate` a ON a.publisher_affiliateID = s.publisher_affiliateID
												LEFT JOIN `device` d ON d.deviceID = s.deviceID
												LEFT JOIN `session_open` o ON o.persistID = s.persistID
												LEFT JOIN `session_open` o2 ON o2.persistID = o.persistID AND o2.createTime < o.createTime
												LEFT JOIN `session_open` lo ON lo.persistID = s.persistID
												LEFT JOIN `session_open` lo2 ON lo2.persistID = lo.persistID AND lo2.createTime > lo.createTime
												LEFT JOIN `blocked_session` b ON b.new_persistID = s.persistID
												WHERE s.persistID = ? AND o2.persistID IS NULL AND lo2.persistID IS NULL
												LIMIT 1
												',

			'l_session_by_mobileID'			=> ['s_session', ['`persistID` = ?' => '`mobileID` = ?', 'LIMIT 1'=>'']],
			'l_session_with_unique_by_mobileID'		=> ['s_session_with_unique', ['`persistID` = ?' => '`mobileID` = ?', 'LIMIT 1'=>'']],
			'l_session_with_open_by_mobileID'		=> ['s_session_with_lastopen', ['`persistID` = ?' => '`mobileID` = ?', 'LIMIT 1'=>'']],
			'l_session_with_uniqlink_by_mobileID'	=> ['s_session_with_uniqlink', ['`persistID` = ?' => '`mobileID` = ?', 'LIMIT 1'=>'']],
			'l_session_with_all_by_mobileID'		=> ['s_session_with_all', ['`persistID` = ?' => '`mobileID` = ?', 'LIMIT 1'=>'']],

			'i_session'						=> 'INSERT INTO `session` (`persistID`,`createTime`,`domainID`,`pageID`,`publisherID`,`publisher_affiliateID`,`mobileID`,`operatorID`,`deviceID`,`countryID`) VALUES (?,?,?,?,?,?,?,?,?,?)',
			'u_session'						=> 'UPDATE `session` SET WHERE `persistID` = ?',
			'u_session_mobile_migration'	=> 'UPDATE `session` SET `mobileID` = ?, `operatorID` = ?, `countryID` = ? WHERE `mobileID` = ?',


			// queries: session_link
			's_session_link'				=> 'SELECT * FROM `session_link` WHERE `persistID` = ? LIMIT 1',
			'i_session_link'				=> 'INSERT INTO `session_link` (`persistID`,`usID`,`ipv4`,`ipv6`,`hostname`,`referer`) VALUES (?,?,?,?,?,?)',
			'u_session_link'				=> 'UPDATE `session_link` SET WHERE `persistID` = ?',


			// queries: session_unique
			's_session_unique'				=> 'SELECT * FROM `session_unique` WHERE `persistID` = ? LIMIT 1',
			'l_session_unique_by_hash'		=> 'SELECT * FROM `session_unique` WHERE `hash` = ? ORDER BY `createTime` ASC',
			'i_session_unique'				=> 'INSERT INTO `session_unique` (`persistID`,`hash`,`createTime`,`device`) VALUES (?,?,?,?)',
			'u_session_unique'				=> 'UPDATE `session_unique` SET WHERE `persistID` = ?',


			// queries: session_pageview
			'l_session_pageview'			=> 'SELECT `pageviewID`,`createTime`,`data` FROM `session_pageview` WHERE `persistID` = ? ORDER BY `createTime` ASC, `pageviewID` ASC',
			'i_session_pageview'			=> 'INSERT INTO `session_pageview` (`createTime`,`persistID`,`data`) VALUES (?,?,?)',


			// queries: session_open
			's_session_open'				=> 'SELECT * FROM `session_open` WHERE `openID` = ? LIMIT 1',
			's_session_open_last'			=> 'SELECT * FROM `session_open` WHERE `persistID` = ? ORDER BY `createTime` DESC LIMIT 1',
			'l_session_open_by_persistID'	=> 'SELECT * FROM `session_open` WHERE `persistID` = ? ORDER BY `createTime` ASC',
			'i_session_open'				=> 'INSERT INTO `session_open` (`createTime`,`persistID`,`apkID`,`apk_build`,`livetime`) VALUES (?,?,?,?,?)',
			'u_session_open_livetime'		=> 'UPDATE `session_open` SET `livetime` = ? WHERE `openID` = ?',


			// queries: blocked_session
			'l_blocked_session'				=> 'SELECT b.*, s.createTime as `new_createTime`, s.pageID as `new_pageID`, s.mobileID as `new_mobileID`, s.operatorID as `new_operatorID`
												FROM `blocked_session` b
												LEFT JOIN `session` s ON s.persistID = b.new_persistID
												WHERE b.persistID = ?
												',
			'i_blocked_session'				=> 'INSERT INTO `blocked_session` (`createTime`,`persistID`,`new_persistID`,`apkID`,`apk_build`,`status`,`data`) VALUES (?,?,?,?,?,?,?)',


			// queries: click
			's_click'						=> 'SELECT c.clickID, c.createTime, p.request
												FROM `click` c
												LEFT JOIN `click_pubdata` p ON p.clickID = c.clickID
												WHERE c.clickID = ?
												LIMIT 1
												',
			's_click_last'					=> ['s_click', ['c.clickID = ?' => 'c.persistID = ? ORDER BY c.createTime DESC']],
			'l_click'						=> ['s_click', ['c.clickID = ?' => 'c.persistID = ?', 'LIMIT 1'=>'']],
			'i_click'						=> 'INSERT INTO `click` (`createTime`,`persistID`,`referer_domainID`) VALUES (?,?,?)',
			'i_click_pubdata'				=> 'INSERT INTO `click_pubdata` (`clickID`,`request`) VALUES (?,?)',


			// queries: blocked_click
			'i_blocked_click'				=> 'INSERT INTO `blocked_click` (`createTime`,`persistID`,`pageID`,`publisherID`,`referer_domainID`,`status`,`request`) VALUES (?,?,?,?,?,?,?)',


			// queries: referer_fqdn
			's_referer_fqdn'				=> 'SELECT * FROM `referer_fqdn` WHERE `fqdn` = ? LIMIT 1',
			'i_referer_fqdn'				=> 'INSERT INTO `referer_fqdn` (`fqdn`) VALUES (?)',

			]];
		}

	protected static function redis_config(){
		return 'mt_traffic';
		}


	/* lvl1 cache */
	protected static $lvl1_cache = [];


	/* Object: session */
	public static function get_session($req = []){

		// alternativ
		$alt = h::eX($req, [
			'persistID'		=> '~1,18446744073709551615/i',
			'mobileID'		=> '~1,4294967295/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'with_data'		=> '~^(?:unique|uniqlink|lastopen|all|with_unique|alldata)$', // DEPRECATED: with_unique, alldata
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define default
		$opt += [
			'with_data'		=> '',
			];

		// DEPRECATED: rename old option
		if($opt['with_data'] == 'with_unique') $opt['with_data'] = 'unique';
		if($opt['with_data'] == 'alldata') $opt['with_data'] = 'all';


		// param order 1: persistID
		if(isset($alt['persistID'])){

			// load entry
			$entry = self::pdo($opt['with_data'] ? 's_session_with_'.$opt['with_data'] : 's_session', [$alt['persistID']]);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return result
			return self::response(200, $entry);
			}

		// param order 2: mobileID
		if(isset($alt['mobileID'])){

			// load list
			$list = self::pdo($opt['with_data'] ? 'l_session_with_'.$opt['with_data'].'_by_mobileID' : 'l_session_by_mobileID', [$alt['mobileID']]);

			// on error
			if($list === false) return self::response(560);

			// return result
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need persistID or mobileID param');
		}

	public static function create_session($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'				=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($req, [

			// normal data
			'createTime'			=> '~Y-m-d H:i:s/d',
			'domainID'				=> '~0,65535/i',
			'pageID'				=> '~0,65535/i',
			'publisherID'			=> '~0,65535/i',
			'publisher_affiliateID' => '~0,16777215/i',
			'mobileID'				=> '~0,4294967295/i',
			'operatorID'			=> '~0,65535/i',
			'deviceID'				=> '~0,16777215/i',
			'countryID'				=> '~0,255/i',

			// options
			'ipv4_range_detection'	=> '~/b',
			'delayed_parsing'		=> '~/b',

			// session link data
			'usID'					=> '~1,24/s',
			'ipv4'					=> '~^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$', // close enough check
			'ipv6'					=> '~^[a-z0-9:]{4,39}$',
			'hostname'				=> '~1,80/s',
			'referer'				=> '~/s',

			// session unique data
			'unique_hash'			=> '~^[a-z0-9]{40}$',
			'unique_device'			=> '~1,255/s',

			// parseable data
			'useragent'				=> '~1,255/s',
			'publisher_uncover_key'	=> '~^[a-zA-Z0-9\-\_]{1,64}$',
			'publisher_uncover_name'=> '~^[a-zA-Z0-9\-\_]{1,120}$',
			'publisher_affiliate_key'=> '~^[a-zA-Z0-9\-\_\:\.]{1,255}$',

			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// remove parseable data, if associated identifier already given
		if(!empty($opt['deviceID'])) unset($opt['useragent']);
		if(!empty($opt['publisher_affiliateID']) or empty($opt['publisherID'])) unset($opt['publisher_affiliate_key']);
		if(!empty($opt['operatorID']) or !empty($opt['countryID'])) unset($opt['ipv4_range_detection']);

		// set default
		$opt += [
			'createTime'			=> h::dtstr('now'),
			'domainID'				=> 0,
			'pageID'				=> 0,
			'publisherID'			=> 0,
			'publisher_affiliateID' => 0,
			'mobileID'				=> 0,
			'operatorID'			=> 0,
			'deviceID'				=> 0,
			'countryID'				=> 0,
			'delayed_parsing'		=> false,
			];

		// load redis
		$redis = self::redis();

		// lock concurrent process
		$lock_key = 'ins_session:'.$mand['persistID'];
		$lock_status = redis::lock_process($redis, $lock_key, ['timeout_ms'=>4000, 'retry_ms'=>400]);

		// if we get this status, the creation was already done
		if($lock_status == 200) return self::response(409);

		// if pageID is given, but not domainID
		if($opt['pageID'] and !$opt['domainID']){

			// load adtarget
			$res = nexus_domain::get_adtarget([
				'pageID'	=> $opt['pageID'],
				]);

			// on success
			if($res->status == 200){

				// take domainID
				$opt['domainID'] = $res->data->domainID;
				}
			}

		// if operatorID is given, but not countryID
		if($opt['operatorID'] and !$opt['countryID']){

			// load operator
			$res = nexus_base::get_operator([
				'operatorID'	=> $opt['operatorID'],
				]);

			// on success
			if($res->status == 200){

				// take countryID
				$opt['countryID'] = $res->data->countryID;
				}
			}

		// define parse param
		$parse_param = [];

		// if uncover_key or affiliate_key are given
		if(isset($opt['publisher_uncover_key']) or isset($opt['publisher_affiliate_key'])){

			// add as parseable param
			$parse_param += [
				'publisherID'				=> $opt['publisherID'],
				'publisher_uncover_key'		=> $opt['publisher_uncover_key'] ?? null,
				'publisher_uncover_name'	=> $opt['publisher_uncover_name'] ?? null,
				'publisher_affiliate_key'	=> $opt['publisher_affiliate_key'] ?? null,
				];
			}

		// if ipv4_range_detection param are given, add as parseable param
		if(!empty($opt['ipv4_range_detection']) and isset($opt['ipv4'])){

			// add as parseable param
			$parse_param += [
				'ipv4'						=> $opt['ipv4'],
				'ipv4_range_detection'		=> true
				];
			}

		// if useragent param is given, add as parseable param
		if(isset($opt['useragent'])) $parse_param['useragent'] = $opt['useragent'];

		// if hostname param and parseable param are given, add as parseable param (avoid parsing if only hostname is given)
		if(isset($opt['hostname']) and $parse_param) $parse_param['hostname'] = $opt['hostname'];

		// if parseable param are defined
		if(!$opt['delayed_parsing'] and $parse_param){

			// parse identifier
			$res = self::parse_special_identifier([
				'persistID'	=> $mand['persistID'],
				] + $parse_param);

			// on error
			if($res->status != 200) return $res;

			// for each identifier
			foreach($res->data as $key => $val){

				// for uncover_publisherID
				if($key === 'uncover_publisherID') $key = 'publisherID';

				// if there is value, overwrite
				if($val !== null) $opt[$key] = $val;
				}
			}

		// create entry
		$inserted = self::pdo('i_session', [$mand['persistID'], $opt['createTime'], $opt['domainID'], $opt['pageID'], $opt['publisherID'], $opt['publisher_affiliateID'], $opt['mobileID'], $opt['operatorID'], $opt['deviceID'], $opt['countryID']]);

		// unlock status (and cache result for 2 minutes)
		if($lock_status == 100) $lock_status = redis::unlock_process($redis, $lock_key, ['ttl' => $inserted ? 120 : 0]);

		// if insert throws an error
		if($inserted === false) return self::response(560);

		// on insert success and given session_link data
		if(isset($opt['usID']) or isset($opt['ipv4']) or isset($opt['ipv6']) or isset($opt['hostname']) or isset($opt['referer'])){

			// add session link data (no need to check result)
			$res = self::create_session_link([
				'persistID'		=> $mand['persistID'],
				'usID'			=> $opt['usID'] ?? null,
				'ipv4'			=> $opt['ipv4'] ?? null,
				'ipv6'			=> $opt['ipv6'] ?? null,
				'hostname'		=> $opt['hostname'] ?? null,
				'referer'		=> $opt['referer'] ?? null,
				]);
			}

		// on insert success and given session unique data
		if(isset($opt['unique_hash'])){

			// add session unique data (no need to check result)
			$res = self::create_session_unique([
				'persistID'		=> $mand['persistID'],
				'hash'			=> $opt['unique_hash'],
				'createTime'	=> $opt['createTime'],
				'device'		=> $opt['unique_device'] ?? null,
				]);
			}

		// if parseable data should be parsed later with redisjob
		if($opt['delayed_parsing'] and $parse_param){

			// add redisjob to update critical session data later
			$res = self::delayed_update_session([
				'redisjob_lvl'	=> 3,
				'persistID'		=> $mand['persistID'],
				] + $parse_param);
			}

		// return success
		return self::response(201, (object)['persistID'=>$mand['persistID']]);
		}

	public static function update_session($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'				=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($req, [

			// normal data
			'createTime'			=> '~Y-m-d H:i:s/d',
			'domainID'				=> '~0,65535/i',
			'pageID'				=> '~0,65535/i',
			'publisherID'			=> '~0,65535/i',
			'publisher_affiliateID' => '~0,16777215/i',
			'mobileID'				=> '~0,4294967295/i',
			'operatorID'			=> '~0,65535/i',
			'deviceID'				=> '~0,16777215/i',
			'countryID'				=> '~0,255/i',

			// options
			'ipv4_range_detection'	=> '~/b',

			// session link data
			'usID'					=> '~1,24/s',
			'ipv4'					=> '~^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$', // close enough check
			'ipv6'					=> '~^[a-z0-9:]{4,39}$',
			'hostname'				=> '~1,80/s',
			'referer'				=> '~/s',

			// session unique data
			'unique_hash'			=> '~^[a-z0-9]{40}$',
			'unique_device'			=> '~1,255/s',

			// parseable data
			'useragent'				=> '~1,255/s',
			'publisher_uncover_key'	=> '~^[a-zA-Z0-9\-\_]{1,64}$',
			'publisher_uncover_name'=> '~^[a-zA-Z0-9\-\_]{1,120}$',
			'publisher_affiliate_key'=> '~^[a-zA-Z0-9\-\_\:\.]{1,255}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// remove parseable data, if associated identifier is already given
		if(isset($opt['deviceID'])) unset($opt['useragent']);
		if(isset($opt['publisher_affiliateID'])) unset($opt['publisher_affiliate_key']);

		// if no publisherID param is given
		if(!isset($opt['publisherID'])){

			// remove associated parseable data
			unset($opt['publisher_uncover_key']);
			unset($opt['publisher_uncover_name']);
			unset($opt['publisher_affiliate_key']);
			}


		// define session update range
		$with_data = null;
		if(isset($opt['unique_hash']) or isset($opt['unique_device'])) $with_data = 'unique';
		if(isset($opt['usID']) or isset($opt['ipv4']) or isset($opt['ipv6']) or isset($opt['hostname']) or isset($opt['referer'])) $with_data = 'uniqlink';

		// load entry
		$res = self::get_session([
			'persistID'		=> $mand['persistID'],
			'with_data'		=> $with_data,
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;


		// if mobileID is already set and no mobileID param given
		if($entry->mobileID and !isset($opt['mobileID']) and (isset($opt['countryID']) or isset($opt['operatorID']))){

			// these keys are not allowed
			unset($opt['countryID']);
			unset($opt['operatorID']);
			unset($opt['ipv4_range_detection']);
			}

		// if pageID is given, but not domainID
		if(!empty($opt['pageID']) and empty($opt['domainID'])){

			// load adtarget
			$res = nexus_domain::get_adtarget([
				'pageID'	=> $opt['pageID'],
				]);

			// on success
			if($res->status == 200){

				// take domainID
				$opt['domainID'] = $res->data->domainID;
				}
			}

		// if operatorID is given, but not countryID
		if(!empty($opt['operatorID']) and empty($opt['countryID'])){

			// load operator
			$res = nexus_base::get_operator([
				'operatorID'	=> $opt['operatorID'],
				]);

			// on success
			if($res->status == 200){

				// take countryID
				$opt['countryID'] = $res->data->countryID;
				}
			}


		// define parse param
		$parse_param = [];

		// if uncover_key or affiliate_key are given
		if(isset($opt['publisher_uncover_key']) or isset($opt['publisher_affiliate_key'])){

			// add as parseable param
			$parse_param += [
				'publisherID'				=> $opt['publisherID'],
				'publisher_uncover_key'		=> $opt['publisher_uncover_key'] ?? null,
				'publisher_uncover_name'	=> $opt['publisher_uncover_name'] ?? null,
				'publisher_affiliate_key'	=> $opt['publisher_affiliate_key'] ?? null,
				];
			}

		// if ipv4_range_detection param are given, add as parseable param
		if(!empty($opt['ipv4_range_detection']) and isset($opt['ipv4'])) $parse_param['ipv4'] = $opt['ipv4'];

		// if useragent param is given, add as parseable param
		if(isset($opt['useragent'])) $parse_param['useragent'] = $opt['useragent'];

		// if hostname param and parseable param are given, add as parseable param (avoid parsing if only hostname is given)
		if(isset($opt['hostname']) and $parse_param) $parse_param['hostname'] = $opt['hostname'];

		// if parse param are defined
		if($parse_param){

			// parse identifier
			$res = self::parse_special_identifier([
				'persistID'					=> $mand['persistID'],
				] + $parse_param);

			// on error
			if($res->status != 200) return $res;

			// for each identifier
			foreach($res->data as $key => $val){

				// for uncover_publisherID
				if($key === 'uncover_publisherID') $key = 'publisherID';

				// if there is value, overwrite
				if($val !== null) $opt[$key] = $val;
				}
			}


		// define update/create param
		$session_update = [];
		$link_update = [];
		$unique_update = [];

		// for all given updateable keys
		foreach(['createTime','domainID','pageID','publisherID','publisher_affiliateID','mobileID','operatorID','deviceID','countryID'] as $key){

			// if value is different
			if(isset($opt[$key]) and $opt[$key] != $entry->{$key}){

				// add key/value to update param
				$session_update[$key] = $opt[$key];
				}
			}

		// for all given updateable keys
		foreach(['usID','ipv4','ipv6','hostname','referer'] as $key){

			// if value is different
			if(isset($opt[$key]) and $opt[$key] != $entry->{$key}){

				// add key/value to update param
				$link_update[$key] = $opt[$key];
				}
			}

		// for all given updateable keys
		foreach(['unique_hash'=>'hash','unique_device'=>'hash_device'] as $opt_key => $entry_key){

			// if value is different
			if(isset($opt[$opt_key]) and $opt[$opt_key] != $entry->{$entry_key}){

				// add key/value to update param
				$unique_update[$entry_key] = $opt[$opt_key];
				}
			}


		// if session needs to be updated
		if($session_update){

			// get update query
			$query = self::pdo_extract('u_session', ['SET' => 'SET `'.implode('` = ?, `', array_keys($session_update)).'` = ?']);

			// update
			$upd = self::pdo($query, array_merge(array_values($session_update), [$entry->persistID]));

			// on error
			if($upd === false) return self::response(560);
			}

		// if session_link needs to be updated
		if($link_update){

			// if session link already exists
			if($entry->link){

				// update session link data
				$res = self::update_session_link([
					'persistID'		=> $mand['persistID'],
					] + $link_update);
				}

			// else
			else {

				// create session link data
				$res = self::create_session_link([
					'persistID'		=> $mand['persistID'],
					] + $link_update);
				}

			// on unexpected error
			if(!in_array($res->status, [201, 204])) return self::response(570, $res);
			}

		// if session_unique needs to be updated
		if($unique_update){

			// if session unique already exists
			if($entry->unique){

				// update session unique data
				$res = self::update_session_unique([
					'persistID'		=> $mand['persistID'],
					] + $unique_update);
				}

			// else
			else {

				// create session unique data
				$res = self::create_session_unique([
					'persistID'		=> $mand['persistID'],
					] + $unique_update);
				}

			// on unexpected error
			if(!in_array($res->status, [201, 204])) return self::response(570, $res);
			}

		// return success
		return self::response(204);
		}


	/* Object: session_link */
	public static function create_session_link($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'			=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'usID'				=> '~1,24/s',
			'ipv4'				=> '~^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$', // close enough check
			'ipv6'				=> '~^[a-z0-9:]{4,39}$',
			'hostname'			=> '~1,80/s',
			'referer'			=> '~/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// set default
		$opt += [
			'usID'				=> '',
			'ipv4'				=> '',
			'ipv6'				=> '',
			'hostname'			=> '',
			'referer'			=> '',
			];

		// create entry
		$ins = self::pdo('i_session_link', [$mand['persistID'], $opt['usID'], $opt['ipv4'], $opt['ipv6'], $opt['hostname'], $opt['referer']]);

		// on error
		if($ins === false) return self::response(560);

		// return success
		return self::response(201, (object)['persistID'=>$mand['persistID']]);
		}

	public static function update_session_link($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'			=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'		=> '~Y-m-d H:i:s/d',
			'usID'				=> '~1,24/s',
			'ipv4'				=> '~^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$', // close enough check
			'ipv6'				=> '~^[a-z0-9:]{4,39}$',
			'hostname'			=> '~1,80/s',
			'referer'			=> '~/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$entry = self::pdo('s_session_link', [$mand['persistID']]);

		// on error
		if(!$entry) return self::response($entry === false ? 560 : 404);

		// define update param
		$update = [];

		// for all given updateable keys
		foreach($opt as $k => $v){

			// if value is different
			if($entry->{$k} != $v){

				// add key/value to update param
				$update[$k] = $v;
				}
			}

		// if something needs to be updated
		if($update){

			// get update query
			$query = self::pdo_extract('u_session_link', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

			// update
			$upd = self::pdo($query, array_merge(array_values($update), [$entry->persistID]));

			// on error
			if($upd === false) return self::response(560);
			}

		// return success
		return self::response(204);
		}


	/* Object: session_unique */
	public static function get_session_unique($req = []){

		// alternative
		$alt = h::eX($req, [
			'persistID'	=> '~1,18446744073709551615/i',
			'hash'		=> '~^[a-z0-9]{40}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: persistID
		if(isset($alt['persistID'])){

			// define cache key
			$cache_key = 'session:unique:'.$alt['persistID'];

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return entry
				return self::response(200, clone self::$lvl1_cache[$cache_key]);
				}

			// define entry
			$entry = null;

			// init redis
			$redis = self::redis();

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// load entry
				$entry = $redis->get($cache_key);
				}

			// if entry is not loaded before
			if(!$entry){

				// seach in DB
				$entry = self::pdo('s_session_unique', [$alt['persistID']]);

				// on error or not found
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache entry in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: hash
		if(isset($alt['hash'])){

			// load entry
			$list = self::pdo('l_session_unique_by_hash', [$alt['hash']]);

			// on error
			if($list === false) return self::response(560);

			// return result
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need persistID or hash param');
		}

	public static function create_session_unique($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'		=> '~1,18446744073709551615/i',
			'hash'			=> '~^[a-z0-9]{40}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'	=> '~Y-m-d H:i:s/d',
			'device'		=> '~1,255/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define entry
		$entry = (object)[
			'persistID'		=> $mand['persistID'],
			'hash'			=> $mand['hash'],
			'createTime'	=> $opt['createTime'] ?? h::dtstr('now'),
			'device'		=> $opt['device'] ?? null,
			];

		// load session_unique with persistID
		$res = self::get_session_unique([
			'persistID'		=> $entry->persistID,
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return $res;

		// if already a entry exists, return conflict
		if($res->status == 200) return self::response(409);

		// create entry
		$ins = self::pdo('i_session_unique', [$entry->persistID, $entry->hash, $entry->createTime, $entry->device]);

		// on error
		if($ins === false) return self::response(560);

		// define cache key
		$cache_key = 'session:unique:'.$entry->persistID;

		// init redis
		$redis = self::redis();

		// if redis accessable
		if($redis){

			// cache entry
			$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
			}

		// cache entry in lvl1 cache
		self::$lvl1_cache[$cache_key] = clone $entry;

		// return success
		return self::response(201, (object)['persistID'=>$entry->persistID, 'hash'=>$entry->hash]);
		}

	public static function update_session_unique($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'		=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'hash'			=> '~^[a-z0-9]{40}$',
			'device'		=> '~1,255/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_session_unique([
			'persistID'		=> $mand['persistID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// define update param
		$update = [];

		// for all given updateable keys
		foreach($opt as $k => $v){

			// if value is different
			if($entry->{$k} != $v){

				// overwrite entry value
				$entry->{$k} = $v;

				// add key/value to update param
				$update[$k] = $v;
				}
			}

		// if something needs to be updated
		if($update){

			// get update query
			$query = self::pdo_extract('u_session_unique', ['SET' => 'SET `'.implode('` = ?, `', array_keys($update)).'` = ?']);

			// update
			$upd = self::pdo($query, array_merge(array_values($update), [$entry->persistID]));

			// on error
			if($upd === false) return self::response(560);

			// define cache key
			$cache_key = 'session:unique:'.$entry->persistID;

			// init redis
			$redis = self::redis();

			// if redis accessable
			if($redis){

				// cache entry
				$redis->set($cache_key, $entry, ['ex'=>21600]); // 6 hours
				}

			// cache entry in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;
			}

		// return success
		return self::response(204);
		}


	/* Object: session_pageview */
	public static function get_session_pageview($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'	=> '~1,18446744073709551615/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$list = self::pdo('l_session_pageview', [$mand['persistID']]);

		// on error
		if($list === false) return self::response(560);

		// foreach entry
		foreach($list as $entry){

			// decode data
			$entry->data = ($entry->data and $entry->data[0] == '{') ? json_decode($entry->data) : null;
			}

		// return success
		return self::response(200, $list);
		}

	public static function create_session_pageview($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'			=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'		=> '~Y-m-d H:i:s/d',
			'data'				=> '~/c',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// set default
		$opt += [
			'createTime'		=> h::dtstr('now'),
			'data'				=> null,
			];

		// convert data
		$opt['data'] = $opt['data'] ? json_encode($opt['data']) : null;

		// create entry
		$ins = self::pdo('i_session_pageview', [$opt['createTime'], $mand['persistID'], $opt['data']]);

		// on error
		if($ins === false) return self::response(560);

		// return success
		return self::response(201, (object)['persistID'=>$mand['persistID']]);
		}


	/* Object: session_open */
	public static function get_session_open($req = []){

		// alternative
		$alt = h::eX($req, [
			'openID'	=> '~1,18446744073709551615/i',
			'persistID'	=> '~1,18446744073709551615/i',
			'last'		=> '~/b',
			], $error, true);

		// on error
		if(isset($alt['last']) and !$alt['last']) $error[] = 'last';
		if($error) return self::response(400, $error);

		// param order 1: openID
		if(isset($alt['openID'])){

			// seach in DB
			$entry = self::pdo('s_session_open', [$alt['openID']]);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// param order 1: persistID + last
		if(isset($alt['persistID']) and isset($alt['last'])){

			// define cache key
			$cache_key = 'session:open:lastof_persistID:'.$alt['persistID'];

			// check lvl1 cache
			if(isset(self::$lvl1_cache[$cache_key])){

				// return entry
				return self::response(200, clone self::$lvl1_cache[$cache_key]);
				}

			// define entry
			$entry = null;

			// init redis
			$redis = self::redis();

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// load entry
				$entry = $redis->get($cache_key);
				}

			// if entry is not loaded before
			if(!$entry){

				// seach in DB
				$entry = self::pdo('s_session_open_last', [$alt['persistID']]);

				// on error or not found
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// cache entry in lvl1 cache
			self::$lvl1_cache[$cache_key] = clone $entry;

			// return entry
			return self::response(200, $entry);
			}

		// param order 3: persistID
		if(isset($alt['persistID'])){

			// load entry
			$list = self::pdo('l_session_open_by_persistID', [$alt['persistID']]);

			// on error
			if($list === false) return self::response(560);

			// return result
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need openID or persistID (+last) param');
		}

	public static function create_session_open($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'		=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'	=> '~Y-m-d H:i:s/d',
			'apkID'			=> '~0,255/i',
			'apk_build'		=> '~0,16777215/i',
			'livetime'		=> '~0,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define entry
		$entry = (object)[
			'openID'		=> null,
			'createTime'	=> $opt['createTime'] ?? h::dtstr('now'),
			'persistID'		=> $mand['persistID'],
			'apkID'			=> $opt['apkID'] ?? 0,
			'apk_build'		=> $opt['apk_build'] ?? 0,
			'livetime'		=> $opt['livetime'] ?? 0,
			];

		// create entry
		$entry->openID = self::pdo('i_session_open', [$entry->createTime, $entry->persistID, $entry->apkID, $entry->apk_build, $entry->livetime]);

		// on error
		if($entry->openID === false) return self::response(560);

		// define cache key
		$cache_key = 'session:open:lastof_persistID:'.$entry->persistID;

		// init redis
		$redis = self::redis();

		// if redis accessable
		if($redis){

			// cache entry
			$redis->set($cache_key, $entry, ['ex'=>86400]); // 24 hours
			}

		// cache entry in lvl1 cache
		self::$lvl1_cache[$cache_key] = clone $entry;

		// return success
		return self::response(201, (object)['openID'=>$entry->openID]);
		}

	public static function update_session_open($req = []){

		// alternativ
		$alt = h::eX($req, [
			'openID'		=> '~1,18446744073709551615/i',
			'persistID'		=> '~1,18446744073709551615/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'livetime_to'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// param
		$param = h::eX($req, [
			'livetime'		=> '~0,4294967295/i',
			], $error, true);


		// on error
		if($error) return self::response(400, $error);
		if(count($alt) != 1) return self::response(400, 'Need openID or persistID param');


		// load entry
		$res = self::get_session_open([
			'openID'	=> $alt['openID'] ?? null,
			'persistID'	=> $alt['persistID'] ?? null,
			'last'		=> isset($alt['persistID']) ? true : null,
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// if livetime no given
		if(isset($opt['livetime_to']) and !isset($param['livetime'])){

			// calc livetime (taking longest live of previous or calculated livetime)
			$param['livetime'] = max($entry->lifetime, h::dtstr('now', 'U') - h::dtstr($entry->createTime, 'U'));
			}

		// if livetime is given and different
		if(isset($param['livetime']) and $param['livetime'] != $entry->livetime){

			// check invalid livetime, return bad request
			if($param['livetime'] < 0 or $param['livetime'] > 4294967295) return self::response(400, ['livetime_to']);

			// take livetime
			$entry->livetime = $param['livetime'];

			// update
			$upd = self::pdo('u_session_open_livetime', [$entry->livetime, $entry->openID]);

			// on error
			if($upd === false) return self::response(560);
			}

		// define cache key
		$cache_key = 'session:open:lastof_persistID:'.$entry->persistID;

		// init redis
		$redis = self::redis();

		// if redis accessable
		if($redis){

			// cache entry
			$redis->set($cache_key, $entry, ['ex'=>86400]); // 24 hours
			}

		// cache entry in lvl1 cache
		self::$lvl1_cache[$cache_key] = clone $entry;

		// return success
		return self::response(204);
		}


	/* Object: blocked_session */
	public static function get_blocked_session($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'		=> '~1,18446744073709551615/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load list
		$list = self::pdo('l_blocked_session', [$mand['persistID']]);

		// on error
		if($list === false) return self::response(560);

		// return result
		return self::response(200, $list);
		}

	public static function create_blocked_session($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'		=> '~1,18446744073709551615/i',
			'status'		=> '~1,255/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'	=> '~Y-m-d H:i:s/d',
			'new_persistID'	=> '~0,18446744073709551615/i',
			'apkID'			=> '~0,255/i',
			'apk_build'		=> '~0,16777215/i',
			'data'			=> '~/c',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// set default
		$opt += [
			'createTime'	=> h::dtstr('now'),
			'new_persistID'	=> 0,
			'apkID'			=> 0,
			'apk_build'		=> 0,
			'data'			=> null,
			];

		// translate status to log error if status does not exist
		$status_text = traffic_service::_translate_to('bs_status_name', $mand['status'], true);

		// if data is set, convert to json
		$opt['data'] = $opt['data'] ? json_encode($opt['data']) : '';

		// create entry
		$blockID = self::pdo('i_blocked_session', [$opt['createTime'], $mand['persistID'], $opt['new_persistID'], $opt['apkID'], $opt['apk_build'], $mand['status'], $opt['data']]);

		// on error
		if($blockID === false) return self::response(560);

		// return success
		return self::response(201, (object)['blockID'=>$blockID]);
		}


	/* Object: click */
	public static function get_click($req = []){

		// alternativ
		$alt = h::eX($req, [
			'clickID'	=> '~1,18446744073709551615/i',
			'persistID'	=> '~1,18446744073709551615/i',
			'last_only'	=> '~/b',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'parse_data'=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// expand message entries
		$expand = function($list) use ($opt){

			// skip processing, if parse_data is not set
			if(empty($opt['parse_data'])) return $list;

			// define if single entry
			$single = !is_array($list);

			// convert single entry to list
			if($single) $list = [$list];

			// for each entry
			foreach($list as $entry){

				// parse data
				$entry->request = $entry->request ? json_decode($entry->request, true) : [];
				if(!is_array($entry->request)) $entry->request = [];
				}

			// return result as single entry or list
			return $single ? reset($list) : $list;
			};


		// param order 1: clickID
		if(isset($alt['clickID'])){

			// load entry
			$entry = self::pdo('s_click', $alt['clickID']);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return result
			return self::response(200, $expand($entry));
			}

		// param order 2: persistID + last
		if(isset($alt['persistID']) and !empty($alt['last_only'])){

			// load entry
			$entry = self::pdo('s_click_last', $alt['persistID']);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return result
			return self::response(200, $expand($entry));
			}

		// param order 3: persistID
		if(isset($alt['persistID'])){

			// load entry
			$list = self::pdo('l_click', [$alt['persistID']]);

			// on error
			if($list === false) return self::response(560);

			// return success
			return self::response(200, $expand($list));
			}

		// other request param invalid
		return self::response(400, 'need clickID or persistID (+last_only) param');
		}

	public static function create_click($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'			=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'		=> '~Y-m-d H:i:s/d',
			'referer_domainID'	=> '~0,16777215/i',
			'referer'			=> '~/s',
			'request'			=> '~/c',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// set default
		$opt += [
			'createTime'		=> h::dtstr('now'),
			'referer_domainID'	=> 0,
			'referer'			=> '',
			];

		// if we have no referer_domainID but a referer
		if(!$opt['referer_domainID'] and $opt['referer'] and preg_match('/^(?:(?:http\:|https\:|\:)?\/\/|)([äüöÄÜÖa-zA-Z0-9\-\.]{0,113}[äüöÄÜÖa-zA-Z0-9]+\.[a-z]{2,6})(?:\:[0-9]{1,5})?(\/.*|)$/', $opt['referer'], $m)){

			// init redis
			$redis = self::redis();

			// define cache key
			$cache_key = 'referer:by_fqdn:'.$m[1];

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// take entry
				$opt['referer_domainID'] = $redis->get($cache_key);
				}

			// if referer_domainID could not be loaded (with redis)
			if(!$opt['referer_domainID']){

				// search in DB
				$entry = self::pdo('s_referer_fqdn', [$m[1]]);

				// on error
				if($entry === false) self::response(560); // log only

				// if not found
				if(!$entry){

					// create entry
					$rfID = self::pdo('i_referer_fqdn', [$m[1]]);

					// on error
					if($rfID === false) self::response(560); // log only

					// and build created entry
					$entry = (object)['referer_domainID' => (int) $rfID]; // converts false|null to 0
					}

				// if redis accessable and entry has an referer
				if($redis and $entry->referer_domainID){

					// cache entry
					$redis->set($cache_key, $entry->referer_domainID, ['ex'=>21600, 'nx']); // 6 hours
					}

				// take referer_domainID
				$opt['referer_domainID'] = $entry->referer_domainID;
				}
			}


		// create entry
		$clickID = self::pdo('i_click', [$opt['createTime'], $mand['persistID'], $opt['referer_domainID']]);

		// on error
		if($clickID === false) return self::response(560);


		// if we request data
		if(!empty($opt['request'])){

			// create entry
			$ins = self::pdo('i_click_pubdata', [$clickID, json_encode($opt['request'])]);

			// on error
			if($ins === false) return self::response(560);
			}

		// return success
		return self::response(201, (object)['clickID'=>$clickID]);
		}


	/* Object: blocked_click */
	public static function create_blocked_click($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'			=> '~1,18446744073709551615/i',
			'pageID'			=> '~1,65535/i',
			'status'			=> '~1,255/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'		=> '~Y-m-d H:i:s/d',
			'publisherID'		=> '~0,65535/i',
			'referer_domainID'	=> '~0,16777215/i',
			'referer'			=> '~/s',
			'request'			=> '~/c',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// set default
		$opt += [
			'createTime'		=> h::dtstr('now'),
			'publisherID'		=> 0,
			'referer_domainID'	=> 0,
			];

		// translate status to log error if status does not exist
		$status_text = traffic_service::_translate_to('bc_status_name', $mand['status'], true);

		// if we have no referer_domainID but a referer
		if(!$opt['referer_domainID'] and isset($opt['referer']) and preg_match('/^(?:(?:http\:|https\:|\:)?\/\/|)([äüöÄÜÖa-zA-Z0-9\-\.]{0,113}[äüöÄÜÖa-zA-Z0-9]+\.[a-z]{2,6})(?:\:[0-9]{1,5})?(\/.*|)$/', $opt['referer'], $m)){

			// init redis
			$redis = self::redis();

			// define cache key
			$cache_key = 'referer:by_fqdn:'.$m[1];

			// if redis accessable and cache key exists
			if($redis and $redis->exists($cache_key)){

				// take referer_domainID
				$opt['referer_domainID'] = $redis->get($cache_key);
				}

			// else
			else{

				// search in DB
				$entry = self::pdo('s_referer_fqdn', [$m[1]]);

				// on error
				if($entry === false) self::response(560); // log only

				// if not found
				if(!$entry){

					// create entry
					$rfID = self::pdo('i_referer_fqdn', [$m[1]]);

					// on error
					if($rfID === false) self::response(560); // log only

					// and build created entry
					$entry = (object)['referer_domainID' => (int) $rfID]; // converts false|null to 0
					}

				// if redis accessable and entry has referer
				if($redis and $entry->referer_domainID){

					// cache entry
					$redis->set($cache_key, $entry->referer_domainID, ['ex'=>21600, 'nx']); // 6 hours
					}

				// take referer_domainID
				$opt['referer_domainID'] = $entry->referer_domainID;
				}
			}

		// create entry
		$blockID = self::pdo('i_blocked_click', [$opt['createTime'], $mand['persistID'], $mand['pageID'], $opt['publisherID'], $opt['referer_domainID'], $mand['status'], !empty($opt['request']) ? json_encode($opt['request']) : '']);

		// on error
		if($blockID === false) return self::response(560);

		// return success
		return self::response(201, (object)['blockID'=>$blockID]);
		}


	/* Helper */
	public static function parse_special_identifier($req = []){

		// optional
		$opt = h::eX($req, [

			// param for IPv4 range detection and hostname resolution
			'ipv4'					=> '~^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$',

			// param for device detection
			'useragent'				=> '~1,255/s',

			// param for publisher uncovering (needs publisherID)
			'publisher_uncover_key'	=> '~^[a-zA-Z0-9\-\_]{1,64}$',
			'publisher_uncover_name'=> '~^[a-zA-Z0-9\-\_]{1,120}$',

			// param for publisher affiliate association (needs publisherID)
			'publisher_affiliate_key'=> '~^[a-zA-Z0-9\-\_\:\.]{1,255}$',

			// already known results (prevents specific calculation for these results)
			'publisherID'			=> '~1,65535/i',
			'operatorID'			=> '~1,65535/i',
			'countryID'				=> '~1,65535/i',
			'hostname'				=> '~1,80/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define identifier
		$identifier = (object)[
			'operatorID'			=> $opt['operatorID'] ?? null,
			'countryID'				=> $opt['countryID'] ?? null,
			'hostname'				=> $opt['hostname'] ?? null,
			'deviceID'				=> null,
			'uncover_publisherID'	=> null,
			'publisher_affiliateID'	=> null,
			];

		// ipv4 range detection
		if(isset($opt['ipv4']) and !$identifier->operatorID){

			// search for matching ip4range
			$res = nexus_ipv4range::get_range([
				'ipv4'		=> $opt['ipv4'],
				]);

			// on success
			if($res->status == 200){

				// take range
				$range = $res->data;

				// for known keys
				foreach(['countryID','operatorID'] as $k){

					// set identifier, if value given
					if($range->{$k}) $identifier->{$k} = $range->{$k};
					}
				}
			}

		// operator country detection
		if($identifier->operatorID and !$identifier->countryID){

			// load operator
			$res = nexus_base::get_operator([
				'operatorID'	=> $identifier->operatorID,
				]);

			// on success
			if($res->status == 200){

				// take countryID
				$identifier->countryID = $res->data->countryID;
				}
			}

		// ipv4 hostname detection (TEMP: resolution temporary deactivated, gethostbyaddr is too slow)
		if(false and isset($opt['ipv4']) and !$identifier->hostname){

			// try to resolve hostname
			$hostname = gethostbyaddr($opt['ipv4']);

			// on success
			if($hostname){

				// take hostname
				$identifier->hostname = $hostname;
				}
			}

		// useragent device detection
		if(isset($opt['useragent'])){

			// search for device
			$res = traffic_base::get_device([
				'useragent' 	=> $opt['useragent'],
				]);

			// if found
			if($res->status == 200){

				// take deviceID
				$identifier->deviceID = $res->data->deviceID;
				}

			// if new
			elseif($res->status == 404){

				// create device
				$res = traffic_base::create_device([
					'useragent' 	=> $opt['useragent'],
					]);

				// if with success
				if($res->status == 201){

					// take deviceID
					$identifier->deviceID = $res->data->deviceID;
					}

				// if creation conflicts with concurrency process
				if($res->status == 409){

					// search for device
					$res = traffic_base::get_device([
						'useragent' 	=> $opt['useragent'],
						]);

					// if found
					if($res->status == 200){

						// take deviceID
						$identifier->deviceID = $res->data->deviceID;
						}
					}
				}
			}

		// publisher unconvering
		if(isset($opt['publisherID']) and isset($opt['publisher_uncover_key'])){

			// try to find publisher
			$res = nexus_publisher::get_publisher([
				'ownerID'		=> $opt['publisherID'],
				'uncover_key'	=> $opt['publisher_uncover_key'],
				]);

			// if found
			if($res->status == 200){

				// take publisherID
				$identifier->uncover_publisherID = $res->data->publisherID;
				}

			// if new
			elseif($res->status == 404){

				// create publisher
				$res = nexus_publisher::create_publisher([
					'name'			=> $opt['publisher_uncover_name'] ?? $opt['publisherID'].'/'.$opt['publisher_uncover_key'],
					'ownerID'		=> $opt['publisherID'],
					'uncover_key'	=> $opt['publisher_uncover_key'],
					'locked_insert'	=> true,
					]);

				// if with success
				if($res->status == 201){

					// take publisherID
					$identifier->uncover_publisherID = $res->data->publisherID;
					}

				// if creation conflicts with concurrency process
				if($res->status == 409){

					// try to find publisher
					$res = nexus_publisher::get_publisher([
						'ownerID'		=> $opt['publisherID'],
						'uncover_key'	=> $opt['publisher_uncover_key'],
						]);

					// if found
					if($res->status == 200){

						// take deviceID
						$identifier->uncover_publisherID = $res->data->publisherID;
						}
					}
				}
			}

		// publisher affiliate association
		if(isset($opt['publisherID']) and isset($opt['publisher_affiliate_key'])){

			// search for affiliate
			$res = traffic_base::get_publisher_affiliate([
				'publisherID' 	=> $identifier->uncover_publisherID ?: $opt['publisherID'],
				'affiliate_key' => $opt['publisher_affiliate_key'],
				]);

			// if found
			if($res->status == 200){

				// take affiliateID
				$identifier->publisher_affiliateID = $res->data->publisher_affiliateID;
				}

			// if new
			elseif($res->status == 404){

				// create affiliate
				$res = traffic_base::create_publisher_affiliate([
					'publisherID' 	=> $identifier->uncover_publisherID ?: $opt['publisherID'],
					'affiliate_key' => $opt['publisher_affiliate_key'],
					]);

				// if with success
				if($res->status == 201){

					// take affiliateID
					$identifier->publisher_affiliateID = $res->data->publisher_affiliateID;
					}

				// if creation conflicts with concurrency process
				if($res->status == 409){

					// search for affiliate
					$res = traffic_base::get_publisher_affiliate([
						'publisherID' 	=> $identifier->uncover_publisherID ?: $opt['publisherID'],
						'affiliate_key' => $opt['publisher_affiliate_key'],
						]);

					// if found
					if($res->status == 200){

						// take affiliateID
						$identifier->publisher_affiliateID = $res->data->publisher_affiliateID;
						}
					}
				}
			}

		// return success
		return self::response(200, $identifier);
		}

	public static function update_session_special_identifier($req = []){ // DEPRECATED

		// mandatory
		$mand = h::eX($req, [
			'persistID'				=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($req, [

			// param for IPv4 range detection and hostname resolution
			'ipv4'					=> '~^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$',

			// param for device detection
			'useragent'				=> '~1,255/s',

			// param for publisher uncovering (needs publisherID)
			'publisher_uncover_key'	=> '~^[a-zA-Z0-9\-\_]{1,64}$',
			'publisher_uncover_name'=> '~^[a-zA-Z0-9\-\_]{1,120}$',

			// param for publisher affiliate association (needs publisherID)
			'publisher_affiliate_key'=> '~^[a-zA-Z0-9\-\_\:\.]{1,255}$',

			// already known results
			'publisherID'			=> '~1,65535/i',
			'operatorID'			=> '~1,65535/i',
			'countryID'				=> '~1,65535/i',
			'hostname'				=> '~1,80/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// parse identifier
		$res = self::parse_special_identifier($opt);

		// on error
		if($res->status != 200) return $res;

		// take identifier
		$identifier = $res->data;

		// for specific keys
		foreach(['operatorID','countryID','hostname'] as $key){

			// if value was already given
			if(isset($opt[$key])){

				// avoid updating
				$identifier->{$key} = null;
				}
			}


		// if identifier gives at least one new value for session
		if($identifier->operatorID or $identifier->countryID or $identifier->uncover_publisherID or $identifier->publisher_affiliateID or $identifier->deviceID){

			// update session with given values (identifier values could be null, which means there are ignored)
			$res = self::update_session([
				'persistID'				=> $mand['persistID'],
				'operatorID'			=> $identifier->operatorID,
				'countryID'				=> $identifier->countryID,
				'publisherID'			=> $identifier->uncover_publisherID,
				'publisher_affiliateID'	=> $identifier->publisher_affiliateID,
				'deviceID'				=> $identifier->deviceID,
				]);

			// on error
			if($res->status != 204) return $res;
			}

		// if identifier gives a hostname
		if($identifier->hostname){

			// update session with given values
			$res = self::update_session_link([
				'persistID'				=> $mand['persistID'],
				'hostname'				=> $identifier->hostname,
				]);

			// on error
			if($res->status != 204) return $res;
			}

		// return success
		return self::response(204);
		}

	public static function migrate_mobile($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from_mobileID'	=> '~1,4294967295/i',
			'to_mobileID'	=> '~1,4294967295/i',
			'to_operatorID'	=> '~0,65535/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// add countryID to param
		$mand['to_countryID'] = 0;

		// if operatorID given
		if($mand['to_operatorID']){

			// load operator
			$res = nexus_base::get_operator([
				'operatorID'	=> $mand['to_operatorID'],
				]);

			// on success, take countryID (failsafe)
			if($res->status == 200) $mand['to_countryID'] = $res->data->countryID;
			}

		// update all matching entries
		$upd = self::pdo('u_session_mobile_migration', [$mand['to_mobileID'], $mand['to_operatorID'], $mand['to_countryID'], $mand['from_mobileID']]);

		// on error
		if($upd === false) return self::response(560);

		// return success
		return self::response(204);
		}


	/* Delayed: helper */
	public static function get_redis_info($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'			=> '~^[a-z0-9\:\_]{1,96}$',
			'persistID'		=> '~1,18446744073709551615/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// define cache key
		$cache_key = 'session:redis_info:'.$mand['type'].':'.$mand['persistID'];

		// init redis
		$redis = self::redis();

		// if redis is not accessable, return error
		if(!$redis) return self::response(503);

		// load entry
		$entry = $redis->get($cache_key);

		// return result
		return $entry ? self::response(200, $entry) : self::response(404);
		}

	public static function set_redis_info($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'		=> '~^[a-z0-9\:\_]{1,96}$',
			'persistID'	=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'data'		=> '~/l',
			'until'		=> '~U/d',
			'unset'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'data'		=> null,
			'until'		=> h::dtstr('+1 hour', 'U'),
			'unset'		=> false,
			];

		// define cache key
		$cache_key = 'session:redis_info:'.$mand['type'].':'.$mand['persistID'];

		// init redis
		$redis = self::redis();

		// if redis is not accessable, return error
		if(!$redis) return self::response(503);

		// if unset option is defined
		if($opt['unset']){

			// expire entry
			$redis->setTimeout($cache_key, 0);
			}

		// else entry should created/updated
		else{

			// cache entry
			$redis->set($cache_key, $opt['data']);
			$redis->expireAt($cache_key, $opt['until']);
			}

		// return success
		return self::response(204);
		}


	/* Delayed: functions */
	public static function delayed_create_session($req = []){

		// add common redisjob
		return cronjob::add_common_redisjob([
			'redisjob_fn'	=> '\\'.__CLASS__.'::create_session',
			] + (array) $req + [
			'redisjob_lvl'	=> 1,
			]);
		}

	public static function delayed_update_session($req = []){

		// add common redisjob
		return cronjob::add_common_redisjob([
			'redisjob_fn'		=> '\\'.__CLASS__.'::update_session',
			'redisjob_abort_not'=> [204, 404],
			'redisjob_retry_on'	=> [404],
			] + (array) $req + [
			'redisjob_lvl'		=> 2,
			]);
		}

	public static function delayed_create_session_unique($req = []){

		// add common redisjob
		return cronjob::add_common_redisjob([
			'redisjob_fn'	=> '\\'.__CLASS__.'::create_session_unique',
			] + (array) $req + [
			'redisjob_lvl'	=> 1,
			]);
		}

	public static function delayed_update_session_unique($req = []){

		// add common redisjob
		return cronjob::add_common_redisjob([
			'redisjob_fn'	=> '\\'.__CLASS__.'::update_session_unique',
			] + (array) $req + [
			'redisjob_lvl'	=> 1,
			]);
		}

	public static function delayed_create_session_open($req = []){

		// add common redisjob
		return cronjob::add_common_redisjob([
			'redisjob_fn'	=> '\\'.__CLASS__.'::create_session_open',
			] + (array) $req + [
			'redisjob_lvl'	=> 1,
			]);
		}

	public static function delayed_update_session_open($req = []){

		// add common redisjob
		return cronjob::add_common_redisjob([
			'redisjob_fn'	=> '\\'.__CLASS__.'::update_session_open',
			] + (array) $req + [
			'redisjob_lvl'	=> 2,
			]);
		}

	public static function delayed_create_blocked_session($req = []){

		// add common redisjob
		return cronjob::add_common_redisjob([
			'redisjob_fn'	=> '\\'.__CLASS__.'::create_blocked_session',
			] + (array) $req + [
			'redisjob_lvl'	=> 3,
			]);
		}

	public static function delayed_create_click($req = []){

		// add common redisjob
		return cronjob::add_common_redisjob([
			'redisjob_fn'	=> '\\'.__CLASS__.'::create_click',
			] + (array) $req + [
			'redisjob_lvl'	=> 1,
			]);
		}

	public static function delayed_create_blocked_click($req = []){

		// add common redisjob
		return cronjob::add_common_redisjob([
			'redisjob_fn'	=> '\\'.__CLASS__.'::create_blocked_click',
			] + (array) $req + [
			'redisjob_lvl'	=> 3,
			]);
		}

	public static function delayed_create_session_pageview($req = []){

		// add common redisjob
		return cronjob::add_common_redisjob([
			'redisjob_fn'	=> '\\'.__CLASS__.'::create_session_pageview',
			] + (array) $req + [
			'redisjob_lvl'	=> 3,
			]);
		}

	public static function delayed_migrate_mobile($req = []){

		// add common redisjob
		return cronjob::add_common_redisjob([
			'redisjob_fn'	=> '\\'.__CLASS__.'::migrate_mobile',
			] + (array) $req + [
			'redisjob_lvl'	=> 2,
			]);
		}

	}
