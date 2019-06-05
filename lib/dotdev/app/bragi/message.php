<?php
/*****
 * Version 1.0.2018-09-14
**/
namespace dotdev\app\bragi;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\mobile;
use \dotdev\app\bragi\profile;
use \dotdev\nexus\service;
use \dotdev\nexus\base;

class message {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	protected static function pdo_config(){
		return ['app_bragi', [

			// base queries
			's_message'								=> 'SELECT m.*, p.poolID
														FROM `message` m
														INNER JOIN `profile` p ON p.profileID = m.profileID
														WHERE m.messageID = ?
														LIMIT 1
														',
			'l_user_message'						=> 'SELECT m.*, p.poolID
														FROM `message` m
														INNER JOIN `profile` p ON p.profileID = m.profileID
														WHERE m.mobileID = ? AND m.deleted = 0 AND m.from != 3
														ORDER BY m.createTime DESC
														',
			'l_user_message_after'					=> ['l_user_message', ['m.mobileID = ? AND' => 'm.mobileID = ? AND m.createTime > ? AND']],
			'l_user_message_all_on_profile'			=> ['l_user_message', ['AND m.from != 3' => 'AND m.profileID = ?']],
			'l_user_message_in_pool'				=> ['l_user_message', ['m.mobileID = ? AND' => 'm.mobileID = ? AND p.poolID = ? AND']],
			'l_user_message_in_pool_after'			=> ['l_user_message', ['m.mobileID = ? AND' => 'm.mobileID = ? AND p.poolID = ? AND m.createTime > ? AND']],

			'l_profile_message'						=> 'SELECT m.*, p.poolID
														FROM `message` m
														INNER JOIN `profile` p ON p.profileID = m.profileID
														WHERE m.profileID = ? AND m.deleted = 0
														ORDER BY m.createTime DESC
														',

			's_last_MO_by_mobileID'					=> 'SELECT * FROM `message` WHERE `mobileID` = ? AND `from` IN (1,3) ORDER BY `messageID` DESC LIMIT 1',
			's_last_MO_by_persistID'				=> 'SELECT * FROM `message` WHERE `persistID` = ? AND `from` IN (1,3) ORDER BY `messageID` DESC LIMIT 1',
			's_last_MT_by_mobileID_and_profileID'	=> 'SELECT * FROM `message` WHERE `mobileID` = ? AND `profileID` = ? AND `from` = 2 ORDER BY `messageID` DESC LIMIT 1',
			's_previous_MO'							=> 'SELECT m.messageID, m.text FROM `message` m WHERE `mobileID` = ? AND `from` IN (1,3) AND m.messageID < ? ORDER BY `messageID` DESC LIMIT 1',

			// DEPRECATED
			'l_messages_by_mobileID_limited'		=> 'SELECT * FROM `message` WHERE `mobileID` = ? AND `deleted` = 0 AND `from` != 3 ORDER BY `messageID` DESC LIMIT 20',
			'l_messages_by_mobileID_and_messageID'	=> 'SELECT * FROM `message` WHERE `mobileID` = ? AND `messageID` > ? AND `deleted` = 0 AND `from` != 3 ORDER BY `messageID` DESC',
			'l_messages_by_persistID'				=> 'SELECT * FROM `message` WHERE `persistID` = ? AND `deleted` = 0 AND `from` != 3 ORDER BY `messageID` DESC',
			'l_messages_by_persistID_and_startTime'	=> 'SELECT * FROM `message` WHERE `persistID` = ? AND `createTime` > ? AND `deleted` = 0 AND `from` != 3 ORDER BY `messageID` DESC',
			'l_messages_by_persistID_limited'		=> 'SELECT * FROM `message` WHERE `persistID` = ? AND `deleted` = 0 AND `from` != 3 ORDER BY `messageID` DESC LIMIT 20',
			'l_messages'							=> 'SELECT * FROM `message` WHERE `deleted` = 0 ORDER BY `messageID` DESC',

			// insert, update, other queries
			'i_message'								=> 'INSERT INTO `message` (`mobileID`,`profileID`,`text`,`from`,`smsgateID`,`persistID`,`createtime`) VALUES (?,?,?,?,?,?,?)',
			'u_message'								=> 'UPDATE `message` SET `deleted` = 1 WHERE `messageID` = ? ',
			'u_message_by_mobileID_profileID'		=> 'UPDATE `message` SET `deleted` = ? WHERE `mobileID` = ? AND `profileID` = ?',

			'c_messages_by_mobileID'				=> 'SELECT count(*) as count FROM `message` WHERE `mobileID` = ? AND `from` = 1',
			'c_month_MOs_by_mobileID'				=> 'SELECT count(*) as count FROM `message` WHERE `mobileID` = ? AND `from` IN (1,3) AND `createTime` BETWEEN DATE_FORMAT(NOW() ,\'%Y-%m-01\') AND NOW()',
			'c_month_MOs_by_persistID'				=> 'SELECT count(*) as count FROM `message` WHERE `persistID` = ? AND `from` IN (1,3) AND `createTime` BETWEEN DATE_FORMAT(NOW() ,\'%Y-%m-01\') AND NOW()',

			'l_MOs_stats'							=> 'SELECT COUNT(messageID) AS MOs, DATE_FORMAT(createTime, \'%Y-%m-%d %a\') AS d
														FROM `message`
														WHERE message.from IN (1,3) AND createTime BETWEEN ? AND ?
														GROUP BY DAY(createTime)
														ORDER BY DATE_FORMAT(createTime, \'%Y-%m-%d %a\')
														',

			'l_MOs_heavy_users'						=> 'SELECT DATE_FORMAT(m.createTime, \'%Y %M %d\') as `Date`, COUNT(*) as `MOs`, COUNT(DISTINCT m.mobileID) as `Users`
														FROM `message` m
														WHERE m.mobileID IN
															(SELECT mobileID FROM `message` m
															 WHERE m.createTime BETWEEN ? AND ? AND m.from IN (1,3)
															 GROUP BY m.mobileID HAVING COUNT(*) > ?)
														AND m.createTime BETWEEN ? AND ? AND m.from IN (1,3)
														GROUP BY YEAR(m.createTime), MONTH(m.createTime), DAY(m.createTime)',

			'l_MOs_heavy_users_by_day'				=> 'SELECT mobileID, count(*) as mos FROM `message` m
															WHERE m.createTime BETWEEN ? AND ? AND m.from IN (1,3)
															GROUP BY m.mobileID HAVING COUNT(*) > 9',

			'l_users_heavy_users'					=> 'SELECT DATE_FORMAT(x.createTime, \'%Y %M %d\') as `Date`, COUNT(DISTINCT x.mobileID) as `Users` FROM
															(Select * from `message` m
														     where mobileID in
																( SELECT mobileID FROM `message` m
																 WHERE m.createTime between ? AND ? AND m.from IN (1,3)
																 GROUP BY m.mobileID HAVING COUNT(*) > ?)
															 AND m.createTime between ? AND ? AND m.from IN (1,3)) as x
														GROUP BY YEAR(x.createTime), MONTH(x.createTime), DAY(x.createTime)',

			'l_top50users'							=> 'SELECT m.mobileID, count(*) as messages_sended, MAX(m.createTime) AS last_MO
														FROM `message` as m
														WHERE m.createTime between ? AND ? AND m.from = 1
														GROUP BY mobileID
														ORDER BY `messages_sended` DESC LIMIT 50
														',

			'l_top50users_active'					=> 'SELECT m.mobileID, count(*) as messages_sended, MAX(m.createTime) AS last_MO
														FROM `message` as m
														JOIN (
															SELECT ms.mobileID from `message` ms
															WHERE ms.createTime > DATE_SUB( NOW(), INTERVAL 48 HOUR )
															GROUP BY ms.mobileID
															) p ON m.mobileID = p.mobileID
														WHERE m.createTime BETWEEN ? AND ? AND m.from = 1
														GROUP BY mobileID
														ORDER BY `messages_sended` DESC LIMIT 50
														',

			'l_senders_by_profileID'				=> 'SELECT m.mobileID, mb.msisdn as msisdn
														FROM `message` m
														INNER JOIN mt_mobile.mobile mb ON m.mobileID = mb.ID
														WHERE `profileID` = ?
														GROUP BY m.mobileID
														',

			'l_profiles_by_mobileID'				=> 'SELECT m.profileID, p.name as profileName, p.imageID
														FROM `message` m
														INNER JOIN `profile` p ON m.profileID = p.profileID
														WHERE `mobileID` = ?
														GROUP BY m.profileID
														',

			'l_activ_users'							=> 'SELECT `mobileID`, COUNT(*) as `TotalMOs`, MAX(createTime) as `LastMO` FROM `message`
														WHERE `from` = 1 AND `createtime` BETWEEN ? AND ?
														GROUP BY `mobileID`
														HAVING COUNT(*) BETWEEN ? AND ? AND MAX(createTime) > DATE_SUB(NOW(), INTERVAL ? HOUR)
														ORDER BY `mobileID` DESC
														',

			'l_inactiv_users'						=> 'SELECT `mobileID`, COUNT(*) as `TotalMOs`, MAX(createTime) as `LastMO` FROM `message`
														WHERE `from` = 1 AND `createtime` BETWEEN ? AND ?
														GROUP BY `mobileID`
														HAVING COUNT(*) BETWEEN ? AND ? AND MAX(createTime) < DATE_SUB(NOW(), INTERVAL ? HOUR)
														ORDER BY `mobileID` DESC
														',

			'l_clusterung_1MO'						=> 'SELECT COUNT(*) as `users`
														FROM (
															SELECT COUNT(*) as c
															FROM `message` m
															WHERE m.from IN (1,3) AND m.createTime > DATE_SUB(NOW(), INTERVAL 1 MONTH)
															GROUP BY m.mobileID
															HAVING count(*) = 1
															) AS `count_msgs_by_mobileID`
														',

			'l_clusterung'							=> 'SELECT COUNT(*) as users, SUM(c) as MOs
														FROM (
															SELECT COUNT(*) as c
															FROM `message` m
															WHERE m.from IN (1,3) AND m.createTime > DATE_SUB(NOW(), INTERVAL 1 MONTH)
															GROUP BY m.mobileID
															HAVING count(*) BETWEEN ? AND ?
															) AS `count_msgs_by_mobileID`
														',

			'l_clusterung_max'						=> 'SELECT COUNT(*) as users, SUM(c) as MOs
														FROM (
															SELECT COUNT(*) as c
															FROM `message` m
															WHERE m.from IN (1,3) AND m.createTime > DATE_SUB(NOW(), INTERVAL 1 MONTH)
															GROUP BY m.mobileID
															HAVING count(*) > ?
															) AS `count_msgs_by_mobileID`
														',
			'l_by_countries'						=> 'SELECT count(*) as mos, p.countryID FROM `message` m
														LEFT JOIN `profile` p ON m.profileID = p.profileID
														WHERE m.createTime between ? AND ? AND m.from IN (1,3)
														GROUP BY p.countryID
														',


			's_all_users_lifetime'					=> 'SELECT AVG(x.delta) AS avg
														FROM (
															SELECT (TO_SECONDS(MAX(m.createTime)) - TO_SECONDS(MIN(m.createTime))) as delta
															FROM `message` m
															WHERE m.from = 1 AND m.createTime BETWEEN ? AND ?
															GROUP BY m.mobileID  HAVING delta > 0
															) x
														',

			// queries: summary
			'sum_message'							=> 'SELECT e.from AS `type`, e.smsgateID, m.operatorID, COUNT(DISTINCT e.messageID) AS `sum`
														FROM `message` e
														INNER JOIN `mt_mobile`.`mobile` m ON e.mobileID = m.ID
														WHERE e.createTime BETWEEN ? AND ?
														GROUP BY e.from, e.smsgateID, m.operatorID
														',

			'sum_unique_user'						=> 'SELECT e.smsgateID, m.operatorID, COUNT(DISTINCT e.mobileID) AS `sum`
														FROM `message` e
														INNER JOIN `mt_mobile`.`mobile` m ON e.mobileID = m.ID
														WHERE e.createTime BETWEEN ? AND ?
														GROUP BY e.smsgateID, m.operatorID
														',

			'sum_average_message'					=> 'SELECT e.from, ROUND(COUNT(e.from)/COUNT(DISTINCT e.mobileID), 0) AS `avg`
														FROM `message` e
														WHERE e.createTime BETWEEN ? AND ?
														GROUP BY e.from
														',
			]];
		}


