<?php
/*****
 * Version	 	1.0.2016-06-03
**/
namespace dotdev\app\bragi;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \dotdev\mobile;
use \dotdev\app\bragi\profile;

class event {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	protected static function pdo_config(){

		return ['app_bragi', [

			'l_events_by_projectID'						=> "SELECT e.eventID, e.createTime, et.name as eventTypeName, e.mobileID FROM `event` e LEFT JOIN `event_type` et ON e.event_typeID = et.event_typeID WHERE `projectID` = ? order by eventID DESC LIMIT 1000",
			'l_events_by_event_typeID'					=> "SELECT e.eventID, e.createTime, p.name as projectName, e.mobileID FROM `event` e LEFT JOIN `project` p ON e.projectID = p.projectID WHERE `event_typeID` = ? order by eventID DESC LIMIT 1000",
			'l_events_by_projectID_and_event_typeID'	=> "SELECT `eventID`, `createTime`, `mobileID` FROM `event` WHERE `event_typeID` = ? AND `projectID` = ? order by eventID DESC LIMIT 1000",
			's_event_type_by_typeName'					=> "SELECT * FROM `event_type` WHERE `name` = ? LIMIT 1",
			's_event_type_by_typeID'					=> "SELECT * FROM `event_type` WHERE `event_typeID` = ? LIMIT 1",
			's_project_by_projectName'					=> "SELECT * FROM `project` WHERE `name` = ? LIMIT 1",
			's_project_by_projectID'					=> "SELECT * FROM `project` WHERE `projectID` = ? LIMIT 1",
			'i_event'									=> "INSERT INTO `event` (`createTime`,`event_typeID`,`projectID`, `mobileID`) VALUES (?,?,?,?)",
			'i_event_type'								=> "INSERT INTO `event_type` (`name`) VALUES (?)",
			'i_project'									=> "INSERT INTO `project` (`name`) VALUES (?)",
			'i_data'									=> "INSERT INTO `event_data` (`eventID`, `data`) VALUES (?,?)",
			's_event_data'								=> "SELECT `data` FROM `event_data` WHERE `eventID` = ?",
			'l_stats_users_by_day'						=> 'SELECT * FROM `event` e
															LEFT JOIN `event_data` ed ON e.eventID = ed.eventID
															WHERE e.createTime BETWEEN ? AND ?
															AND e.event_typeID = ?',

			]];
		}


	public static function redis(){

		return redis::load_resource('app_bragi');
		}


