<?php
/*****
 * Version 1.0.2018-12-11
**/
namespace dotdev\apk;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\nexus\base as nexus_base;
use \dotdev\nexus\service as nexus_service;
use \dotdev\nexus\domain as nexus_domain;
use \dotdev\persist;
use \dotdev\reflector;
use \dotdev\cronjob;
use \dotdev\mobile;
use \dotdev\mobile\abo;
use \dotdev\mobile\otp;
use \dotdev\smsgate\service as smsgate_service;
use \dotdev\traffic\session as traffic_session;
use \dotdev\traffic\event as traffic_event;
use \dotdev\bragi\media as bragi_media;
use \dotdev\app\video;

// DEPRECATED
use \dotdev\app\bragi\profile as old_bragi_profile;
use \dotdev\app\bragi\message as old_bragi_message;
use \dotdev\app\bragi\event as old_bragi_event;

class oldapi {
	use \tools\libcom_trait;

	/* helper */
	protected static function _patch_request_data($req = []){

		// convert request data to array
		$req = (array) $req;

		// check for invalid IMSI
		if(isset($req['imsi']) and !h::is($req['imsi'], '~^[1-7]{1}[0-9]{5,15}$')){

			// unset IMSI
			unset($req['imsi']);
			}

		// check for invalid startTime (Bug in Flirrdy)
		if(isset($req['startTime']) and !h::is($req['startTime'], '~Y-m-d H:i:s/d')){

			// unset IMSI
			unset($req['startTime']);
			}

		// return patched request data
		return $req;
		}


