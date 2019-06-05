<?php
/*****
 * Version 1.2.2017-09-27
 **/
namespace dotdev\app\adzoona;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\app\adzoona\partner;
use \dotdev\app\adzoona\stats\service;
use \tools\redis;
use \xadmin\dotdev\livestat;


class stats {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait,
		\dotdev\app\adzoona\stats_trait;

	/* PDO Config */
	protected static function pdo_config() {
		return ['app_adzoona:stats', [

			/* Object: stats_hour */
			'l_stats_hour'						=> 'SELECT * FROM `stats_hour`',

			'l_stats_hour_by_createTime'		=> 'SELECT s.createTime, a.partnerID, a.publisherID, a.domainID, a.pageID, s.param_sum
													FROM `stats_hour` s
													INNER JOIN `partner_access` a ON a.partner_accessID = s.partner_accessID
													WHERE s.createTime BETWEEN ? AND ?',

			'l_stats_hour_by_partner_accessID'	=> 'SELECT createTime, partner_accessID, param_sum
													FROM `stats_hour`
													WHERE partner_accessID = ?',

			'l_stats_hour_by_partnerID'			=> 'SELECT *
													FROM `stats_hour` s
													INNER JOIN `partner_access` a ON a.partner_accessID = s.partner_accessID
													WHERE a.partnerID = ? AND s.createTime BETWEEN ? AND ?',

			'd_stats_hour_by_max_createTime'	=> 'DELETE FROM `stats_hour` WHERE createTime <= ?',

			'd_stats_hour'						=> 'DELETE s.* FROM `stats_hour` s
													INNER JOIN `partner_access` a ON a.partner_accessID = s.partner_accessID
													WHERE a.partnerID = ? AND s.createTime BETWEEN ? AND ?',



			/* Object: stats_day */
			'l_stats_day'						=> 'SELECT * FROM `stats_day`',

			'l_stats_day_by_partnerID'			=> 'SELECT *
													FROM `stats_day` s
													INNER JOIN `partner_access` a ON a.partner_accessID = s.partner_accessID
													WHERE a.partnerID = ? AND s.createTime BETWEEN ? AND ?',

			'i_stats_hour'						=> 'INSERT INTO `stats_hour` (`createTime`, `partner_accessID`,`param_sum`) VALUES (?,?,?)',
			'i_stats_day'						=> 'INSERT INTO `stats_day` (`createTime`, `partner_accessID`,`param_sum`) VALUES (?,?,?)',


			's_partner_accessID'				=> 'SELECT partner_accessID, status
													FROM `partner_access`
													WHERE partnerID = ? AND publisherID = ? AND domainID = ? AND pageID = ? LIMIT 1',

			'd_stats_day'						=> 'DELETE s.* FROM `stats_day` s
													INNER JOIN `partner_access` a ON a.partner_accessID = s.partner_accessID
													WHERE a.partnerID = ? AND s.createTime BETWEEN ? AND ?',


			]];
		}


	/* Redis */
	public static function redis() {

		return redis::load_resource('app_adzoona');
		}