	public static function get($req){

		// Alternativ or both
		$alt = h::eX($req, [
			'project'	=> '~^.{1,160}$',
			'event'		=> '~^.{1,160}$',
			], $error, true);
		if($error) return self::response(400, $error);

		if(!empty($alt['project']) && !empty($alt['event'])){

			// Get project
			$res = self::get_project(['projectName' => $alt['project']]);
			if(!in_array($res->status, [200, 404])){
				return self::response(500, 'project '.h::encode_php($alt['project']).' konnte nicht verarbeitet werden: '.$res->status);
				}
			elseif($res->status == 404){
				return self::response(406, 'Unbekannter project: '.h::encode_php($alt['project']));
				}
			$project = $res->data;

			// Get event
			$res = self::get_type(['typeName' => $alt['event']]);
			if(!in_array($res->status, [200, 404])){
				return self::response(500, 'event '.h::encode_php($alt['event']).' konnte nicht verarbeitet werden: '.$res->status);
				}
			elseif($res->status == 404){
				return self::response(406, 'Unbekannter event: '.h::encode_php($alt['event']));
				}
			$event_type = $res->data;

			$res = self::pdo('l_events_by_projectID_and_event_typeID', [$event_type->event_typeID, $project->projectID]);
			if($res === false) return self::response(560);

			// associate data
			if(!empty($res)){
				foreach($res as $event){
					$result = self::pdo('s_event_data', $event->eventID);
					if(!$result) continue; // if false or null, no action
					else $event->data = $result[0]->data;
					}
				}

			return self::response(200, $res);
			}

		if(!empty($alt['project'])){
			// Get project
			$res = self::get_project(['projectName' => $alt['project']]);
			if(!in_array($res->status, [200, 404])){
				return self::response(500, 'project '.h::encode_php($alt['project']).' konnte nicht verarbeitet werden: '.$res->status);
				}
			elseif($res->status == 404){
				return self::response(406, 'Unbekannter project: '.h::encode_php($alt['project']));
				}
			$project = $res->data;
			$res = self::pdo('l_events_by_projectID', $project->projectID);
			if($res === false) return self::response(560);

			// associate data
			if(!empty($res)){
				foreach($res as $event){
					$result = self::pdo('s_event_data', $event->eventID);
					if(!$result) continue; // if false or null, no action
					else $event->data = $result[0]->data;
					}
				}

			return self::response(200, $res);
			}

		if(!empty($alt['event'])){
			// Get event
			$res = self::get_type(['typeName' => $alt['event']]);
			if(!in_array($res->status, [200, 404])){
				return self::response(500, 'event '.h::encode_php($alt['event']).' konnte nicht verarbeitet werden: '.$res->status);
				}
			elseif($res->status == 404){
				return self::response(406, 'Unbekannter event: '.h::encode_php($alt['event']));
				}
			$event_type = $res->data;
			$res = self::pdo('l_events_by_event_typeID', $event_type->event_typeID);
			if($res === false) return self::response(560);

			// associate data
			if(!empty($res)){
				foreach($res as $event){
					$result = self::pdo('s_event_data', $event->eventID);
					if(!$result) continue; // if false or null, no action
					else $event->data = $result[0]->data;
					}
				}

			return self::response(200, $res);
			}

		return self::response(400, ['project|event']);

		}


	public static function get_type($req){
		$alt = h::eX($req, [
			'typeID'	=> '~1,255/i',
			'typeName'	=> '~^.{1,160}$'
			], $error, true);
		if($error) return self::response(400, $error);
		elseif(empty($alt)) return self::response(400, ['typeID|typeName']);

		$redis = self::redis();
		if(!$redis or !$redis->isConnected()){
			return self::response(500, 'Verbindung zu Redisserver konnte nicht aufgebaut werden: '.h::encode_php($redis));
			}


		if(!empty($alt['typeID'])){
			if($redis->exists("event_type:id:".$alt['typeID'])){
				$event_type = $redis->get('event_type:id:'.$alt['typeID']);
				return self::response(200, $event_type);
				}

			$res = self::pdo('s_event_type_by_typeID', $alt['typeID']);
			if(!$res) return self::response($res === false ? 560 : 404);

			$redis->set('event_type:id:'.$res->event_typeID, $res);
			$redis->setTimeout("event_type:id:".$res->event_typeID, 1200);

			}

		if(!empty($alt['typeName'])){

			if($redis->exists("event_type:typeName:".$alt['typeName'])){
				$event_type = $redis->get('event_type:typeName:'.$alt['typeName']);
				return self::response(200, $event_type);
				}

			$res = self::pdo('s_event_type_by_typeName', strtolower($alt['typeName']));
			if(!$res) return self::response($res === false ? 560 : 404);

			$redis->set('event_type:typeName:'.$res->name, $res);
			$redis->setTimeout("event_type:typeName:".$res->name, 1200);

			}

		return self::response(200, $res);
		}


	public static function create_type($req){

		$mand = h::eX($req, [
			'name'			=> '~^.{1,160}$',
			], $error);
		if($error) return self::response(400, $error);

		$event_typeID = self::pdo('i_event_type', [
			$mand['name'],
			]);
		if($event_typeID === false) return self::response(560);

		return self::response(201, (object)['event_typeID'=>$event_typeID]);

		}


