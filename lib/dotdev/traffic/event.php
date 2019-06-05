<?php
/*****
 * Version 1.4.2019-02-01
**/
namespace dotdev\traffic;

use \tools\error as e;
use \tools\helper as h;
use \tools\http;
use \dotdev\cronjob;
use \dotdev\nexus\base as nexus_base;
use \dotdev\nexus\domain as nexus_domain;
use \dotdev\nexus\levelconfig as nexus_lc;
use \dotdev\nexus\publisher as nexus_publisher;
use \dotdev\nexus\catlop as nexus_catlop;
use \dotdev\nexus\adjust as nexus_adjust;
use \dotdev\livestat;

class event {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO/Redis Config */
	protected static function pdo_config(){
		return ['mt_traffic:event', [

			// queries: event
			's_base_event'				=> 'SELECT * FROM `event` e WHERE e.eventID = ? LIMIT 1',
			's_abo_event'				=> 'SELECT e.*, a.aboID, a.productID, a.paidafter, a.terminateTime, a.charges, a.charges_max, a.charges_refunded, a.livetime
											FROM `event_abo` a
											INNER JOIN `event` e ON e.eventID = a.eventID
											WHERE a.eventID = ? OR a.aboID = ?
											LIMIT 1
											',
			's_otp_event'				=> 'SELECT e.*, o.otpID, o.productID
											FROM `event_otp` o
											INNER JOIN `event` e ON e.eventID = o.eventID
											WHERE o.eventID = ? OR o.otpID = ?
											LIMIT 1
											',
			's_smspay_event'			=> 'SELECT e.*, s.smspayID, s.productID, s.lastTime, s.mo, s.mt, s.billing
											FROM `event_smspay` s
											INNER JOIN `event` e ON e.eventID = s.eventID
											WHERE s.eventID = ? OR s.smspayID = ?
											LIMIT 1
											',

			's_base_event_last'			=> 'SELECT e.*
											FROM `event` e
											WHERE e.persistID = ? AND e.type = ?
											ORDER BY e.createTime DESC
											LIMIT 1
											',

			'l_base_event_by_persistID'	=> 'SELECT * FROM `event` e WHERE e.persistID = ? ORDER BY e.createTime ASC',
			'l_event_comlog'			=> 'SELECT * FROM `event_comlog` c WHERE c.eventID = ? ORDER BY c.createTime ASC',

			'i_event'					=> 'INSERT INTO `event` (`createTime`,`clickID`,`persistID`,`callbacks`,`income`,`cost`,`type`) VALUES (?,?,?,?,?,?,?)',
			'i_event_abo'				=> 'INSERT INTO `event_abo` (`eventID`,`aboID`,`productID`,`paidafter`,`terminateTime`,`charges`,`charges_max`,`charges_refunded`,`livetime`) VALUES (?,?,?,?,?,?,?,?,?)',
			'i_event_otp'				=> 'INSERT INTO `event_otp` (`eventID`,`otpID`,`productID`) VALUES (?,?,?)',
			'i_event_smspay'			=> 'INSERT INTO `event_smspay` (`eventID`,`smspayID`,`productID`,`lastTime`,`mo`,`mt`,`billing`) VALUES (?,?,?,?,?,?,?)',
			'i_event_comlog'			=> 'INSERT INTO `event_comlog` (`createTime`,`eventID`,`trigger`,`request`,`httpcode`,`response`) VALUES (?,?,?,?,?,?)',

			'u_event'					=> 'UPDATE `event` SET `callbacks` = ?, `income` = ?, `cost` = ? WHERE `eventID` = ?',
			'u_event_abo'				=> 'UPDATE `event_abo` SET `paidafter` = ?, `terminateTime` = ?, `charges` = ?, `charges_max` = ?, `charges_refunded` = ?, `livetime` = ? WHERE `eventID` = ?',
			'u_event_smspay'			=> 'UPDATE `event_smspay` SET `lastTime` = ?, `mo` = ?, `mt` = ?, `billing` = ? WHERE `eventID` = ?',

			// queries: event triggering
			's_csession'				=> 'SELECT
												s.persistID, s.domainID, s.pageID, s.publisherID, s.publisher_affiliateID, s.mobileID, s.operatorID, s.deviceID, s.countryID,
												lo.apkID, lo.apk_build as `last_apk_build`,
												c.clickID, c.createTime, p.request
											FROM `click` c
											INNER JOIN `session` s ON s.persistID = c.persistID
											LEFT JOIN `click_pubdata` p ON p.clickID = c.clickID
											LEFT JOIN `session_open` lo ON lo.persistID = s.persistID
											LEFT JOIN `session_open` lo2 ON lo2.persistID = lo.persistID AND lo2.createTime > lo.createTime
											WHERE c.clickID = ?
											LIMIT 1
											',
			's_csession_by_persistID'	=> 'SELECT
												s.persistID, s.domainID, s.pageID, s.publisherID, s.publisher_affiliateID, s.mobileID, s.operatorID, s.deviceID, s.countryID,
												lo.apkID, lo.apk_build as `last_apk_build`,
												c.clickID, c.createTime, p.request
											FROM `session` s
											LEFT JOIN `click` c ON c.persistID = s.persistID
											LEFT JOIN `click` c2 ON c2.persistID = c.persistID AND c2.createTime > c.createTime
											LEFT JOIN `click_pubdata` p ON p.clickID = c.clickID
											LEFT JOIN `session_open` lo ON lo.persistID = s.persistID
											LEFT JOIN `session_open` lo2 ON lo2.persistID = lo.persistID AND lo2.createTime > lo.createTime
											WHERE s.persistID = ? AND c2.persistID IS NULL
											LIMIT 1
											',

			's_event_mlogic'			=> 'SELECT `type`, COUNT(*) as `sum` FROM `event` WHERE `persistID` = ? AND `callbacks` > 0 GROUP BY `type`',

			]];
		}

	protected static function redis_config(){
		return 'mt_traffic';
		}


