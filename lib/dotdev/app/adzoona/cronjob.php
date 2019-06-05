<?php
/*****
 * Version 1.0.2018-09-03
**/
namespace dotdev\app\adzoona;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\app\adzoona\partner;
use \dotdev\app\adzoona\stats;
use \dotdev\nexus\domain;
use \dotdev\nexus\levelconfig as levelconfig_nexus;
use \tools\http;

class cronjob {

	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;


	/* PDO Config */
	protected static function pdo_config(){
		return ['app_adzoona:cronjob', [

			'd_stats_hour'		=> 'DELETE FROM `stats_hour` WHERE createTime <= ?',

			]];
		}


	/* CRONJOB */

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
	* function called from cronjob_create_stats_hour on 00:00:00 of next day
	*/
	public static function cronjob_create_stats_day(){

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


	/* CREATE */

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

			// if partnerID is set, call create_stats_single_hour with partnerID, else without
			if(isset($opt['partnerID'])) {
				$stat[] = stats::create_stats_single_hour(['from' => $from, 'to' => $to, 'server' => $mand['server_list'], 'partnerID' => $opt['partnerID']]);
				}
			else {
				$stat[] = stats::create_stats_single_hour(['from' => $from, 'to' => $to, 'server' => $mand['server_list']]);
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

			// if partnerID is set, call create_stats_single_day with partnerID, else without
			if(isset($opt['partnerID'])) {
				$stat[] = stats::create_stats_single_day(['from' => $from, 'to' => $to, 'partnerID' => $opt['partnerID']]);
				}
			else {
				$stat[] = stats::create_stats_single_day(['from' => $from, 'to' => $to]);
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
	private static function delete_stats_hour(){

		$last_day = date('Y-m-d', strtotime('-1 month'));

		// delete every entry older than 1 month
		$delete = self::pdo('d_stats_hour', [$last_day]);

		//return error
		if(!$delete) return self::response($delete === false ? 560 : 404);

		// return result
		return self::response(200, $delete);
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
	}