	/* Object: stats_hour */
	public static function get_stats_hour($req = []) {

		// alternative
		$alt = h::eX($req, [
			'from'				=> '~Y-m-d H:i:s/d',
			'to'				=> '~Y-m-d H:i:s/d',
			'partner_accessID'	=> '~1,65535/i',
			'partnerID'			=> '~1,65535/i',
			], $error, true);

		// on error
		if ($error) return self::response(400, $error);

		// param order 1: from, to, partnerID
		if(isset($alt['from']) and isset($alt['to']) and isset($alt['partnerID'])) {

			// load entry
			$list = self::pdo('l_stats_hour_by_partnerID', [$alt['partnerID'], $alt['from'], $alt['to']]);
			if(!$list) return self::response($list === false ? 560 : 404);

			// convert json in object
			foreach($list as $entry) {
				$entry->param_sum = json_decode($entry->param_sum);
				}

			// return success
			return self::response(200, $list);
			}

		// param order 2: partner_accessID
		if(isset($alt['partner_accessID'])) {

			// load entry
			$list = self::pdo('l_stats_hour_by_partner_accessID', [$alt['partner_accessID']]);
			if(!$list) return self::response($list === false ? 560 : 404);

			// convert json in object
			foreach($list as $entry) {
				$entry->param_sum = json_decode($entry->param_sum);
				}

			// return success
			return self::response(200, $list);
			}

		// param order 3: no param
		if(empty($req)) {

			// load list
			$list = self::pdo('l_stats_hour');
			if($list === false) return self::response(560);

			// convert json in object
			foreach($list as $entry) {
				$entry->param_sum = json_decode($entry->param_sum);
				}

			// return success
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need partnerID|from|to|partner_accessID or no parameter');
		}

	public static function create_stats_hour($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'createTime'		=> '~Y-m-d H:i:s/d',
			'partner_accessID'	=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'param_sum'			=> '~/l',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		if(isset($opt['param_sum'])) $opt['param_sum'] = json_encode($opt['param_sum']);

		// set default
		$opt += [
			'param_sum'	=> '{}',
			];

		// create entry
		$stats = self::pdo('i_stats_hour', [$mand['createTime'], $mand['partner_accessID'], $opt['param_sum']]);
		if($stats === false) return self::response(560);

		return self::response(201, (object)['stats'=>$stats]);
		}

	public static function delete_stats_hour($req = []) {

		// alternative
		$mand = h::eX($req, [
			'from'				=> '~Y-m-d H:i:s/d',
			'to'				=> '~Y-m-d H:i:s/d',
			'partnerID'			=> '~1,65535/i',
			], $error);
		if($error) return self::response(400, $error);

		// check stats_hour
		$res = self::get_stats_hour(['partnerID'=>$mand['partnerID'], 'from' => $mand['from'], 'to' => $mand['to']]);
		if($res->status == 404) return self::response(406);
		elseif($res->status != 200) return $res;

		// delete stats_hour
		$delete = self::pdo('d_stats_hour', [$mand['partnerID'], $mand['from'], $mand['to']]);
		if($delete === false) return self::response(560);

		return self::response(200);
		}


	/* Object: stats_day */
	public static function get_stats_day($req = []) {

		// alternative
		$alt = h::eX($req, [
			'from'		=> '~Y-m-d H:i:s/d',
			'to'		=> '~Y-m-d H:i:s/d',
			'partnerID'	=> '~1,65535/i',
			], $error, true);

		// on error
		if ($error) return self::response(400, $error);

		// param order 1: from, to, partnerID
		if(isset($alt['from']) and isset($alt['to']) and isset($alt['partnerID'])) {

			// load list
			$list = self::pdo('l_stats_day_by_partnerID', [$alt['partnerID'], $alt['from'], $alt['to']]);
			if(!$list) return self::response($list === false ? 560 : 404);

			// convert json in object
			foreach($list as $entry) {
				$entry->param_sum = json_decode($entry->param_sum);
				}

			// return success
			return self::response(200, $list);
			}

		// param order 3: no param
		if(empty($req)) {

			// load list
			$list = self::pdo('l_stats_day');
			if($list === false) return self::response(560);

			// convert json in object
			foreach($list as $entry) {
				$entry->param_sum = json_decode($entry->param_sum);
				}

			// return success
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need partnerID|from|to or no parameter');

		}

	public static function create_stats_day($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'createTime'		=> '~Y-m-d H:i:s/d',
			'partner_accessID'	=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'param_sum'			=> '~/l',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		if(isset($opt['param_sum'])) $opt['param_sum'] = json_encode($opt['param_sum']);

		// set default
		$opt += [
			'param_sum'	=> '{}',
			];

		// create entry
		$stats = self::pdo('i_stats_day', [$mand['createTime'], $mand['partner_accessID'], $opt['param_sum']]);
		if($stats === false) return self::response(560);

		return self::response(201, (object)['stats'=>$stats]);
		}

	public static function delete_stats_day($req = []) {

		// alternative
		$mand = h::eX($req, [
			'from'				=> '~Y-m-d H:i:s/d',
			'to'				=> '~Y-m-d H:i:s/d',
			'partnerID'			=> '~1,65535/i',
			], $error);
		if($error) return self::response(400, $error);

		// check stats_hour
		$res = self::get_stats_day(['partnerID'=>$mand['partnerID'], 'from' => $mand['from'], 'to' => $mand['to']]);
		if($res->status == 404) return self::response(406);
		elseif($res->status != 200) return $res;

		// delete stats_hour
		$delete = self::pdo('d_stats_day', [$mand['partnerID'], $mand['from'], $mand['to']]);
		if($delete === false) return self::response(560);

		return self::response(200);
		}


	/* for Adzoona Controller */

	/*
	* called from adzoona controller
	* get stats of current hour
	*/
	public static function calculate_stats_current_hour($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'partnerID'		=> '~1,65535/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		$partner_access = [];

		// count object for statistic
		$count = (object) [
			'insert_success'	=> 0,
			'insert_skip'		=> 0,
			'insert_error'		=> 0,
			'no_data'			=> 0,
			];

		$partnerID = $mand['partnerID'];

		$from = date('Y-m-d H').':00:00';
		$to = date('Y-m-d H:i:s');

		// get partner_access list for the partnerID
		$list = partner::get_partner_access(['partnerID' => $partnerID]);
		if($list->status != 200) return self::response(404);

		$all_partner = $list->data;

		// check for access and create a list only with permission to access publisher/domain/adtarget
		foreach($all_partner as $key => $entry) {

			// if actually partnerID != partnerID in list, remove element
			if($entry->partnerID != $partnerID) {
				unset($all_partner[$key]);
				continue;
				}

			// remove publisher with no access
			if($entry->partnerID != 0 and $entry->publisherID != 0 and $entry->domainID == 0 and $entry->pageID == 0 and $entry->status != 'online') {
				foreach($all_partner as $key2 => $entry2) {
					if($entry2->publisherID == $entry->publisherID) unset($all_partner[$key2]);
					}
				}

			// remove domain with no access
			if($entry->partnerID != 0 and $entry->publisherID != 0 and $entry->domainID != 0 and $entry->pageID == 0 and $entry->status != 'online') {
				foreach($all_partner as $key2 => $entry2) {
					if($entry2->publisherID == $entry->publisherID and $entry2->domainID == $entry->domainID) unset($all_partner[$key2]);
					}
				}

			// remove page with no access
			if($entry->partnerID != 0 and $entry->publisherID != 0 and $entry->domainID != 0 and $entry->pageID != 0 and $entry->status != 'online') {
				foreach($all_partner as $key2 => $entry2) {
					if($entry2->publisherID == $entry->publisherID and $entry2->domainID == $entry->domainID and $entry2->pageID == $entry->pageID) unset($all_partner[$key2]);
					}
				}

			if($entry->partnerID == 0 or $entry->publisherID == 0 or $entry->domainID == 0 or $entry->pageID == 0 or $entry->status != 'online') unset($all_partner[$key]);

			}

		// edited $all_partner save as $partner_access
		$partner_access = $all_partner;

		// if partner_access list == empty, continue with next partnerID
		if(empty($partner_access)) return self::response(404);

		$result = [];

		// load data
		foreach($partner_access as $access) {

			// get summaries
			$res = livestat::get_summary(['from' => $from, 'to' => $to, 'run' => [
				'result_session'	=> ['sum' => 'traffic_session', 'param' => ['publisherID' => $access->publisherID, 'domainID' => $access->domainID, 'pageID' => $access->pageID]],
				'result_click'		=> ['sum' => 'traffic_click', 'param' => ['publisherID' => $access->publisherID, 'domainID' => $access->domainID, 'pageID' => $access->pageID]],
				'result_event'		=> ['sum' => 'traffic_event', 'param' => ['publisherID' => $access->publisherID, 'domainID' => $access->domainID, 'pageID' => $access->pageID]],
				'result_callback'	=> ['sum' => 'traffic_callback', 'param' => ['publisherID' => $access->publisherID, 'domainID' => $access->domainID, 'pageID' => $access->pageID]]
				]]);

			$result[] = $res;
			}

		// merge stats
		$result = self::merge_stats(['result' => $result]);

		$tmp = [];

		// abort if no data was found
		if(empty($result)) {
			return self::response(404, 'no data found');
			}

		// calculate partner_accessID and insert entries
		foreach($result as $entry) {

			// get partner_accessID for partnerID+publisherID+domainID+pageID
			$res = partner::get_partner_access(['partnerID' => $partnerID, 'publisherID' => $entry->publisherID, 'domainID' => $entry->domainID, 'pageID' => $entry->pageID]);
			if($res->status != 200) return self::response(404);

			// partner_accessID
			$partner_accessID = $res->data->partner_accessID;
			$partner_access_status = $res->data->status;

			// create empty object for sum_
			$object_sum = (object) [];

			// foreach key in this entry begins with sum_ -> push in object
			foreach($entry as $key => $value) {
				if(strpos($key, "sum_") === 0) {
					$object_sum->{$key} = $value;
					}
				}

			// create object
			$tmp[] = (object) [
				'createTime'		=> $entry->time,
				'partner_accessID'	=> $partner_accessID,
				'param_sum'			=> $object_sum,
				'partnerID'			=> $partnerID,
				'publisherID'		=> $entry->publisherID,
				'domainID'			=> $entry->domainID,
				'pageID'			=> $entry->pageID,
				'status'			=> $partner_access_status,
				];
			}

		return self::response(200, $tmp);
		}


	/* extended create functions for a timerange */

	/**
	* create stats for each hour for each partner for a server
	*/
	public static function calculate_timeserie_create_hourly_stats($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'server_list'		=> '~/a',
			'from'				=> '~Y-m-d H:i:s/d',
			'to'				=> '~Y-m-d H:i:s/d',
			], $error);

		$opt = h::eX($req, [
			'partnerID'			=> '~1,65535/i',
			], $error, true);

		// error
		if($error) return self::response(400, $error);

		// step range and format
		$step_range = '+1 hour';
		$step_format = 'd. H\h';

		// init times
		$from = $mand['from'];
		$end_time = h::date($mand['to'].' -1 sec');

		// result array
		$timeline = [];
		$stat = [];

		// do for each step
		do{

			// calc and convert next "to" time
			$to = h::date($from.' '.$step_range.' -1 sec');
			$to = h::dtstr($to < $end_time ? $to : $end_time);

			// generate step
			$step = (object)[
				"name"	=> h::dtstr($from, $step_format),
				"time"	=> $from,
				];

			// if partnerID is set, call create_stats_single_hour with partnerID, else without
			if(isset($opt['partnerID'])) {
				$stat[] = service::create_stats_hourly(['from' => $from, 'to' => $to, 'server' => $mand['server_list'], 'partnerID' => $opt['partnerID']]);
				}
			else {
				$stat[] = service::create_stats_hourly(['from' => $from, 'to' => $to, 'server' => $mand['server_list']]);
				}

			// take "to" as new "from" time
			$from = h::dtstr($to.' +1 sec');

			} while (h::date($to) < $end_time);

		// default stat object
		$stat_merge = (object) [
			'insert_success'	=> 0,
			'insert_skip'		=> 0,
			'insert_error'		=> 0,
			'no_data'			=> 0,
			];

		// increase default stat object
		foreach($stat as $entry) {
			$stat_merge->insert_success += $entry->data->insert_success;
			$stat_merge->insert_skip += $entry->data->insert_skip;
			$stat_merge->insert_error += $entry->data->insert_error;
			$stat_merge->no_data += $entry->data->no_data;
			}

		// return result
		return self::response(200, (object)['request' => $mand, 'stats' => $stat_merge]);
		}