	public static function get($req){

		// optional
		$opt = h::eX($req, [
			'mobileID'	=> '~1,4294967295/i',
			'persistID'	=> '~1,18446744073709551615/i',
			'profileID'	=> '~1,16777215/i',
			'messageID'	=> '~1,4294967295/i',
			'startTime' => '~Y-m-d H:i:s/d',
			'poolID'	=> '~1,65535/i',
			'unread'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: mobileID + profileID
		if(isset($opt['mobileID']) and isset($opt['profileID'])){

			// load list from DB
			$list = self::pdo('l_user_message_all_on_profile', [$opt['mobileID'], $opt['profileID']]);

			// on error
			if($list === false) return self::response(560);

			// return result
			return self::response(200, $list);
			}

		// param order 2: mobileID + messageID (DEPRECATED)
		if(isset($opt['mobileID']) and isset($opt['messageID'])){

			// load list from DB (messages >= messageID)
			$list = self::pdo('l_messages_by_mobileID_and_messageID', [$opt['mobileID'], $opt['messageID']]);

			// on error
			if($list === false) return self::response(560);

			// return result
			return self::response(200, $list);
			}

		// param order 3: mobileID + poolID
		if(isset($opt['mobileID']) and isset($opt['poolID'])){

			// if startTime should limit list
			if(isset($opt['startTime'])){

				// load list from DB
				$list = self::pdo('l_user_message_in_pool_after', [$opt['mobileID'], $opt['poolID'], $opt['startTime']]);
				}

			// full list
			else{

				// load list from DB
				$list = self::pdo('l_user_message_in_pool', [$opt['mobileID'], $opt['poolID']]);
				}

			// on error
			if($list === false) return self::response(560);

			// return result
			return self::response(200, $list);
			}

		// param order 4: mobileID only
		if(isset($opt['mobileID'])){

			// DEPRECATED: param 'unread' should be used only for selecting unread messages. first param limit 20 MOs/MTs (TEMP MARIO)
			if(!empty($opt['unread'])){

				// load list from DB
				$list = self::pdo('l_messages_by_mobileID_limited', $opt['mobileID']);
				}

			// if startTime should limit list
			elseif(isset($opt['startTime'])){

				// load list from DB
				$list = self::pdo('l_user_message_after', [$opt['mobileID'], $opt['startTime']]);
				}

			// full list
			else{

				// load list from DB
				$list = self::pdo('l_user_message', $opt['mobileID']);
				}

			// on error
			if($list === false) return self::response(560);

			// return result
			return self::response(200, $list);
			}

		// param order 5: persistID only (DEPRECATED)
		if(isset($opt['persistID'])){

			// TEMP: param 'unread' should be used only for selecting unread messages. first param limit 20 MOs/MTs (TEMP MARIO)
			if(!empty($opt['unread'])){

				// load list from DB
				$list = self::pdo('l_messages_by_persistID_limited', $opt['persistID']);
				}

			// if startTime should limit list
			elseif(isset($opt['startTime'])){

				// load list from DB
				$list = self::pdo('l_messages_by_persistID_and_startTime', [$opt['persistID'], $opt['startTime']]);
				}

			// full list
			else{

				// load list from DB
				$list = self::pdo('l_messages_by_persistID', $opt['persistID']);
				}

			// on error
			if($list === false) return self::response(560);

			// return result
			return self::response(200, $list);
			}

		// param order 6: profileID only
		if(isset($opt['profileID'])){

			// load list from DB
			$list = self::pdo('l_profile_message', $opt['profileID']);

			// on error
			if($list === false) return self::response(560);

			// return result
			return self::response(200, $list);
			}

		// param order 6: messageID only
		if(isset($opt['messageID'])){

			// load entry from DB
			$entry = self::pdo('s_message', $opt['messageID']);

			// on error or not found
			if(!$entry) return self::response($list === false ? 560 : 404);

			// return result
			return self::response(200, $entry);
			}

		// other request param invalid
		return self::response(400, 'Need at least messageID, mobileID, persistID or profileID');
		}


	public static function create($req){

		// mandatory
		$mand = h::eX($req, [
			'mobileID'	=> '~1,4294967295/i',
			'profileID'	=> '~0,16777215/i',
			'text'		=> '~^.{0,65535}$',
			'from'		=> '~^[0-9]{1}$',
			'smsgateID'	=> '~1,65535/i',
			'persistID'	=> '~0,18446744073709551615/i',
			], $error);
		// optional
		$opt = h::eX($req, [
			'messageID'		=> '~^[a-zA-Z0-9]{1,32}$',
			'receiveTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);
		if($error) return self::response(400, $error);

		$messageID = self::pdo('i_message', [
			$mand['mobileID'],
			$mand['profileID'],
			$mand['text'],
			$mand['from'],
			$mand['smsgateID'],
			$mand['persistID'],
			$opt['receiveTime'] ?? h::dtstr('now')
			]);
		if($messageID === false) return self::response(560);

		return self::response(201, (object)['ID'=>$messageID]);

		}


	public static function update(){

		}


	// delete a message or a chat
	public static function archive($req){

		// Alternativ
		$alt = h::eX($req, [
			'mobileID'	=> '~1,4294967295/i',
			'profileID'	=> '~1,16777215/i',
			'messageID'	=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// this is a delete message request
		if(!empty($alt['messageID'])){

			// set message's 'deleted' field to 1
			$res = self::pdo('u_message', $alt['messageID']);

			// on error return 560 else 204 (also 204 if messageID do not exists)
			return self::response($res === false ? 560 : 204);
			}

		// this is a delete chat request
		if(!empty($alt['mobileID']) && !empty($alt['profileID'])){

			// set chat's 'deleted' field to 1
			$res = self::pdo('u_message_by_mobileID_profileID', [1, $alt['mobileID'], $alt['profileID']]);

			// on error return 560 else 204 (also 204 if chat do not exists)
			return self::response($res === false ? 560 : 204);
			}

		// on missing or invalid parameter(s) error
		return self::response(400, ['messageID|mobileID|profileID']);

		}


	public static function get_list(){

		$res = self::pdo('l_messages');
		if($res === false) return self::response(560);

		return self::response(200, $res);
		}


	public static function get_archived(){
		// Select deleted messages by mobileID
		}

	/* Last 30 days MOs daily count */
	public static function get_MOs_stats($req = []){

		// optional
		$opt = h::eX($req, [
			'from'			=> '~Y-m-d H:i:s/d',
			'to'			=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default range
		if(!isset($opt['from']))	$opt['from'] 	= h::dtstr('now -30 days');
		if(!isset($opt['to'])) 		$opt['to'] 		= h::dtstr('now');

		// load
		$res = self::pdo('l_MOs_stats', [$opt['from'], $opt['to']]);

		// on error
		if($res === false) return self::response(560);


		return self::response(200, $res);
		}


	public static function get_top50users($req = []){

		// alternativ
		$alt = h::eX($req, [
			'from'		=> '~Y-m-d H:i:s/d',
			'to'		=> '~Y-m-d H:i:s/d',
			'active'	=> '~^.*$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// create missing range points
		if(!isset($alt['from'])) $alt['from'] 	= h::dtstr('now -30 days');
		if(!isset($alt['to'])) $alt['to'] 		= h::dtstr('now');

		if(!empty($alt['active'])){

			$alt['active'] = '_'.$alt['active'];

			}

		$res = self::pdo('l_top50users'.$alt['active'], [$alt['from'], $alt['to']]);
		if($res === false) return self::response(560);

		foreach($res as $user){

			// format last MO date
			$user->last_MO = h::dtstr($user->last_MO, 'd.m.Y');

			// get MSISDN
			$result = mobile::get(['mobileID'=>$user->mobileID]);

			$user->msisdn = ($result->status == 200) ? $result->data->msisdn : 0;

			}

		return self::response(200, $res);
		}


	/*
	 * Get all MOs and mobileID clustered in ranges [1 MO, 2-5 MOs, 6-20 MOs, 21-50 MOs, 51-100 MOs, 101-200 MOs, >200 MOs]
	*/
	public static function get_clustering($req){

		// optional
		$opt = h::eX($req, [
			'clusters' => []
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		/* $opt[ "clusters"] = [ 5, 20, 50, 100, 200 ] */

		// declare variable
		$clustered = [];

		/* get First cluster range [1 MO] */
		$res = self::pdo('l_clusterung_1MO');

		// on error
		if($res === false) return self::response(560);

		// assign variable
		$res[0]->range = "1";

		// for this range only, #MOs = #users
		$res[0]->MOs = $res[0]->users;
		array_push($clustered, $res);

		/* get Second cluster range [2-x MOs] */
		$res = self::pdo('l_clusterung', [2, $opt['clusters'][0]]);

		// on error
		if($res === false) return self::response(560);

		// assign variable
		$res[0]->range = "2 - ".$opt['clusters'][0];
		array_push($clustered, $res);

		/* Next clusters ranges, except last one */
		for($i = 0; $i < count($opt['clusters']) - 1; ++$i) {
				$res = self::pdo('l_clusterung', [$opt['clusters'][$i] + 1, $opt['clusters'][$i + 1]]);

				// on error
				if($res === false) return self::response(560);

				// assign variable
				$res[0]->range = $opt['clusters'][$i] + 1 . " - ".$opt['clusters'][$i + 1];
				array_push($clustered, $res);
			}

		/* get Last cluster range (> max) */
		$res = self::pdo('l_clusterung_max', end($opt['clusters']));

		// on error
		if($res === false) return self::response(560);

		// assign variable
		$res[0]->range = "+".end($opt['clusters']);
		array_push($clustered, $res);

		return self::response(200, $clustered);
		}


	/* Get all customers for one fake profile */
	public static function get_senders($req){

		// mandatory
		$mand = h::eX($req, [
			'profileID'	=> '~1,16777215/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// check if profile exist
		$res = profile::get(['profileID'=>$mand['profileID']]);

		// on error
		if(!in_array($res->status, [200, 404])){
			return self::response(500, 'Profile konnte nicht eingelesen werden für name '.$mand['name']);
			}

		// profile not found
		elseif($res->status == 404){
			e::logtrigger('ProfileID konnte nicht gefunden werden: '.h::encode_php($mand['profileID']));
			return self::response(404, 'Unbekannter profileID: '.h::encode_php($mand['profileID']));
			}

		// refresh profileID
		$profileID = $res->data->profileID;

		$res = self::pdo('l_senders_by_profileID', $profileID);
		if($res === false) return self::response(560);

		return self::response(200, $res);
		}


	/* Get a MSISDN all it's chatting profiles */
	public static function get_profiles($req){

		$alt = h::eX($req, [
			'msisdn'				=> '~^$|^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'imsi'					=> '~^$|^[1-9]{1}[0-9]{5,15}$',
			'mobileID'				=> '~^$|^[1-9]{1}[0-9]{0,9}$',
			], $error, true);
		if($error) return self::response(400, $error);
		elseif(empty($alt)) return self::response(400, ['msisdn|imsi|mobileID']);

		if(!empty($alt['msisdn'])) $alt['msisdn'] = $alt['msisdn'][0];

		$res = mobile::get($alt);

		if(!in_array($res->status, [200, 404])){
			return self::response(500, h::encode_php('Mobile konnte nicht verarbeitet werden: '.$res->status));
			}
		elseif($res->status == 404){
			return self::response(404, 'Unbekannter Mobile: '.h::encode_php($alt));
			}
		$mobileID = $res->data->mobileID;

		$res = self::pdo('l_profiles_by_mobileID', $mobileID);
		if($res === false) return self::response(560);

		return self::response(200, $res);
		}


	/* Count MOs by mobileID */
	public static function count($req){
		$mand = h::eX($req, [
			'mobileID'	=> '~1,4294967295/i',
			], $error);
		if($error) return self::response(400, $error);

		$res = self::pdo('c_messages_by_mobileID', $mand['mobileID']);
		if($res === false) return self::response(560);
		$res = $res[0];
		return self::response(200, $res->count); // return 0 if no message was found for this mobileID
		}


	/* Count MOs since first day of current month by mobileID */
	public static function month_MOs_count($req){

		// mandatory
		$mand = h::eX($req, [
			'mobileID'	=> '~1,4294967295/i',
			'persistID'	=> '~1,18446744073709551615/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		if(isset($mand['mobileID'])){

			// load from DB, amount of MOs for this month by mobileID
			$res = self::pdo('c_month_MOs_by_mobileID', $mand['mobileID']);
			}
		else{

			// load from DB, amount of MOs for this month by persistID
			$res = self::pdo('c_month_MOs_by_persistID', $mand['persistID']);
			}

		// on error
		if($res === false) return self::response(560);

		// return status 200 and amount
		return self::response(200, $res[0]->count);
		}


	/* MobileID has reached autostop amount MOs since first day of current month, and one of its last MO's text is confirming text */
	public static function month_MOs_unlocked($req){

		// mandatory
		$mand = h::eX($req, [
			'mobileID'	=> '~1,4294967295/i',
			'persistID'	=> '~1,18446744073709551615/i',
			'smsgateID'	=> '~1,65535/i',
			], $error, true);

		// on error
		if ($error) return self::response(400, $error);

		// get gate
		$res = service::get_smsgate(['smsgateID'=>$mand['smsgateID']]);

		// on error
		if(!in_array($res->status, [200, 404])){
			return self::response(500, 'Gate '.h::encode_php($mand['smsgateID']).' could not be loaded: '.$res->status);
			}

		// not found
		elseif($res->status == 404){
			e::logtrigger('Gate '.$mand['smsgateID'].' could not be found');
			return self::response(406, 'Unknown GateId: '.$mand['smsgateID']);
			}

		// assign gate
		$gate = $res->data;

		// declare variable
		$unlocked = true;

		// check for the gate configuration
		if(!empty($gate->param['autostop'])){

			// load month MO amount
			$res = self::month_MOs_count($mand);

			// on error
			if ($res->status != 200) return self::response(560);

			// assign variable
			$count = $res->data;

			// assign variables
			$start = $gate->param['autostop']['start'];
			$interval = $gate->param['autostop']['interval'];

			// monthly MOs limit is reached (e.g. 200 + 50-tuple)
			if ($count >= $start AND $count % $interval === 0) {

				// user is locked
				$unlocked = false;

				}

			// monthly MOs first limit exceed (e.g. 201)
			elseif ($count > $start) {

				if(isset($mand['mobileID'])){

					// load last MO from DB
					$res = self::pdo('s_last_MO_by_mobileID', $mand['mobileID']);
					}
				else{

					// load last MO from DB
					$res = self::pdo('s_last_MO_by_persistID', $mand['persistID']);
					}

				// on error
				if($res === false) return self::response(560);

				// check if last MO text is confirming text
				$unlocked = in_array(strtolower(trim($res->text)), $gate->param['autostop']['unlock_words']);

				// last MO text is not a confirming text
				if (!$unlocked) {

					// loop previous messages inside the last interval for confirming text (e.g. loop from #276 to #251)
					while ($count % $interval != 0) {

						// assign last messageID
						$last_MO_msgID = $res->messageID;

						// load previous MO from DB
						$res = self::pdo('s_previous_MO', [$mand['mobileID'], $last_MO_msgID]);

						// on error
						if($res === false) return self::response(560);

						// check if previous MO text is 'JA'
						$unlocked = in_array(strtolower(trim($res->text)), $gate->param['autostop']['unlock_words']);

						// previous MO text is a confirming text
						if ($unlocked) {
							break;
							}

						else {

							// reassign last MO messageID
							$last_MO_msgID = $res->messageID;

							// continue loop
							$count--;

							}

						}

					}

				}

			}

		// return true or false
		return self::response(200, $unlocked) ;
		}


	/* Get all MSISDN active (inactive) in a range of time and a range of sended MOs */
	public static function get_users_stats($req){

		// mandatory
		$mand = h::eX($req, [
			'active'	=> '~[0-1]{1}$',
			'period'	=> '~^[0-9]{1,3}$',
			'range'		=> []
		], $error);
		if($error) return self::response(400, $error);

		// alternativ
		$alt = h::eX($req, [
			'from'		=> '~Y-m-d H:i:s/d',
			'to'		=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// create missing range points
		if(!isset($alt['from'])) $alt['from'] 	= h::dtstr('now -30 days');
		if(!isset($alt['to'])) $alt['to'] 		= h::dtstr('now');

		$mand['active'] = $mand['active'] == 1 ? 'activ' : 'inactiv';

		// get list
		$res = self::pdo('l_'.$mand['active'].'_users', [$alt['from'], $alt['to'], $mand['range'][0], $mand['range'][1], $mand['period']]);

		// on error
		if($res === false) return self::response(560);

		foreach($res as $user){

			// get MSISDN
			$result = mobile::get(['mobileID'=>$user->mobileID]);

			$user->msisdn = ($result->status == 200) ? $result->data->msisdn : 0;

			}

		return self::response(200, $res);

		}


	public static function get_users_lifetime($req){

		// mandatory
		$mand = h::eX($req, [
			'lifetime'				=> '~^.*$',
			], $error);

		// alternativ
		$alt = h::eX($req, [
			'from'		=> '~Y-m-d H:i:s/d',
			'to'		=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if ($error) return self::response(400, $error);

		// create missing range points
		if(!isset($alt['from'])) $alt['from'] = date("Y-m-01 00:00:00");
		if(!isset($alt['to'])) $alt['to'] = h::dtstr('now');

		// load lifetime for all users
		if ($mand['lifetime'] == 'All users') {

			$res = self::pdo('s_all_users_lifetime',  [$alt['from'], $alt['to']]);

			if($res === false) return self::response(560);

			// format
			$time = $res[0]->avg;
			$seconds = $time % 60;
			$time = ($time - $seconds) / 60;
			$minutes = $time % 60;
			$hours = ($time - $minutes) / 60;
			$res = round($hours).'h '.$minutes.'m '.$seconds.'s ';

			}

		// TODO: load lifetime for activ/inavtiv users
		else {

			return self::response(404);

			}

		// return
		return self::response(200, $res);

		}


	/* get last MT */
	public static function get_last_MT($req = []){

		// alternative
		$mand = h::eX($req, [
			'mobileID'	=> '~1,4294967295/i',
			'profileID'	=> '~1,16777215/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// seach in DB
		$entry = self::pdo('s_last_MT_by_mobileID_and_profileID', [$mand['mobileID'], $mand['profileID']]);

		// on error or not found
		if(!$entry) return self::response($entry === false ? 560 : 404);

		// return entry
		return self::response(200, $entry);
		}


	/*
	* get heavy users statistic
	*/
	public static function get_heavy_users_MOs($req = []){

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

		foreach ($timeserie as $value) {

			// get list
			$list = self::pdo('l_MOs_heavy_users_by_day', [$value->time, $value->next]);

			// on error
			if($list === false) return self::response(560);

			$value->users = count($list);
			$value->MOs = array_sum(array_column($list,'mos'));

			}

		return self::response(200, $timeserie);
		}


	/*
	* get Mo's sum sorted by country
	*/
	public static function get_by_countries($req = []){

		// alternative
		$alt = h::eX($req, [
			'from'		=> '~Y-m-d H:i:s/d',
			'to'		=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// create missing range points
		if(!isset($alt['from'])) $alt['from'] = h::dtstr('now -30 days');
		if(!isset($alt['to'])) $alt['to'] = h::dtstr('now');

		// load list from DB
		$list = self::pdo('l_by_countries', [$alt['from'], $alt['to']]);

		// on error
		if($list === false) return self::response(560);

		// load countries
		$res = base::get_country();

		// on error
		if($res->status != 200) return self::response(560);

		// complete profiles parameters
		foreach ($list as $item) {

			// assign country name each item
			foreach ($res->data as $country) {
				if($item->countryID == $country->countryID){
					$item->code = $country->code;
					}
				}
			}

		// return list
		return self::response(200, $list);

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


	/* Summary functions */
	public static function get_summary($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'		=> '~^message|unique_user|average_message$',
			], $error);

		// alternativ
		$alt = h::eX($req, [
			'from'		=> '~Y-m-d H:i:s/d',
			'to'		=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// create missing range points
		if(!isset($alt['from'])) $alt['from'] = '2000-01-01 00:00:00';
		if(!isset($alt['to'])) $alt['to'] = h::dtstr('now');

		// get list
		$list = self::pdo('sum_'.$mand['type'], [$alt['from'], $alt['to']]);
		if($list === false) return self::response(560);

		return self::response(200, $list);
		}


	}
