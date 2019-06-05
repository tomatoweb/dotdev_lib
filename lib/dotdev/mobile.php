<?php
/*****
 * Version 3.0.2018-08-20
**/
namespace dotdev;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \dotdev\nexus\base as nexus_base;
use \dotdev\mobile\migrate as mobile_migrate;

class mobile {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_mobile', [

			// queries: mobile
			's_mobile'					=> 'SELECT
												m.ID AS `mobileID`, m.createTime, m.msisdn, m.operatorID, m.confirmTime,
												i.mobileID as `info_mobileID`, i.blacklistlvl, i.mp_status, i.mp_statusTime, i.info,
												t1.createTime AS `imsi_createTime`, t1.imsi
											FROM `mobile` m
											LEFT JOIN `mobile_info` i ON i.mobileID = m.ID
											LEFT JOIN `imsi` t1 ON t1.mobileID = m.ID
											LEFT JOIN `imsi` t2 ON t2.mobileID = t1.mobileID AND t2.createTime > t1.createTime
											WHERE m.ID = ? AND t2.mobileID IS NULL
											GROUP BY m.ID
											LIMIT 1
											',
			's_mobile_migrated'			=> ['s_mobile', ['FROM `mobile` m' => 'FROM `mobile_migrate` mm INNER JOIN `mobile` m ON m.ID = mm.referID', 'm.ID = ?' => 'mm.mobileID = ?']],
			's_mobile_by_msisdn'		=> ['s_mobile', ['m.ID = ?' => 'm.msisdn = ?']],
			's_mobile_by_imsi'			=> ['s_mobile', ['FROM `mobile` m' => 'FROM `imsi` si INNER JOIN `mobile` m ON m.ID = si.mobileID', 'm.ID = ?' => 'si.imsi = ?']],
			's_mobile_by_persistID'		=> ['s_mobile', ['FROM `mobile` m' => 'FROM `persistlink` pl INNER JOIN `mobile` m ON m.ID = pl.mobileID', 'm.ID = ?' => 'pl.persistID = ?']],

			'i_mobile'					=> 'INSERT INTO `mobile` (`createTime`,`msisdn`,`operatorID`,`confirmTime`) VALUES (?,?,?,?)',
			'u_mobile'					=> 'UPDATE `mobile` SET `msisdn` = ?, `operatorID` = ?, `confirmTime` = ? WHERE `ID` = ?',

			// queries: mobile info
			'i_mobile_info'				=> 'INSERT INTO `mobile_info` (`mobileID`,`blacklistlvl`,`mp_status`,`mp_statusTime`,`info`) VALUES (?,?,?,?,?)',
			'u_mobile_info'				=> 'UPDATE `mobile_info` SET `blacklistlvl` = ?, `mp_status` = ?, `mp_statusTime` = ?, `info` = ? WHERE `mobileID` = ?',

			// queries: persistlink
			's_persistlink_by_persistID'=> 'SELECT `createTime`, `persistID`, `mobileID`, `domainID`, `pageID` FROM `persistlink` WHERE `persistID` = ? LIMIT 1',
			'l_persistlink_by_mobileID'	=> 'SELECT `createTime`, `persistID`, `mobileID`, `domainID`, `pageID` FROM `persistlink` WHERE `mobileID` = ?',
			'i_persistlink'				=> 'INSERT INTO `persistlink` (`createTime`,`persistID`,`mobileID`,`domainID`,`pageID`) VALUES (?,?,?,?,?)',
			'd_persistlink'				=> 'DELETE FROM `persistlink` WHERE `persistID` = ?',

