<?php
/*****
 * Version 1.0.2018-11-19
**/
namespace dotdev\mobile\abo;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \dotdev\nexus\service;
use \dotdev\mobile;
use \dotdev\mobile\abo;
use \dotdev\mobile\client;

class reminder {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_mobile:reminder', [

			/* queries for abo_reminder_text */
			's_reminder_text'			=> "SELECT `ID` AS `reminder_textID`, `hash`, `text` FROM `abo_reminder_text` WHERE `ID` = ? LIMIT 1",
			's_reminder_text_by_hash'	=> "SELECT `ID` AS `reminder_textID`, `hash`, `text` FROM `abo_reminder_text` WHERE `hash` = ? LIMIT 1",
			'l_reminder_text'			=> "SELECT `ID` AS `reminder_textID`, `hash`, `text` FROM `abo_reminder_text` ORDER BY `ID` ASC",
			'i_reminder_text'			=> "INSERT INTO `abo_reminder_text` (`hash`,`text`) VALUES (?,?)",
			'u_reminder_text'			=> "UPDATE `abo_reminder_text` SET `hash` = ?, `text` = ? WHERE `ID` = ?",

			/* queries for abo_reminder */
			's_reminder'				=> "SELECT `ID` AS `reminderID`, `aboID`, `textID`, `planTime`, `smsID`, `done` FROM `abo_reminder` WHERE `ID` = ? LIMIT 1",
			'i_reminder'				=> "INSERT INTO `abo_reminder` (`aboID`,`textID`,`planTime`) VALUES (?,?,?)",
			'u_reminder'				=> "UPDATE `abo_reminder` SET `planTime` = ?, `smsID` = ?, `done` = ? WHERE `ID` = ?",

			/* queries for abo_reminder job */
			'l_reminder_for_execution'	=> "SELECT
												r.ID AS `reminderID`, r.textID AS `reminder_textID`,
												a.mobileID, a.productID, m.operatorID, a.persistID,
												IF(a.confirmTime = '0000-00-00 00:00:00', 0, 1) AS `confirmed`,
												IF(a.terminateTime = '0000-00-00 00:00:00', 0, 1) AS `terminated`
											FROM `abo_reminder` r
											INNER JOIN `abo` a ON a.ID = r.aboID
											INNER JOIN `mobile` m ON m.ID = a.mobileID
											WHERE r.done = 0 AND r.planTime <= ?
											",