	public static function get_project($req){
		$alt = h::eX($req, [
			'projectID'		=> '~1,255/i',
			'projectName'	=> '~^.{1,160}$'
			], $error, true);
		if($error) return self::response(400, $error);
		elseif(empty($alt)) return self::response(400, ['projectID|projectName']);

		$redis = self::redis();
		if(!$redis or !$redis->isConnected()){
			return self::response(500, 'Verbindung zu Redisserver konnte nicht aufgebaut werden: '.h::encode_php($redis));
			}

		if(!empty($alt['projectID'])){
			if($redis->exists("project:id:".$alt['projectID'])){
				$project = $redis->get('project:id:'.$alt['projectID']);
				return self::response(200, $project);
				}

			$res = self::pdo('s_project_by_projectID', $alt['projectID']);
			// false or Null
			if(!$res) return self::response($res === false ? 560 : 404);

			$redis->set('project:id:'.$res->projectID, $res);
			$redis->setTimeout("project:id:".$res->projectID, 1200);

			}

		if(!empty($alt['projectName'])){

			if($redis->exists("project:projectName:".$alt['projectName'])){
				$project = $redis->get('project:projectName:'.$alt['projectName']);
				return self::response(200, $project);
				}

			$res = self::pdo('s_project_by_projectName', strtolower($alt['projectName']));
			if(!$res) return self::response($res === false ? 560 : 404);

			$redis->set('project:projectName:'.$res->name, $res);
			$redis->setTimeout("project:projectName:".$res->name, 1200);

			}

		return self::response(200, $res);
		}


	public static function create_project($req){

		$mand = h::eX($req, [
			'name'			=> '~^.{1,160}$',
			], $error);
		if($error) return self::response(400, $error);

		$projectID = self::pdo('i_project', [
			$mand['name'],
			]);
		if($projectID === false) return self::response(560);

		return self::response(201, (object)['projectID'=>$projectID]);

		}


	public static function create($req){

		$mand = h::eX($req, [
			'event' 	=> '~^.{1,160}$',
			], $error);

		$opt = h::eX($req, [
			'imsi' 		=> '~^$|^[nN][uU][lL][lL]$|^0$|^[1-9]{1}[0-9]{2,15}$', // "", 0, null, false or valid imsi (3 char min)
			'data'		=> '~^.{0,65535}$',
			'project' 	=> '~^.{1,160}$'
			], $error, true);
		if($error) return self::response(400, $error);

		$request_time = h::dtstr($_SERVER['REQUEST_TIME']);

		// get or create event_type
		$res = self::get_type(['typeName' => strtolower($mand['event'])]);

		if(!in_array($res->status, [200, 404])){
			return self::response(500, 'event_type konnte nicht eingelesen werden für event_type_name '.$mand['event']);
			}

		if($res->status == 404){
			$create = self::create_type(['name' => strtolower($mand['event'])]);
			if($create->status != 201){
				return self::response(500, 'event_type konnte nicht erstellt werden: '.$mand['event']);
				}
			$res = self::get_type(['typeID' => $create->data->event_typeID]);
			if($res->status != 200){
				return self::response(500, 'event_type konnte nach Erstellung nicht geladen werden, event_typeID: '.$create->data->event_typeID);
				}
			}

		$event_typeID = $res->data->event_typeID;

		// get mobileID
		if( !empty($opt['imsi']) && strtolower($opt['imsi']) != 'null'){
			$res = mobile::get(['imsi' => $opt['imsi']]);
			if(!in_array($res->status, [200, 404])){
				return self::response(500, 'mobileID für IMSI '.h::encode_php($opt['imsi']).' konnte nicht verarbeitet werden: '.$res->status);
				}
			elseif($res->status == 200){
				$mobileID = $res->data->mobileID;
				}
			// else default mobileID = 0
			}

		// get or create project
		if(!empty($opt['project'])){
			$res = self::get_project(['projectName' => strtolower($opt['project'])]);

			if(!in_array($res->status, [200, 404])){
				return self::response(500, 'project konnte nicht eingelesen werden für project_name '.$opt['project']);
				}

			if($res->status == 404){
				$create = self::create_project(['name' => strtolower($opt['project'])]);
				if($create->status != 201){
					return self::response(500, 'project konnte nicht erstellt werden: '.$opt['project']);
					}
				$res = self::get_project(['projectID' => $create->data->projectID]);
				if($res->status != 200){
					return self::response(500, 'project konnte nach Erstellung nicht geladen werden, projectID: '.$create->data->projectID);
					}
				}

			$projectID = $res->data->projectID;
			}

		$eventID = self::pdo('i_event', [$request_time, $event_typeID, !empty($projectID) ? $projectID	: 0, !empty($mobileID) ? $mobileID	: 0]);
		if($eventID === false) return self::response(560);

		$insert = self::pdo('i_data', [$eventID, !empty($opt['data']) ? $opt['data'] : 0]);
		if($insert === false) return self::response(560);

		return self::response(201, (object)['eventId'=>$eventID]);

		}