	/* Object: event */
	public static function get_event($req = []){

		// one mandatory
		$alt = h::eX($req, [
			'eventID'		=> '~1,4294967295/i',
			'type'			=> '~^[a-z]{1,8}$',
			'aboID'			=> '~1,4294967295/i',
			'otpID'			=> '~1,4294967295/i',
			'smspayID'		=> '~1,4294967295/i',
			'persistID'		=> '~1,18446744073709551615/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'with_comlog'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// define function to alter result on success
		$on_success_alter = function($result) use ($alt, $opt){

			// make list (of objects)
			$list = is_array($result) ? $result : [$result];

			// if comlog is wanted
			if(!empty($opt['with_comlog'])){

				// for each entry
				foreach($list as $entry){

					// load comlog data
					$entry->comlog = self::pdo('l_event_comlog', $entry->eventID);

					// on error
					if($entry->comlog === false) return self::response(560);
					}
				}

			// return result
			return self::response(200, $result);
			};


		// param order 1: eventID (+ type)
		if(isset($alt['eventID'])){

			// try to load cached entry
			$entry = self::_get_cached_event('eventID', $alt['eventID']);

			// if no entry could be loaded
			if(!$entry){

				// param order 1-1: abo event
				if(isset($alt['type']) and $alt['type'] == 'abo'){

					// search in DB
					$entry = self::pdo('s_abo_event', [$alt['eventID'], 0]);
					}

				// param order 1-2: OTP event
				elseif(isset($alt['type']) and $alt['type'] == 'otp'){

					// search in DB
					$entry = self::pdo('s_otp_event', [$alt['eventID'], 0]);
					}

				// param order 1-3: SMSPay event
				elseif(isset($alt['type']) and $alt['type'] == 'smspay'){

					// search in DB
					$entry = self::pdo('s_smspay_event', [$alt['eventID'], 0]);
					}

				// param order 1-4: base event
				else{

					// search in DB
					$entry = self::pdo('s_base_event', [$alt['eventID']]);

					// on error
					if(!$entry) return self::response($entry === false ? 560 : 404);

					// for specific event types
					if(in_array($entry->type, ['abo','otp','smspay'])){

						// reload event with complete data
						$entry = self::pdo('s_'.$entry->type.'_event', [$alt['eventID'], 0]);
						}
					}

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// cache entry
				self::_set_cached_event($entry);
				}

			// return entry
			return $on_success_alter($entry);
			}

		// param order 2: aboID
		if(isset($alt['aboID'])){

			// try to load cached entry
			$entry = self::_get_cached_event('aboID', $alt['aboID']);

			// if no entry could be loaded
			if(!$entry){

				// search in DB
				$entry = self::pdo('s_abo_event', [0, $alt['aboID']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// cache entry
				self::_set_cached_event($entry);
				}

			// return entry
			return $on_success_alter($entry);
			}

		// param order 3: otpID
		if(isset($alt['otpID'])){

			// try to load cached entry
			$entry = self::_get_cached_event('otpID', $alt['otpID']);

			// if no entry could be loaded
			if(!$entry){

				// search in DB
				$entry = self::pdo('s_otp_event', [0, $alt['otpID']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// cache entry
				self::_set_cached_event($entry);
				}

			// return entry
			return $on_success_alter($entry);
			}

		// param order 4: smspayID
		if(isset($alt['smspayID'])){

			// try to load cached entry
			$entry = self::_get_cached_event('smspayID', $alt['smspayID']);

			// if no entry could be loaded
			if(!$entry){

				// search in DB
				$entry = self::pdo('s_smspay_event', [0, $alt['smspayID']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// cache entry
				self::_set_cached_event($entry);
				}

			// return entry
			return $on_success_alter($entry);
			}

		// param order 5: persistID+type
		if(isset($alt['persistID']) and isset($alt['type'])){

			// load last base event
			$entry = self::pdo('s_base_event_last', [$alt['persistID'], $alt['type']]);

			// on error
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// if type is abo|otp|smspay
			if(in_array($entry->type, ['abo','otp','smspay'])){

				// reload as subtype event
				$entry = self::pdo('s_'.$entry->type.'_event', [$entry->eventID, 0]);

				// on error
				if($entry === false) return self::response(560);

				// if entry is not found again
				if(!$entry) return self::response(500, 'Cannot load eventID '.$entry->eventID.' as '.$entry->type.'_event');
				}

			// return entry
			return $on_success_alter($entry);
			}

		// param order 6: persistID only
		if(isset($alt['persistID'])){

			// load last base event
			$list = self::pdo('l_base_event_by_persistID', [$alt['persistID']]);

			// on error
			if($list === false) return self::response(560);

			// foreach entry
			foreach($list as $k => $entry){

				// skip if type is not abo|otp|smspay
				if(!in_array($entry->type, ['abo','otp','smspay'])) continue;

				// reload as subtype event
				$list[$k] = self::pdo('s_'.$entry->type.'_event', [$entry->eventID, 0]);

				// on error
				if($list[$k] === false) return self::response(560);

				// if entry is not found again
				if(!$list[$k]) return self::response(500, 'Cannot load eventID '.$entry->eventID.' as '.$entry->type.'_event');
				}

			// return list
			return $on_success_alter($list);
			}

		// other request param invalid
		return self::response(400, 'need eventID(+type)|aboID|otpID|smspayID|persistID+type parameter');
		}

	public static function create_event($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'			=> '~^[a-z]{1,8}$',
			'persistID'		=> '~1,18446744073709551615/i',
			], $error);

		// optional | conditional
		$opt = h::eX($req, [

			// event param
			'createTime'	=> '~Y-m-d H:i:s/d',
			'clickID'		=> '~0,18446744073709551615/i',
			'callbacks'		=> '~0,65535/i',
			'income'		=> '~0,99999/f',
			'cost'			=> '~0,99999/f',

			// event_* param
			'aboID'			=> '~1,4294967295/i',
			'otpID'			=> '~1,4294967295/i',
			'smspayID'		=> '~1,4294967295/i',
			'productID'		=> '~1,65535/i',

			// event_abo param
			'paidafter'		=> '~0,4294967295/i',
			'charges'		=> '~0,65535/i',
			'charges_max'	=> '~0,65535/i',
			'charges_refunded'=> '~0,65535/i',
			'terminateTime' => '~Y-m-d H:i:s/d',
			'livetime'		=> '~0,4294967295/i',

			// event_smspay param
			'lastTime' 		=> '~Y-m-d H:i:s/d',
			'mo'			=> '~0,65535/i',
			'mt'			=> '~0,65535/i',
			'billing'		=> '~0,65535/i',
			], $error, true);

		// check existance of conditional needed param
		if(isset($mand['type']) and in_array($mand['type'], ['abo','otp','smspay'])){
			if($mand['type'] == 'abo' and !isset($opt['aboID']) and !in_array('aboID', $error)) $error[] = 'aboID';
			if($mand['type'] == 'otp' and !isset($opt['otpID']) and !in_array('otpID', $error)) $error[] = 'otpID';
			if($mand['type'] == 'smspay' and !isset($opt['smspayID']) and !in_array('smspayID', $error)) $error[] = 'smspayID';
			if(!isset($opt['productID']) and !in_array('productID', $error)) $error[] = 'productID';
			}

		// on error
		if($error) return self::response(400, $error);

		// define entry
		$entry = [
			'eventID'		=> null,
			'createTime'	=> $opt['createTime'] ?? h::dtstr('now'),
			'clickID' 		=> $opt['clickID'] ?? 0,
			'persistID'		=> $mand['persistID'],
			'callbacks'		=> $opt['callbacks'] ?? 0,
			'income'		=> $opt['income'] ?? 0,
			'cost'			=> $opt['cost'] ?? 0,
			'type'			=> $mand['type'],
			];


		// create entry
		$entry['eventID'] = self::pdo('i_event', [$entry['createTime'], $entry['clickID'], $entry['persistID'], $entry['callbacks'], $entry['income'], $entry['cost'], $entry['type']]);

		// on error
		if($entry['eventID'] === false) return self::response(560);

		// if this is an abo event
		if($entry['type'] == 'abo'){

			// add abo event specific keys
			$entry += [
				'aboID'			=> $opt['aboID'],
				'productID'		=> $opt['productID'],
				'paidafter'		=> $opt['paidafter'] ?? null,
				'terminateTime' => $opt['terminateTime'] ?? '0000-00-00 00:00:00',
				'charges'		=> $opt['charges'] ?? 0,
				'charges_max'	=> $opt['charges_max'] ?? 0,
				'charges_refunded'=> $opt['charges_refunded'] ?? 0,
				'livetime'		=> $opt['livetime'] ?? 0,
				];

			// create entry
			$ins = self::pdo('i_event_abo', [$entry['eventID'], $entry['aboID'], $entry['productID'], $entry['paidafter'], $entry['terminateTime'], $entry['charges'], $entry['charges_max'], $entry['charges_refunded'], $entry['livetime']]);

			// on error
			if($ins === false) return self::response(560);
			}

		// if this is an otp event
		if($entry['type'] == 'otp'){

			// add otp event specific keys
			$entry += [
				'otpID'			=> $opt['otpID'],
				'productID'		=> $opt['productID'],
				];

			// create entry
			$ins = self::pdo('i_event_otp', [$entry['eventID'], $entry['otpID'], $entry['productID']]);

			// on error
			if($ins === false) return self::response(560);
			}

		// if this is an smspay event
		if($entry['type'] == 'smspay'){

			// add smspay event specific keys
			$entry += [
				'smspayID'		=> $opt['smspayID'],
				'productID'		=> $opt['productID'],
				'lastTime' 		=> $opt['lastTime'] ?? h::dtstr('now'),
				'mo'			=> $opt['mo'] ?? 0,
				'mt'			=> $opt['mt'] ?? 0,
				'billing'		=> $opt['billing'] ?? 0,
				];

			// create entry
			$ins = self::pdo('i_event_smspay', [$entry['eventID'], $entry['smspayID'], $entry['productID'], $entry['lastTime'], $entry['mo'], $entry['mt'], $entry['billing']]);

			// on error
			if($ins === false) return self::response(560);
			}

		// cache event
		self::_set_cached_event((object) $entry);

		// return success
		return self::response(201, (object)['eventID'=>$entry['eventID']]);
		}

	public static function update_event($req = []){

		// alternative (one is mandatory)
		$alt = h::eX($req, [
			'eventID'		=> '~1,4294967295/i',
			'type'			=> '~^[a-z]{1,8}$',
			'aboID'			=> '~1,4294967295/i',
			'otpID'			=> '~1,4294967295/i',
			'smspayID'		=> '~1,4294967295/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [

			// event param
			'callbacks'		=> '~0,65535/i',
			'incr_callbacks'=> '~1,100/i',
			'income'		=> '~0,99999/f',
			'cost'			=> '~0,99999/f',

			// event_abo param
			'paidafter'		=> '~0,4294967295/i',
			'terminated'	=> '~/b',
			'terminateTime' => '~Y-m-d H:i:s/d',
			'charges'		=> '~0,65535/i',
			'charges_max'	=> '~0,65535/i',
			'charges_refunded'=> '~0,65535/i',
			'livetime'		=> '~0,4294967295/i',

			// event_smspay param
			'lastTime' 		=> '~Y-m-d H:i:s/d',
			'mo'			=> '~0,65535/i',
			'mt'			=> '~0,65535/i',
			'billing'		=> '~0,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// load entry
		$res = self::get_event($alt);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;


		// convert incr_callback param
		if(isset($opt['incr_callbacks'])){

			// if callbacks is not already set
			if(!isset($opt['callbacks'])){

				// calc new callbacks values (not exceeding value limit)
				$opt['callbacks'] = min($entry->callbacks + $opt['incr_callbacks'], 65535);
				}

			// unset special param
			unset($opt['incr_callbacks']);
			}

		// convert reopen param
		if(isset($opt['terminated'])){

			// if terminateTime is not already set
			if(!isset($opt['terminateTime'])){

				// convert to terminateTime
				$opt['terminateTime'] = $opt['terminated'] ? h::dtstr('now') : '0000-00-00 00:00:00';
				}

			// unset special param
			unset($opt['terminated']);
			}


		// updateable rows
		$u = ['event'=>false, 'abo'=>false, 'smspay'=>false];

		// replace params
		foreach($opt as $k => $v){

			// update (or add) param
			$entry->{$k} = $v;

			// check if event itself must be updated
			if(in_array($k, ['callbacks','income','cost'])) $u['event'] = true;

			// check if event_abo must be updated
			if($entry->type == 'abo' and in_array($k, ['paidafter', 'terminateTime', 'charges', 'charges_max', 'charges_refunded', 'livetime'])) $u['abo'] = true;

			// check if event_smspay must be updated
			if($entry->type == 'smspay' and in_array($k, ['lastTime', 'mo', 'mt', 'billing'])) $u['smspay'] = true;
			}

		// update event
		if($u['event']){

			// update entry
			$upd = self::pdo('u_event', [$entry->callbacks, $entry->income, $entry->cost, $entry->eventID]);

			// on error
			if($upd === false) return self::response(560);
			}

		// update event_abo
		if($u['abo']){

			// update entry
			$upd = self::pdo('u_event_abo', [$entry->paidafter, $entry->terminateTime, $entry->charges, $entry->charges_max, $entry->charges_refunded, $entry->livetime, $entry->eventID]);

			// on error
			if($upd === false) return self::response(560);
			}

		// update event_abo
		if($u['smspay']){

			// update entry
			$upd = self::pdo('u_event_smspay', [$entry->lastTime, $entry->mo, $entry->mt, $entry->billing, $entry->eventID]);

			// on error
			if($upd === false) return self::response(560);
			}

		// cache event
		self::_set_cached_event((object) $entry);

		// return success
		return self::response(204);
		}

	public static function _set_cached_event($entry){

		// init redis
		$redis = self::redis();

		// abort, if redis is not accessable or entry seems invalid
		if(!$redis or !is_object($entry) or empty($entry->eventID)) return null;

		// cache entry with eventID
		$redis->set('event:by_eventID:'.$entry->eventID, $entry, ['ex'=>600]); // 10 min

		// for each specific key
		foreach(['aboID', 'otpID', 'smspayID'] as $specific_key){

			// if key is not set
			if(empty($entry->{$specific_key})) continue;

			// cache eventID with specific key
			$redis->set('event:eventID_by_'.$specific_key.':'.$entry->{$specific_key}, $entry->eventID, ['ex'=>600]); // 10 min

			// skip further searching
			break;
			}

		// return success
		return true;
		}

	public static function _get_cached_event($key, $value){

		// init redis
		$redis = self::redis();

		// if redis is not accessable, abort here
		if(!$redis) return null;

		// define entry
		$entry = null;

		// if keyname is not eventID and value is set
		if($key != 'eventID' and $value){

			// if entry exists
			if($redis->exists('event:eventID_by_'.$key.':'.$value)){

				// load eventID
				$eventID = $redis->get('event:eventID_by_'.$key.':'.$value);

				// if eventID is set
				if($eventID){

					// overwrite values
					$key = 'eventID';
					$value = $eventID;
					}
				}
			}

		// if keyname is eventID and value is set
		if($key == 'eventID' and $value){

			// if entry exists
			if($redis->exists('event:by_eventID:'.$value)){

				// load entry
				$entry = $redis->get('event:by_eventID:'.$value);
				}
			}

		// return result
		return $entry;
		}


	/* System: event triggering */
	public static function trigger_event($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'				=> '~^[a-z]{1,8}$',
			'persistID'			=> '~1,18446744073709551615/i',
			], $error);

		// optional | conditional
		$opt = h::eX($req, [

			// subevent & status
			'subevent'			=> '~^(?:mo|mt|otp|charge|abo)$',
			'status'			=> '~^(?:paid|create|confirm|terminate|refund|update)$',

			// event param
			'createTime'		=> '~Y-m-d H:i:s/d',
			'income'			=> '~0,99999/f',
			'income_increase'	=> '~0,99999/f',
			'cost'				=> '~0,99999/f',

			// event_* param
			'aboID'				=> '~1,4294967295/i',
			'otpID'				=> '~1,4294967295/i',
			'smspayID'			=> '~1,4294967295/i',
			'productID'			=> '~1,65535/i',

			// event_abo param
			'paidafter'			=> '~0,4294967295/i',
			'charges'			=> '~0,65535/i',
			'charges_max'		=> '~0,65535/i',
			'charges_refunded'	=> '~0,65535/i',
			'terminated'		=> '~/b',
			'terminateTime' 	=> '~Y-m-d H:i:s/d',
			'livetime'			=> '~0,4294967295/i',

			// event_smspay param
			'mo'				=> '~0,65535/i',
			'mt'				=> '~0,65535/i',
			'billing'			=> '~0,65535/i',
			'lastTime'			=> '~Y-m-d H:i:s/d',

			// other param
			'force_callback'	=> '~/b',
			], $error, true);

		// check existance of conditional needed param
		if(isset($mand['type']) and in_array($mand['type'], ['abo','otp','smspay'])){
			if($mand['type'] == 'abo' and !isset($opt['aboID']) and !in_array('aboID', $error)) $error[] = 'aboID';
			if($mand['type'] == 'otp' and !isset($opt['otpID']) and !in_array('otpID', $error)) $error[] = 'otpID';
			if($mand['type'] == 'smspay' and !isset($opt['smspayID']) and !in_array('smspayID', $error)) $error[] = 'smspayID';
			if(!isset($opt['productID']) and !in_array('productID', $error)) $error[] = 'productID';
			}

		// on error
		if($error) return self::response(400, $error);


		// define base defaults
		$opt += [
			'createTime'		=> h::dtstr('now'),
			'income_increase'	=> 0.00,
			'subevent'			=> $mand['type'],
			'status'			=> 'update',
			];

		// define event
		$event = null;


		// for specific event types reuse previous event (with associated identifier)
		if(in_array($mand['type'], ['abo','otp','smspay','install','error'])){

			// try to load event
			$res = self::get_event($mand + $opt);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// and take event if found
			if($res->status == 200) $event = $res->data;

			// if 'install' event already exists
			if($event and $event->type == 'install'){

				// discard event and rename type to generate 'reinst' event
				$event = null;
				$mand['type'] = 'reinst';
				}
			}

		// if an event is given
		if($event){

			// load session from associated or last click
			$session = $event->clickID ? self::pdo('s_csession', [$event->clickID]) : self::pdo('s_csession_by_persistID', [$event->persistID]);

			// on error
			if($session === false) return self::response(560);

			// if session not found
			if(!$session){

				// log error
				//e::logtrigger('DEBUG: Session for eventID '.$event->eventID.' not found. ('.h::encode_php($mand).')');

				// return 412 precondition failed
				return self::response(412);
				}

			// update event
			$res = self::update_event([
				'type'			=> $event->type,
				'eventID'		=> $event->eventID,
				] + $opt);

			// on error
			if($res->status != 204) return self::response(570, $res);
			}

		// or
		else{

			// load session from last click
			$session = self::pdo('s_csession_by_persistID', [$mand['persistID']]);

			// on error
			if($session === false) return self::response(560);

			// if session not found
			if(!$session){

				// log error
				//e::logtrigger('DEBUG: Session for new event not found. ('.h::encode_php($mand).')');

				// return 412 precondition failed
				return self::response(412);
				}

			// create event
			$res = self::create_event([
				'type'			=> $mand['type'],
				'persistID'		=> $session->persistID,
				'clickID'		=> $session->clickID, // this could be null
				] + $opt);

			// on error
			if($res->status != 201) return self::response(570, $res);

			// take event (object hast eventID only)
			$event = $res->data;

			// increment domain event counter (without checking response)
			$res = livestat::incr_dh_counter([
				'group'			=> 'traffic_event_domain_'.$session->domainID,
				'name'			=> $mand['type'],
				'datetime'		=> $opt['createTime'],
				]);
			}


		// if session has no publisherID or pageID, then no further processing for callback is needed
		if(!$session->publisherID or !$session->pageID){

			// return done
			return self::response(204);
			}


		// load publisher of session (reloading to highest owner of publisher)
		$res = nexus_publisher::get_publisher([
			'publisherID'	=> $session->publisherID,
			'load_top_owner'=> true,
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take levelconfig
		$publisher = $res->data;


		// load adtarget of session
		$res = nexus_domain::get_adtarget([
			'pageID'	=> $session->pageID,
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take adtarget
		$adtarget = $res->data;


		// load levelconfig for adtarget and defined publisher
		$res = nexus_lc::get_levelconfig([
			'level'			=> 'user-inherited',
			'pageID'		=> $adtarget->pageID,
			'publisherID'	=> $publisher->publisherID,
			'format'		=> 'nested',
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take levelconfig
		$lc = $res->data;


		// if publisher is not enabled, then no further processing for callback is needed
		if($publisher->status != 'enabled' or !h::gX($lc, 'pub:callback_fn')){

			// return done
			return self::response(204);
			}


		// define (extended) callback
		$callback_fn = h::gX($lc, 'pub:callback_fn');
		$adtarget_callback_event = h::gX($lc, 'app:callback_on_event');
		$trigger_set = [];
		$run_callback = false;


		// check for defined trigger (only take array if with at least one entry)
		if(h::cX($lc, 'pub:callback_trigger', '~^\[.+\]$')){

			// read and convert settings
			$trigger_set = json_decode(h::gX($lc, 'pub:callback_trigger'));

			// if setting is somehow empty or not an array
			if(empty($trigger_set) or !is_array($trigger_set)){

				// abort with error
				return self::response(500, 'Invalid configured trigger_set for Adtargets '.$adtarget->pageID.' publisherID '.$adtarget->publisherID.': '.h::encode_php($trigger_set).' (persistID '.$mand['persistID'].')');
				}
			}

		// if no trigger defined, define default (but only the adtarget defined event type is considered, which means only one trigger is defined in the set)
		if(!$trigger_set and $adtarget_callback_event){

			// trigger: for abo event, if type defined on adtarget, if first charge, if status is 'paid', if is paid in the first 48 hours
			if($adtarget_callback_event == 'abo') $trigger_set = [(object)[
				'type'			=> 'abo',
				'onlydefined'	=> true,
				'subevent'		=> 'charge',
				'status'		=> 'paid',
				'paidcharge'	=> 1,
				'paidbefore'	=> 172800,
				]];

			// trigger: for otp event, if type defined on adtarget, if otp is paid
			elseif($adtarget_callback_event == 'otp') $trigger_set = [(object)[
				'type'			=> 'otp',
				'onlydefined'	=> true,
				'status'		=> 'paid',
				]];

			// trigger: for smspay event, if type defined on adtarget, for MO/MT, if status is 'paid', if first billing
			elseif($adtarget_callback_event == 'smspay') $trigger_set = [(object)[
				'type'			=> 'smspay',
				'onlydefined'	=> true,
				'subevent'		=> 'mo',
				'status'		=> 'paid',
				'billing'		=> 1,
				],(object)[
				'type'			=> 'smspay',
				'onlydefined'	=> true,
				'subevent'		=> 'mt',
				'status'		=> 'paid',
				'billing'		=> 1,
				]];

			// trigger: for any other event, if type defined on adtarget, if no callback was sent before
			else $trigger_set = [(object)[
				'type'			=> $adtarget_callback_event,
				'onlydefined'	=> true,
				'max'			=> 1,
				]];
			}

		// load firm of this server
		$res = nexus_base::get_firm([
			'self'	=> true,
			]);

		// on error
		if($res->status != 200) return self::response(500, 'Cannot load mtservice_url: '.$res->status);

		// take firm
		$firm = $res->data;

		// load event
		$res = self::get_event([
			'type'		=> $mand['type'],
			'eventID'	=> $event->eventID,
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take event
		$event = $res->data;

		// set temporary keys
		$event->status = $opt['status'];
		$event->subevent = $opt['subevent'];
		$event->income_increase = $opt['income_increase'];


		// run trigger configuration
		foreach($trigger_set as $trigger){

			// basically check configuration
			if(!is_object($trigger) or !h::cX($trigger, 'type', '~^[a-z]{1,8}$')){

				// abort with error
				return self::response(500, 'Invalid configured trigger for Adtargets '.$adtarget->pageID.' publisherID '.$adtarget->publisherID.': '.h::encode_php($trigger).' (persistID '.$mand['persistID'].')');
				}

			// mandatory: type rule
			if($event->type != $trigger->type) continue;

			// optional: only defined event rule (means only a given 'app:callback_on_event' type is allowed for callback)
			if(!empty($trigger->onlydefined) and $event->type != $adtarget_callback_event) continue;

			// optional: subevent rule
			if(isset($trigger->subevent) and $event->subevent != $trigger->subevent) continue;

			// optional: status rule
			if(isset($trigger->status) and $event->status != $trigger->status) continue;

			// optional: max rule (callbacks >= event maximum = skip)
			if(isset($trigger->max) and $event->callbacks >= $trigger->max) continue;

			// optional for abo: for specific paid charge count
			if($event->type == 'abo' and isset($trigger->paidcharge) and $event->charges != $trigger->paidcharge) continue;

			// optional for abo: must be paid before rule (skip if paidafter is null (equal not paid) or paidafter >= rule)
			if($event->type == 'abo' and isset($trigger->paidbefore) and ($event->paidafter === null or $event->paidafter >= $trigger->paidbefore)) continue;

			// optional for abo: must be paid after rule (skip if paidafter is null (equal not paid) or paidafter < rule)
			if($event->type == 'abo' and isset($trigger->paidafter) and ($event->paidafter === null or $event->paidafter < $trigger->paidafter)) continue;

			// optional for smspay: for specific MO count
			if($event->type == 'smspay' and isset($trigger->mo) and $event->mo != $trigger->mo) continue;

			// optional for smspay: for specific MT count
			if($event->type == 'smspay' and isset($trigger->mt) and $event->mt != $trigger->mt) continue;

			// optional for smspay: for specific billing count
			if($event->type == 'smspay' and isset($trigger->billing) and $event->billing != $trigger->billing) continue;

			// rule matches, so allow callback
			$run_callback = true;

			// define trigger name
			$event->trigger_name = $trigger->name ?? $event->type;

			// check if multi logic is defined (detecting if this event is not the first for a group of event-types)
			if(!empty($trigger->mlogic) and is_array($trigger->mlogic) and !empty($trigger->mlogicname) and is_string($trigger->mlogicname)){

				// load summary of event types
				$list = self::pdo('s_event_mlogic', $mand['persistID']);

				// on error
				if($list === false) return self::response(560);

				// for each entry in list
				foreach($list as $entry){

					// skip every non associated event type
					if(!in_array($entry->type, $trigger->mlogic)) continue;

					// redefine trigger name, as this is not the first event
					$event->trigger_name = $trigger->mlogicname;
					break;
					}
				}

			// stop iteration
			break;
			}

		// if callback should be send
		if($run_callback or !empty($opt['force_callback'])){

			// to be on the safe side, check function
			if(!$callback_fn or !is_callable($callback_fn)){

				// return error
				return self::response(501, 'Callback method for Adtargets '.$adtarget->pageID.' publisherID '.$adtarget->publisherID.' unavailable: '.h::encode_php($callback_fn).' (persistID '.$mand['persistID'].')');
				}

			// send to publisher callback function
			$res = call_user_func($callback_fn, [
				'event'		=> $event,
				'session'	=> $session,
				'adtarget'	=> $adtarget,
				'lc'		=> $lc,
				'firm'		=> $firm,
				]);

			// if we have response data
			if($res->status == 200){

				// create entry
				$ins = self::pdo('i_event_comlog', [$opt['createTime'], $event->eventID, substr($res->data->trigger, 0, 32), $res->data->request, $res->data->httpcode, $res->data->response]);

				// on error (only trigger error message)
				if($ins === false) self::response(560);

				// update event
				$res = self::update_event([
					'type'			=> $mand['type'],
					'eventID'		=> $event->eventID,
					'incr_callbacks'=> 1,
					]);

				// on error
				if($res->status != 204) return self::response(570, $res);
				}

			// else if status is something other than not acceptable (406 is a special case for aborting callbacks)
			elseif($res->status != 406){

				// log error
				//e::logtrigger('DEBUG: Sending callback for eventID '.$event->eventID.' failed with internal status: '.$res->status.' (persistID '.$event->persistID.')');
				}
			}

		// return success
		return self::response(204);
		}

	public static function delayed_event_trigger($req = []){

		// add common redisjob
		return cronjob::add_common_redisjob([
			'redisjob_fn'		=> '\\'.__CLASS__.'::trigger_event',
			'redisjob_abort_not'=> [204, 412],
			'redisjob_retry_on'	=> [412],
			] + (array) $req + [
			'redisjob_lvl'		=> 2,
			]);
		}


	/* System: base callback */
	public static function base_callback($req = []){

		// mandatory
		$mand = h::eX($req, [
			'event'		=> '~/o',
			'session'	=> '~/o',
			'adtarget'	=> '~/o',
			'lc'		=> '~/c',
			'firm'		=> '~/o',
			], $error);

		// optional
		$opt = h::eX($req, [
			'log_header'=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// sub mandatory
		$sub = h::eX($mand['lc'], [
			'pub:callback_method'	=> '~^(?:GET|get|POST|post|)$',
			'pub:callback_url'		=> '~^(?:http(?:s|)\:\/\/|{).+$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// define method and param array
		$method = strtolower($sub['pub:callback_method']);
		$param = [
			'method'	=> strtoupper($method),
			'log_header'=> !empty($opt['log_header']),
			];

		// create replaceable key array
		$replaceable = [
			'{unix_timestamp}' => time(),
			'{event:unixtime}' => h::dtstr($mand['event']->createTime, 'U'),
			];

		// loopin array
		$loopin = [];

		// check loopin config
		if(h::cX($mand['lc'], 'pub:callback_loopin', '~^{.+}$')){
			$loopin = json_decode(h::gX($mand['lc'], 'pub:callback_loopin'), true) ?: [];
			}

		// for each event variable
		foreach($mand['event'] as $key => $val){

			// eventID, createTime, clickID, persistID, callbacks, income, cost, type
			// type == 'abo': (aboID, productID, terminateTime, charges, charges_max, charges_refunded, livetime)
			// type == 'otp': (otpID, productID)
			// type == 'smspay': (smspayID, productID, mo)

			// skip request key
			if($key == 'request') continue;

			// set key as replaceable
			$replaceable['{event:'.$key.'}'] = $val;

			// set key in loopin array
			if(!empty($loopin['event:'.$key]) and is_string($loopin['event:'.$key])) $replaceable['{'.$loopin['event:'.$key].'}'] = $val;
			}

		// for each session variable
		foreach($mand['session'] as $key => $val){

			// domainID, pageID, publisherID, publisher_affiliateID, mobileID, operatorID
			// clickID, createTime, persistID, request

			// skip request key
			if($key == 'request') continue;

			// set key as replaceable
			$replaceable['{session:'.$key.'}'] = $val;

			// set key in loopin array
			if(!empty($loopin['session:'.$key]) and is_string($loopin['session:'.$key])) $replaceable['{'.$loopin['session:'.$key].'}'] = $val;
			}

		// for each adtarget variable
		foreach($mand['adtarget'] as $key => $val){

			// pageID, domainID, publisherID, publisher, hash, fqdn, domain, appID, firmID, countryID, adtarget_(videoID|game|ckey|prelp)

			// skip request key
			if($key == 'request') continue;

			// set key as replaceable
			$replaceable['{adtarget:'.$key.'}'] = $val;

			// set key in loopin array
			if(!empty($loopin['adtarget:'.$key]) and is_string($loopin['adtarget:'.$key])) $replaceable['{'.$loopin['adtarget:'.$key].'}'] = $val;
			}

		// for each publisher variable
		foreach(h::gX($mand['lc'], 'pub') as $key => $val){

			// skip specific keys
			if(in_array($key, ['enabled','color','info','affiliate_param','click_param','click_rule','callback_fn','callback_method','callback_url','callback_loopin','callback_urlencode','callback_useragent','uncover_name_param','uncover_param'])) continue;

			// set key as replaceable
			$replaceable['{pub:'.$key.'}'] = $val;

			// set key in loopin array
			if(!empty($loopin['pub:'.$key]) and is_string($loopin['pub:'.$key])) $replaceable['{'.$loopin['pub:'.$key].'}'] = $val;
			}

		// for each firm variable
		foreach($mand['firm'] as $key => $val){

			// name, mtservice_fqdn, firmID

			// set key as replaceable
			$replaceable['{firm:'.$key.'}'] = $val;

			// set key in loopin array
			if(!empty($loopin['firm:'.$key]) and is_string($loopin['firm:'.$key])) $replaceable['{'.$loopin['firm:'.$key].'}'] = $val;
			}

		// check for publisher request data
		if(h::cX($mand['session'], 'request', '~^{.+}$')){

			// for each request variable
			foreach(json_decode(h::gX($mand['session'], 'request'), true) ?: [] as $key => $val){

				// set key as replaceable
				$replaceable['{request:'.$key.'}'] = $val;

				// set key in loopin array
				if(!empty($loopin['request:'.$key]) and is_string($loopin['request:'.$key])) $replaceable['{'.$loopin['request:'.$key].'}'] = $val;
				}
			}

		// devide url from param definition
		list($sub['pub:callback_url'], $paramdef) = explode('?', $sub['pub:callback_url']) + [null, null];

		// create param array from definition (while skipping empty strings)
		foreach(explode('&', $paramdef) as $def){

			// extract key und replacestring
			list($key, $replacestring) = explode('=', $def) + [null, null];

			// if key is not set, url is wrong -> skip this
			if(!h::is($key, '~^.+$')) continue;

			// get value
			$val = h::replace_in_str($replacestring, $replaceable);

			// skip keys with empty value
			if($val == '') continue;

			// check if value seems unreplaced
			if(h::is($val, '~^\{[a-zA-Z0-9\:\_]+\}$')){

				// if value must come from publisher/user redirect: 412 Precondition failed
				if(strpos($val, '{request:') !== false) return self::response(412);

				// else config seems wrong: 417 Expectation Failed
				return self::response(417);
				}

			// finally add param
			$param[$method][$key] = $val;
			}

		// also replace in url
		$param['url'] = h::replace_in_str($sub['pub:callback_url'], $replaceable);

		// check if no request replacement is found in url, if: 412 Precondition failed
		if(strpos($param['url'], '{request:') !== false) return self::response(412);

		// set urlencoding
		$param['urlencode'] = (bool) h::gX($mand['lc'], 'pub:callback_urlencode');

		// add fake useragent
		if(h::gX($mand['lc'], 'pub:callback_useragent')) $param['useragent'] = h::gX($mand['lc'], 'pub:callback_useragent');

		// finally send callback
		$curl_obj = http::curl_obj($param);

		// return success
		return self::response(200, (object)[
			'request'	=> $curl_obj->method.' '.$curl_obj->url.(is_string($curl_obj->get) ? $curl_obj->get : '').($curl_obj->post ? "\n".(is_array($curl_obj->post) ? implode("\n", $curl_obj->post) : $curl_obj->post) : ''),
			'httpcode'	=> $curl_obj->httpcode,
			'response'	=> $curl_obj->content,
			'header_out'=> h::gX($curl_obj, 'header_out'),
			'header_in'	=> h::gX($curl_obj, 'header_in'),
			'trigger'	=> $mand['event']->trigger_name ?? '',
			]);
		}

	public static function adjust_callback($req = []){

		// mandatory
		$mand = h::eX($req, [
			'event'		=> '~/o',
			'session'	=> '~/o',
			'adtarget'	=> '~/o',
			'lc'		=> '~/c',
			], $error);

		// optional
		$opt = h::eX($req, [
			'log_header'=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// if no apkID defined, return Precondition Failed
		if(empty($mand['session']->apkID)) return self::response(412);

		// load apk
		$res = nexus_catlop::get_apk([
			'apkID'	=> $mand['session']->apkID,
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// add adjust_app token
		$mand['event']->adjust_app = $res->data->adjust_app;


		// try to load adjust app
		$res = nexus_adjust::get_adjust_app([
			'adjust_app'	=> $mand['event']->adjust_app,
			]);

		// on unexpected error
		if(!in_array($res->status, [200,404])) return self::response(570, $res);

		// if event was not found, return Precondition Failed
		if($res->status == 404) return self::response(412);

		// take adjust_app
		$adjust_app = $res->data;


		// try to load adjust event
		$res = nexus_adjust::get_adjust_event([
			'adjust_app'	=> $mand['event']->adjust_app,
			'event_key'		=> $mand['event']->trigger_name,
			]);

		// on unexpected error
		if(!in_array($res->status, [200,404])) return self::response(570, $res);

		// if event was not found, return Not Acceptable (aborting callback with no error)
		if($res->status == 404) return self::response(406);

		// add adjust_event token
		$mand['event']->adjust_event = $res->data->adjust_event;


		// add adjust_time (on own timezone, since we don't know the timezone defined in adjust account)
		$mand['event']->adjust_time = h::dtstr($mand['event']->createTime, 'Y-M-D\TH:i:s').'Z'.$adjust_app->timezone;


		// load firm of this server
		$res = nexus_base::get_firm([
			'self'	=> true,
			]);

		// on error
		if($res->status != 200) return self::response(500, 'Cannot load mtservice_url: '.$res->status);

		// take firm
		$firm = $res->data;


		// add adjust_callback_params (internal informations callbacks sent back)
		$mand['event']->adjust_callback_params = json_encode((object)[

			// eventID and firmID
			'eID'	=> (string) $mand['event']->eventID,
			'fID'	=> (string) $firm->firmID,

			// generated hash with salt to validate source of callback
			'ch'	=> sha1($firm->firmID.'-'.$mand['event']->eventID.'-'.$adjust_app->callback_salt),
			]);


		// try to find and decode request param
		$request_data = h::is($mand['session']->request, '~^{.+}$') ? json_decode($mand['session']->request, true) : false;

		// if request params are missing or invalid, return Precondition Failed
		if(!$request_data) return self::response(412);


		// define optional known adjust device identifier
		$device_ids = ['idfa','idfv','mac','mac_md5','mac_sha1','android_id','win_adid','win_naid','win_hwid','fire_adid'];

		// for each identifier
		foreach($device_ids as $key){

			// if key does not exist in request data
			if(!isset($request_data[$key])){

				// set empty default value (these params got removed from callback url without error)
				$request_data[$key] = '';
				}
			}

		// rewrite request data to session
		$mand['session']->request = json_encode($request_data);


		// forward to base callback function
		return self::base_callback([
			'event'		=> $mand['event'],
			'session'	=> $mand['session'],
			'adtarget'	=> $mand['adtarget'],
			'lc'		=> $mand['lc'],
			'log_header'=> $opt['log_header'] ?? null,
			'firm'		=> $firm,
			]);
		}



	/* System: test callback */
	public static function test_callback($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'			=> '~^[a-z]{1,8}$',
			'persistID'		=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'trigger_name'	=> '~/s',
			'aboID'			=> '~1,4294967295/i',
			'otpID'			=> '~1,4294967295/i',
			'smspayID'		=> '~1,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// if special event type and we have optional parameter
		if(in_array($mand['type'], ['abo','otp','smspay']) and !empty($opt)){

			// try loading event
			$res = self::get_event($mand + $opt);

			// on error
			if($res->status != 200) return $res;

			// take event
			$event = $res->data;

			// load session
			if($event->clickID) $session = self::pdo('s_csession', [$event->clickID]);
			else $session = self::pdo('s_csession_by_persistID', [$event->persistID]);

			// on error
			if($session === false) return self::response(560);

			// if session not found
			if(!$session){

				// log error
				e::logtrigger('DEBUG: Session for eventID '.$event->eventID.' not found. ('.h::encode_php($mand).')');

				// return 412 precondition failed
				return self::response(412);
				}
			}

		// or if we don't have optional parameter
		else{

			// search in DB for last session click
			$session = self::pdo('s_csession_by_persistID', [$mand['persistID']]);

			// on error
			if($session === false) return self::response(560);

			// if session not found
			if(!$session){

				// log error
				e::logtrigger('DEBUG: Session for new event not found. ('.h::encode_php($mand).')');

				// return 412 precondition failed
				return self::response(412);
				}

			// this is nearly like a real generated event
			$event = (object)([
				'eventID' 		=> 0,
				'createTime'	=> h::dtstr('now'),
				'clickID'		=> $session->clickID ?: 0,
				'persistID'		=> $mand['persistID'],
				'callbacks'		=> 0,
				'income'		=> 0,
				'income_increase'=> 0,
				'cost'			=> 0,
				] + ($mand['type'] == 'abo' ? [
				'type'			=> 'abo',
				'aboID'			=> 0,
				'productID'		=> 0,
				'terminateTime'	=> '0000-00-00 00:00:00',
				'charges'		=> 1,
				'charges_max'	=> 1,
				'charges_refunded'=> 0,
				'livetime'		=> 0,
				] : []) + ($mand['type'] == 'otp' ? [
				'type'			=> 'otp',
				'otpID'			=> 0,
				'productID'		=> 0,
				] : []) + ($mand['type'] == 'smspay' ? [
				'type'			=> 'smspay',
				'smspayID'		=> 0,
				'productID'		=> 0,
				'mo'			=> 1,
				'lastTime'		=> h::dtstr('now'),
				] : []) + [
				'type'			=> $mand['type'],
				]);
			}


		// append trigger_name
		$event->trigger_name = $opt['trigger_name'] ?? $event->type;


		// load adtarget
		$res = nexus_domain::get_adtarget([
			'pageID'	=> $session->pageID,
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take adtarget
		$adtarget = $res->data;


		// load publisher of session (reloading to highest owner of publisher) or adtargets publisherID
		$res = nexus_publisher::get_publisher([
			'publisherID'	=> $session->publisherID ?: $adtarget->publisherID,
			'load_top_owner'=> true,
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take levelconfig
		$publisher = $res->data;


		// load levelconfig for adtarget and defined publisher
		$res = nexus_lc::get_levelconfig([
			'level'			=> 'user-inherited',
			'pageID'		=> $session->pageID,
			'publisherID'	=> $publisher->publisherID,
			'format'		=> 'nested',
			]);

		// on error
		if($res->status != 200) return $res;

		// take leveconfig
		$lc = $res->data;


		// define callback function
		$callback_fn = h::gX($lc, 'pub:callback_fn') ?: null;

		// check function
		if(!$callback_fn or !is_callable($callback_fn)){
			return self::response(501, 'Publisher callback method unavailable: '.h::encode_php($callback_fn));
			}

		// send to publisher callback function
		return call_user_func($callback_fn, [
			'event'		=> $event,
			'session'	=> $session,
			'adtarget'	=> $adtarget,
			'lc'		=> $lc,
			'log_header'=> true,
			]);
		}

	}
