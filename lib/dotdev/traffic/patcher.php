<?php
/*****
 * Version 1.0.2019-02-08
**/
namespace dotdev\traffic;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \dotdev\traffic\session as traffic_session;
use \dotdev\nexus\publisher as nexus_publisher;
use \dotdev\nexus\levelconfig as nexus_lc;
use \dotdev\cronjob;

class patcher {

	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_traffic:patcher', [

			// migrate block clicks
			'l_blocked_click_by_persistID'	=> 'SELECT * FROM `blocked_click` WHERE `persistID` = ? ORDER BY `createTime` ASC',
			'd_blocked_click'				=> 'DELETE FROM `blocked_click` WHERE `blockID` = ?',
			'l_click_by_persistID'			=> 'SELECT * FROM `click` WHERE `persistID` = ? ORDER BY `createTime` ASC',
			'l_event_by_persistID'			=> 'SELECT * FROM `event` WHERE `persistID` = ? ORDER BY `createTime` ASC',
			'u_event_clickID'				=> 'UPDATE `event` SET `clickID` = ? WHERE `eventID` = ?',

			// fix
			'l_clicksession_intime_pub'		=> 'SELECT s.persistID, d.request
												FROM `session` s
												INNER JOIN `click` c ON c.persistID = s.persistID
												INNER JOIN `click_pubdata` d ON d.clickID = c.clickID
												WHERE s.createTime BETWEEN ? AND ? AND s.publisherID = ?
												GROUP BY s.persistID
												'
			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_traffic');
		}


	/* migrate block clicks */
	public static function migrate_blocked_clicks($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'			=> '~1,18446744073709551615/i',
			], $error, true);

		// error
		if($error) return self::response(400, $error);

		// define stat
		$stat = (object)[
			'persistID'			=> $mand['persistID'],
			'blocked_clicks'	=> 0,
			'converted_clicks'	=> 0,
			'clicks'			=> 0,
			'events'			=> 0,
			'updated_events'	=> 0,
			];

		// load blocked click list
		$blocked_list = self::pdo('l_blocked_click_by_persistID', $mand['persistID']);

		// on error or empty list
		if($blocked_list === false) return self::response(560);

		// return success, if lis is empty
		if(!$blocked_list) return self::response(200, $stat);

		// increment stat
		$stat->blocked_clicks = count($blocked_list);

		// update session with first entry in list
		$res = traffic_session::update_session([
			'persistID'		=> $blocked_list[0]->persistID,
			'pageID'		=> $blocked_list[0]->pageID,
			'publisherID'	=> $blocked_list[0]->publisherID,
			]);

		// on error
		if($res->status != 204) return self::response(570, $res);

		// for each click
		foreach($blocked_list as $entry){

			// convert request param, if given
			if($entry->request) $entry->request = json_decode($entry->request);

			// create click
			$res = traffic_session::create_click([
				'persistID'			=> $entry->persistID,
				'createTime'		=> $entry->createTime,
				'referer_domainID'	=> $entry->referer_domainID,
				'request'			=> $entry->request ?: null,
				]);

			// on error
			if($res->status != 201) return self::response(570, $res);

			// take clickID
			$entry->clickID = $res->data->clickID;

			// now delete blocked click
			$del = self::pdo('d_blocked_click', $entry->blockID);

			// on error
			if($del === false) return self::response(560);

			// increment stat
			$stat->converted_clicks++;
			}

		// load click list
		$click_list = self::pdo('l_click_by_persistID', $mand['persistID']);

		// on error
		if($click_list === false) return self::response(560);

		// return success, if list is empty
		if(!$click_list) return self::response(200, $stat);

		// increment stat
		$stat->clicks = count($click_list);

		// load event list
		$event_list = self::pdo('l_event_by_persistID', $mand['persistID']);

		// on error
		if($event_list === false) return self::response(560);

		// return success, if list is empty
		if(!$event_list) return self::response(200, $stat);

		// increment stat
		$stat->events = count($event_list);

		// for each event
		foreach($event_list as $event){

			// skip event, if clickID already exists (which cannot be one of the converted blocked clicks)
			if($event->clickID) continue;

			// search for previous click
			foreach($click_list as $click){

				// abort if click as younger than event
				if(h::date($click->createTime) > h::date($event->createTime)) break;

				// take clickID
				$event->clickID = $click->clickID;
				}

			// update event
			$upd = self::pdo('u_event_clickID', [$event->clickID, $event->eventID]);

			// on error
			if($upd === false) return self::response(560);

			// load event
			$res = event::get_event([
				'type'		=> $event->type,
				'eventID'	=> $event->eventID,
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// take entry
			$event = $res->data;

			// retrigger event (which could trigger a callback, if condition is true)
			$res = event::trigger_event([
				'type'			=> $event->type,
				'event'			=> in_array($event->type, ['abo','otp','smspay']) ? 'paid' : 'update',
				'persistID'		=> $event->persistID,
				'aboID'			=> $event->aboID ?? null,
				'otpID'			=> $event->otpID ?? null,
				'smspayID'		=> $event->smspayID ?? null,
				'productID'		=> $event->productID ?? null,
				]);

			// on error
			if($res->status != 204) return self::response(570, $res);

			// increment stat
			$stat->updated_events++;
			}

		// return success
		return self::response(200, $stat);
		}


	/* fix session operatorID with mobile operatorID */
	public static function fix_session_operator_discrepancy($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from'				=> '~Y-m-d H:i:s/d',
			'to'				=> '~Y-m-d H:i:s/d',
			'step'				=> '~^\+[0-9]{1,2} (?:month|week|day|hour|min)$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// step range
		$step_range = $mand['step'];

		// init times
		$from = $mand['from'];
		$end_time = h::date($mand['to']);

		// result array
		$stat = (object)['interval'=>0, 'affected'=>0, 'updated'=>0];

		// do for each step
		do{

			// calc and convert next "to" time
			$to = h::date($from.' '.$step_range.' -1 sec');
			$to = h::dtstr($to < $end_time ? $to : $end_time);

			// increment interval
			$stat->interval++;

			// load list
			$res = self::pdo_query([
				'query'		=> 'SELECT s.persistID, m.operatorID
								FROM `mt_traffic`.`session` s
								INNER JOIN `mt_mobile`.`mobile` m ON m.ID = s.mobileID
								WHERE s.createTime BETWEEN ? AND ? AND s.mobileID != 0 AND m.operatorID != 0 AND s.operatorID != m.operatorID
								',
				'param'		=> [$from, $to],
				]);

			// on error
			if($res->status != 200) return $res;

			// take list
			$affected_list = $res->data;

			// for each entry
			foreach($affected_list as $entry){

				// increment affected
				$stat->affected++;

				// update session
				$res = self::pdo_query([
					'query'		=> 'UPDATE `session` SET `operatorID` = ? WHERE `persistID` = ?',
					'param'		=> [$entry->operatorID, $entry->persistID],
					]);

				// on error
				if($res->status != 200) return $res;

				// increment updated
				$stat->updated++;
				}

			// take "to" as new "from" time
			$from = h::dtstr($to.' +1 sec');

			} while (h::date($to) < $end_time);

		// return result
		return self::response(200, (object)['request' => $mand, 'stats' => $stat]);
		}

	public static function fix_session_missing_mobile_info($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from'				=> '~Y-m-d H:i:s/d',
			'to'				=> '~Y-m-d H:i:s/d',
			'step'				=> '~^\+[0-9]{1,2} (?:month|week|day|hour|min)$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// step range
		$step_range = $mand['step'];

		// init times
		$from = $mand['from'];
		$end_time = h::date($mand['to']);

		// result array
		$stat = (object)['interval'=>0, 'affected'=>0, 'updated'=>0];

		// do for each step
		do{

			// calc and convert next "to" time
			$to = h::date($from.' '.$step_range.' -1 sec');
			$to = h::dtstr($to < $end_time ? $to : $end_time);

			// increment interval
			$stat->interval++;

			// load list
			$res = self::pdo_query([
				'query'		=> 'SELECT s.persistID, m.ID as `mobileID`, m.operatorID
								FROM `mt_traffic`.`session` s
								INNER JOIN `mt_mobile`.`persistlink` l ON l.persistID = s.persistID
								INNER JOIN `mt_mobile`.`mobile` m ON m.ID = l.mobileID
								WHERE s.createTime BETWEEN ? AND ? AND s.mobileID = 0 AND s.operatorID = 0
								',
				'param'		=> [$from, $to],
				]);

			// on error
			if($res->status != 200) return $res;

			// take list
			$affected_list = $res->data;

			// for each entry
			foreach($affected_list as $entry){

				// increment affected
				$stat->affected++;

				// update session
				$res = self::pdo_query([
					'query'		=> 'UPDATE `session` SET `mobileID` = ?, `operatorID` = ? WHERE `persistID` = ?',
					'param'		=> [$entry->mobileID, $entry->operatorID, $entry->persistID],
					]);

				// on error
				if($res->status != 200) return $res;

				// increment updated
				$stat->updated++;
				}

			// take "to" as new "from" time
			$from = h::dtstr($to.' +1 sec');

			} while (h::date($to) < $end_time);

		// return result
		return self::response(200, (object)['request' => $mand, 'stats' => $stat]);
		}


	/* remove unwanted autogenerated publisher */
	public static function delete_autogenerated_publisher($req = []){

		// mandatory
		$mand = h::eX($req, [
			'publisherID_list'	=> '~!empty/a',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// for each entry
		foreach($mand['publisherID_list'] as $publisherID) {

			// check publisherID
			if(!h::is($publisherID, '~1,65535/i')) return self::response(400, ['publisherID_list']);

			// load publisher
			$res = nexus_publisher::get_publisher([
				'publisherID'	=> $publisherID,
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return $res;

			// abort if publisher not exists or is not owned by another publisher
			if($res->status == 404 or empty($res->data->ownerID)) return self::response(406);
			}

		// define query value for publisherIDs
		$pidparam = implode(',', $mand['publisherID_list']);

		// update all affected sessions
		$res = self::pdo_query([
			'query' => '
				UPDATE `session`
				SET `publisherID` = 0, `publisher_affiliateID` = 0
				WHERE `publisherID` IN ('.$pidparam.')
				'
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// delete publisher
		$res = nexus_publisher::pdo_query([
			'query' => '
				DELETE
				FROM `publisher`
				WHERE `publisherID` IN ('.$pidparam.')
				'
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// delete levelconfig of publisher
		$res = nexus_lc::pdo_query([
			'query' => '
				DELETE
				FROM `levelconfig`
				WHERE `publisherID` IN ('.$pidparam.')
				'
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// delete publisher affiliates
		$res = self::pdo_query([
			'query' => '
				DELETE
				FROM `publisher_affiliate`
				WHERE `publisherID` IN ('.$pidparam.')
				'
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// return success
		return self::response(204);
		}


	/* fixing traffic of publisher not separated to uncover publisher */
	public static function fix_uncover_publisher_traffic($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from'			=> '~Y-m-d H:i:s/d',
			'to'			=> '~Y-m-d H:i:s/d',
			'step'			=> '~^\+[0-9]{1,2} (?:month|week|day|hour|min)$',
			'publisherID'	=> '~1,65535/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);


		// load publisher setting
		$res = nexus_publisher::get_publisher([
			'publisherID'	=> $mand['publisherID'],
			]);

		// on error
		if(!in_array($res->status, [200, 404])) return $res;

		// if publisher not exists, return not acceptable
		if($res->status == 404) return self::response(406);

		// take publisher
		$publisher = $res->data;


		// load levelconfig for publisher
		$res = nexus_lc::get_levelconfig([
			'level'			=> 'pub',
			'publisherID'	=> $publisher->publisherID,
			]);

		// on error
		if($res->status != 200) return $res;

		// take levelconfig data
		$publisher->levelconfig = $res->data;


		// convert uncover param
		$publisher->levelconfig['pub:uncover_param'] = !empty($publisher->levelconfig['pub:uncover_param']) ? json_decode($publisher->levelconfig['pub:uncover_param']) : [];
		$publisher->levelconfig['pub:uncover_name_param'] = !empty($publisher->levelconfig['pub:uncover_name_param']) ? json_decode($publisher->levelconfig['pub:uncover_name_param']) : [];

		// if unconver param not valid, return precondition failed
		if(empty($publisher->levelconfig['pub:uncover_param']) or !is_array($publisher->levelconfig['pub:uncover_param']) or !is_array($publisher->levelconfig['pub:uncover_name_param'])) return self::response(412);



		// step range
		$step_range = $mand['step'];

		// init times
		$from = $mand['from'];
		$end_time = h::date($mand['to']);

		// result array
		$stat = (object)[
			'interval'		=> 0,
			'sessions'		=> 0,
			'unparseable'	=> 0,
			'no_uncover_key'=> 0,
			'no_uncover_ID'	=> 0,
			'updated'		=> 0,
			];

		// do for each step
		do{

			// calc and convert next "to" time
			$to = h::date($from.' '.$step_range.' -1 sec');
			$to = h::dtstr($to < $end_time ? $to : $end_time);

			// increment interval
			$stat->interval++;


			// load session list
			$session_list = self::pdo('l_clicksession_intime_pub', [$from, $to, $publisher->publisherID]);

			// on error
			if($session_list === false) return self::response(560);


			// for each session
			foreach($session_list as $session){

				// parse click data
				$session->request = $session->request ? json_decode($session->request) : null;

				// if click data was unparseable or not given
				if(!is_object($session->request)){

					// count and continue
					$stat->unparseable++;
					continue;
					}

				// define uncover_key and uncover_name
				$uncover_key = null;
				$uncover_name = null;

				// check uncover key
				foreach($publisher->levelconfig['pub:uncover_param'] as $param_name){

					// if valid param exists in click data
					if(h::cX($session->request, $param_name, '~^[a-zA-Z0-9\-\_]{1,16}$')){

						// take and skip further search
						$uncover_key = h::gX($session->request, $param_name);
						}
					}

				// check uncover name
				foreach($publisher->levelconfig['pub:uncover_name_param'] as $param_name){

					// if valid param exists in click data
					if(h::cX($session->request, $param_name, '~^[a-zA-Z0-9\-\_]{1,120}$')){

						// take and skip further search
						$uncover_name = h::gX($session->request, $param_name);
						}
					}

				// if uncover key was not found
				if($uncover_key === null){

					// count and continue
					$stat->no_uncover_key++;
					continue;
					}


				// parse uncover publisher
				$res = traffic_session::parse_special_identifier([
					'publisherID'			=> $publisher->publisherID,
					'publisher_uncover_key'	=> $uncover_key,
					'publisher_uncover_name'=> $uncover_name,
					]);

				// on unexpected error
				if($res->status != 200) return self::response(570, $res);

				// take result
				$parsed = $res->data;

				// if no uncover_publisherID found
				if(!$parsed->uncover_publisherID){

					// count and continue
					$stat->no_uncover_ID++;
					continue;
					}

				// update session
				$res = traffic_session::update_session([
					'persistID'				=> $session->persistID,
					'publisherID'			=> $parsed->uncover_publisherID,
					]);

				// on unexpected error
				if($res->status != 204) return self::response(570, $res);

				// count and continue
				$stat->updated++;
				}


			// take "to" as new "from" time
			$from = h::dtstr($to.' +1 sec');

			} while (h::date($to) < $end_time);

		// return result
		return self::response(200, (object)['request' => $mand, 'stats' => $stat]);
		}


	/* collect uncover publisher and migrate them (ONLY for Adjust Publisher) */
	public static function clean_adjust_owned_publisher($req = []){

		// mandatory
		$mand = h::eX($req, [
			'ownerID'	=> '~1,65535/i',		// publisherID of Adjust
			], $error);

		// optional
		$opt = h::eX($req, [
			'delete_publisher'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default
		$opt += [
			'delete_publisher'	=> false,
			];

		// get publisher with ownerID
		$res = nexus_publisher::get_publisher(['publisherID' => $mand['ownerID']]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// abort here if not Adjust
		if($res->data->name != 'Adjust') return self::response(400, 'publisher is not Adjust');

		// get list of ownerID
		$res = nexus_publisher::pdo_query([
			'query' => '
				SELECT p.name, p.publisherID, p.ownerID
				FROM `publisher` p
				WHERE `ownerID` = ?
				',
			'param'	=> [$mand['ownerID']],
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take ownerID list
		$ownerID_list = $res->data;

		// collected publisherIDs
		$collected_publisherIDs = [];

		// for each publisher of ownerID
		foreach($ownerID_list as $publisher_owner){

			// define stop pos to detect real tracker_name
			$stop_pos = strpos($publisher_owner->name, '__');

			// reduce uncover_name to real tracker_name
			$adjust_uncover_key = $stop_pos ? substr($publisher_owner->name, 0, $stop_pos) : $publisher_owner->name;

			// remove additional unwanted name suffixes
			if(preg_match('/^(.*)_([A-Z]{2})$/', $adjust_uncover_key, $match)) $adjust_uncover_key = $match[1];

			// specific renaming for consistence
			$renaming = [
				'Motive_Interactive'	=> 'Motive_Interactive_Inc',
				'Curate'				=> 'Curate_Mobile_Ltd',
				];

			// do renaming
			foreach($renaming as $from => $to){
				if($adjust_uncover_key != $from) continue;
				$adjust_uncover_key = $to;
				break;
				}

			// set new useful uncover_key
			$calculated_uncover_key = preg_replace('/[^a-z0-9\_]/', '_', strtolower($adjust_uncover_key));

			// set new uncover_name
			$calculated_name = $adjust_uncover_key;

			// try get publisher
			$res = nexus_publisher::get_publisher([
				'ownerID'		=> $publisher_owner->ownerID,
				'uncover_key'	=> $calculated_uncover_key
				]);

			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if publisher is missing
			elseif($res->status == 404){

				// create publisher
				$res = nexus_publisher::create_publisher([
					'name'			=> $calculated_name,
					'status'		=> 'enabled',
					'ownerID'		=> $publisher_owner->ownerID,
					'uncover_key'	=> $calculated_uncover_key,
					]);

				// on error
				if($res->status != 201) return self::response(570, $res);

				// reload publisher
				$res = nexus_publisher::get_publisher([
					'publisherID'		=> $res->data->publisherID ?? 0,
					]);

				// on error
				if($res->status != 200) return self::response(570, $res);
				}

			// take publisher
			$valid_publisher = $res->data;

			// dont set publisherID of valid publisher
			if($valid_publisher->publisherID == $publisher_owner->publisherID) continue;

			// check if publisherID was set
			if(!isset($collected_publisherIDs[$valid_publisher->publisherID])){

				// set valid publisherID
				$collected_publisherIDs[$valid_publisher->publisherID] = [];
				}

			// set publisherID for valid publisherID
			$collected_publisherIDs[$valid_publisher->publisherID][] = $publisher_owner->publisherID;
			}

		// check if no update/clean is needed
		if(empty($collected_publisherIDs)) return self::response(204);

		// for each valid publisherID with collected publisherIDs
		foreach($collected_publisherIDs as $publisherID => $publisherID_array){

			// define query value for publisherIDs
			$pidparam = implode(',', $publisherID_array);

			// update all affected sessions
			$res = self::pdo_query([
				'query' => '
					UPDATE `session`
					SET `publisherID` = ?, `publisher_affiliateID` = 0
					WHERE `publisherID` IN ('.$pidparam.')
					',
				'param'	=> [$publisherID],
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			//
			if(!$opt['delete_publisher']) continue;

			// delete publisher
			$res = nexus_publisher::pdo_query([
				'query' => '
					DELETE
					FROM `publisher`
					WHERE `publisherID` IN ('.$pidparam.')
					'
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// delete levelconfig of publisher
			$res = nexus_lc::pdo_query([
				'query' => '
					DELETE
					FROM `levelconfig`
					WHERE `publisherID` IN ('.$pidparam.')
					'
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// delete publisher affiliates
			$res = self::pdo_query([
				'query' => '
					DELETE
					FROM `publisher_affiliate`
					WHERE `publisherID` IN ('.$pidparam.')
					'
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);
			}

		// return success
		return self::response(204);
		}

	/* get sessions in range, fix publisherID for them */
	public static function fix_session_publisher($req = []){

		// mandatory
		$mand = h::eX($req, [
			'start_publisherID'	=> '~1,65535/i',
			'end_publisherID'	=> '~1,65535/i',
			'ownerID'			=> '~1,65535/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// collect sessions, for each different session one entry with click data
		$res = self::pdo_query([
			'query' => '
				SELECT DISTINCT s.publisherID, s.persistID, cp.request
				FROM `session` s
				INNER JOIN `click` c ON c.persistID = s.persistID
				INNER JOIN `click_pubdata` cp ON cp.clickID = c.clickID
				WHERE `publisherID` BETWEEN ? AND ?
				GROUP BY s.publisherID
				',
			'param'	=> [$mand['start_publisherID'], $mand['end_publisherID']]
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take data
		$session_list = $res->data;

		// if empty
		if(empty($session_list)) return self::response(400, 'session list is empty');

		// result
		$result = [
			'missing tracker_token (old publisherID)' 											=> [],
			'no publisher found with uncover key (old publisherID => calculated_uncover_key)'	=> [],
			'successfull publisherIDs edited (old => new)'										=> [],
			];

		// for each session
		foreach ($session_list as $session) {

			// take request
			$request = json_decode($session->request);

			// edit result if no tracker_name
			if(!isset($request->tracker_name)){
				$result['missing tracker_token (old publisherID)'][] = $session->publisherID;
				continue;
				}

			// define stop pos to detect real tracker_name
			$stop_pos = strpos($request->tracker_name, '::');

			// reduce uncover_name to real tracker_name
			$adjust_uncover_key = $stop_pos ? substr($request->tracker_name, 0, $stop_pos) : $request->tracker_name;

			// remove additional unwanted name suffixes
			if(preg_match('/^(.*)_([A-Z]{2})$/', $adjust_uncover_key, $match)) $adjust_uncover_key = $match[1];

			// specific renaming for consistence
			$renaming = [
				'Motive_Interactive'	=> 'Motive_Interactive_Inc',
				'Curate'				=> 'Curate_Mobile_Ltd',
				];

			// do renaming
			foreach($renaming as $from => $to){
				if($adjust_uncover_key != $from) continue;
				$adjust_uncover_key = $to;
				break;
				}

			// set new useful uncover_key
			$calculated_uncover_key = preg_replace('/[^a-z0-9\_]/', '_', strtolower($adjust_uncover_key));

			// search publisher
			$res = nexus_publisher::get_publisher([
				'ownerID'		=> $mand['ownerID'],
				'uncover_key'	=> $calculated_uncover_key
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// on 404
			if($res->status == 404){

				// edit result if no publisher exist with uncover key
				$result['no publisher found with uncover key (old publisherID => calculated_uncover_key)'][$session->publisherID] = $calculated_uncover_key;
				continue;
				}

			// take publisherID of valid publisher
			$valid_publisherID = $res->data->publisherID;

			// update all affected sessions
			$res = self::pdo_query([
				'query' => '
					UPDATE `session`
					SET `publisherID` = ?, `publisher_affiliateID` = 0
					WHERE `publisherID` = ?
					',
				'param'	=> [$valid_publisherID, $session->publisherID],
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// edit result
			$result['successfull publisherIDs edited (old => new)'][$session->publisherID] = $valid_publisherID;
			}

		return self::response(200, $result);
		}

	}