			// queries: IMSI
			'i_imsi'					=> 'INSERT INTO `imsi` (`imsi`,`createTime`,`mobileID`) VALUES (?,?,?)',

			]];
		}


	/* Redis */
	protected static function redis(){

		return redis::load_resource('mt_mobile');
		}


	/* redis cache helper */
	protected static function _set_cached_mobile($mobile, $assoc = [], $update = false){

		// init redis
		$redis = self::redis();

		// if redis is not accessable, return error
		if(!$redis) return false;

		// define redis set options
		$redis_set_option = ['ex'=>3600]; // 60 min
		if(!$update) $redis_set_option[] = 'nx';

		// cache mobile entry
		$redis->set('mobile_entry:'.$mobile->mobileID, $mobile, $redis_set_option);

		// if MSISDN is given
		if(!empty($mobile->msisdn)){

			// cache association to mobileID
			$redis->set('mobileID:by_msisdn:'.$mobile->msisdn, $mobile->mobileID, $redis_set_option);
			}

		// if IMSI is given
		if(!empty($mobile->imsi)){

			// cache association to mobileID
			$redis->set('mobileID:by_imsi:'.$mobile->imsi, $mobile->mobileID, $redis_set_option);
			}

		// if assoc key persistID is given
		if(!empty($assoc['persistID'])){

			// cache association to persistID
			$redis->set('mobileID:by_persistID:'.$assoc['persistID'], $mobile->mobileID, $redis_set_option);
			}

		// if assoc key mobileID is given (but differend from already cached mobileID)
		if(!empty($assoc['mobileID']) and $mobile->mobileID != $assoc['mobileID']){

			// cache association to IMSI
			$redis->set('mobileID:by_migrated:'.$assoc['mobileID'], $mobile->mobileID, $redis_set_option);
			}

		// if assoc key IMSI is given (but differend from already cached mobile IMSI)
		if(!empty($assoc['imsi']) and $mobile->imsi != $assoc['imsi']){

			// cache association to IMSI
			$redis->set('mobileID:by_imsi:'.$assoc['imsi'], $mobile->mobileID, $redis_set_option);
			}

		// return success
		return true;
		}

	protected static function _get_cached_mobile_by($key, $val){

		// init redis
		$redis = self::redis();

		// if redis is not accessable, return null
		if(!$redis) return null;

		// for an associated key
		if(in_array($key, ['msisdn','persistID','imsi'])){

			// if no mobileID found for associated key, return null
			if(!$redis->exists('mobileID:by_'.$key.':'.$val)) return null;

			// load mobileID (and define key as mobileID)
			$val = $redis->get('mobileID:by_'.$key.':'.$val);
			$key = 'mobileID';
			}

		// if key is not ID
		if($key != 'mobileID'){

			// log error
			e::logtrigger('Invalid _get_cached_mobile_by() access with $key = '.h::encode_php($key).' and $val = '.h::encode_php($val));

			// return null
			return null;
			}

		// check existance
		$exists = $redis->exists('mobile:entry:'.$val);

		// if entry does not exist
		if(!$exists){

			// check as migrated mobileID
			$val = $redis->get('mobileID:by_migrated:'.$val);

			// if not found too, return null
			if(!$val) return null;

			// check existance again
			$exists = $redis->exists('mobile:entry:'.$val);
			}

		// return entry, if found
		return $exists ? $redis->get('mobile:entry:'.$val) : null;
		}

	protected static function _unset_cached_mobile($set){

		// init redis
		$redis = self::redis();

		// if redis is not accessable, return null
		if(!$redis) return false;

		// for each entry in set
		foreach($set as $key => $val){

			// skip unsupported keys
			if(!in_array($key, ['mobileID','msisdn','persistID','imsi'])) continue;

			// define key
			$cache_key = ($key == 'mobileID') ? 'mobile_entry:'.$val : 'mobileID:by_'.$key.':'.$val;

			// if cache entry does not exist, skip
			if(!$redis->exists($cache_key)) continue;

			// expire cache entry
			$redis->expire($cache_key, 0);
			}

		// return success
		return true;
		}


	/* object: mobile */
	public static function get_mobile($req = []){

		// alternative
		$alt = h::eX($req, [
			'mobileID'		=> '~1,4294967295/i',
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'persistID'		=> '~1,18446744073709551615/i',
			'imsi'			=> '~^[1-7]{1}[0-9]{5,15}$',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'extended'		=> '~/b',
			'with_infotext'	=> '~/b',
			'reload_cache'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert msisdn param
		if(!empty($alt['msisdn'])) $alt['msisdn'] = $alt['msisdn'][0];

		// define defaults
		$opt += [
			'extended'		=> true,
			'with_infotext'	=> false,
			'reload_cache'	=> false,
			];

		// define function to alter result on success
		$on_success_alter = function($result) use ($alt, $opt){

			// make list (of objects)
			$list = is_array($result) ? $result : [$result];

			// if infotext is wanted
			if(!$opt['with_infotext']){

				// for each entry
				foreach($list as $entry){

					// unset info text
					unset($entry->info);
					}
				}

			// if extended option is not set, return result
			if(!$opt['extended']) return self::response(200, $result);

			// define additional extended keys (and its defaults)
			$add_keys = [
				'countryID'		=> 0,
				'operator'		=> null,
				'code'			=> null,
				'prefix_nat'	=> null,
				'prefix_int'	=> null,
				];

			// for each entry
			foreach($list as $entry){

				// unset info_mobileID value
				unset($entry->info_mobileID);

				// set confirmed status
				$entry->confirmed = ($entry->confirmTime != '0000-00-00 00:00:00');

				// add keys with default value
				foreach($add_keys as $k => $v){
					$entry->{$k} = $v;
					}

				// if operatorID is given
				if($entry->operatorID){

					// load operator
					$res = nexus_base::get_operator([
						'operatorID'	=> $entry->operatorID,
						]);

					// if found
					if($res->status == 200){

						// take data
						$entry->countryID = $res->data->countryID;
						$entry->operator = $res->data->name;
						$entry->code = $res->data->code;
						$entry->prefix_nat = $res->data->prefix_nat;
						$entry->prefix_int = $res->data->prefix_int;
						}
					}

				// if no countryID is given
				if(!$entry->countryID and $entry->msisdn){

					// load country with MSISDN
					$res = nexus_base::get_country([
						'msisdn'	=> $entry->msisdn,
						]);

					// if found
					if($res->status == 200){

						// take data
						$entry->countryID = $res->data->countryID;
						$entry->code = $res->data->code;
						$entry->prefix_int = $res->data->prefix_int;
						$entry->prefix_nat = $res->data->prefix_nat;
						}
					}

				// add national number
				$entry->natnumber = ($entry->code and $entry->msisdn) ? $entry->prefix_nat.substr((string) $entry->msisdn, strlen((string) $entry->prefix_int)) : $entry->msisdn;
				}

			// return result
			return self::response(200, $result);
			};


		// param order 1: mobileID
		if(isset($alt['mobileID'])){

			// if cache should not be reloaded
			if(!$opt['reload_cache']){

				// try to load cached entry
				$entry = self::_get_cached_mobile_by('mobileID', $alt['mobileID']);

				// return success (with altered entry)
				if($entry) return $on_success_alter($entry);
				}

			// load entry from DB
			$entry = self::pdo('s_mobile', $alt['mobileID']);

			// on error
			if($entry === false) return self::response(560);

			// if entry not given
			if(!$entry){

				// try to load entry from DB with migrated mobileID
				$entry = self::pdo('s_mobile_migrated', $alt['mobileID']);

				// on error
				if($entry === false) return self::response(560);
				}

			// if still not found
			if(!$entry){

				// return not found
				return self::response(404);
				}

			// cache entry (also caching migrated mobileID over requested mobileID)
			self::_set_cached_mobile($entry, ['mobileID' => $alt['mobileID']], $opt['reload_cache']);

			// return success (with altered entry)
			return $on_success_alter($entry);
			}

		// param order 2-4: msisdn, persistID, imsi
		foreach(['msisdn', 'persistID', 'imsi'] as $assoc_key){

			// skip if assoc_key not given
			if(!isset($alt[$assoc_key])) continue;

			// if cache should not be reloaded
			if(!$opt['reload_cache']){

				// try to load cached entry
				$entry = self::_get_cached_mobile_by($assoc_key, $alt[$assoc_key]);

				// return success (with altered entry)
				if($entry) return $on_success_alter($entry);
				}

			// load entry from DB
			$entry = self::pdo('s_mobile_by_'.$assoc_key, $alt[$assoc_key]);

			// on error
			if($entry === false) return self::response(560);

			// if still not found
			if(!$entry){

				// return not found
				return self::response(404);
				}

			// cache entry
			self::_set_cached_mobile($entry, [$assoc_key => $alt[$assoc_key]], $opt['reload_cache']);

			// return success (with altered entry)
			return $on_success_alter($entry);
			}

		// other request param invalid
		return self::response(400, 'need mobileID, msisdn, persistID or imsi parameter');
		}

	public static function create_mobile($req = []){

		// optional
		$opt = h::eX($req, [
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'operatorID'	=> '~0,65535/i',
			'createTime'	=> '~Y-m-d H:i:s/d',
			'confirmTime'	=> '~Y-m-d H:i:s/d',
			'confirmed'		=> '~/b',
			'blacklistlvl'	=> '~0,255/i',
			'mp_status'		=> '~0,999/i',
			'mp_statusTime'	=> '~Y-m-d H:i:s/d',
			'info'			=> '~/s',
			'persistID'		=> '~1,18446744073709551615/i',
			'domainID'		=> '~0,65535/i',
			'pageID'		=> '~0,65535/i',
			'imsi'			=> '~^[1-7]{1}[0-9]{5,15}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert msisdn
		if(isset($opt['msisdn'])) $opt['msisdn'] = $opt['msisdn'][0];

		// convert confirmed to confirmTime, if confirmTime is not given
		if(!empty($opt['confirmed']) and !isset($opt['confirmTime'])) $opt['confirmTime'] = h::dtstr('now');

		// define defaults
		$opt += [
			'msisdn'		=> null,
			'operatorID'	=> 0,
			'createTime'	=> h::dtstr('now'),
			'confirmTime'	=> '0000-00-00 00:00:00',
			];


		// init redis
		$redis = self::redis();

		// define lock status
		$lock_status = [];

		// define unlock function
		$redis_unlock_processes = function($ttl = 0) use ($redis, &$lock_status){

			// for each successful lock
			foreach($lock_status as $lock_key => $status){

				// unlock locked processes (immediately or after defined seconds)
				$lock_status[$lock_key] = !$ttl
					? redis::unlock_process($redis, $lock_key)
					: redis::unlock_process($redis, $lock_key, ['ttl'=>$ttl]);
				}
			};

		// for keys with already existing values in DB
		foreach(['msisdn','persistID','imsi'] as $assoc_key){

			// skip, if param not given
			if(!isset($opt[$assoc_key])) continue;

			// define lock key
			$lock_key = 'insert_mobile:'.$assoc_key.':'.$opt[$assoc_key];

			// lock for assoc key
			$lock_status[$lock_key] = redis::lock_process($redis, $lock_key, ['timeout_ms'=>800, 'retry_ms'=>400]);

			// if a concurrent process already locked that lock_key before
			if($lock_status[$lock_key] == 200) return self::response(409);

			// if we do not get the lock, return error
			if($lock_status[$lock_key] != 100) return self::response(500, 'Locking key '.$lock_key.' ends in lock status: '.$lock_status[$lock_key].' ('.h::encode_php($opt).')');

			// try to load with given information
			$res = self::get_mobile([
				$assoc_key	=> $opt[$assoc_key],
				]);

			// on error or found
			if($res->status != 404){

				// unlock locked process immediately
				$redis_unlock_processes();

				// on error
				if($res->status != 200) return $res;

				// if already found, return conflict
				return self::response(409);
				}
			}

		// if no operatorID is given, but IMSI
		if(!$opt['operatorID'] and isset($opt['imsi'])){

			// load operator of hni
			$res = nexus_base::get_operator([
				'hni'	=> (int) substr((string) $opt['imsi'], 0, 5),
				]);

			// on error
			if(!in_array($res->status, [200, 404])){

				// unlock locked process immediately
				$redis_unlock_processes();

				// return error
				return self::response(570, $res);
				}

			// if operator is found
			if($res->status == 200){

				// take operatorID
				$opt['operatorID'] = $res->data->operatorID;
				}
			}

		// insert mobile
		$mobileID = self::pdo('i_mobile', [$opt['createTime'], $opt['msisdn'], $opt['operatorID'], $opt['confirmTime']]);

		// on error
		if($mobileID === false) return self::response(560);

		// if blacklistlvl, mp_status or info is given
		if(isset($opt['blacklistlvl']) or isset($opt['mp_status']) or isset($opt['info'])){

			// add mobile info defaults
			$opt += [
				'blacklistlvl'	=> 0,
				'mp_status'		=> 0,
				'mp_statusTime'	=> $opt['createTime'],
				'info'			=> '',
				];

			// insert persistID link
			$ins = self::pdo('i_mobile_info', [$mobileID, $opt['blacklistlvl'], $opt['mp_status'], $opt['mp_statusTime'], $opt['info']]);

			// on error
			if($ins === false) return self::response(560);
			}

		// if persistID is given
		if(isset($opt['persistID'])){

			// insert persistID link
			$ins = self::pdo('i_persistlink', [$opt['createTime'], $opt['persistID'], $mobileID, $opt['domainID'] ?? 0, $opt['pageID'] ?? 0]);

			// on error
			if($ins === false) return self::response(560);

			// if redis accessable
			if($redis){

				// cache entry
				$redis->set('persistlink_base:'.$opt['persistID'], (object)[
					'createTime'=> $opt['createTime'],
					'persistID'	=> $opt['persistID'],
					], ['ex'=>3600, 'nx']); // 60 min
				}
			}

		// if IMSI is given
		if(isset($opt['imsi'])){

			// insert IMSI entry
			$ins = self::pdo('i_imsi', [$opt['imsi'], $opt['createTime'], $mobileID]);

			// on error
			if($ins === false) return self::response(560);
			}


		// create mobile entry from given data
		$mobile = (object)[
			'mobileID'			=> (int) $mobileID,
			'createTime'		=> $opt['createTime'],
			'msisdn'			=> $opt['msisdn'],
			'operatorID'		=> $opt['operatorID'],
			'confirmTime'		=> $opt['confirmTime'],
			'info_mobileID'		=> (isset($opt['blacklistlvl']) and isset($opt['mp_status']) and isset($opt['info'])) ? (int) $mobileID : null,
			'blacklistlvl'		=> $opt['blacklistlvl'] ?? null,
			'mp_status'			=> $opt['mp_status'] ?? null,
			'mp_statusTime'		=> $opt['mp_statusTime'] ?? null,
			'info'				=> $opt['info'] ?? null,
			'imsi_createTime'	=> isset($opt['imsi']) ? $opt['createTime'] : null,
			'imsi'				=> $opt['imsi'] ?? null,
			];

		// cache entry
		self::_set_cached_mobile($mobile, ['persistID'=>$opt['persistID'] ?? null, 'imsi'=>$opt['imsi'] ?? null]);

		// unlock locked process immediately
		$redis_unlock_processes();

		// return success
		return self::response(201, (object)['mobileID'=>$mobileID]);
		}

	public static function update_mobile($req = []){

		// mandatory
		$mand = h::eX($req, [
			'mobileID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'insertTime'	=> '~Y-m-d H:i:s/d',
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'operatorID'	=> '~1,65535/i',
			'confirmTime'	=> '~Y-m-d H:i:s/d',
			'confirmed'		=> '~/b',
			'blacklistlvl'	=> '~0,255/i',
			'mp_status'		=> '~0,999/i',
			'mp_statusTime'	=> '~Y-m-d H:i:s/d',
			'info'			=> '~/s',
			'imsi'			=> '~^[1-7]{1}[0-9]{5,15}$',
			'persistID'		=> '~1,18446744073709551615/i',
			'domainID'		=> '~0,65535/i',
			'pageID'		=> '~0,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert msisdn
		if(isset($opt['msisdn'])) $opt['msisdn'] = $opt['msisdn'][0];

		// convert confirmed to confirmTime, if confirmTime is not given
		if(!empty($opt['confirmed']) and !isset($opt['confirmTime'])) $opt['confirmTime'] = h::dtstr('now');

		// define defaults
		$opt += [
			'insertTime'	=> h::dtstr('now'),
			];


		// init redis
		$redis = self::redis();

		// define lock status
		$lock_status = [];

		// define lock key
		$update_lock_key = 'update_mobile:'.$mand['mobileID'];

		// lock for assoc key
		$lock_status[$update_lock_key] = redis::lock_process($redis, $update_lock_key, ['timeout_ms'=>800, 'retry_ms'=>400]);

		// if we do not get the lock, return error
		if($lock_status[$update_lock_key] != 100) return self::response(500, 'Locking key '.$update_lock_key.' ends in lock status: '.$lock_status[$update_lock_key].' ('.h::encode_php($mand + $opt).')');

		// define unlock function
		$redis_unlock_processes = function($ttl = 0) use ($redis, &$lock_status, $update_lock_key){

			// for each successful lock
			foreach($lock_status as $lock_key => $status){

				// unlock locked processes (immediately or after defined seconds)
				$lock_status[$lock_key] = ($lock_key == $update_lock_key or !$ttl)
					? redis::unlock_process($redis, $lock_key)
					: redis::unlock_process($redis, $lock_key, ['ttl'=>$ttl]);
				}
			};


		// load mobile
		$res = self::get_mobile([
			'mobileID'		=> $mand['mobileID'],
			'extended'		=> false,
			'with_infotext'	=> true,
			]);

		// on error
		if($res->status != 200){

			// unlock locked process immediately
			$redis_unlock_processes();

			// return error
			return $res;
			}

		// take entry
		$entry = $res->data;


		// if no operatorID is given, but IMSI
		if(!$entry->operatorID and !isset($opt['operatorID']) and isset($opt['imsi'])){

			// load operator of hni
			$res = nexus_base::get_operator([
				'hni'	=> (int) substr((string) $opt['imsi'], 0, 5),
				]);

			// on error
			if(!in_array($res->status, [200, 404])){

				// unlock locked process immediately
				$redis_unlock_processes();

				// return error
				return self::response(570, $res);
				}

			// if operator is found
			if($res->status == 200){

				// take operatorID
				$opt['operatorID'] = $res->data->operatorID;
				}
			}


		// for each association key
		foreach(['msisdn','imsi'] as $assoc_key){

			// if this association key is given and its value is the same as already set
			if(isset($opt[$assoc_key]) and $entry->{$assoc_key} and $entry->{$assoc_key} == $opt[$assoc_key]){

				// unset value
				unset($opt[$assoc_key]);
				}
			}


		// for keys with already existing values in DB (prevents concurrent inserts with same values)
		foreach(['msisdn','persistID','imsi'] as $assoc_key){

			// skip, if param not given
			if(!isset($opt[$assoc_key])) continue;

			// define lock key
			$lock_key = 'insert_mobile:'.$assoc_key.':'.$opt[$assoc_key];

			// lock for assoc key
			$lock_status[$lock_key] = redis::lock_process($redis, $lock_key, ['timeout_ms'=>800, 'retry_ms'=>400]);

			// if a concurrent process already locked that lock_key before
			if($lock_status[$lock_key] == 200) return self::response(409);

			// if we do not get the lock, return error
			if($lock_status[$lock_key] != 100) return self::response(500, 'Locking key '.$lock_key.' ends in lock status: '.$lock_status[$lock_key].' ('.h::encode_php($mand + $opt).')');
			}


		// define updateable or insertable
		$mobile_update = false;
		$mobileinfo_update = false;
		$imsi_insert = false;
		$persistlink_insert = false;

		// define function for migration and merging mobile entries
		$migrate_and_merge = function($from_entry, $to_entry) use (&$mobile_update, &$mobileinfo_update, $redis){

			// additional check, migration of entries with MSISDN not allowed
			if($from_entry->msisdn) return self::response(500, 'DEBUG: Cannot migrate mobileID '.$from_entry->mobileID.' (with MSISDN '.h::encode_php($from_entry->msisdn).') to mobileID '.$to_entry->mobileID.' (with MSISDN '.$to_entry->msisdn.').');

			// if to_entry is not confirmed
			if($to_entry->confirmTime == '0000-00-00 00:00:00'){

				// if operatorID only set in migrated entry
				if(!$to_entry->operatorID and $from_entry->operatorID){

					// migrate value
					$to_entry->operatorID = $from_entry->operatorID;

					// define mobile update
					$mobile_update = true;
					}

				// for mobile info specific keys
				foreach(['blacklistlvl','mp_status','mp_statusTime','info'] as $key){

					// if key not only set in migrated entry, skip key
					if($to_entry->{$key} or !$from_entry->{$key}) continue;

					// migrate value
					$to_entry->{$key} = $from_entry->{$key};

					// define mobile info update
					$mobileinfo_update = true;
					}
				}

			// update database for migrated mobile
			$res = mobile_migrate::migrate_mobile([
				'from_mobileID'	=> $from_entry->mobileID,
				'to_mobileID'	=> $to_entry->mobileID,
				'to_operatorID'	=> $to_entry->operatorID,
				]);

			// unset cache for migrated mobileID (and IMSI)
			self::_unset_cached_mobile([
				'mobileID'		=> $from_entry->mobileID,
				'imsi'			=> $from_entry->imsi,
				]);

			// on migrate mobile error
			if($res->status != 204) return $res;

			// if an IMSI entry was migrated and is the primary now
			if($from_entry->imsi and (!$to_entry->imsi or h::date($to_entry->imsi_createTime) < h::date($from_entry->imsi_createTime))){

				// take imsi
				$to_entry->imsi = $from_entry->imsi;
				$to_entry->imsi_createTime = $from_entry->imsi_createTime;
				}

			// return merged entry
			return self::response(200, $to_entry);
			};


		// if (a new) MSISDN is given
		if(isset($opt['msisdn'])){

			// if mobile has already a different MSISDN
			if($entry->msisdn and $entry->msisdn != $opt['msisdn']){

				// log error
				e::logtrigger('DEBUG: Cannot replace MSISDN '.$entry->msisdn.' with new MSISDN '.$opt['msisdn'].' for mobileID '.$entry->mobileID);

				// unlock locked processes immediately
				$redis_unlock_processes();

				// return conflict
				return self::response(409);
				}


			// try to load MSISDN entry
			$msisdn_entry = self::pdo('s_mobile_by_msisdn', $opt['msisdn']);

			// on error
			if($msisdn_entry === false) return self::response(560);

			// if MSISDN entry is found
			if($msisdn_entry){

				// migrate and merge entry to MSISDNs entry
				$res = $migrate_and_merge($entry, $msisdn_entry);

				// on error
				if($res->status != 200){

					// unlock locked processes immediately
					$redis_unlock_processes();

					// return result
					return $res;
					}

				// overwrite entry with merged entry
				$entry = $res->data;
				}
			}

		// if (a new) IMSI is given
		if(isset($opt['imsi'])){

			// try to load IMSI entry
			$imsi_entry = self::pdo('s_mobile_by_imsi', $opt['imsi']);

			// on error
			if($imsi_entry === false) return self::response(560);

			// if IMSI entry is found
			if($imsi_entry){

				// if mobileID of IMSI is different
				if($imsi_entry->mobileID != $entry->mobileID){

					// if both found IMSI and this entry is attached to a MSISDN
					if($imsi_entry->msisdn and $entry->msisdn){

						// log error
						e::logtrigger('DEBUG: Migrating IMSI '.$imsi_entry->imsi.' to mobileID '.$entry->mobileID.' (with MSISDN '.h::encode_php($entry->msisdn).') blocked, because its already attached to mobileID '.$imsi_entry->mobileID.' (with MSISDN '.$imsi_entry->msisdn.').');

						// unlock locked processes immediately
						$redis_unlock_processes();

						// return conflict
						return self::response(409);
						}

					// define which entry should be migrated (entry with MSISDN or older mobileID has priority)
					if($imsi_entry->msisdn) $which = 'entry';
					elseif($entry->msisdn) $which = 'imsi_entry';
					else $which = ($imsi_entry->mobileID < $entry->mobileID) ? 'entry' : 'imsi_entry';

					// migrate and merge entry to MSISDNs entry
					$res = ($which == 'entry') ? $migrate_and_merge($entry, $imsi_entry) : $migrate_and_merge($imsi_entry, $entry);

					// on error
					if($res->status != 200){

						// unlock locked processes immediately
						$redis_unlock_processes();

						// return result
						return $res;
						}

					// overwrite entry with merged entry
					$entry = $res->data;
					}

				// else if IMSI already matches mobileID (should only happen for older IMSI, if mobile has more than one IMSI)
				else {

					// do nothing with IMSI data, so unset it
					unset($opt['imsi']);
					}
				}

			// else no IMSI entry was found
			else {

				// define IMSI insertion
				$imsi_insert = true;
				}
			}

		// if (a new) persistID is given
		if(isset($opt['persistID'])){

			// try to load persistlink entry
			$persistlink = self::pdo('s_persistlink_by_persistID', $opt['persistID']);

			// on error
			if($persistlink === false) return self::response(560);

			// if persistlink entry is found
			if($persistlink){

				// if persistlink is already attached to another mobile
				if($persistlink->mobileID != $entry->mobileID){

					// log error
					e::logtrigger('DEBUG: persistID '.$persistlink->persistID.' has already a persistlink with different mobileID '.$persistlink->mobileID.' (mobileID '.h::encode_php($entry->mobileID).', MSISDN '.h::encode_php($entry->msisdn).')');

					// unlock locked processes immediately
					$redis_unlock_processes();

					// return conflict
					return self::response(409);
					}

				// else if persistlink already matches mobileID
				else {

					// do nothing with persistlink data, so unset it
					unset($opt['persistID']);
					}
				}

			// else no persistlink entry was found
			else {

				// define persistlink insertion
				$persistlink_insert = true;
				}
			}


		// check basic param
		foreach(['msisdn','operatorID','confirmTime'] as $key){

			// skip key, if not given
			if(!isset($opt[$key])) continue;

			// skip key, if value is the same
			if($entry->{$key} == $opt[$key]) continue;

			// skip confirmTime, if entry already confirmed
			if($key == 'confirmTime' and $entry->confirmTime != '0000-00-00 00:00:00') continue;

			// take value
			$entry->{$key} = $opt[$key];

			// define update
			$mobile_update = true;
			}

		// check basic param
		foreach(['blacklistlvl','mp_status','mp_statusTime','info'] as $key){

			// skip key, if not given
			if(!isset($opt[$key])) continue;

			// skip key, if value is the same (but not for mp_status, since this could generate a new mp_statusTime)
			if($entry->{$key} === $opt[$key] and $key != 'mp_status') continue;

			// take value
			$entry->{$key} = $opt[$key];

			// define update
			$mobileinfo_update = true;
			}


		// if mobile entry update is defined
		if($mobile_update){

			// update mobile entry
			$upd = self::pdo('u_mobile', [$entry->msisdn, $entry->operatorID, $entry->confirmTime, $entry->mobileID]);

			// on error
			if($upd === false) return self::response(560);
			}

		// if mobile info update is defined (but no entry exists)
		if($mobileinfo_update and !$entry->info_mobileID){

			// define mobile info defaults
			if($entry->blacklistlvl === null) $entry->blacklistlvl = 0;
			if($entry->mp_statusTime === null or (isset($opt['mp_status']) and !isset($opt['mp_statusTime']))) $entry->mp_statusTime = h::dtstr('now');
			if($entry->mp_status === null) $entry->mp_status = 0;
			if($entry->info === null) $entry->info = '';

			// insert mobile info
			$ins = self::pdo('i_mobile_info', [$entry->mobileID, $entry->blacklistlvl, $entry->mp_status, $entry->mp_statusTime, $entry->info]);

			// on error
			if($ins === false) return self::response(560);

			// set mobileID as info_mobileID
			$entry->info_mobileID = $entry->mobileID;
			}

		// if mobile info update is defined
		elseif($mobileinfo_update){

			// set mp_statusTime, if missing but mp_status is given
			if(isset($opt['mp_status']) and !isset($opt['mp_statusTime'])) $entry->mp_statusTime = h::dtstr('now');

			// update mobile info
			$upd = self::pdo('u_mobile_info', [$entry->blacklistlvl, $entry->mp_status, $entry->mp_statusTime, $entry->info, $entry->mobileID]);

			// on error
			if($upd === false) return self::response(560);
			}

		// if IMSI is given
		if($imsi_insert){

			// insert IMSI entry
			$ins = self::pdo('i_imsi', [$opt['imsi'], $opt['insertTime'], $entry->mobileID]);

			// on error
			if($ins === false) return self::response(560);

			// if inserted IMSI is the new primary IMSI
			if(!$entry->imsi or h::date($entry->imsi_createTime) <= h::date($opt['insertTime'])){

				// set new IMSI and its createTime
				$entry->imsi = $opt['imsi'];
				$entry->imsi_createTime = $opt['insertTime'];
				}
			}

		// if persistID is given
		if($persistlink_insert){

			// insert persistID link
			$ins = self::pdo('i_persistlink', [$opt['insertTime'], $opt['persistID'], $entry->mobileID, $opt['domainID'] ?? 0, $opt['pageID'] ?? 0]);

			// on error
			if($ins === false) return self::response(560);

			// if redis accessable
			if($redis){

				// cache entry
				$redis->set('persistlink_base:'.$opt['persistID'], (object)[
					'createTime'=> $opt['insertTime'],
					'persistID'	=> $opt['persistID'],
					], ['ex'=>3600, 'nx']); // 60 min
				}
			}

		// cache or update cached entry
		self::_set_cached_mobile($entry, ['persistID'=>$opt['persistID'] ?? null, 'imsi'=>$opt['imsi'] ?? null], true);

		// unlock update processes immediately
		$redis_unlock_processes();

		// return success
		return self::response(204, (object)['mobileID'=>$entry->mobileID]);
		}


	/* object: persistlink */
	public static function get_persistlink($req = []){

		// alternativ
		$alt = h::eX($req, [
			'mobileID'		=> '~1,4294967295/i',
			'persistID'		=> '~1,18446744073709551615/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'addtimeonly'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: mobileID
		if(isset($alt['mobileID'])){

			// load list
			$list = self::pdo('l_persistlink_by_mobileID', $alt['mobileID']);

			// on error
			if($list === false) return self::response(560);

			// return result
			return self::response(200, $list);
			}

		// param order 2: persistID
		if(isset($alt['persistID'])){

			// define cache key
			$cache_key = 'persistlink_base:'.$alt['persistID'];

			// init redis
			$redis = self::redis();

			// if redis accessable and entry exists
			if(!empty($opt['addtimeonly']) and $redis and $redis->exists($cache_key)){

				// return entry
				return self::response(200, $redis->get($cache_key));
				}

			// load entry
			$entry = self::pdo('s_persistlink_by_persistID', $alt['persistID']);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// define persistlink_base entry
			$base_entry = (object)[
				'createTime'=> $entry->createTime,
				'persistID'	=> $entry->persistID,
				];

			// if redis accessable
			if($redis){

				// cache entry
				$redis->set($cache_key, $base_entry, ['ex'=>3600, 'nx']); // 60 min
				}

			// return result
			return self::response(200, !empty($opt['addtimeonly']) ? $base_entry : $entry);
			}

		// param order 3: persistID
		if(isset($alt['persistID'])){

			// load entry
			$entry = self::pdo('s_persistlink_by_persistID', $alt['persistID']);

			// return result
			return self::response(200, $entry);
			}

		// other request param invalid
		return self::response(400, 'need mobileID or persistID parameter');
		}

	public static function delete_persistlink($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'		=> '~1,18446744073709551615/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// delete persistlink
		$del = self::pdo('d_persistlink', [$mand['persistID']]);

		// on error
		if($del === false) return self::response(560);

		// unset cache for persistID
		self::_unset_cached_mobile([
			'persistID'		=> $mand['persistID'],
			]);

		// return success
		return self::response(204);
		}


	/* DEPRECATED */
	public static function get($req = []){

		// optional
		$opt = h::eX($req, [
			'ID'			=> '~1,4294967295/i',
			'mobileID'		=> '~1,4294967295/i',
			'msisdn'		=> '~^(?:\+|00|)(?:[1-9]{1}[0-9]{5,14})$',
			'persistID'		=> '~1,4294967295/i',
			'imsi'			=> '~^[1-9]{1}[0-9]{5,15}$',
			'reload_cache'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if old 'ID' param is given
		if(isset($opt['ID'])){

			// but not mobileID, create it with ID param
			if(!isset($opt['mobileID'])) $opt['mobileID'] = $opt['ID'];

			// unset ID
			unset($opt['ID']);
			}

		// forward to new function
		$res = self::get_mobile($opt);

		// on success
		if($res->status == 200){

			// append ID as mobileID
			$res->data->ID = $res->data->mobileID;
			}

		// return result
		return $res;
		}

	public static function create($req = []){

		// mandatory
		$mand = h::eX($req, [
			'msisdn'		=> '~^(?:\+|00|)(?:[1-9]{1}[0-9]{5,14})$',
			'operatorID'	=> '~0,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'confirmed'		=> '~/b',
			'confirmTime'	=> '~Y-m-d H:i:s/d',
			'createTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// unset empty operatorID
		if(!$mand['operatorID']) unset($mand['operatorID']);

		// forward to new function
		$res = self::create_mobile($mand + $opt);

		// on success
		if($res->status == 201){

			// append ID as mobileID
			$res->data->ID = $res->data->mobileID;
			}

		// return result
		return $res;
		}

	public static function create_dummy($req = []){

		// alternative
		$alt = h::eX($req, [
			'operatorID'	=> '~1,65535/i',
			'imsi'			=> '~^[1-9]{1}[0-9]{5,15}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(empty($alt)) return self::response(400, 'Need operatorID or imsi param');

		// forward to new function
		$res = self::create_mobile($alt);

		// on success
		if($res->status == 201){

			// append ID as mobileID
			$res->data->ID = $res->data->mobileID;
			}

		// return result
		return $res;
		}

	public static function upgrade_dummy($req = []){

		// alternative
		$alt = h::eX($req, [
			'ID'			=> '~1,4294967295/i',
			'mobileID'		=> '~1,4294967295/i',
			'imsi'			=> '~^[1-9]{1}[0-9]{5,15}$',
			], $error, true);

		// mandatory
		$mand = h::eX($req, [
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'imsi'			=> '~^[1-9]{1}[0-9]{5,15}$',
			'confirmTime'	=> '~Y-m-d H:i:s/d',
			'confirmed'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(empty($alt)) return self::response(400, 'Need mobileID or imsi param');

		// if old 'ID' param is given
		if(isset($alt['ID'])){

			// but not mobileID, create it with ID param
			if(!isset($alt['mobileID'])) $alt['mobileID'] = $alt['ID'];

			// unset ID
			unset($alt['ID']);
			}

		// if mobileID not set
		if(!isset($alt['mobileID'])){

			// take imsi to define mobileID
			$res = self::get_mobile([
				'imsi'		=> $alt['imsi'],
				]);

			// on error
			if($res->status != 200) return $res;

			// take mobileID
			$alt['mobileID'] = $res->data->mobileID;
			}

		// forward to new function
		$res = self::update_mobile([
			'mobileID'		=> $alt['mobileID'],
			'msisdn'		=> $mand['msisdn'][0],
			] + $opt);

		// on success
		if($res->status == 204){

			// append ID as mobileID
			$res->data->ID = $res->data->mobileID;
			}

		// return result
		return $res;
		}

	public static function confirm($req = []){

		// alternative
		$alt = h::eX($req, [
			'ID'			=> '~1,4294967295/i',
			'mobileID'		=> '~1,4294967295/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'confirmTime'	=> '~Y-m-d H:i:s/d',
			'operatorID'	=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(empty($alt)) return self::response(400, ['mobileID']);

		// if old 'ID' param is given
		if(isset($alt['ID'])){

			// but not mobileID, create it with ID param
			if(!isset($alt['mobileID'])) $alt['mobileID'] = $alt['ID'];

			// unset ID
			unset($alt['ID']);
			}

		// forward to new function
		$res = self::update_mobile([
			'mobileID'		=> $alt['mobileID'],
			'confirmed'		=> true,
			] + $opt);

		// on success
		if($res->status == 204){

			// append ID as mobileID
			$res->data->ID = $res->data->mobileID;
			}

		// return result
		return $res;
		}

	public static function update_operator($req = []){

		// alternative
		$alt = h::eX($req, [
			'ID'			=> '~1,4294967295/i',
			'mobileID'		=> '~1,4294967295/i',
			], $error, true);

		// mandatory
		$mand = h::eX($req, [
			'operatorID'	=> '~1,65535/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);
		if(empty($alt)) return self::response(400, ['mobileID']);

		// if old 'ID' param is given
		if(isset($alt['ID'])){

			// but not mobileID, create it with ID param
			if(!isset($alt['mobileID'])) $alt['mobileID'] = $alt['ID'];

			// unset ID
			unset($alt['ID']);
			}

		// forward to new function
		$res = self::update_mobile([
			'mobileID'		=> $alt['mobileID'],
			'operatorID'	=> $mand['operatorID'],
			]);

		// on success
		if($res->status == 204){

			// append ID as mobileID
			$res->data->ID = $res->data->mobileID;
			}

		// return result
		return $res;
		}

	public static function set_info($req = []){

		// alternative
		$alt = h::eX($req, [
			'ID'			=> '~1,4294967295/i',
			'mobileID'		=> '~1,4294967295/i',
			], $error, true);

		// mandatory
		$opt = h::eX($req, [
			'blacklistlvl'	=> '~0,255/i',
			'info'			=> '~/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(empty($alt)) return self::response(400, ['mobileID']);

		// if old 'ID' param is given
		if(isset($alt['ID'])){

			// but not mobileID, create it with ID param
			if(!isset($alt['mobileID'])) $alt['mobileID'] = $alt['ID'];

			// unset ID
			unset($alt['ID']);
			}

		// forward to new function
		$res = self::update_mobile([
			'mobileID'		=> $alt['mobileID'],
			] + $opt);

		// on success
		if($res->status == 204){

			// append ID as mobileID
			$res->data->ID = $res->data->mobileID;
			}

		// return result
		return $res;
		}

	public static function get_info($req = []){

		// alternative
		$alt = h::eX($req, [
			'ID'			=> '~1,4294967295/i',
			'mobileID'		=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(empty($alt)) return self::response(400, ['mobileID']);

		// if old 'ID' param is given
		if(isset($alt['ID'])){

			// but not mobileID, create it with ID param
			if(!isset($alt['mobileID'])) $alt['mobileID'] = $alt['ID'];

			// unset ID
			unset($alt['ID']);
			}

		// forward to new function
		$res = self::get_mobile([
			'mobileID'		=> $alt['mobileID'],
			'with_infotext'	=> true,
			]);

		// on error
		if($res->status != 200) return $res;

		// return old version response
		return self::response(200, (object)[
			'mobileID'		=> $res->data->mobileID,
			'blacklistlvl'	=> $res->data->blacklistlvl,
			'info'			=> $res->data->info,
			]);
		}

	public static function add_persistlink($req = []){

		// alternative
		$alt = h::eX($req, [
			'ID'			=> '~1,4294967295/i',
			'mobileID'		=> '~1,4294967295/i',
			], $error, true);

		// mandatory
		$mand = h::eX($req, [
			'persistID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'	=> '~Y-m-d H:i:s/d',
			'domainID'		=> '~0,65535/i',
			'pageID'		=> '~0,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(empty($alt)) return self::response(400, ['mobileID']);

		// if old 'ID' param is given
		if(isset($alt['ID'])){

			// but not mobileID, create it with ID param
			if(!isset($alt['mobileID'])) $alt['mobileID'] = $alt['ID'];

			// unset ID
			unset($alt['ID']);
			}

		// forward to new function
		$res = self::update_mobile([
			'mobileID'		=> $alt['mobileID'],
			'persistID'		=> $mand['persistID'],
			'insertTime'	=> $opt['createTime'] ?? null,
			'domainID'		=> $opt['domainID'] ?? null,
			'pageID'		=> $opt['pageID'] ?? null,
			]);

		// on error
		if($res->status != 204) return $res;

		// return success
		return self::response(201, (object)['mobileID'=>$alt['mobileID'], 'persistID'=>$mand['persistID']]);
		}

	}
