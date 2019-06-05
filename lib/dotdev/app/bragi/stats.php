<?php
/*****
 * Version 1.0.2018-09-14
 **/
namespace dotdev\app\bragi;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class stats {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config() {
		return ['app_bragi', [

			/* Object: stats_messages_by_profiles */
			'l_stats_messages_by_profiles'			=> 'SELECT p.profileID, p.name as profileName, count(*) as messages_received, count(DISTINCT mobileID) as senders, p.poolID
														FROM `message` m LEFT JOIN `profile` p ON m.profileID = p.profileID
														WHERE m.createTime between ? AND ? AND m.from = 1 AND p.hidden = 0
														GROUP BY profileName
														ORDER BY messages_received DESC',
				]];
		}


	/* Redis */
	public static function redis() {

		return redis::load_resource('app_bragi');
		}

	/* Object: stats_messages_by_profiles */
	public static function get_stats_messages_by_profiles($req = []){

		// alternativ
		$alt = h::eX($req, [
			'from'		=> '~Y-m-d H:i:s/d',
			'to'		=> '~Y-m-d H:i:s/d',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'step'		=> '~^(\+[0-9]{1,2} (month|week|day|hour|min))$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// create missing range points
		if(!isset($alt['from'])) 	$alt['from'] 	= h::dtstr('now -30 days');
		if(!isset($alt['to'])) 		$alt['to'] 		= h::dtstr('now');

		// get messages and senders per profile
		$res = self::pdo('l_stats_messages_by_profiles', [$alt['from'], $alt['to']]);

		// on error
		if($res === false) return self::response(560);

		return self::response(200, $res);
		}

	}