			/* queries for create_reminder_for_msisdn */
			'l_imp_new_msisdn_reminder'	=> "SELECT a.ID as `aboID`, a.mobileID, a.productID, m.operatorID
											FROM `abo` a
											INNER JOIN `mobile` m ON m.ID = a.mobileID
											LEFT JOIN `abo_reminder` r ON r.aboID = a.ID AND r.done = 0
											WHERE m.msisdn = ? AND a.confirmTime != '0000-00-00 00:00:00' AND a.terminateTime = '0000-00-00 00:00:00' AND r.ID IS NULL
											GROUP BY a.ID
											",
			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_mobile');
		}


	/* lvl1 cache */
	protected static $lvl1_cache = [];



	/* Object: reminder_text */
	public static function get_reminder_text($req = []){

		// alternative
		$alt = h::eX($req, [
			'reminder_textID'	=> '~1,65535/i',
			'hash'				=> '~^[a-z0-9]{40}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1-2: reminder_textID, hash
		foreach(['reminder_textID', 'hash'] as $param_key){

			// if param key exists
			if(isset($alt[$param_key])){

				// define cache key
				$cache_key = 'reminder_text:by_'.$param_key.':'.$alt[$param_key];

				// check lvl1 cache
				if(isset(self::$lvl1_cache[$cache_key])){

					// return entry
					return self::response(200, clone self::$lvl1_cache[$cache_key]);
					}

				// init redis
				$redis = self::redis();

				// if redis accessable and entry exists
				if($redis and $redis->exists($cache_key)){

					// load entry
					$entry = $redis->get($cache_key);
					}

				// else
				else{

					// seach in DB
					$entry = self::pdo('s_reminder_text'.($param_key == 'hash' ? '_by_hash' : ''), [$alt[$param_key]]);

					// on error or not found
					if(!$entry) return self::response($entry === false ? 560 : 404);

					// if redis accessable
					if($redis){

						// cache entry
						$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
						}
					}

				// cache entry in lvl1 cache
				self::$lvl1_cache[$cache_key] = clone $entry;

				// return entry
				return self::response(200, $entry);
				}
			}

		// param order 3: no param
		if(empty($req)){

			// load list from DB
			$list = self::pdo('l_reminder_text');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need reminder_textID, hash or no parameter');
		}

	public static function create_reminder_text($req = []){

		// mandatory
		$mand = h::eX($req, [
			'text'		=> '~1,160/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// create hash
		$hash = sha1($mand['text']);

		// check if text already exists
		$res = self::get_reminder_text([
			'hash'		=> $hash,
			]);

		// if already exists, return conflict
		if($res->status == 200) return self::response(409);

		// on unexpected error
		if($res->status != 404) return self::response(570, $res);

		// create entry
		$reminder_textID = self::pdo('i_reminder_text', [$hash, $mand['text']]);

		// on error
		if($reminder_textID === false) return self::response(560);

		// return success
		return self::response(201, (object)['reminder_textID' => $reminder_textID]);
		}

	public static function update_reminder_text($req = []){

		// mandatory
		$mand = h::eX($req, [
			'reminder_textID'	=> '~1,65535/i',
			'text'				=> '~1,160/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_reminder_text([
			'reminder_textID'	=> $mand['reminder_textID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// define old cache key for hash
		$cache_key_hash_old = 'reminder_text:by_hash:'.$entry->hash;

		// replace params
		$entry->text = $mand['text'];
		$entry->hash = sha1($mand['text']);

		// check if hash already exists
		$res = self::get_reminder_text([
			'hash'	=> $entry->hash,
			]);

		// if already exists, return conflict
		if($res->status == 200) return self::response(409);

		// on unexpected error
		if($res->status != 404) return self::response(570, $res);


		// update
		$upd = self::pdo('u_reminder_text', [$entry->hash, $entry->text, $entry->reminder_textID]);

		// on error
		if($upd === false) return self::response(560);

		// define cache keys
		$cache_key = 'reminder_text:by_reminder_textID:'.$entry->reminder_textID;
		$cache_key_hash = 'reminder_text:by_hash:'.$entry->hash;

		// unset lvl1 cache
		unset(self::$lvl1_cache[$cache_key]);
		unset(self::$lvl1_cache[$cache_key_hash]);
		unset(self::$lvl1_cache[$cache_key_hash_old]);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire entry
			$redis->setTimeout($cache_key, 0);
			$redis->setTimeout($cache_key_hash, 0);
			$redis->setTimeout($cache_key_hash_old, 0);
			}

		// return success
		return self::response(204);
		}



	/* Object: reminder */
	public static function get_reminder($req = []){

		// alternative
		$alt = h::eX($req, [
			'reminderID'	=> '~1,16777215/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: reminderID
		if(isset($alt['reminderID'])){

			// seach in DB
			$entry = self::pdo('s_reminder', [$alt['reminderID']]);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return entry
			return self::response(200, $entry);
			}

		// other request param invalid
		return self::response(400, 'need reminderID');
		}

	public static function create_reminder($req = []){

		// mandatory
		$mand = h::eX($req, [
			'aboID'		=> '~1,4294967295/i',
			'text'		=> '~1,160/s',
			'planTime'	=> '~Y-m-d H:i:s/d',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// define reminder_textID and create hash
		$reminder_textID = 0;
		$hash = sha1($mand['text']);

		// load reminder text
		$res = self::get_reminder_text([
			'hash'		=> $hash,
			]);

		// on error
		if(!in_array($res->status, [200,404])) return self::response(570, $res);

		// if entry does not exists
		if($res->status == 404){

			// create reminder text
			$res = self::create_reminder_text([
				'text'	=> $mand['text'],
				]);

			// on unexpected error
			if($res->status != 201) return self::response(570, $res);
			}

		// take reminder_textID
		$reminder_textID = $res->data->reminder_textID;

		// create reminder
		$reminderID = self::pdo('i_reminder', [$mand['aboID'], $reminder_textID, $mand['planTime']]);

		// on error
		if(!$reminderID) return self::response(560);

		// return success
		return self::response(201, (object)['reminderID' => $reminderID]);
		}

	public static function update_reminder($req = []){

		// mandatory
		$mand = h::eX($req, [
			'reminderID'	=> '~1,16777215/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'planTime'		=> '~Y-m-d H:i:s/d',
			'smsID'			=> '~1,16777215/i',
			'done'			=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_reminder([
			'reminderID'	=> $mand['reminderID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_reminder', [$entry->planTime, $entry->smsID, $entry->done ? 1 : 0, $entry->reminderID]);

		// on error
		if($upd === false) return self::response(560);

		// return success
		return self::response(204);
		}


	/* Cronjob: execute undone reminder */
	public static function execute_undone($req = []){

		// load list of reminder to send
		$list = self::pdo('l_reminder_for_execution', h::dtstr('now'));

		// on error
		if($list === false) return self::response(560);

		// define statistics
		$stat = (object)[
			'sent'			=> 0,
			'unconfirmed'	=> 0,
			'terminated'	=> 0,
			'failed'		=> [],
			];

		// for each entry
		foreach($list as $entry){

			// if abo not confirmed or already terminated
			if(!$entry->confirmed or $entry->terminated){

				// update reminder
				$res = self::update_reminder([
					'reminderID'	=> $entry->reminderID,
					'done'			=> true,
					]);

				// on error
				if($res->status != 204){
					!isset($stat->failed['update_reminder'][$res->status]) ? $stat->failed['update_reminder'][$res->status] = 0 : $stat->failed['update_reminder'][$res->status]++;
					continue;
					}

				// update stat
				$entry->terminated ? $stat->terminated++ : $stat->unconfirmed++;
				continue;
				}

			// load reminder_text
			$res = self::get_reminder_text([
				'reminder_textID'	=> $entry->reminder_textID,
				]);

			// on error
			if($res->status != 200){
				!isset($stat->failed['get_reminder_text'][$res->status]) ? $stat->failed['get_reminder_text'][$res->status] = 0 : $stat->failed['get_reminder_text'][$res->status]++;
				continue;
				}

			// take text
			$sms_text = $res->data->text;

			// load product
			$res = service::get_product([
				'type'		=> 'abo',
				'productID'	=> $entry->productID,
				]);

			// on error
			if($res->status != 200){
				!isset($stat->failed['get_product_'.$entry->productID][$res->status]) ? $stat->failed['get_product_'.$entry->productID][$res->status] = 0 : $stat->failed['get_product_'.$entry->productID][$res->status]++;
				continue;
				}

			// take product
			$product = $res->data;

			// check for reminderServiceID
			if(!h::gX($product, 'param:reminder:serviceID')){
				!isset($stat->failed['no_serviceID_'.$entry->productID][$res->status]) ? $stat->failed['no_serviceID_'.$entry->productID][$res->status] = 0 : $stat->failed['no_serviceID_'.$entry->productID][$res->status]++;
				continue;
				}

			// take serviceID
			$serviceID = h::gX($product, 'param:reminder:serviceID');

			// check for reminderSenderString
			$sender_string = h::gX($product, 'param:reminder:sender');

			// send sms
			$res = client::send_sms([
				'serviceID'		=> $serviceID,
				'mobileID'		=> $entry->mobileID,
				'text'			=> $sms_text,
				'senderString'	=> $sender_string ?: null,
				'persistID'		=> $entry->persistID ?: null,
				'operatorID'	=> $entry->operatorID,
				]);

			// on error
			if($res->status != 201){
				!isset($stat->failed['send_sms'][$res->status]) ? $stat->failed['send_sms'][$res->status] = 0 : $stat->failed['send_sms'][$res->status]++;
				continue;
				}

			// take sms data
			$sms = $res->data;

			// update reminder
			$res = self::update_reminder([
				'reminderID'	=> $entry->reminderID,
				'smsID'			=> $sms->smsID,
				'done'			=> true,
				]);

			// on error
			if($res->status != 204){
				!isset($stat->failed['update_reminder'][$res->status]) ? $stat->failed['update_reminder'][$res->status] = 0 : $stat->failed['update_reminder'][$res->status]++;
				continue;
				}

			// update stats
			$stat->sent++;
			}

		// return result
		return self::response(200, $stat);
		}


	/* Helper: import msisdn list */
	public static function create_reminder_for_msisdn($req = []){

		// mandatory
		$mand = h::eX($req, [
			'msisdn_list'	=> '~!empty/a',
			], $error);

		// optional
		$opt = h::eX($req, [
			'planTime'		=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// for each msisdn
		foreach($mand['msisdn_list'] as $key => $msisdn) {

			// check publisherID
			if(!preg_match('/^(?:\+|00|)([1-9]{1}[0-9]{5,14})$/', $msisdn, $match)) return self::response(400, ['msisdn_list']);

			// save correct msisdn
			$mand['msisdn_list'][$key] = $match[1];
			}

		// define defaults
		$opt += [
			'planTime'		=> h::dtstr('now'),
			];

		// define statistics
		$stat = (object)[
			'msisdn_given'		=> count($mand['msisdn_list']),
			'imp_found'			=> 0,
			'reminder_created'	=> 0,
			'failed'			=> [],
			];

		// reminder text cache
		$reminder_text = [];

		// for each msisdn
		foreach($mand['msisdn_list'] as $msisdn) {

			// load list of reminder to send
			$imp_list = self::pdo('l_imp_new_msisdn_reminder', [$msisdn]);

			// on error
			if($imp_list === false) return self::response(560);

			// update stat
			if($imp_list) $stat->imp_found++;

			// for each import entry (aboID, mobileID, productID, operatorID)
			foreach($imp_list as $imp){

				// if no reminder text is defined for productID
				if(empty($reminder_text[$imp->productID])){

					// load product
					$res = service::get_product([
						'type'		=> 'abo',
						'productID'	=> $imp->productID,
						]);

					// on error
					if($res->status != 200) return self::response(500, 'No product for productID '.$imp->productID.' found: '.$res->status.' (Nothing imported!)');

					// take product
					$product = $res->data;

					// define reminder text
					$reminder_text[$imp->productID] = h::gX($product, 'param:reminder:text');

					// if no text could be defined, abort
					if(empty($reminder_text[$imp->productID])) return self::response(500, 'No message text for productID '.$imp->productID.' found. (Nothing imported!)');
					}

				// create reminder
				$res = self::create_reminder([
					'aboID'		=> $imp->aboID,
					'text'		=> $reminder_text[$imp->productID],
					'planTime'	=> $opt['planTime'],
					]);

				// on error
				if($res->status != 201){
					!isset($stat->failed['create_reminder'][$res->status]) ? $stat->failed['create_reminder'][$res->status] = 0 : $stat->failed['create_reminder'][$res->status]++;
					continue;
					}

				// update stat
				$stat->reminder_created++;
				}
			}

		// return result
		return self::response(200, $stat);
		}
	}
