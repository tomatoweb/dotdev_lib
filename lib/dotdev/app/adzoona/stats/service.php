<?php
/*****
 * Version 1.0.2018-09-03
**/
namespace dotdev\app\adzoona\stats;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\app\adzoona\partner;
use \dotdev\app\adzoona\stats;
use \dotdev\nexus\domain;
use \dotdev\nexus\levelconfig as levelconfig_nexus;
use \tools\http;

class service {

	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait,
		\dotdev\app\adzoona\stats_trait;


	/* PDO Config */
	protected static function pdo_config(){
		return ['app_adzoona:service', [

			's_partner_accessID_by_createTime'	=> 'SELECT partner_accessID, createTime
													FROM `stats_hour`
													WHERE partner_accessID = ? AND createTime = ? LIMIT 1',

			'l_stats_hour_by_partnerID'			=> 'SELECT s.partner_accessID, s.param_sum
													FROM `stats_hour` s
													INNER JOIN `partner_access` a ON a.partner_accessID = s.partner_accessID
													WHERE a.partnerID = ? AND s.createTime BETWEEN ? AND ?',

			'l_stats_hour_by_createTime'		=> 'SELECT s.partner_accessID, s.param_sum
													FROM `stats_hour` s
													WHERE s.createTime BETWEEN ? AND ?',

			's_stats_day_by_partner_accessID'	=> 'SELECT partner_accessID, createTime
													FROM `stats_day`
													WHERE partner_accessID = ? AND createTime = ? LIMIT 1',
			]];
		}


	/* CRONJOB */

	/**
	* function only for cronjob to create stats_hour
	*/
	public static function cronjob_hourly_create_stats($req = []){

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
		$stats_hour = stats::calculate_timeserie_create_hourly_stats(['from' => $from, 'to' => $to, 'server_list' => $mand['server_list']]);

		// if current time is new day (00:min:sec) call create_stats_day for create stats for previous day
		if(substr($to, -8) == '00:00:00') $stats_day = self::cronjob_daily_create_stats();

		// return
		return self::response(200, (object)['request_hour' => $stats_hour->data, 'request_day' => $stats_day != null ? $stats_day->data : null]);
		}


	/**
	* function called from cronjob_hourly_create_stats on 00:00:00 of next day
	*/
	public static function cronjob_daily_create_stats(){

		// calculate from (== one day back) and to (== that day with time: 00:00:00) => exactly previous day
		$from = date('Y-m-d 00:00:00', strtotime('-1 day'));
		$to = h::dtstr('today');

		// if this function is called (new day) than delete hour stats 1 month ago
		$stats_delete = stats::monthly_delete_stats_hour();

		// create stats for previous day
		$get_stats_day = stats::calculate_timeserie_create_daily_stats(['from' => $from, 'to' => $to]);

		// return both results
		return self::response(200, (object)['stats_delete' => $stats_delete, 'stats_day' => $get_stats_day]);
		}


	/* CREATE */

