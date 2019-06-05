<?php
/*****
 * Version 1.0.2019-04-04
**/
namespace dotdev\app\datingmgm;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class helper {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO/Redis Config */
	protected static function pdo_config(){
		return ['app_datingmgm', [
			'l_last_active_chats'		=> 'SELECT p.portal, p.fake_slug, p.real_slug
											FROM `profile_pairs` p
											INNER JOIN `profile_chat_logs` l ON l.pair_id = p.id
											WHERE p.fake_slug NOT IN (?,?) AND l.direction = \'OUT\'
											ORDER BY l.created_at DESC
											LIMIT 20
											',

			'l_moderator_report'		=> 'SELECT STRAIGHT_JOIN
												u.from_agency_id AS `agency_id`,
												p.portal,
												COUNT(DISTINCT (p.real_id)) AS `real_profiles`,
												SUM(IF(l.direction = \'IN\' AND l.marketing = 0, 1, 0)) AS `msg_in`,
												SUM(IF(l.direction = \'OUT\', 1, 0)) AS `msg_out`,
												SUM(IF(l.state = \'reminder_first\', 1, 0)) AS `msg_first_reminder`,
												SUM(IF(l.state = \'reminder_first\' AND l.answer_id IS NOT NULL, 1, 0)) AS `msg_first_reminder_answered`,
												SUM(IF(l.state = \'reminder_second\', 1, 0)) AS `msg_second_reminder`,
												SUM(IF(l.state = \'reminder_second\' AND l.answer_id IS NOT NULL, 1, 0)) AS `msg_second_reminder_answered`,
												SUM(l.has_started) AS `msg_start`,
												SUM(IF(l.has_started = 1 AND l.answer_id IS NOT NULL AND l.marketing = 0, 1, 0)) AS `msg_start_answers`,
												SUM(IF(l.has_started = 0 AND l.answer_id IS NOT NULL AND l.marketing = 0, 1, 0)) AS `msg_answers`,
												IFNULL(SUM(IF(l.has_started = 1, CHAR_LENGTH(l.message), 0)) DIV SUM(l.has_started), 0) AS `first_message_length`,
												IFNULL(SUM(IF(l.direction = \'OUT\', l.response_time, 0)) DIV SUM(IF(l.direction = \'OUT\', 1, 0)), 0) AS `msg_response_time`,
												SUM(IF(l.marketing = 1, 1, 0)) AS `msg_marketing`
											FROM `profile_chat_logs` l
											INNER JOIN `users` u ON l.from_user_id = u.id AND (u.deleted_at IS NULL)
											INNER JOIN `profile_pairs` p ON l.pair_id = p.id
											WHERE l.created_at BETWEEN ? AND ?
											GROUP BY u.from_agency_id, p.portal
											',
			'l_agency_moderator_report'	=> 'SELECT STRAIGHT_JOIN
												u.from_agency_id AS `agency_id`,
												u.id AS `user_id`,
												COUNT(DISTINCT (p.real_id)) AS `real_profiles`,
												SUM(IF(l.direction = \'IN\' AND l.marketing = 0, 1, 0)) AS `msg_in`,
												SUM(IF(l.direction = \'OUT\', 1, 0)) AS `msg_out`,
												SUM(IF(l.state = \'reminder_first\', 1, 0)) AS `msg_first_reminder`,
												SUM(IF(l.state = \'reminder_first\' AND l.answer_id IS NOT NULL, 1, 0)) AS `msg_first_reminder_answered`,
												SUM(IF(l.state = \'reminder_second\', 1, 0)) AS `msg_second_reminder`,
												SUM(IF(l.state = \'reminder_second\' AND l.answer_id IS NOT NULL, 1, 0)) AS `msg_second_reminder_answered`,
												SUM(l.has_started) AS `msg_start`,
												SUM(IF(l.has_started = 1 AND l.answer_id IS NOT NULL AND l.marketing = 0, 1, 0)) AS `msg_start_answers`,
												SUM(IF(l.has_started = 0 AND l.answer_id IS NOT NULL AND l.marketing = 0, 1, 0)) AS `msg_answers`,
												IFNULL(SUM(IF(l.has_started = 1, CHAR_LENGTH(l.message), 0)) DIV SUM(l.has_started), 0) AS `first_message_length`,
												IFNULL(SUM(IF(l.direction = \'OUT\', l.response_time, 0)) DIV SUM(IF(l.direction = \'OUT\', 1, 0)), 0) AS `msg_response_time`,
												SUM(IF(l.marketing = 1, 1, 0)) AS `msg_marketing`
											FROM `profile_chat_logs` l
											INNER JOIN `users` u ON l.from_user_id = u.id AND (u.deleted_at IS NULL)
											INNER JOIN `profile_pairs` p ON l.pair_id = p.id
											WHERE l.created_at BETWEEN ? AND ? AND u.from_agency_id = ?
											GROUP BY u.from_agency_id, u.id
											',

			'l_message_count'			=> 'SELECT l.direction, COUNT(*) AS `sum`
											FROM `profile_chat_logs` l
											WHERE l.created_at BETWEEN ? AND ?
											GROUP BY l.direction
											',
			]];
		}

	protected static function redis_config(){
		return 'app_datingmgm';
		}


	/* lvl1 cache */
	protected static $lvl1_cache = [];



	/* Helper: statUpdateAction */
	public static function get_last_active_chats($req = []){

		// mandatory
		$mand = h::eX($req, [
			'skip_slug_1'	=> '~/s',
			'skip_slug_2'	=> '~/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load list from DB
		$list = self::pdo('l_last_active_chats', [$mand['skip_slug_1'], $mand['skip_slug_2']]);

		// on error
		if($list === false) return self::response(560);

		/*
			The list is longer than wanted, because we avoid the cost intense GROUPBY.
			We try to take the first 3 chats with unique real_slug
		*/
		$new_list = [];
		$found = [];

		// for each chat
		foreach($list as $entry){

			// check if we already added real_slug
			if(isset($found[$entry->real_slug])) continue;

			// add real_slug as found
			$found[$entry->real_slug] = true;

			// add chat
			$new_list[] = $entry;

			// skip searching if we already found 3 chats
			if(count($new_list) >= 3) break;
			}

		// return list
		return self::response(200, $new_list);
		}

	public static function get_moderator_report($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from'			=> '~Y-m-d H:i:s/d',
			'to'			=> '~Y-m-d H:i:s/d',
			], $error);

		// optional
		$opt = h::eX($req, [
			'agency_id'		=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: with agency_id
		if(isset($opt['agency_id'])){

			// load list from DB
			$list = self::pdo('l_agency_moderator_report', [$mand['from'], $mand['to'], $opt['agency_id']]);
			}

		// param order 2: no agency_id
		else{

			// load list from DB
			$list = self::pdo('l_moderator_report', [$mand['from'], $mand['to']]);
			}

		// on error
		if($list === false) return self::response(560);

		// return list
		return self::response(200, $list);
		}

	public static function get_message_report($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from'			=> '~Y-m-d H:i:s/d',
			'to'			=> '~Y-m-d H:i:s/d',
			'step'			=> '~^(\+[0-9]{1,2} (month|week|day|hour|min))$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'step_format'	=> '~/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);



		// step range and format
		$format_list = ['month'=>'Y-m', 'week'=>'Y-m-d', 'day'=>'m-d', 'hour'=>'d. H\h', 'min'=>'H:i'];
		$step_range = $mand['step'][0];
		$step_format = isset($opt['step_format']) ? $opt['step_format'] : $format_list[$mand['step'][1]];

		// init times
		$from = $mand['from'];
		$end_time = h::date($mand['to']);

		// result array
		$timeline = [];

		// run loop
		do{

			// calc and convert next "to" time
			$to = h::date($from.' '.$step_range.' -1 sec');
			$to = h::dtstr($to < $end_time ? $to : $end_time);

			// generate step
			$step = (object)[
				'name'	=> h::dtstr($from, $step_format),
				'time'	=> $from,
				'result'=> [],
				];


			// load list from DB
			$list = self::pdo('l_message_count', [$from, $to]);

			// on error
			if($list === false) return self::response(560);

			// take summary
			$step->result = $list;

			// add to result
			$timeline[] = $step;

			// take "to" as new "from" time
			$from = h::dtstr($to.' +1 sec');

			// loop as long as we do not reach end time
			} while (h::date($to) < $end_time);

		// return result
		return self::response(200, $timeline);
		}

	}