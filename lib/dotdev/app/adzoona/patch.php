<?php
/*****
 * Version 1.1.2018-09-03
**/
namespace dotdev\app\adzoona;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\app\adzoona\partner;
use \dotdev\app\adzoona\stats;
use \dotdev\nexus\domain;
use \dotdev\nexus\levelconfig as levelconfig_nexus;
use \tools\http;

class patch {

	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/**
	* HELP:
	* partner_accessID == unique combination of partnerID, publisherID, domainID and pageID
	*/

	/**
	* function only for cronjob to create stats_hour
	*/
	public static function cronjob_create_stats_hour($req = []){

		// mandatory
		$mand = h::eX($req, [
			'server_list'		=> '~/a',
			// 'step'				=> '~^(\+[0-9]{1,2} (hour))$',
			], $error);

		// error
		if($error) return self::response(400, $error);

		// set to null
		$stats_hour = null;
		$stats_day = null;

		// calculate from (== one hour back) and to (== that hour with min:sec => 00:00) => exactly previous hour
		$from = date('Y-m-d H:00:00', strtotime('-1 hour'));
		$to = date('Y-m-d H:00:00');

		// create stats hour
		$stats_hour = self::create_stats_hour(['from' => $from, 'to' => $to, 'server_list' => $mand['server_list']]);

		// if current time is new day (00:min:sec) call create_stats_day for create stats for previous day
		if(substr($to, -8) == '00:00:00') $stats_day = self::cronjob_create_stats_day();

		// return
		return self::response(200, ['request_hour' => $stats_hour->data, 'request_day' => $stats_day != null ? $stats_day->data : null]);
		}


	/**
	* function only for cronjob to create stats_day
	*/
	protected static function cronjob_create_stats_day(){

		// calculate from (== one day back) and to (== that day with time: 00:00:00) => exactly previous day
		$from = date('Y-m-d 00:00:00', strtotime('-1 day'));
		$to = h::dtstr('today');

		// if this function is called (new day) than delete hour stats 1 month ago
		$stats_delete = self::delete_stats_hour();

		// create stats for previous day
		$get_stats_day = self::create_stats_day(['from' => $from, 'to' => $to]);

		// return both results
		return self::response(200, ['stats_delete' => $stats_delete, 'stats_day' => $get_stats_day]);
		}


