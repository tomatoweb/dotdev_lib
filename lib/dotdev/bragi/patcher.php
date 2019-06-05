<?php
/*****
 * Version 1.0.2019-02-15
**/
namespace dotdev\bragi;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;


class patcher {

	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['app_bragi', [

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

		return redis::load_resource('app_bragi');
		}


	/* fix unwanted "http://https://" string in message */
	public static function fix_missadded_string_in_message($req = []){

		// mandatory
		$mand = h::eX($req, [
			'pattern' 			=> '~/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		return self::response(200, (object)['pattern' => $mand['pattern']]);

		// delete messages containing string
		$del = self::pdo('d_affected_message', $entry->blockID);

		// on error
		if($del === false) return self::response(560);

		// return
		return self::response(200, (object)['request' => $mand]);
		}

	}