	/**
	* called from stats.php
	* get all partnerIDs with pubID != 0, domID != 0 and pageID != 0
	* for each combination get_summary and write into DB
	*/
	public static function create_stats_hourly($req = []){

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
							'result_event'		=> ['sum' => 'traffic_event', 'param' => ['publisherID' => $access->publisherID, 'domainID' => $access->domainID, 'pageID' => $access->pageID]],
							'result_callback'	=> ['sum' => 'traffic_callback', 'param' => ['publisherID' => $access->publisherID, 'domainID' => $access->domainID, 'pageID' => $access->pageID]]
							]]
						]);
					$result[] = json_decode($res);
					}
				}

			// merge stats
			$result = self::merge_stats(['result' => $result]);

			// abort if no data was found
			if(empty($result)) {
				$count->no_data++;
				continue;
				}

			// calculate partner_accessID and insert entries
			foreach($result as $entry) {

				// get partner_accessID for partnerID+publisherID+domainID+pageID
				$res = partner::get_partner_access(['partnerID' => $partnerID, 'publisherID' => $entry->publisherID, 'domainID' => $entry->domainID, 'pageID' => $entry->pageID]);
				if($res->status != 200) return self::response(404);

				// partner_accessID
				$partner_accessID = $res->data->partner_accessID;

				// look if createTime and partner_accessID already exist, than skip insertion
				$res = self::pdo('s_partner_accessID_by_createTime', [$partner_accessID, $entry->time]);

				// skip if entry found (to prevent duplicate entry error)
				if(isset($res)) {
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
	* get all partner_accessIDs
	* for each ID sum for a day
	*/
	public static function create_stats_daily($req = []){

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
			$list = self::pdo('l_stats_hour_by_partnerID', [$opt['partnerID'], $mand['from'], $mand['to']]);
			}
		else {

			// sum stats_hour for all partner_accessID for a day
			$list = self::pdo('l_stats_hour_by_createTime', [$mand['from'], $mand['to']]);
			}

		// check if data is empty
		if(empty($list)) {
			$count->no_hour_data++;
			return self::response(200, $count);;
			}

		$tmp = [];

		// == foreach(partner_accessID) look if already exist and create tmp
		foreach($list as $key => $entry) {

			// look if createTime and partner_accessID already exist, than skip insertion
			$res = self::pdo('s_stats_day_by_partner_accessID', [$entry->partner_accessID, $mand['from']]);

			// skip if entry found (to prevent duplicate entry error)
			if(isset($res)) {
				$count->insert_skip++;
				continue;
				}

			// decode param_sum
			$list[$key]->param_sum = json_decode($entry->param_sum);

			$tmp[$entry->partner_accessID] = (object) [
				'partner_accessID'	=> $entry->partner_accessID,
				'createTime'		=> $mand['from'],
				'param_sum'			=> (object) [],
				];

			}

		// create param_sum for query
		foreach($list as $entry) {

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
	* called from stats.php by create_stats_single_hour
	*/
	public function ajax_nsexec($req = []){

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
	* called from stats.php by create_stats_single_hour
	*/
	public static function check_stats_holes($req = []){

		// mandatory
		$mand = h::eX($req, [
			'partnerID'		=> '~1,65535/i',
			'from'			=> '~Y-m-d H:i:s/d',
			'to'			=> '~Y-m-d H:i:s/d',
			'db_table'		=> '~^stats_hour|stats_day$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		$list = stats::{'get_'.$mand['db_table']}($mand);
		if($list->status == 404) $list->data = [];

		if($mand['db_table'] == 'stats_hour') {
			$step_range = '+1 hour';
			$from = date('Y-m-d H:00:00', strtotime($mand['from']));
			}
		elseif($mand['db_table'] == 'stats_day') {
			$step_range = '+1 day';
			$from = date('Y-m-d 00:00:00', strtotime($mand['from']));
			}

		$end_time = h::date($mand['to'].' -1 sec');

		$count = (object) [
			'no_data'	=> 0,
			'data'		=> 0,
			'details'	=> [],
			];

		// do for each step
		do{

			// calc and convert next "to" time
			$to = h::date($from.' '.$step_range.' -1 sec');
			$to = h::dtstr($to < $end_time ? $to : $end_time);

			$found = false;

			foreach ($list->data as $key => $entry) {
				if($entry->createTime == $from) {
					$count->data++;
					$found = true;
					break;
					}
				}

			if($found == false) {
				$count->no_data++;
				$count->details[] = (object) ['createTime' => $from, 'found' => false];
				}
			else {
				$count->details[] = (object) ['createTime' => $from, 'found' => true];
				}


			// take "to" as new "from" time
			$from = h::dtstr($to.' +1 sec');
			} while (h::date($to) < $end_time);

		return self::response(200, $count);
		}


	/*
	* get every 10 minutes new charges and mo's and send it via callback to adzoona.com
	* ! momentan verworfen !
	*/
	public function cronjob_callback_send($req = []){

		// mandatory
		$mand = h::eX($req, [
			'server_list'	=> '~/a',
			], $error);

		// optional
		$opt = h::eX($req, [
			'from'			=> '~Y-m-d H:i:s/d',
			'to'			=> '~Y-m-d H:i:s/d',
			], $error, true);

		// error
		if($error) return self::response(400, $error);

		$date = time();

		if(isset($opt['from']) and isset($opt['to'])) {
			$from = $opt['from'];
			$to = $opt['to'];
			}
		else {
			$ten_minutes_back = strtotime('-10 minutes', $date);
			$from = date('Y-m-d H:i:00', $ten_minutes_back);

			$one_minute_back = strtotime('-1 minutes', $date);
			$to = date('Y-m-d H:i:59', $one_minute_back);
			}

		$result = [];
		$server_list = $mand['server_list'];

		// load data
		foreach($server_list as $server) {

			// start requesting servers
			$res = self::ajax_nsexec([
				'remote' => $server, 'ns' => '\\xadmin\\dotdev\\livestat::get_summary', 'data' => ['from' => $from, 'to' => $to, 'run' => [
					'result_event_abo'		=> ['sum' => 'traffic_event_abo'],
					'result_event_smspay'	=> ['sum' => 'traffic_event_smspay']
					]]
				]);
			$result[] = json_decode($res);
			}

		$result = self::merge_callback(['result' => $result]);

		return self::response(200, (object) ['from' => $from, 'to' => $to, 'result' => $result]);
		}

	}