	/**
	* create stats for each day for each partner_access for a server
	*/
	public static function calculate_timeserie_create_daily_stats($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'from'				=> '~Y-m-d H:i:s/d',
			'to'				=> '~Y-m-d H:i:s/d',
			], $error);

		$opt = h::eX($req, [
			'partnerID'			=> '~1,65535/i',
			], $error, true);

		// error
		if($error) return self::response(400, $error);

		// step range and format
		$format_list = ['month'=>'Y-m', 'week'=>'Y-m-d', 'day'=>'m-d', 'hour'=>'d. H\h', 'min'=>'H:i'];
		$step_range = '+1 day';
		$step_format = 'm-d';

		// init times
		$from = $mand['from'];
		$end_time = h::date($mand['to'].' -1 sec');

		// result array
		$timeline = [];
		$stat = [];

		// do for each step
		do{

			// calc and convert next "to" time
			$to = h::date($from.' '.$step_range.' -1 sec');
			$to = h::dtstr($to < $end_time ? $to : $end_time);

			// generate step
			$step = (object)[
				"name"	=> h::dtstr($from, $step_format),
				"time"	=> $from,
				];

			// if partnerID is set, call create_stats_single_day with partnerID, else without
			if(isset($opt['partnerID'])) {
				$stat[] = service::create_stats_daily(['from' => $from, 'to' => $to, 'partnerID' => $opt['partnerID']]);
				}
			else {
				$stat[] = service::create_stats_daily(['from' => $from, 'to' => $to]);
				}


			// take "to" as new "from" time
			$from = h::dtstr($to.' +1 sec');

			} while (h::date($to) < $end_time);

		// default stat object
		$stat_merge = (object) [
			'insert_success'	=> 0,
			'insert_skip'		=> 0,
			'insert_error'		=> 0,
			'no_hour_data'		=> 0
			];

		// increase default stat object
		foreach($stat as $entry) {
			$stat_merge->insert_success += $entry->data->insert_success;
			$stat_merge->insert_skip += $entry->data->insert_skip;
			$stat_merge->insert_error += $entry->data->insert_error;
			$stat_merge->no_hour_data += $entry->data->no_hour_data;
			}

		// return result
		return self::response(200, (object)['request' => $mand, 'stats' => $stat_merge]);
		}


	/**
	* delete stats_hour entries older than 1 month
	*/
	public static function monthly_delete_stats_hour() {

		$last_day = date('Y-m-d', strtotime('-1 month'));

		// delete every entry older than 1 month
		$delete = self::pdo('d_stats_hour_by_max_createTime', [$last_day]);

		//return error
		if(!$delete) return self::response($delete === false ? 560 : 404);

		// return result
		return self::response(200, $delete);
		}

	}
