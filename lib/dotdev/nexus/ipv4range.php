<?php
/*****
 * Version 1.5.2018-05-28
**/
namespace dotdev\nexus;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \tools\http;

class ipv4range {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config() {
		return ['mt_nexus', [

			// queries: ipv4range
			's_ipv4range'								=> 'SELECT * FROM `ipv4range` WHERE `ipv4rangeID` = ? LIMIT 1',
			's_ipv4range_by_ip'							=> 'SELECT * FROM `ipv4range` WHERE `ip_start` <= ? AND `ip_end` >= ? LIMIT 1',
			'l_ipv4range'								=> 'SELECT * FROM `ipv4range` ORDER BY `ip_start` ASC',
			'l_ipv4range_by_countryID'					=> 'SELECT * FROM `ipv4range` WHERE `countryID` = ? ORDER BY `ip_start` ASC',
			'l_ipv4range_by_operatorID'					=> 'SELECT * FROM `ipv4range` WHERE `operatorID` = ? ORDER BY `ip_start` ASC',

			'i_ipv4range'								=> 'INSERT INTO `ipv4range` (`countryID`,`operatorID`,`ip_start`,`ip_end`,`ip_total`,`owner`) VALUES (?,?,?,?,?,?)',
			'u_ipv4range'								=> 'UPDATE `ipv4range` SET `countryID` = ?, `operatorID` = ?, `ip_start` = ?, `ip_end` = ?, `ip_total` = ?, `owner` = ? WHERE `ipv4rangeID` = ?',
			'd_ipv4range'								=> 'DELETE FROM `ipv4range` WHERE `ipv4rangeID` = ?',
			'd_ipv4range_by_countryID'					=> 'DELETE FROM `ipv4range` WHERE `countryID` = ?',

			// queries: ipv4range_regex
			's_ipv4range_regex'							=> 'SELECT * FROM `ipv4range_regex` WHERE `ipv4range_regexID` = ? LIMIT 1',
			'l_ipv4range_regex'							=> 'SELECT * FROM `ipv4range_regex` ORDER BY `countryID` ASC',
			'l_ipv4range_regex_by_operatorID'			=> 'SELECT * FROM `ipv4range_regex` WHERE `operatorID` = ?',
			'l_ipv4range_regex_by_countryID'			=> 'SELECT * FROM `ipv4range_regex` WHERE `countryID` = ?',

			'i_ipv4range_regex'							=> 'INSERT INTO `ipv4range_regex` (`countryID`,`operatorID`,`regex`) VALUES (?,?,?)',
			'u_ipv4range_regex'							=> 'UPDATE `ipv4range_regex` SET `operatorID` = ?, `regex` = ? WHERE `ipv4range_regexID` = ?',
			'd_ipv4range_regex'							=> 'DELETE FROM `ipv4range_regex` WHERE `ipv4range_regexID`=?',
			]];
		}


	/* Redis */
	protected static function redis(){

		return redis::load_resource('mt_nexus');
		}


	/* lvl1 cache */
	protected static $lvl1_cache = [];