	/**
	* create stats for each hour for each partner for a server
	*/
	public static function create_stats_hour($req = []){

		// mandatory
		$mand = h::eX($req, [
			'server_list'		=> '~/a',
			'from'				=> '~Y-m-d H:i:s/d',
			'to'				=> '~Y-m-d H:i:s/d',
			// 'step'				=> '~^(\+[0-9]{1,2} (hour))$',
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

			// if partnerID is set, call get_stats_hour with partnerID, else without
			if(isset($opt['partnerID'])) {
				$stat[] = self::get_stats_hour(['from' => $from, 'to' => $to, 'server' => $mand['server_list'], 'partnerID' => $opt['partnerID']]);
				}
			else {
				$stat[] = self::get_stats_hour(['from' => $from, 'to' => $to, 'server' => $mand['server_list']]);
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
		return self::response(200, ['request' => $mand, 'stats' => $stat_merge]);
		}


	/**
	* create stats for each day for each partner_access for a server
	*/
	public static function create_stats_day($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from'				=> '~Y-m-d H:i:s/d',
			'to'				=> '~Y-m-d H:i:s/d',
			// 'step'				=> '~^(\+[0-9]{1,2} (month|week|day|hour|min))$',
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

			// if partnerID is set, call get_stats_hour with partnerID, else without
			if(isset($opt['partnerID'])) {
				$stat[] = self::get_stats_day(['from' => $from, 'to' => $to, 'partnerID' => $opt['partnerID']]);
				}
			else {
				$stat[] = self::get_stats_day(['from' => $from, 'to' => $to]);
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
		return self::response(200, ['request' => $mand, 'stats' => $stat_merge]);
		}


	/**
	* delete stats_hour entries older than 1 month
	*/
	protected static function delete_stats_hour(){

		$current_day = date('Y-m-d');
		$last_day = date('Y-m-d', strtotime('-1 month'));

		// delete every entry older than 1 month
		$delete = \dotdev\app\adzoona\stats::pdo_query([
			'query' => "
				DELETE FROM `stats_hour`
					WHERE createTime <= ?
				",
			'param' => [$last_day],
			]);

		// return result
		return self::response(200, $delete->data);
		}


	/*
	* called manually
	* get stats of current hour
	* no write into DB
	*/
	public static function get_stats_current_hour($req = []){

		// mandatory
		$mand = h::eX($req, [
			'server'		=> '~/a',
			'from'			=> '~Y-m-d H:i:s/d',
			'to'			=> '~Y-m-d H:i:s/d',
			'partnerID'		=> '~1,65535/i'
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
		$server_list = $mand['server'];

		// load data
		foreach($server_list as $server) {

			foreach($partner_access as $access) {

				// start requesting servers
				$res = self::ajax_nsexec([
					'remote' => $server, 'ns' => '\\xadmin\\dotdev\\livestat::get_summary', 'data' => ['from' => $mand['from'], 'to' => $mand['to'], 'run' => [
						'result_session'	=> ['sum' => 'traffic_session', 'param' => ['publisherID' => $access->publisherID, 'domainID' => $access->domainID, 'pageID' => $access->pageID]],
						'result_click'		=> ['sum' => 'traffic_click', 'param' => ['publisherID' => $access->publisherID, 'domainID' => $access->domainID, 'pageID' => $access->pageID]],
						'result_event'		=> ['sum' => 'traffic_event', 'param' => ['publisherID' => $access->publisherID, 'domainID' => $access->domainID, 'pageID' => $access->pageID]]
						]]
					]);

				$result[] = json_decode($res);
				}
			}

		// merge stats
		$result = self::merge_stats($result);

		$tmp = [];

		// abort if no data was found
		if(empty($result)) {
			$count->no_data++;
			return self::response(404, 'no data found');
			}

		// calculate partner_accessID and insert entries
		foreach($result as $entry) {

			// get partner_accessID for partnerID+publisherID+domainID+pageID
			$res = \dotdev\app\adzoona\partner::pdo_query([
				'query' => "
					SELECT
						partner_accessID, status
					FROM `partner_access`
					WHERE partnerID = ? AND publisherID = ? AND domainID = ? AND pageID = ? LIMIT 1
					",
				'param' => [$partnerID, $entry->publisherID, $entry->domainID, $entry->pageID],
				]);

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


	/**
	* called from create_stats_hour
	* get all partnerIDs with pubID != 0, domID != 0 and pageID != 0
	* for each combination get_summary and write into DB
	*/
	protected static function get_stats_hour($req = []){

		// mandatory
		$mand = h::eX($req, [
			'server'		=> '~/a',
			'from'			=> '~Y-m-d H:i:s/d',
			'to'			=> '~Y-m-d H:i:s/d',
			], $error);

		$opt = h::eX($req, [
			'partnerID'		=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// create default arrays
		$domainIDs		= [];
		$firmIDs		= [];

		if(isset($opt['partnerID'])) {

			// get partner_access list for the partnerID
			$list = partner::get_partner_access(['partnerID' => $opt['partnerID']]);
			if($list->status != 200) return self::response(404);
			}
		else {

			// get partner_access list for all partnerIDs
			$list = partner::get_partner_access([]);
			if($list->status != 200) return self::response(404);
			}

		$partnerIDs = [];

		// collect all partnerID's in array
		foreach($list->data as $partner) {
			$partnerIDs[$partner->partnerID] = $partner->partnerID;
			}

		$partner_access = [];

		// count object for statistic
		$count = (object) [
			'insert_success'	=> 0,
			'insert_skip'		=> 0,
			'insert_error'		=> 0,
			'no_data'			=> 0,
			];

		// reduce partnerIDs
		foreach($partnerIDs as $partnerID) {

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

			$partner_access = $all_partner;

			// if partner_access list == empty, continue with next partnerID
			if(empty($partner_access)) continue;

			$domain_access = [];
			$publisher_access = [];

			// create array of domainIDs and array of publisherIDs
			foreach($partner_access as $entry) {
				$domain_access[$entry->domainID] = $entry->domainID;
				$publisher_access[$entry->publisherID] = $entry->publisherID;
				}

			$domainIDs = [];
			$firmIDs = [];

			/*if there is no server_list, this can be activated to automaticly get all needed server*/

			// for each publisherID collect firmIDs
			/*foreach($publisher_access as $publisherID) {
				$domain = domain::get_adtarget(['publisherID' => $publisherID]);

				if($domain->status == 200) {
					foreach($domain->data as $entry) {

						if(isset($domain_access[$entry->domainID])) {

							$domainIDs[] = (object) [
							'domainID'	=> $entry->domainID,
							'firmID'	=> $entry->firmID,
							];

							$firmIDs[] = $entry->firmID;
							}
						}
					}
				}

			// unique domainID's (+ firmID) & firmID's
			$domainIDs = array_unique($domainIDs, SORT_REGULAR);
			$firmIDs = array_unique($firmIDs);

			$server = [];

			// collect levelconfig -> server + firmID
			foreach($firmIDs as $firmID) {
				$lc = levelconfig_nexus::get(['level' => 'firm', 'firmID' => $firmID]);
				if($lc->status != 200) return;
				if(isset($lc->data['firm:servercom_domain'])) $server[] = (object) ['server' => $lc->data['firm:servercom_domain'], 'firmID' => $firmID];
				}*/

			$result = [];
			$server_list = $mand['server'];

			// load data
			foreach($server_list as $server) {

				foreach($partner_access as $access) {

					// start requesting servers
					$res = self::ajax_nsexec([
						'remote' => $server, 'ns' => '\\xadmin\\dotdev\\livestat::get_summary', 'data' => ['from' => $mand['from'], 'to' => $mand['to'], 'run' => [
							'result_session'	=> ['sum' => 'traffic_session', 'param' => ['publisherID' => $access->publisherID, 'domainID' => $access->domainID, 'pageID' => $access->pageID]],
							'result_click'		=> ['sum' => 'traffic_click', 'param' => ['publisherID' => $access->publisherID, 'domainID' => $access->domainID, 'pageID' => $access->pageID]],
							'result_event'		=> ['sum' => 'traffic_event', 'param' => ['publisherID' => $access->publisherID, 'domainID' => $access->domainID, 'pageID' => $access->pageID]]
							]]
						]);
					$result[] = json_decode($res);
					}
				}

			// merge stats
			$result = self::merge_stats($result);

			// abort if no data was found
			if(empty($result)) {
				$count->no_data++;
				continue;
				}

			// calculate partner_accessID and insert entries
			foreach($result as $entry) {

				// get partner_accessID for partnerID+publisherID+domainID+pageID
				$res = \dotdev\app\adzoona\partner::pdo_query([
					'query' => "
						SELECT
							partner_accessID
						FROM `partner_access`
						WHERE partnerID = ? AND publisherID = ? AND domainID = ? AND pageID = ? LIMIT 1
						",
					'param' => [$partnerID, $entry->publisherID, $entry->domainID, $entry->pageID],
					]);

				// partner_accessID
				$partner_accessID = $res->data->partner_accessID;

				// look if createTime and partner_accessID already exist, than skip insertion
				$res = \dotdev\app\adzoona\partner::pdo_query([
					'query' => "
						SELECT
							partner_accessID, createTime
						FROM `stats_hour`
						WHERE partner_accessID = ? AND createTime = ? LIMIT 1
						",
					'param' => [$partner_accessID, $entry->time],
					]);

				// skip if entry found (to prevent duplicate entry error)
				if(isset($res->data)) {
					$count->insert_skip++;
					continue;
					}

				// create empty object for sum_
				$object_sum = (object) [];

				// foreach key in this entry begins with sum_ -> push in object
				foreach($entry as $key => $value) {
					if(strpos($key, "sum_") === 0) {
						$object_sum->{$key} = $value;
						}
					}

				// create stats_hour entry
				$res = stats::create_stats_hour([
					'createTime'		=> $entry->time,
					'partner_accessID'	=> $partner_accessID,
					'param_sum'			=> $object_sum,
					]);

				if($res->status != 201) {
					$count->insert_error++;
					continue;
					}

				$count->insert_success++;

				}

			}

		return self::response(200, $count);
		}


	/**
	* called from create_stats_day
	* get all partner_accessIDs
	* for each ID sum for a day and write into DB
	*/
	protected static function get_stats_day($req = []){

		// mandatory
		$mand = h::eX($req, [
			// 'server'		=> '~/l',
			'from'			=> '~Y-m-d H:i:s/d',	// begin day
			'to'			=> '~Y-m-d H:i:s/d',	// end day
			], $error);

		// optional
		$opt = h::eX($req, [
			'partnerID'		=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// count object for statistic
		$count = (object) [
			'insert_success'=> 0,
			'insert_error'	=> 0,
			'insert_skip'	=> 0,
			'no_hour_data'	=> 0,
			];

		if(isset($opt['partnerID'])) {

			// sum stats_hour for one partner_accessID for a day
			$list = \dotdev\app\adzoona\stats::pdo_query([
				'query' => "
					SELECT s.partner_accessID, s.param_sum
						FROM `stats_hour` s
						INNER JOIN `partner_access` a ON a.partner_accessID = s.partner_accessID
						WHERE s.createTime BETWEEN ? AND ? AND a.partnerID = ?
					",
				'param' => [$mand['from'], $mand['to'], $opt['partnerID']],
				]);
			}
		else {

			// sum stats_hour for all partner_accessID for a day
			$list = \dotdev\app\adzoona\stats::pdo_query([
				'query' => "
					SELECT s.partner_accessID, s.param_sum
						FROM `stats_hour` s
						WHERE s.createTime BETWEEN ? AND ?
					",
				'param' => [$mand['from'], $mand['to']],
				]);
			}

		// check if data is empty
		if(empty($list->data)) {
			$count->no_hour_data++;
			return self::response(200, $count);;
			}

		$tmp = [];

		// == foreach(partner_accessID) look if already exist and create tmp
		foreach($list->data as $key => $entry) {

			// look if createTime and partner_accessID already exist, than skip insertion
			$res = \dotdev\app\adzoona\stats::pdo_query([
				'query' => "
					SELECT
						partner_accessID, createTime
					FROM `stats_day`
					WHERE partner_accessID = ? AND createTime = ? LIMIT 1
					",
				'param' => [$entry->partner_accessID, $mand['from']],
				]);

			// skip if entry found (to prevent duplicate entry error)
			if(isset($res->data)) {
				$count->insert_skip++;
				continue;
				}

			// decode param_sum
			$list->data[$key]->param_sum = json_decode($entry->param_sum);

			$tmp[$entry->partner_accessID] = (object) [
				'partner_accessID'	=> $entry->partner_accessID,
				'createTime'		=> $mand['from'],
				'param_sum'			=> (object) [],
				];

			}

		// create param_sum for query
		foreach($list->data as $entry) {

			if (is_object($entry->param_sum)) {
				// foreach key in param_sum, sum for tmp
				foreach($entry->param_sum as $key => $value) {
					if(!isset($tmp[$entry->partner_accessID]->param_sum->{$key})) {
						$tmp[$entry->partner_accessID]->param_sum->{$key} = $value;
						}
					else {
						$tmp[$entry->partner_accessID]->param_sum->{$key} += $value;
						}
					}
				}
			}

		// insert into db
		foreach($tmp as $entry) {

			// insert data into stats_day db
			$res = stats::create_stats_day([
				'partner_accessID'	=> $entry->partner_accessID,
				'createTime'		=> $entry->createTime,
				'param_sum'			=> $entry->param_sum,
				]);

			if($res->status != 201) {
				$count->insert_error++;
				continue;
				}

			$count->insert_success++;

			}

		// return result
		return self::response(200, $count);

		}


	/*
	* called from create_stats_hour for request server
	*/
	protected static function ajax_nsexec($req = []){

		// mandatory
		$mand = h::eX($req, [
			'ns'		=> '~^(?:\\\\[a-zA-Z0-9\_]{1,24}){1,8}\:\:[a-zA-Z0-9\_]{1,32}$',
			'remote'	=> '~^[a-zA-Z0-9\-\.]{1,64}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			// 'dsn'		=> '~^[a-zA-Z0-9\_]{1,24}$',
			'data'		=> '~/l',
			], $error, true);

		// on error
		if($error){
			e::logtrigger('Invalid param for ajax_nsexec: '.h::encode_php($error));
			return self::response(400);
			}

		// remote call
		if(!empty($mand['remote'])){

			// send request to servercom
			$curl_obj = http::curl_obj([
				'url'		=> 'http://'.$mand['remote'].'/com/nsexec.json',
				'ipv4only'	=> true,
				'method'	=> 'POST',
				'jsonencode'=> true,
				'post'		=> [
					'ns'	=> $mand['ns'],
					] + (isset($opt['data']) ? [
					'data'	=> $opt['data'],
					] : []),
				]);

			// if request was basically ok
			if($curl_obj->httpcode == 200){

				// if content is json
				if($curl_obj->contenttype == "text/json; charset=utf-8"){

					// convert json and return success
					// return $this->response(200, json_decode($curl_obj->content));
					return $curl_obj->content;
					}

				// or log error
				e::logtrigger("Cannot use response of type: ".$curl_obj->contenttype);

				// and return 500
				return self::response(500);
				}

			// else return http statuscode
			return self::response($curl_obj->httpcode);
			}

		// local call
		else{

			// change dsn_subpath
			if(!empty($opt['dsn'])){
				pdo_cache::change_dsn_subpath($opt['dsn']);
				}

			// execute
			$res = isset($opt['data']) ? call_user_func($mand['ns'], $opt['data']) : call_user_func($mand['ns']);

			// return success with return data of function
			return self::response(200, $res);
			}
		}


	/*
	* called from get_stats hour or day
	* merge stats
	*/
	protected static function merge_stats($req = []){

		$result = $req;

		// create a default temp object
		$tmp = (object) [
			'time'				=> $result[0]->data->from,
			'result_session'	=> [],
			'result_click'		=> [],
			'result_event'		=> []
			];

		// foreach entry in result write in temp object to reduce result
		foreach($result as $entry) {

			foreach($entry->data->result_session as $session) {
				$tmp->result_session[] = $session;
				}

			foreach($entry->data->result_click as $click) {
				$tmp->result_click[] = $click;
				}

			foreach($entry->data->result_event as $event) {
				$tmp->result_event[] = $event;
				}

			}

		$tmp2 = [];

		// if there is no data, return empty array
		if(empty($tmp->result_session) and empty($tmp->result_click) and empty($tmp->result_event)) return $tmp2;

		// merge result_session
		foreach($tmp->result_session as $session) {
			$hash = $session->publisherID.'-'.$session->domainID.'-'.$session->pageID;

			$tmp2[$hash] = (object) [
				'time'				=> $tmp->time,
				'publisherID'		=> $session->publisherID,
				'domainID'			=> $session->domainID,
				'pageID'			=> $session->pageID,
				];

			$tmp2[$hash]->sum_session = 0;
			}

		foreach($tmp->result_session as $session) {
			$hash = $session->publisherID.'-'.$session->domainID.'-'.$session->pageID;

			$tmp2[$hash]->sum_session += $session->sum;
			}

		// merge result_click
		foreach($tmp->result_click as $click) {
			$hash = $click->publisherID.'-'.$click->domainID.'-'.$click->pageID;

			if(empty($tmp2[$hash])) {
				$tmp2[$hash] = (object) [
					'time'				=> $tmp->time,
					'publisherID'		=> $click->publisherID,
					'domainID'			=> $click->domainID,
					'pageID'			=> $click->pageID,
					];
				}

			$tmp2[$hash]->sum_click = 0;

			}

		foreach($tmp->result_click as $click) {
			$hash = $click->publisherID.'-'.$click->domainID.'-'.$click->pageID;

			$tmp2[$hash]->sum_click += $click->sum;
			}

		// merge result_event
		foreach($tmp->result_event as $event) {
			$hash = $event->publisherID.'-'.$event->domainID.'-'.$event->pageID;

			if(empty($tmp2[$hash])) {
				$tmp2[$hash] = (object) [
					'time'				=> $tmp->time,
					'publisherID'		=> $event->publisherID,
					'domainID'			=> $event->domainID,
					'pageID'			=> $event->pageID,
					];
				}

			$tmp2[$hash]->{'sum_'.$event->type} = 0;
			$tmp2[$hash]->sum_income = 0;
			$tmp2[$hash]->sum_cost = 0;

			}

		foreach($tmp->result_event as $event) {
			$hash = $event->publisherID.'-'.$event->domainID.'-'.$event->pageID;

			$tmp2[$hash]->{'sum_'.$event->type} += $event->sum;
			$tmp2[$hash]->sum_income += $event->income;
			$tmp2[$hash]->sum_cost += $event->cost;
			}


		return $tmp2;
		}

	}