	public static function update(){

		}


	// delete
	public static function archive($req){

		}


	public static function get_list(){

		}


	public static function get_archived(){

		}

	/*
	* get TAN users statistic
	*/
	public static function get_stats_users($req = []){

		// alternativ
		$alt = h::eX($req, [
			'from'		=> '~Y-m-d/d',
			'to'		=> '~Y-m-d/d',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'step'		=> '~^(\+[0-9]{1,2} (month|week|day|hour|min))$',
			'event'		=> '~^.{1,160}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// create missing range points
		if(!isset($alt['from'])) 	$alt['from'] 	= h::dtstr('now -30 days');
		if(!isset($alt['to'])) 		$alt['to'] 		= h::dtstr('now');

		// create missing step
		$step_range = isset($opt['step']) ? $opt['step'][0] :'+1 day';


		// format range points
		//$from = h::date($alt['from']);
		//$to = h::date($alt['to']);

		// get time serie
		$timeserie = self::get_timeserie([
			'from'	=> $alt['from'],
			'to'	=> $alt['to'],
			'step'	=> $step_range
			]);


		if(!empty($opt['event'])){

			// Get event
			$res = self::pdo('s_event_type_by_typeName', $opt['event']);
			if(!$res) return self::response($res === false ? 560 : 404);

			$event_type = $res->event_typeID;
			}

		foreach ($timeserie as $value) {

			// get list
			$list = self::pdo('l_stats_users_by_day', [$value->time, $value->next, $event_type]);

			// on error
			if($list === false) return self::response(560);

			$value->users = $list;
			//$value->MOs = array_sum(array_column($list,'mos'));

			}

		return self::response(200, $timeserie);
		}

	/*
	* get timeserie for timerange by step
	*/
	public static function get_timeserie($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'from'			=> '~Y-m-d H:i:s/d',
			'to'			=> '~Y-m-d H:i:s/d',
			'step'			=> '~^(\+[0-9]{1,2} (month|week|day|hour|min))$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'step_format'	=> '~^.*$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// step range and format
		$format_list = ['month'=>'Y-m', 'week'=>'Y-m-d', 'day'=>'m-d', 'hour'=>'d. H\h', 'min'=>'H:i'];
		$step_range = $mand['step'][0];
		$step_format = isset($opt['step_format']) ? $opt['step_format'] : $format_list[$mand['step'][1]];

		// init times
		$from = $mand['from'];
		$end_time = h::date($mand['to'].' -1 sec');

		// result array
		$timeline = [];

		// run loop
		do{

			// calc and convert next "to" time
			$to = h::date($from.' '.$step_range.' -1 sec');

			// last iteration check
			$to = h::dtstr($to < $end_time ? $to : $end_time);

			if($step_range == '+1 hour') {
				// generate step
				$step[$from] = (object)[
					"name"			=> h::dtstr($from, $step_format),
					"time"			=> $from
					];
				}
			else {
				// generate step
				$step[substr($from,0,10)] = (object)[
					"name"			=> h::dtstr($from, $step_format),
					"time"			=> $from,
					"next"			=> $to
					];
				}

			// add to result
			$timeline = $step;

			// take "to" as new "from" time
			$from = h::dtstr($to.' +1 sec');

			// loop as long as we do not reach end time
			} while (h::date($to) < $end_time);

		// add current hour
		if($step_range == '+1 hour') {
			// generate step
			$timeline[$mand['to']] = (object)[
				"name"			=> h::dtstr($mand['to'], $step_format),
				"time"			=> $mand['to']
				];
			}

		// return result
		return $timeline;
		}




	}