	/* Object: ipv4_range */
	public static function get_range($req = []) {

		// alternative
		$alt = h::eX($req, [
			'ipv4rangeID'	=> '~1,4294967295/i',
			'countryID'		=> '~1,255/i',
			'operatorID'	=> '~1,255/i',
			'ipv4'			=> '~^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$',
			'IP'			=> '~^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$', // DEPRECATED
			], $error, true);

		// error
		if($error) return self::response(400, $error);

		// DEPRECATED
		if(isset($alt['IP'])){
			if(!isset($alt['ipv4'])) $alt['ipv4'] = $alt['IP'];
			unset($alt['IP']);
			}


		// define function to expanded entry
		$build_entry = function($entry){
			return (object)[
				'ipv4rangeID'	=> $entry->ipv4rangeID,
				'countryID'		=> $entry->countryID,
				'operatorID'	=> $entry->operatorID,
				'ip_start_long'	=> $entry->ip_start,			// = int
				'ip_end_long'	=> $entry->ip_end,
				'ip_start'		=> long2ip($entry->ip_start),	// = string
				'ip_end'		=> long2ip($entry->ip_end),
				'ip_total'		=> $entry->ip_total,
				'updated_at'	=> $entry->updated_at,
				'owner'			=> $entry->owner,
				];
			};

		// param order 1: ipv4rangeID
		if(isset($alt['ipv4rangeID'])){

			// define cache key
			$cache_key = 'ipv4:by_ipv4rangeID:'.$alt['ipv4rangeID'];

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
				$entry = self::response(200, $redis->get($cache_key));
				}

			// else
			else{

				// seach in DB
				$entry = self::pdo('s_ipv4range', [$alt['ipv4rangeID']]);

				// on error or not found
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// expand entry
				$entry = $build_entry($entry);

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

		// param order 2: countryID
		if(isset($alt['countryID'])){

			// load list from DB
			$list = self::pdo('l_ipv4range_by_countryID', [$alt['countryID']]);

			// on error
			if($list === false) return self::response(560);

			// expand entries
			foreach($list as $k => $entry) {
				$list[$k] = $build_entry($entry);
				}

			// return result
			return self::response(200, $list);
			}

		// param order 3: operatorID
		if(isset($alt['operatorID'])){

			// load list from DB
			$list = self::pdo('l_ipv4range_by_operatorID', [$alt['operatorID']]);

			// on error
			if($list === false) return self::response(560);

			// expand entries
			foreach($list as $k => $entry) {
				$list[$k] = $build_entry($entry);
				}

			// return result
			return self::response(200, $list);
			}

		// param order 4: ipv4
		if(isset($alt['ipv4'])) {

			// define integer version of IP
			$ip_long = ip2long($alt['ipv4']);

			// load redis
			$redis = self::redis();

			// define cache key
			$cache_key = 'iplookup';

			// first check if a preloading is needed
			if($redis and !$redis->exists($cache_key)){

				// define lock key
				$lock_key = 'ipv4range_preloading';

				// try to get priority (reload status every 0.4 seconds, but timeout after 1.6 seconds)
				$lock_status = redis::lock_process($redis, $lock_key, ['timeout_ms'=>2000, 'retry_ms'=>500]);

				// if this process got the priority
				if($lock_status == 100){

					// fallback code using DB
					$list = self::pdo('l_ipv4range');

					// on error
					if($list === false) return self::response(560);

					// for each entry
					foreach($list as $k => $entry){

						// expand entry
						$list[$k] = $build_entry($entry);

						// cache entry
						$redis->zadd($cache_key, $entry->ip_end, $list[$k]);
						}

					// special preloading code here
					$redis->set($cache_key, $list, ['ex'=>21600, 'nx']); // 6 hours, set only if not set

					// set lock_status to 200 und expire lock_key after 2 minutes
					$lock_status = redis::unlock_process($redis, $lock_key, ['ttl'=>120]);
					}
				}

			// best case, simply retry accessing redis here
			if($redis and $redis->exists($cache_key)){

				// access preloaded stuff if wanted
				$list = $redis->zrangebyscore($cache_key, $ip_long, 4294967295, ['limit' => [0,1]]);

				// for each entry
				foreach($list as $entry) {

					// 0 or 1 entry; if entry -> check if entry is the one looking for given IP
					if($ip_long >= $entry->ip_start_long and $ip_long <= $entry->ip_end_long){

						// return found entry
						return self::response(200, $entry);
						}
					}

				// return not found
				return self::response(404);
				}

			// search in DB
			$entry = self::pdo('s_ipv4range_by_ip', [$ip_long, $ip_long]);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// expand entry
			$entry = $build_entry($entry);

			// return entry
			return self::response(200, $entry);
			}

		// param order 5: no param
		if(empty($req)) {

			// load list from DB
			$list = self::pdo('l_ipv4range');

			// on error
			if($list === false) return self::response(560);

			// expand entries
			foreach($list as $k => $entry) {
				$list[$k] = $build_entry($entry);
				}

			// return result
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need ipv4rangeID or countryID or operatorID or IP or no parameter');
		}

	public static function create_range($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'ip_start'			=> '~^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$',
			'ip_end'			=> '~^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$',
			'owner'				=> '~/s',
			'countryID'			=> '~1,255/i',
			'operatorID'		=> '~0,255/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// define cache_key
		$cache_key = 'iplookup';

		// defines ip_total, both IPs in int
		$ip_start_long = ip2long($mand['ip_start']);
		$ip_end_long = ip2long($mand['ip_end']);
		$ip_total = $ip_end_long - $ip_start_long + 1;

		// init redis
		$redis = self::redis();

		// check if redis with cache key exists, if not, build it new (complete db entries)
		if($redis and !$redis->exists($cache_key)){

			// load list
			$list = self::pdo('l_ipv4range');

			// on error
			if($list === false) return self::response(560);

			// for each entry
			foreach($list as $entry){

				// add to cache
				$redis->zadd($cache_key, $entry->ip_end, (object)[
					'ipv4rangeID'	=> $entry->ipv4rangeID,
					'countryID'		=> $entry->countryID,
					'operatorID'	=> $entry->operatorID,
					'ip_start_long' => $entry->ip_start,
					'ip_end_long'	=> $entry->ip_end,
					'ip_start'		=> long2ip($entry->ip_start),
					'ip_end'		=> long2ip($entry->ip_end),
					'ip_total'		=> $ip_total,
					'owner'			=> $entry->owner
					]);
				}
			}

		//
		$result = $redis->zrangebyscore($cache_key, $ip_start_long, 4294967295, ['limit' => [0,1]]);

		// for each entry
		foreach($result as $entry){

			// check if new ip_start is between an entry and its IPs or new ip_end is bigger than an ip_start of an entry
			if(($ip_start_long >= $entry->ip_start_long and $ip_end_long <= $entry->ip_end_long) or ($ip_end_long >= $entry->ip_start_long)){

				// return conflict
				return self::response(409);
				}
			}

		// insert entry
		$ipv4rangeID = self::pdo('i_ipv4range', [$mand['countryID'], $mand['operatorID'], $ip_start_long, $ip_end_long, $ip_total, $mand['owner']]);

		// on error
		if($ipv4rangeID === false) return self::response(560);

		// add to cache
		$redis->zadd($cache_key, $ip_end_long, (object) [
			'ipv4rangeID'	=> $ipv4rangeID,
			'countryID'		=> $mand['countryID'],
			'operatorID'	=> $mand['operatorID'],
			'ip_start_long'	=> $ip_start_long,
			'ip_end_long'	=> $ip_end_long,
			'ip_start'		=> $mand['ip_start'],
			'ip_end'		=> $mand['ip_end'],
			'ip_total'		=> $ip_total,
			'owner'			=> $mand['owner']
			]);

		// return success
		return self::response(201, (object)['ipv4rangeID' => $ipv4rangeID]);
		}

	public static function update_range($req = []) {

		//mandatory
		$mand = h::eX($req, [
			'ipv4rangeID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'countryID'			=> '~1,255/i',
			'operatorID'		=> '~0,255/i',
			'ip_start'			=> '~^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$',
			'ip_end'			=> '~^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$',
			'owner'				=> '~/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_range([
			'ipv4rangeID' => $mand['ipv4rangeID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$range = $res->data;

		// define cache key
		$cache_key = 'iplookup';

		// defines ip_total, both IPs in int
		$ip_start_long = ip2long($opt['ip_start']);
		$ip_end_long = ip2long($opt['ip_end']);
		$ip_total = $ip_end_long - $ip_start_long + 1;

		// init redis
		$redis = self::redis();

		// check if redis with cache key exists, if not, build it new (complete db entries)
		if($redis and !$redis->exists($cache_key)){

			// load list
			$list = self::pdo('l_ipv4range');

			// on error
			if($list === false) return self::response(560);

			// for each entry
			foreach($list as $entry){

				// add to cache
				$redis->zadd($cache_key, $entry->ip_end, (object)[
					'ipv4rangeID'	=> $entry->ipv4rangeID,
					'countryID'		=> $entry->countryID,
					'operatorID'	=> $entry->operatorID,
					'ip_start_long' => $entry->ip_start,
					'ip_end_long'	=> $entry->ip_end,
					'ip_start'		=> long2ip($entry->ip_start),
					'ip_end'		=> long2ip($entry->ip_end),
					'ip_total'		=> $ip_total,
					'owner'			=> $entry->owner
					]);
				}
			}

		//
		$result = $redis->zrangebyscore($cache_key, $ip_start_long, 4294967295, ['limit' => [1,1]]);

		// for each entry
		foreach($result as $entry){

			// check if new ip_start is between an entry and its IPs or new ip_end is bigger than an ip_start of an entry
			if(($ip_start_long >= $entry->ip_start_long and $ip_end_long <= $entry->ip_end_long) or ($ip_end_long >= $entry->ip_start_long)){

				// return conflict
				return self::response(409);
				}
			}

		// for each entry in $opt, set the given value to the key
		foreach($opt as $k => $v){
			$range->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_ipv4range', [$range->countryID, $range->operatorID, $ip_start_long, $ip_end_long, $ip_total, $range->owner, $range->ipv4rangeID]);

		// on error
		if($upd === false) return self::response(560);

		// overwrite entry in cache
		$redis->zadd($cache_key, $ip_end_long, (object) [
			'ipv4rangeID'	=> $range->ipv4rangeID,
			'countryID'		=> $range->countryID,
			'operatorID'	=> $range->operatorID,
			'ip_start_long'	=> $ip_start_long,
			'ip_end_long'	=> $ip_end_long,
			'ip_start'		=> $range->ip_start,
			'ip_end'		=> $range->ip_end,
			'ip_total'		=> $ip_total,
			'owner'			=> $range->owner
			]);

		// delete cached entry
		$redis->zRemRangeByScore($cache_key, $ip_start_long, $ip_end_long);

		// define cache key
		$cache_key = 'ipv4:by_ipv4rangeID:'.$mand['ipv4rangeID'];

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);

		// if redis is accessable
		if($redis){

			// expire entry
			$redis->setTimeout($cache_key, 0);
			}

		// return success
		return self::response(204);
		}

	public static function delete_range($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'ipv4rangeID'		=> '~1,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// delete entry in DB
		$entry = self::pdo('d_ipv4range', [$mand['ipv4rangeID']]);

		// on error or not found
		if(!$entry) return self::response($entry === false ? 560 : 404);

		// init redis
		$redis = self::redis();

		// if redis accessable
		if($redis){

			// delete its key
			$redis->del('ipv4:by_ipv4rangeID:'.$mand['ipv4rangeID']);
			$redis->del('iplookup');
			}

		// return success
		return self::response(204);
		}



	/* ipv4range_regex */
	public static function get_ipv4range_regex($req = []) {

		// alternative
		$alt = h::eX($req, [
			'ipv4range_regexID'	=> '~1,65535/i',
			'countryID'			=> '~1,255/i',
			'operatorID'		=> '~1,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: ipv4range_regexID
		if(isset($alt['ipv4range_regexID'])){

			// search in DB
			$entry = self::pdo('s_ipv4range_regex', [$alt['ipv4range_regexID']]);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);


			// return entry
			return self::response(200, $entry);
			}

		// param order 2: countryID
		if(isset($alt['countryID'])){

			// load list
			$list = self::pdo('l_ipv4range_regex_by_countryID', [$alt['countryID']]);

			// on error
			if($list === false) return self::response(560);

			// return result
			return self::response(200, $list);
			}

		// param order 3: operatorID
		if(isset($alt['operatorID'])){

			// load list
			$list = self::pdo('l_ipv4range_regex_by_operatorID', [$alt['operatorID']]);

			// on error
			if($list === false) return self::response(560);

			// return result
			return self::response(200, $list);
			}

		if(empty($req)){

			// load list
			$list = self::pdo('l_ipv4range_regex');

			// on error
			if($list === false) return self::response(560);

			// return result
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need ipv4range_regexID or countryID or operatorID or nothing');
		}

	public static function create_ipv4range_regex($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'countryID'		=> '~1,255/i',
			'operatorID'	=> '~0,255/i',
			'regex'			=> '~/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// create entry
		$ipv4range_regexID = self::pdo('i_ipv4range_regex', [$mand['countryID'], $mand['operatorID'], $mand['regex']]);

		// on error
		if($ipv4range_regexID === false) return self::response(560);

		// return success
		return self::response(201, (object)['ipv4range_regexID' => $ipv4range_regexID]);
		}

	public static function delete_ipv4range_regex($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'ipv4range_regexID'		=> '~1,65535/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// delete entry in DB
		$entry = self::pdo('d_ipv4range_regex', [$mand['ipv4range_regexID']]);

		// on error
		if($entry === false) return self::response(560);

		// return success
		return self::response(204);
		}

	public static function update_ipv4range_regex($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'ipv4range_regexID'	=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'operatorID'		=> '~0,255/i',
			'regex'				=> '~/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_ipv4range_regex([
			'u_ipv4range_regex'	=> $mand['ipv4range_regexID'],
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
		$upd = self::pdo('u_aggregator', [$entry->name, $entry->aggregatorID]);

		// on error
		if($upd === false) return self::response(560);

		// return success
		return self::response(204);
		}



	/* Helper */
	public static function import_csv($req = []) {

		// alternative
		$mand = h::eX($req, [
			'url'				=> '~^(?:http(?:|s)\:\/\/)[a-zA-Z0-9\.\/]{0,150}',
			'countryID'			=> '~1,255/i',
			'ip_startColumn'	=> '~/i',
			'ip_endColumn'		=> '~/i',
			'ownerColumn'		=> '~/i',
			'delimiter'			=> '~[,;|t]{1}',
			'sumLast'			=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// additional check if columns are not equal
		if(in_array($mand['ip_startColumn'], [$mand['ip_endColumn'], $mand['ownerColumn']]) || $mand['ip_endColumn'] == $mand['ownerColumn']) return self::response(400, 'two columns are the same');

		// load site informations incl. content of csv
		$curl_obj = http::curl_obj([
			'methode'	=> 'GET',
			'url'		=> $mand['url'],
			]);

		// if content include html tag '<' (like a website that cannot be reached) return error
		if($curl_obj->httpcode != 200) return self::response(404);

		// save content as string
		$content = $curl_obj->content;

		// if content include html tag '<' (like a website that cannot be reached) return error
		if(strpos($content, "<") !== false) return self::response(406);

		// define field positions
		$f_ipstart = $mand['ip_startColumn']-1;
		$f_ipend = $mand['ip_endColumn']-1;
		$f_owner = $mand['ownerColumn']-1;

		// define import list
		$list = [];

		// set the correct delimiter for tab
		if($mand['delimiter'] == 't') $mand['delimiter'] = "\t";

		// trim content and explode lines
		$lines = explode("\n", trim($content));

		// count how many entries exist
		$line_count = count($lines);

		// if there aren't any lines, return error
		if(!$line_count) return self::response(406);

		// define minimal field count
		$min_fields = null;

		// check up to 10 lines
		for($i = 0; $i < min(10, $line_count); $i++){

			// count fields
			$field_count = substr_count($lines[$i], $mand['delimiter']);

			// check if each defined field is not beyond given field range
			if($field_count < max($f_ipstart, $f_ipend, $f_owner)){

				// return error
				return self::response(406);
				}

			// take lowest field count
			$min_fields = ($min_fields === null) ? $field_count : min($min_fields, $field_count);
			}

		// build the result object
		foreach($lines as $line) {

			// explode fields
			$fields = $mand['sumLast'] ? explode($mand['delimiter'], $line, $min_fields) : explode($mand['delimiter'], $line);

			// for each needed field
			foreach([$f_ipstart, $f_ipend, $f_owner] as $k){

				// if owner field is empty, set it as unknown
				if($k == $f_owner and empty($fields[$k])) $fields[$k] = 'unknown';

				// skip line if field does not exist
				if(!isset($fields[$k])) continue 2;

				// trim field
				$fields[$k] = trim($fields[$k]);

				// skip line if field is empty (TODO: deeper validation is useful here)
				if(!$fields[$k]) continue 2;
				}

			// add range
			$list[] = (object)[
				'ip_start'	=> ip2long($fields[$f_ipstart]),
				'ip_end'	=> ip2long($fields[$f_ipend]),
				'owner'		=> $fields[$f_owner]
				];
			}

		// import ranges
		return self::import_country_range( [
			'countryID'	=> $mand['countryID'],
			'list'		=> $list,
			]);
		}

	public static function import_country_range($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'countryID'	=> '~1,255/i',
			'list'		=> '~!empty/a',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// for each list entry
		foreach($mand['list'] as $k => $entry) {

			// deep mandatory
			$mand['list'][$k] = h::eX($entry, [
				'ip_start'	=> '~1,4294967295/i',
				'ip_end'	=> '~1,4294967295/i',
				'owner'		=> '~/s',
				], $error);

			// on error
			if($error) return self::response(400, $error);
			}

		// loads list from ipv4range_regex table with countryID
		$regex_list = self::pdo('l_ipv4range_regex_by_countryID', $mand['countryID']);

		// on error
		if($regex_list === false) return self::response(560);

		// deletes all entries with countryID (to clean table)
		$del = self::pdo('d_ipv4range_by_countryID', $mand['countryID']);

		// on error
		if($del === false) return self::response(560);

		// for each entry
		foreach($mand['list'] as $entry) {

			// define entry for operatorID and IP sum
			$operatorID = 0;
			$ip_total = $entry['ip_end'] - $entry['ip_start'] + 1;

			// for each regex
			foreach($regex_list as $regex) {

				// try to identify operatorID with regex
				if(preg_match('/'.$regex->regex.'/', $entry['owner'])){

					// take operatorID
					$operatorID = $regex->operatorID;
					break;
					}
				}

			// insert range
			$ipv4rangeID = self::pdo('i_ipv4range', [$mand['countryID'], $operatorID, $entry['ip_start'], $entry['ip_end'], $ip_total, $entry['owner']]);

			// on error
			if($ipv4rangeID === false) return self::response(560);
			}

		// completly unset lvl1 cache
		self::$lvl1_cache = [];

		// unset redis cache
		self::redis_unset([
			'search' => 'ipv4:*',
			]);
		self::redis_unset([
			'search' => 'iplookup',
			]);

		// return success
		return self::response(204);
		}

	}