	/* nexus functions */
	public static function get_product($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'			=> '~^(?:abo|smsabo|otp|smspay)$',
			'productID'		=> '~1,65535/i',
			], $error);

		// on error
		if($error) return self::response(400, 'APK oldapi get_product bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');

		// forward function request
		return nexus_service::get_product($mand);
		}



	/* mobile client functions */
	public static function get_mtservice_url($req = []){

		// define static for service url
		static $service_url = null;

		// load service url
		if(!$service_url){
			$service_url = include($_SERVER['ENV_PATH'].'/config/service/mtservice/server.php');
			}

		// return service url
		return self::response(200, (object)['url' => $service_url]);
		}

	public static function get_mobile($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// optional
		$opt = h::eX($patched_req, [

			// one is mandatory
			'mobileID'			=> '~1,4294967295/i',
			'msisdn'			=> '~^(?:\+|00|)(?:[1-9]{1}[0-9]{5,14})$',
			'persistID'			=> '~1,4294967295/i',
			'imsi'				=> '~^[1-7]{1}[0-9]{5,15}$',

			// first and second are mandatory, if any of these exists
			'type' 				=> '~^(?:abo|smsabo|otp|smspay)$',
			'productID'			=> '~1,65535/i',
			'productID_list'	=> '~!empty/a',

			// optional for dummy creation
			'autocreate'		=> '~/b',
			'operatorID'		=> '~1,65535/i',

			// optional for persistlink
			'autopersistlink'	=> '~/b',
			], $error, true);

		// additional checks
		if(!in_array('productID', $error) and (isset($opt['type']) or isset($opt['productID_list'])) and !isset($opt['productID'])) $error[] = 'productID';
		if(!in_array('type', $error) and (isset($opt['productID']) or isset($opt['productID_list'])) and !isset($opt['type'])) $error[] = 'type';

		// on error
		if($error) return self::response(400, 'APK oldapi get_mobile bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');

		// for each useable key
		foreach(['mobileID', 'msisdn', 'imsi', 'persistID'] as $key){

			// skip if no value exists
			if(!isset($opt[$key])) continue;

			// load mobile with given value
			$res = mobile::get_mobile([
				$key	=> $opt[$key],
				]);

			// abort if status is not 404
			if($res->status != 404) break;
			}

		// if there is no useable key that loads
		if(!isset($res)) return self::response(400, 'APK oldapi get_mobile bad request for: Need at least mobileID, msisdn, persistID or imsi (req = '.h::encode_php($req).')');

		// on error
		if(!in_array($res->status, [200, 404])) return $res;

		// if not found
		if($res->status == 404){

			// if autocreate is not true, abort here with 404
			if(empty($opt['autocreate'])) return self::response(404);

			// create dummy with given values
			$res = mobile::create_mobile([
				'imsi' 		=> $opt['imsi'] ?? null,
				'operatorID'=> $opt['operatorID'] ?? null,
				]);

			// on error
			if($res->status == 406) return $res;
			if($res->status != 201) return self::response(570, $res);

			// take mobileID
			$mobileID = $res->data->mobileID;

			// load mobile
			$res = mobile::get_mobile([
				'mobileID'	=> $mobileID,
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);
			}

		// take mobile
		$mobile = $res->data;


		// if autopersistlink is true
		if(!empty($opt['autopersistlink']) and isset($opt['persistID'])){

			// define value for autocreated persistID (if existing does not work)
			$mobile->autocreated_persistID = null;

			// define association param
			$assoc_param = [
				'persistID'		=> $opt['persistID'],
				'mobileID'		=> $mobile->mobileID,
				'operatorID'	=> $mobile->operatorID ?: null,
				'countryID'		=> $mobile->countryID ?: null,
				];

			// load already existing association
			$res = mobile::get_persistlink([
				'persistID'		=> $assoc_param['persistID'],
				]);

			// on error
			if(!in_array($res->status, [200,404])) return $res;

			// if association already exists (for another mobileID)
			if($res->status == 200 and $res->data->mobileID != $assoc_param['mobileID']){

				// take previous persistlink
				$prev = $res->data;

				// create new persistID
				$res = persist::create();

				// on error
				if($res->status != 200) return self::response(570, $res);

				// define persistID as the new replaced one
				$assoc_param['persistID'] = $res->data->persistID;
				$mobile->autocreated_persistID = $res->data->persistID;

				// DEBUG for deeper analysis
				e::logtrigger('DEBUG: Persistlink for persistID '.$prev->persistID.' has already another mobileID '.$prev->mobileID.'. New persistID '.$mobile->autocreated_persistID.' created. Copy existing session for mobileID '.$assoc_param['mobileID'].'.');

				// load source session
				$res = traffic_session::get_session([
					'persistID'		=> $opt['persistID'],
					]);

				// on error
				if(!in_array($res->status, [200,404])) return $res;

				// if source session exists
				if($res->status == 200){

					// take source session data
					$source_session = $res->data;

					// create a copy of that session
					$res = traffic_session::create_session([
						'persistID'		=> $assoc_param['persistID'],
						'domainID'		=> $source_session->domainID,
						'pageID'		=> $source_session->pageID,
						'mobileID'		=> $assoc_param['mobileID'],
						'operatorID'	=> $assoc_param['operatorID'] ?: null,
						'countryID'		=> $assoc_param['countryID'] ?: null,
						]);
					}
				}

			// add association
			$res = self::set_mobile_persist_association($assoc_param);

			// on error
			if($res->status != 204) return $res;
			}


		// if productID and type is set
		if(isset($opt['productID']) and isset($opt['type'])){

			// set productID_list if not set
			if(!isset($opt['productID_list'])) $opt['productID_list'] = [];

			// add default productID, if not given in productID_list
			if(!in_array($opt['productID'], $opt['productID_list'])) $opt['productID_list'][] = $opt['productID'];

			// define now value
			$now = h::date('now');

			// predefine some values in mobile entry
			$mobile->product_access = false;
			$mobile->product_contingent = 0;
			$mobile->product_min_paid = null;
			$mobile->product_max_paid = null;
			$mobile->payment_processes_created = 0;
			$mobile->payment_processes_confirmed = 0;
			$mobile->abo_list = [];
			$mobile->charge_list = [];
			$mobile->otp_list = [];

			// for abo products
			if(in_array($opt['type'], ['abo', 'smsabo'])){

				// load abo list
				$res = abo::get([
					'mobileID'		=> $mobile->mobileID,
					'productID_list'=> $opt['productID_list'],
					]);

				// on error
				if($res->status != 200) return self::response(570, $res);

				// run each abo
				foreach($res->data as $abo){

					// count created
					$mobile->payment_processes_created++;

					// if product is not confirmed
					if(!$abo->confirmed) continue;

					// count confirmed
					$mobile->payment_processes_confirmed++;

					// copy abo to list
					$abo_copy = clone $abo;
					unset($abo_copy->charges);
					$mobile->abo_list[] = $abo_copy;
					$abo_copy->next_chargeTime = $abo->createTime;

					// if abo is not ended and not refunded
					if(!$abo->ended and !$abo->refunded){

						// load product
						$res = nexus_service::get_product([
							'type'		=> 'abo',
							'productID'	=> $abo->productID,
							]);

						// on error
						if($res->status !== 200){
							return self::response(500, 'Cannot load productID '.$abo->productID.' of aboID '.$abo->aboID.': '.$res->status);
							}

						// take product
						$product = $res->data;

						// predefine min and max paid, if this is the first abo
						if($mobile->product_min_paid === null){
							$mobile->product_min_paid = false;
							$mobile->product_max_paid = true;
							}

						// define generally access is allowed
						$mobile->product_access = true;

						// run through each charge of actual interval
						for($len = count($abo->charges), $pos = $len - $product->charges; $pos < $len; $pos++){

							// define min_paid true, if at least one was paid
							if($abo->charges[$pos]->paid) $mobile->product_min_paid = true;

							// define max_paid false, if at least one was not paid
							if(!$abo->charges[$pos]->paid) $mobile->product_max_paid = false;

							// if abo has contingent
							if($abo->charges[$pos]->contingent > 0){

								// add contingent of charge
								$mobile->product_contingent += $abo->charges[$pos]->contingent;

								// and save charge with time as key
								$mobile->charge_list[$abo->charges[$pos]->endTime.'-'.$abo->charges[$pos]->chargeID] = $abo->charges[$pos];
								}

							// define time of first charge in next interval
							$abo_copy->next_chargeTime = h::date($abo->charges[$pos]->endTime, '+1 sec', 'Y-m-d H:i:s');
							}

						// sort list of taken charges by time
						if($mobile->charge_list){
							ksort($mobile->charge_list);
							$mobile->charge_list = array_values($mobile->charge_list);
							}

						// if abo is not paid and is primary productID
						if(!$abo->paid and $abo->productID == $opt['productID']){

							// if low money contingent is allowed
							if(!empty($product->param['low_money_contingent'])){

								// calc low money contingent, eg.: 4 + 97 - 100 or 4 + 15 - 6
								$n = $product->param['low_money_contingent'] + $mobile->product_contingent - $product->contingent;

								// define contingent
								$mobile->product_contingent = ($n > 0) ? $n : 0;
								}

							// else
							else{

								// define empty contingent
								$mobile->product_contingent = 0;
								}
							}

						// unset product
						unset($product);
						}
					}
				}

			// for OTP products
			if($opt['type'] == 'otp'){

				// load otp list
				$res = otp::get([
					'mobileID'		=> $mobile->mobileID,
					'productID_list'=> $opt['productID_list'],
					]);

				// on error
				if($res->status != 200) return self::response(570, $res);

				// run each entry
				foreach($res->data as $otp){

					// count created
					$mobile->payment_processes_created++;

					// if paid
					if($otp->paid){

						// count
						$mobile->payment_processes_confirmed = 0;

						// take entry
						$mobile->otp_list[] = $otp;
						}

					// if otp is paid, but not refunded or expired
					if($otp->paid and !$otp->refunded and !$otp->expired){

						// define generally access is allowed
						$mobile->product_access = true;

						// predefine min and max paid, if this is the first paid otp
						if($mobile->product_min_paid === null){
							$mobile->product_min_paid = true;
							$mobile->product_max_paid = true;
							}

						// add contingent, if given
						$mobile->product_contingent += $otp->contingent;
						}
					}
				}

			}


		// return result
		return self::response(200, $mobile);
		}

	public static function create_mobile_dummy($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// alternative
		$alt = h::eX($patched_req, [
			'operatorID'	=> '~1,65535/i',
			'imsi'			=> '~^[1-9]{1}[0-9]{5,15}$',
			], $error, true);

		// additional check
		if(!$alt and !$error) $error[] = 'operatorID|imsi';

		// on error
		if($error) return self::response(400, 'APK oldapi create_mobile_dummy bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');

		// forward to new function
		$res = mobile::create_mobile($alt);

		// on success
		if($res->status == 201){

			// append ID as mobileID
			$res->data->ID = $res->data->mobileID;
			}

		// return result
		return $res;
		}

	public static function create_persistence($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// optional
		$opt = h::eX($patched_req, [
			'pageID'			=> '~1,65535/i',
			'imsi'				=> '~^[1-9]{1}[0-9]{5,15}$',
			'autocreate_mobile' => '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, 'APK oldapi create_persistence bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');

		// define defaults
		$opt += [
			'createTime'		=> h::dtstr($_SERVER['REQUEST_TIME']),
			'autocreate_mobile' => false,
			];



		// load persist data
		$res = persist::create([
			'createTime'	=> $opt['createTime'],
			]);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// define result
		$result = (object)[
			'persistID'		=> $res->data->persistID,
			'mobileID'		=> null,
			'countryID'		=> 0,
			'operatorID'	=> 0,
			'domainID'		=> 0,
			'pageID'		=> 0,
			'publisherID'	=> 0,
			];


		// if imsi is given
		if(isset($opt['imsi'])){

			// try to load (or create) mobile with imsi
			$res = self::get_mobile([
				'imsi' 			=> $opt['imsi'],
				'autocreate' 	=> $opt['autocreate_mobile'],
				]);

			// abort if status is not 404
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if found
			if($res->status == 200){

				// take values
				$result->mobileID = $res->data->mobileID;
				$result->countryID = $res->data->countryID;
				$result->operatorID = $res->data->operatorID;
				}
			}


		// if imsi is given, but no countryID is defined
		if(isset($opt['imsi']) and !$res->data->countryID){

			// load operator with hni
			$res = nexus_base::get_operator([
				'hni'	=> (int) substr((string) $mand['imsi'], 0, 5),
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);


			// if operator is found
			if($res->status == 200){

				// take countryID and operatorID
				$result->countryID = $res->data->countryID;
				$result->operatorID = $res->data->operatorID;
				}

			// if not found
			else{

				// load country with mcc
				$res = nexus_base::get_country([
					'mcc'	=> (int) substr((string) $mand['imsi'], 0, 3),
					]);

				// on error
				if(!in_array($res->status, [200, 404])) return self::response(570, $res);

				// if not found
				if($res->status == 200){

					// take data
					$result->countryID = $res->data->countryID;
					}
				}
			}

		// if pageID is given
		if(isset($opt['pageID'])){

			// load adtarget
			$res = nexus_domain::get_adtarget([
				'pageID'	=> $opt['pageID'],
				]);

			// on error
			if($res->status !== 200) return self::response(570, $res);

			// take values
			$result->domainID = $res->data->domainID;
			$result->pageID = $res->data->pageID;
			$result->publisherID = $res->data->publisherID;

			// create session
			$res = traffic_session::create_session([
				'persistID'		=> $result->persistID,
				'createTime'	=> $opt['createTime'],
				'domainID'		=> $result->domainID,
				'pageID'		=> $result->pageID,
				'publisherID'	=> $result->publisherID,
				'mobileID'		=> $result->mobileID ?: null, // could be 0
				'countryID'		=> $result->countryID ?: null, // could be 0
				'operatorID'	=> $result->operatorID ?: null, // could be 0
				]);

			// on error
			if(!in_array($res->status, [201, 409])){
				return self::response(500, 'APK oldapi: Adding traffic session for persistID '.$result->persistID.' failed with: '.$res->status.' (req = '.h::encode_php($req).')');
				}
			}

		// if mobileID is given
		if($result->mobileID){

			// add persistlink
			$res = mobile::update_mobile([
				'mobileID'		=> $result->mobileID,
				'persistID'		=> $result->persistID,
				'insertTime'	=> $opt['createTime'],
				'domainID'		=> $result->domainID,
				'pageID'		=> $result->pageID,
				]);

			// on error
			if($res->status != 204){
				return self::response(500, 'APK oldapi: Adding persistlink for persistID '.$result->persistID.' failed with: '.$res->status.' (req = '.h::encode_php($req).')');
				}
			}

		// return result
		return self::response(201, $result);
		}

	public static function create_abo($req = []){

		// log request
		e::logtrigger('APK oldapi outdated create_abo request: '.h::encode_php($req));

		// return forbidden
		return self::response(403);
		}



	/* mobile client helper */
	public static function set_mobile_persist_association($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'		=> '~1,4294967295/i',
			'mobileID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'	=> '~Y-m-d H:i:s/d',
			'countryID'		=> '~1,255/i',
			'operatorID'	=> '~1,65535/i',
			'domainID'		=> '~1,65535/i',
			'pageID'		=> '~1,65535/i',
			'persistlink'	=> '~/b',

			// option
			'delayed'		=> '~/b',
			'delayed_until'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, 'APK oldapi set_mobile_persist_association bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');

		// if delayed is set
		if(!empty($opt['delayed']) or !empty($opt['delayed_until'])){

			// define start for redis job
			$start_at = $opt['delayed_until'] ?? null;

			// remove any delay param to prevent infinite loop
			unset($opt['delayed']);
			unset($opt['delayed_until']);

			// add redisjob to recall this function later
			$res = cronjob::add_redisjob([
				'fn'		=> '\\'.__METHOD__,
				'param'		=> $mand + $opt,
				'start_at'	=> $start_at,
				]);

			// return success
			return self::response(204);
			}


		// set default opt
		$opt += [
			'persistlink'	=> true,
			];

		// load traffic session
		$res = traffic_session::get_session([
			'persistID'	=> $mand['persistID'],
			]);

		// on error
		if(!in_array($res->status, [200, 404])){
			return self::response(500, 'Loading traffic-session with persistID '.$mand['persistID'].' failed with: '.$res->status.' (mobileID '.$mand['mobileID'].')');
			}

		// if session is found
		if($res->status == 200){

			// define session
			$session = $res->data;

			// if operatorID exists
			if(!empty($opt['operatorID'])){

				// load country
				$res = nexus_base::get_operator([
					'operatorID'	=> $opt['operatorID'],
					]);

				// on error
				if($res->status != 200){

					// log error
					e::logtrigger('Loading operator with operatorID '.$opt['operatorID'].' failed with: '.$res->status.' (persistID '.$mand['persistID'].', mobileID '.$mand['mobileID'].')');
					}

				// on success
				else{

					// take/overwrite countryID
					$opt['countryID'] = $res->data->countryID;
					}
				}

			// update session
			$res = traffic_session::update_session([
				'persistID'		=> $mand['persistID'],
				'mobileID'		=> $mand['mobileID'],
				'operatorID'	=> $opt['operatorID'] ?? null,
				'countryID'		=> $opt['countryID'] ?? null,
				]);

			// on error
			if($res->status != 204){
				return self::response(500, 'Updating traffic-session with persistID '.$mand['persistID'].' failed with: '.$res->status.' (mobileID '.$mand['mobileID'].')');
				}

			// overwrite request param with session data (for persistlink)
			foreach(['createTime', 'domainID', 'pageID'] as $k){
				$opt[$k] = $session->{$k};
				}
			}

		// if persistlink should added
		if(!empty($opt['persistlink'])){

			// add persistlink
			$res = mobile::update_mobile([
				'mobileID'		=> $mand['mobileID'],
				'persistID'		=> $mand['persistID'],
				'insertTime'	=> $opt['createTime'] ?? null,
				'domainID'		=> $opt['domainID'] ?? null,
				'pageID'		=> $opt['pageID'] ?? null,
				]);

			// on error
			if($res->status != 204){
				return self::response(500, 'Adding persistlink for persistID '.$mand['persistID'].' failed with: '.$res->status.' (mobileID '.$mand['mobileID'].')');
				}
			}

		// return success
		return self::response(204);
		}



	/* traffic db functions */
	public static function trigger_event($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'		=> '~1,18446744073709551615/i',
			'type'			=> '~^(?:install|error)$',
			], $error);

		// on error
		if($error) return self::response(400, 'APK oldapi trigger_event bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');

		// add redisjob for
		$res = traffic_event::delayed_event_trigger([
			'type'			=> $mand['type'],
			'persistID'		=> $mand['persistID'],
			'createTime'	=> h::dtstr($_SERVER['REQUEST_TIME']),
			'redisjob_start'=> '+3 sec',
			]);

		// on error
		if($res->status != 204) return self::response(500, 'APK creating delayed trigger event failed with: '.$res->status.' ('.h::encode_php($mand).')');

		// return success
		return self::response(204);
		}



	/* bragi functions */
	public static function get_chat($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($patched_req, [
			'mobileID'	=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($patched_req, [
			'poolID'	=> '~0,255/i',
			'startTime' => '~Y-m-d H:i:s/d',
			'unread'	=> '~[0-1]{1}$',
			'fsk'		=> '~0,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, 'APK oldapi get_chat bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');


		// load mobile
		$res = mobile::get_mobile([
			'mobileID'	=> $mand['mobileID'],
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if mobile does not exist
		if($res->status == 404){

			// return error
			e::logtrigger('APK oldapi: mobileID '.$mand['mobileID'].' not found');
			return self::response(404);
			}

		// take mobile
		$mobile = $res->data;


		// load message list
		$res = old_bragi_message::get([
			'mobileID'	=> $mobile->mobileID,
			'poolID'	=> $opt['poolID'] ?? null,
			'startTime' => $opt['startTime'] ?? null,
			'unread'	=> $opt['unread'] ?? null,
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take message list
		$message_list = $res->data;

		// select only unread messages
		if(!empty($opt['unread'])){
			foreach($message_list as $key => $message){
				if($message->read) unset($message_list[$key]);
				}
			}

		// declare variables
		$chat_list = [];

		// prepare messages for return
		foreach($message_list as $key => $message){

			// if current message contents a profileID
			if(!isset($chat_list['profileID'.$message->profileID])){

				// load profile
				$res = self::get_fake_profile([
					'profileID'	=> $message->profileID,
					'fsk'		=> $opt['fsk'] ?? null,
					]);

				// on unexpected error
				if(!in_array($res->status, [200, 404])) return self::response(570, $res);

				// profile not found
				if($res->status == 404){

					// skip message
					continue;
					}

				// initialize a new index in profiles list
				$chat_list['profileID'.$message->profileID] = [];

				// insert the related profile into profiles list
				if(is_array($res->data) or is_object($res->data)){
					foreach($res->data as $key => $value) {
						$chat_list['profileID'.$message->profileID][$key] = $value;
						}
					}
				}

			// initialize a new index in profile array
			if(!isset($chat_list['profileID'.$message->profileID]['msgs'])) $chat_list['profileID'.$message->profileID]['msgs'] = [];

			// insert messages in profile array
			array_push($chat_list['profileID'.$message->profileID]['msgs'], $message);
			}

		// param poolID
		if(isset($opt['poolID'])){

			// for each chat
			foreach($chat_list as $key => $chat){

				// remove non-pool chats
				if(!isset($chat['poolID']) or $chat['poolID'] != $opt['poolID']) unset($chat_list[$key]);
				}
			}

		// add monthly limit status to messages list (autostop feature is removed)
		$chat_list['locked'] = false;

		// return chat
		return self::response(200, $chat_list);
		}

	public static function get_user_profile($req = []){

		// alternativ
		$alt = h::eX($req, [
			'mobileID'	=> '~1,4294967295/i',
			'profileID'	=> '~1,16777215/i',
			], $error, true);

		// on error
		if($error) return self::response(400, 'APK oldapi get_user_profile bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');


		// define mobile
		$mobile = null;

		// if mobileID is given
		if(isset($alt['mobileID'])){

			// load mobile
			$res = mobile::get_mobile([
				'mobileID'		=> $alt['mobileID'],
				]);

			// on unexpected error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if mobile does not exist
			if($res->status == 404){

				// return error
				e::logtrigger('APK oldapi: mobileID '.$alt['mobileID'].' not found');
				return self::response(404);
				}

			// take mobile
			$mobile = $res->data;
			}



		// load profile by mobileID
		$res = old_bragi_profile::get([
			'profileID'	=> $alt['profileID'] ?? null,
			'mobileID'	=> $mobile ? $mobile->mobileID : null,
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// profile not found
		if($res->status == 404){

			if(!isset($alt['mobileID'])) return self::response(404);

			// load profile by mobileID
			$res = old_bragi_profile::get([
				'mobileID'	=> $alt['mobileID'],
				]);

			// on unexpected error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// profile not found
			if($res->status == 404){

				// return error
				//e::logtrigger('APK oldapi: profile for mobileID '.$mobile->mobileID.' not found (from param mobileID '.$mand['mobileID'].')');
				return self::response(404);
				}
			}

		// take profile
		$profile = $res->data;

		// return profile
		return self::response(200, $profile);
		}

	public static function create_user_profile($req = []){

		// mandatory
		$mand = h::eX($req, [
			'mobileID'		=> '~1,4294967295/i',
			'name'			=> '~^[a-zA-Z0-9_-]{3,30}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'age'			=> '~18,99/i',
			'plz'			=> '~0,160/s',
			'weight'		=> '~1,255/i',
			'height'		=> '~1,255/i',
			'description'	=> '~0,500/s',
			'gender'		=> '~^[mMfF]$',
			'orientation'	=> '~^[mMfFbB]$',
			], $error, true);

		// on error
		if($error) return self::response(400, 'APK oldapi create_user_profile bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');


		// load mobile
		$res = mobile::get_mobile([
			'mobileID'		=> $mand['mobileID'],
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if mobile does not exist
		if($res->status == 404){

			// return error
			e::logtrigger('APK oldapi: mobileID '.$mand['mobileID'].' not found');
			return self::response(404);
			}

		// take mobile
		$mobile = $res->data;


		// create profile
		$res = old_bragi_profile::create([
			'mobileID'		=> $mobile->mobileID,
			'name'			=> $mand['name'],
			'age'			=> $opt['age'] ?? null,
			'plz'			=> $opt['plz'] ?? null,
			'weight'		=> $opt['weight'] ?? null,
			'height'		=> $opt['height'] ?? null,
			'description'	=> $opt['description'] ?? null,
			'countryID'		=> $mobile->countryID,
			'gender'		=> isset($opt['gender']) ? strtoupper($opt['gender']) : null,
			'orientation'	=> isset($opt['orientation']) ? strtoupper($opt['orientation']) : null,
			]);

		// a profile allready exists, return conflict
		if($res->status == 409) return self::response(409);

		// on error
		if($res->status != 201) return self::response(570, $res);

		// return result
		return self::response(201, (object)['profileID' => $res->data->profileID]);
		}

	public static function update_user_profile($req = []){

		// alternativ
		$alt = h::eX($req, [
			'mobileID'		=> '~1,4294967295/i',
			'profileID'		=> '~1,16777215/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~^[a-zA-Z0-9_-]{3,30}$',
			'age'			=> '~18,99/i',
			'plz'			=> '~0,160/s',
			'weight'		=> '~1,255/i',
			'height'		=> '~1,255/i',
			'description'	=> '~0,500/s',
			'gender'		=> '~^[mMfF]$',
			'orientation'	=> '~^[mMfFbB]$',
			], $error, true);

		// on error
		if($error) return self::response(400, 'APK oldapi update_user_profile bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');


		// define mobile
		$mobile = null;

		// if
		if(isset($alt['mobileID'])){

			// load mobile
			$res = mobile::get_mobile([
				'mobileID'		=> $alt['mobileID'],
				]);

			// on unexpected error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if mobile does not exist
			if($res->status == 404){

				// return error
				e::logtrigger('APK oldapi: mobileID '.$alt['mobileID'].' not found');
				return self::response(404);
				}

			// take mobile
			$mobile = $res->data;
			}



		// load profile by mobileID
		$res = old_bragi_profile::get([
			'profileID'	=> $alt['profileID'] ?? null,
			'mobileID'	=> $mobile ? $mobile->mobileID : null,
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// profile not found
		if($res->status == 404){

			// return error
			e::logtrigger('APK oldapi: profile for mobileID '.$mobile->mobileID.' not found (req = '.h::encode_php($req).')');
			return self::response(404);
			}

		// take profile
		$profile = $res->data;

		// update profile
		$res = old_bragi_profile::update([
			'profileID'		=> $profile->profileID,
			'name'			=> $opt['name'] ?? null,
			'age'			=> $opt['age'] ?? null,
			'plz'			=> $opt['plz'] ?? null,
			'weight'		=> $opt['weight'] ?? null,
			'height'		=> $opt['height'] ?? null,
			'description'	=> $opt['description'] ?? null,
			'countryID'		=> $mobile->countryID ?? null,
			'gender'		=> isset($opt['gender']) ? strtoupper($opt['gender']) : null,
			'orientation'	=> isset($opt['orientation']) ? strtoupper($opt['orientation']) : null,
			]);

		// on conflict
		if($res->status == 409) return self::response(409);

		// on error
		if($res->status != 204) return self::response(570, $res);

		// return success
		return self::response(204);
		}

	public static function get_fake_profile($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// append poolID, poolID does not exist (fix really old ChatAPKs without a poolID)
		if(!isset($patched_req['poolID'])) $patched_req['poolID'] = 1;

		// alternativ
		$alt = h::eX($patched_req, [
			'profileID'		=> '~1,16777215/i',
			'poolID'		=> '~1,255/i',
			], $error, true);

		// optional
		$opt = h::eX($patched_req, [
			'fsk'			=> '~0,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, 'APK oldapi get_fake_profile bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');


		// define function to expand/convert profiles
		$expand_profile = function($profile_list) use ($alt, $opt){

			// define if single entry
			$single = !is_array($profile_list);

			// convert single entry to list
			if($single) $profile_list = [$profile_list];

			// for each profile
			foreach($profile_list as $key => $profile){

				// TEMP: check for strange error when profileID does not exist
				if(empty($profile) or !isset($profile->profileID)){

					// log error
					e::logtrigger('APK oldapi get_fake_profile lost already loaded profile in profile_list: '.h::encode_php($profile).' ('.h::encode_php($alt + $opt).')');
					continue;
					}

				// rewrite object
				$fake_profile = (object)[
					'profileID'		=> $profile->profileID,
					'profileName'	=> $profile->profileName,
					'age'			=> $profile->age,
					'plz'			=> $profile->plz,
					'height'		=> $profile->height,
					'weight'		=> $profile->weight,
					'description'	=> $profile->description,
					'createTime'	=> $profile->createTime,
					'imageID'		=> 0,
					'hidden'		=> $profile->hidden,
					'countryID'		=> $profile->countryID,
					'poolID'		=> $profile->poolID,
					'gender'		=> $profile->gender,
					'orientation'	=> $profile->orientation,
					'imageName'		=> "",
					'thumbName' 	=> null,
					];

				// define prio_imageID
				$prio_imageID = $profile->imageID;

				// load media files
				$res = bragi_media::get_profile_media([
					'profileID'		=> $fake_profile->profileID,
					'moderator_list'=> [0,2],
					'max_fsk'		=> $opt['fsk'] ?? null,
					]);

				// on error
				if($res->status != 200){

					// log error
					e::logtrigger('APK oldapi get_fake_profile cannot load profile media of profileID '.$fake_profile->profileID.': '.$res->status.' ('.h::encode_php($alt + $opt).')');
					continue;
					}

				// take file list
				$file_list = $res->data;

				// for each file
				foreach($file_list as $file){

					// define ext
					$file_ext = strtolower(substr($file->name, strrpos($file->name, '.') + 1));

					// skip every non image file
					if(!in_array($file_ext, ['jpg','jpeg','png'])) continue;

					// for basic files
					if($file->moderator == 0 and !$fake_profile->imageName){

						// define first image as profile image
						$fake_profile->imageName = $file->name;
						$fake_profile->imageID = $file->imageID;
						}

					// for specific profile image
					if($file->moderator == 0 and $prio_imageID and $file->imageID == $prio_imageID){

						// (re)define matching image as profile image
						$fake_profile->imageName = $file->name;
						$fake_profile->imageID = $file->imageID;
						}

					// for thumb files
					if($file->moderator == 2 and !$fake_profile->thumbName){

						// define first thumb image as thumb image
						$fake_profile->thumbName = $file->name;
						}
					}

				// add profile to result
				$profile_list[$key] = $fake_profile;
				}

			// return result as single entry or list
			return $single ? reset($profile_list) : $profile_list;
			};


		// param order 1: profileID
		if(isset($alt['profileID'])){

			// load profile
			$res = old_bragi_profile::get([
				'profileID'		=> $alt['profileID'],
				]);

			// on unexpected error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if not found
			if($res->status == 404) return $res;

			// take expanded profile
			$profile = $expand_profile($res->data);

			// return result
			return self::response(200, $profile);
			}

		// param order 2: poolID
		if(isset($alt['poolID'])){

			// define result list
			$result = [];

			// load profiles
			$res = old_bragi_profile::get_list([
				'poolID'	=> $alt['poolID'],
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// take expanded profile list
			$profile_list = $expand_profile($res->data);

			// return profiles list
			return self::response(200, $profile_list);
			}


		// other request param invalid
		return self::response(400, 'need profileID or poolID parameter');
		}

	public static function get_profile_media($req = []){

		// mandatory
		$mand = h::eX($req, [
			'profileID'	=> '~1,16777215/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'fsk'		=> '~0,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, 'APK oldapi get_profile_media bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');


		// define result list
		$result = [];

		// load media files
		$res = bragi_media::get_profile_media([
			'profileID'		=> $mand['profileID'],
			'moderator'		=> 0,
			'max_fsk'		=> $opt['fsk'] ?? null,
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take file list
		$file_list = $res->data;

		// for each file
		foreach($file_list as $file){

			// define ext
			$file_ext = strtolower(substr($file->name, strrpos($file->name, '.') + 1));

			// skip every non image file
			if(!in_array($file_ext, ['jpg','jpeg','png'])) continue;

			// add to result
			$result[] = (object)[
				'imageID'	=> $file->imageID,
				'imageName'	=> $file->name,
				'moderator'	=> $file->moderator,
				'fsk'		=> $file->fsk,
				];
			}

		// return result
		return self::response(200, $result);
		}

	public static function prepare_upload($req = []){

		// log request
		//e::logtrigger('APK oldapi outdated prepare_upload request: '.h::encode_php($req));

		// return forbidden
		return self::response(403);
		}



	/* apk event functions */
	public static function create_bragi_event($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($patched_req, [
			'event' 	=> '~1,160/s',
			], $error);

		// optional
		$opt = h::eX($patched_req, [
			'project' 	=> '~1,160/s',
			'imsi'		=> '~^[1-7]{1}[0-9]{5,15}$',
			'mobileID'	=> '~1,4294967295/i',
			'persistID'	=> '~1,18446744073709551615/i',
			'data'		=> '~0,65535/s',
			], $error, true);

		// on error
		if($error) return self::response(400, 'APK oldapi create_bragi_event bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');

		// forward function request
		return old_bragi_event::create($mand + $opt);
		}



	/* video functions */
	public static function get_poolvideo($req = []){

		// alternativ
		$alt = h::eX($req, [
			'poolID'	=> '~1,65535/i',
			'videoID'	=> '~1,16777215/i',
			'hash'		=> '~^[a-z0-9]{16}$',
			], $error, true);

		// on error
		if($error) return self::response(400, 'APK oldapi get_poolvideo bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');

		// forward function request
		return video::get_poolvideo($alt);
		}

	public static function get_poolvideo_paged($req = []){

		// mandatory
		$mand = h::eX($req, [
			'poolID'	=> '~1,65535/i',
			'max'		=> '~1,256/i',
			'page'		=> '~1,65536/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'filterTag'	=> '~^[a-z0-9\_]{1,32}$',
			'orderBy'	=> '~^(?:videoID|ID|createTime|voting|votes|views)$',
			'order'		=> '~^(?:DESC|ASC)$',
			], $error, true);

		// on error
		if($error) return self::response(400, 'APK oldapi get_poolvideo_paged bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');

		// forward function request
		return video::get_poolvideo_paged($mand + $opt);
		}

	public static function add_video_voting($req = []){

		// mandatory
		$mand = h::eX($req, [
			'poolID'	=> '~1,65535/i',
			'voting'	=> '~1,5/f',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'videoID'	=> '~1,16777215/i',
			'hash'		=> '~^[a-z0-9]{16}$',
			], $error, true);

		// on error
		if($error) return self::response(400, 'APK oldapi add_video_voting bad request for: '.implode(', ', $error).' (req = '.h::encode_php($req).')');

		// forward function request
		return video::add_video_voting($mand + $alt);
		}


	/* outdated */
	public static function basemobileget($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// log request
		//e::logtrigger('APK oldapi outdated basemobileget request: '.h::encode_php($req));

		// return forbidden
		return self::get_mobile($patched_req);
		}

	public static function apk_get_config($req = []){

		// log request
		e::logtrigger('APK oldapi outdated apk_get_config request: '.h::encode_php($req));

		// return forbidden
		return self::response(403);
		}

	public static function addvisitorcall($req = []){

		// log request
		//e::logtrigger('APK oldapi outdated addvisitorcall request: '.h::encode_php($req));

		// return forbidden
		return self::response(403);
		}

	}
