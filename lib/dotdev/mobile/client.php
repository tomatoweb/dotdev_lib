<?php
/*****
 * Version 2.2.2019-01-29
**/
namespace dotdev\mobile;

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
use \dotdev\mobile\sms;
use \dotdev\mobile\smspay;
use \dotdev\mobile\tan;
use \dotdev\smsgate\service as smsgate_service;
use \dotdev\traffic\session;

class client {
	use \tools\libcom_trait;

	/* Service URL */
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


	/* Mobile */
	public static function get_mobile($req = []){

		// optional
		$opt = h::eX($req, [

			// one is mandatory
			'mobileID'			=> '~1,4294967295/i',
			'msisdn'			=> '~^(?:\+|00|)(?:[1-9]{1}[0-9]{5,14})$',
			'persistID'			=> '~1,4294967295/i',
			'imsi'				=> '~^[1-9]{1}[0-9]{5,15}$',

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
		if($error) return self::response(400, $error);

		// for each useable key
		foreach(['mobileID', 'msisdn', 'imsi', 'persistID'] as $key){

			// skip if no value exists
			if(!isset($opt[$key])) continue;

			// load mobile with given value
			$res = mobile::get_mobile([$key => $opt[$key]]);

			// abort if status is not 404
			if($res->status != 404) break;
			}

		// if there is no useable key that loads
		if(!isset($res)) return self::response(400, 'Need at least mobileID, msisdn, persistID or imsi');

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
				$res = session::get_session([
					'persistID'		=> $opt['persistID'],
					]);

				// on error
				if(!in_array($res->status, [200,404])) return $res;

				// if source session exists
				if($res->status == 200){

					// take source session data
					$source_session = $res->data;

					// create a copy of that session
					$res = session::create_session([
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

	public static function ibr_mobile($req = []){

		/* possible IBR error stati
			102 = pending (waiting for response of external service)
			200	= identify done, mobile user detected
			307 = user redirection needed
			400	= missing or invalid libcom param
			403	= identify done, but no mobile user detected
			500	= unexpected error (DB Error, Invalid Config, etc.)
			501	= not implemented
			502 = external service failed (or its intepretation)
			503	= temporary error on external service
		/* */

		// mandatory
		$mand = h::eX($req, [
			'type' 			=> '~^(?:abo|otp)$',
			'productID'		=> '~1,65535/i',
			'persistID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'restart'		=> '~/b',
			'domainID'		=> '~1,65535/i',
			'pageID'		=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);



		// load product
		$res = nexus_service::get_product([
			'type'		=> $mand['type'],
			'productID'	=> $mand['productID'],
			]);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// take product
		$product = $res->data;

		// if service function is not available
		if(!is_callable($product->serviceNS.'::identify_mobile')){

			// return error
			return self::response(501, 'Service method '.$product->serviceNS.'::identify_mobile unavailable');
			}

		// call service function
		$call = call_user_func($product->serviceNS.'::identify_mobile', [
			'persistID'	=> $mand['persistID'],
			'productID'	=> $product->productID,
			'type'		=> $mand['type'],
			'restart'	=> !empty($opt['restart']),
			]);

		// remove restart param
		unset($opt['restart']);

		// if service call was successful
		if($call->status == 200){

			// load persist data
			$res = persist::get([
				'persistID'	=> $mand['persistID'],
				]);

			// on error
			if($res->status !== 200) return self::response(570, $res);

			// take entry
			$persist = $res->data;

			// add association (failsafe)
			self::set_mobile_persist_association([
				'persistID'		=> $mand['persistID'],
				'mobileID'		=> $call->data->mobileID,
				'createTime'	=> $persist->createTime,
				'operatorID'	=> $call->data->operatorID ?? null,
				'countryID'		=> $call->data->countryID ?? null,
				'domainID'		=> $opt['domainID'] ?? null,
				'pageID'		=> $opt['pageID'] ?? null,
				'delayed'		=> true,
				]);
			}

		// if service call was successful, but MSISDN is needed
		if($call->status == 200 and h::cX($product->param, 'need_msisdn', true)){

			// load mobile
			$res = mobile::get_mobile([
				'mobileID'	=> $call->data->mobileID,
				]);

			// if found, but mobile entry has no MSISDN
			if($res->status == 200 and !$res->data->msisdn){

				// return 403 forbidden
				return self::response(403);
				}

			// if not found or an error occured
			elseif($res->status != 200){

				// return error
				return self::response(570, $res);
				}

			// add operatorID and countryID
			if(!empty($res->data->operatorID)) $call->operatorID = $res->data->operatorID;
			if(!empty($res->data->countryID)) $call->countryID = $res->data->countryID;
			}

		// return call response
		return $call;
		}

	public static function ibi_mobile($req = []){

		/* possible IBI error stati
			200	= mobile was found or created (but identification is not confirmed)
			400	= missing or invalid libcom param
			403	= HLR Lookup failed (MSISDN does not exist)
			500	= unexpected error (DB Error, Invalid Config, etc.)
			501	= not implemented
			503	= external service error
		/* */

		// mandatory
		$mand = h::eX($req, [
			'type'			=> '~^(?:abo|otp)$',
			'productID'		=> '~1,65535/i',
			'persistID'		=> '~1,4294967295/i',
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'confirmTime'	=> '~Y-m-d H:i:s/d',
			'operatorID'	=> '~0,65535/i',
			'domainID'		=> '~1,65535/i',
			'pageID'		=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// extract msisdn
		$mand['msisdn'] = $mand['msisdn'][0];

		// load product
		$res = nexus_service::get_product([
			'type'		=> $mand['type'],
			'productID'	=> $mand['productID'],
			]);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// take product
		$product = $res->data;

		// load IBI mode and serviceID, if given
		$ibi_enabled = h::gX($product->param, 'identify:ibi');
		$hlrlookup_serviceID = h::gX($product->param, 'identify:hlrlookup_serviceID');

		// if IBI mode is not allowed
		if(!$ibi_enabled){

			// return error
			return self::response(500, 'DEBUG: calling ibi_mobile, but IBI ('.h::encode_php($ibi_enabled).') is not allowed ('.$mand['type'].', productID '.$mand['productID'].', persistID '.$mand['persistID'].')');
			}

		// try to load mobile
		$check = mobile::get_mobile([
			'msisdn'	=> $mand['msisdn'],
			]);

		// if hlrlookup_serviceID is given, request cannot preconfirm msisdn and mobile is not found or found, but unconfirmed
		if($hlrlookup_serviceID and empty($opt['confirmTime']) and ($check->status == 404 or ($check->status == 200 and !$check->data->confirmed))){

			// load service
			$res = nexus_service::get_service([
				'serviceID'	=> $hlrlookup_serviceID,
				]);

			// on error
			if($res->status !== 200) return self::response(570, $res);

			// take service
			$lookup_service = $res->data;

			// if service function is not available
			if(!is_callable($lookup_service->ns.'::hlr_lookup')){

				// return error
				return self::response(501, 'Service method '.$lookup_service->ns.'::hlr_lookup unavailable');
				}

			// call service function
			$hlr = call_user_func($lookup_service->ns.'::hlr_lookup', [
				'persistID'	=> $mand['persistID'],
				'serviceID'	=> $lookup_service->serviceID,
				'msisdn'	=> $mand['msisdn'],
				]);

			// if lookup failed
			if(in_array($hlr->status, [404, 410])){

				// return error
				return self::response(403);
				}

			// if lookup has unexpected error
			if($hlr->status != 200){

				// return hlr error
				return $hlr;
				}

			// if mobile entry not exists
			if($check->status == 404){

				// create confirmed mobile entry
				$res = mobile::create_mobile([
					'msisdn'	=> $hlr->data->msisdn,
					'operatorID'=> $hlr->data->operatorID,
					'confirmed'	=> true,
					]);

				// on error
				if(!in_array($res->status, [201, 409])) return $res;
				}

			// if mobile entry exists, but not confirmed
			else{

				// confirm mobile
				$res = mobile::update_mobile([
					'mobileID'	=> $check->data->mobileID,
					'confirmed'	=> true,
					]);

				// on error
				if($res->status != 204) return $res;
				}

			// reload mobile entry
			$check = mobile::get_mobile([
				'msisdn'	=> $mand['msisdn'],
				]);
			}

		// else if unconfirmed mobile is found and confirmTime is given
		elseif($check->status == 200 and !$check->data->confirmed and !empty($opt['confirmTime'])){

			// confirm mobile
			$res = mobile::update_mobile([
				'mobileID'		=> $check->data->mobileID,
				'confirmTime'	=> $opt['confirmTime'],
				]);

			// on error
			if($res->status != 204) return $res;
			}

		// else if mobile is not found
		elseif($check->status == 404){

			// create entry with MSISDN (and operatorID, if given)
			$res = mobile::create_mobile([
				'msisdn'		=> $mand['msisdn'],
				'operatorID'	=> $opt['operatorID'] ?? 0,
				'confirmTime'	=> $opt['confirmTime'] ?? null,
				]);

			// on error
			if(!in_array($res->status, [201, 409])) return $res;

			// reload mobile entry
			$check = mobile::get_mobile([
				'msisdn'	=> $mand['msisdn'],
				]);
			}


		// return error, if entry still does not exists
		if($check->status != 200) return self::response(570, $check);


		// load persist data
		$res = persist::get([
			'persistID'	=> $mand['persistID'],
			]);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// take entry
		$persist = $res->data;

		// add association (failsafe)
		self::set_mobile_persist_association([
			'persistID'		=> $mand['persistID'],
			'mobileID'		=> $check->data->mobileID,
			'createTime'	=> $persist->createTime,
			'operatorID'	=> $check->data->operatorID ?: null,
			'countryID'		=> $check->data->countryID ?: null,
			'domainID'		=> $opt['domainID'] ?? null,
			'pageID'		=> $opt['pageID'] ?? null,
			'delayed'		=> true,
			]);

		// return success
		return self::response(200, (object)['mobileID'=>$check->data->mobileID]);
		}

	public static function ibi_tan_confirm($req = []){

		/* possible IBI tan confirmation error stati
			100	= wait for user input (tan was sent before)
			204	= tan was correct (identification is confirmed)
			400	= missing or invalid libcom param
			401	= tan was invalid (retry possible)
			403	= tas was invalid and/or expired (no retry possible, but restart after expire time (e.g. 7 Days) or recreation_lock time (e.g. 2 Hours) is reached)
			500	= unexpected error (DB Error, Invalid Config, etc.)
			501	= not implemented
			503	= external service error
		/**/

		// mandatory
		$mand = h::eX($req, [
			'serviceID'				=> '~1,65535/i',
			'mobileID'				=> '~1,4294967295/i',
			'persistID'				=> '~1,4294967295/i',
			'sms_text'				=> '~1,160/s',
			'expire'				=> '~^[0-9]{1,2} (?:month|week|day|hour|min)$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'check_tan'				=> '~4,12/s',
			'tan_length'			=> '~4,12/i',
			'tan_char'				=> '~^[a-zA-Z0-9]$/s',
			'tan_retry'				=> '~0,255/i',
			'retry_expires'			=> '~/b',
			'match_expires'			=> '~/b',
			'allow_recreation'		=> '~/b',
			'recreation_lock'		=> '~^[0-9]{1,2} (?:month|week|day|hour|min)$',
			'sms_senderString'		=> '~^[0-9]{1,16}$|^[a-zA-Z0-9]{1,11}$',
			], $error, true);

		// additional check for tan placeholder in sms text
		if(isset($mand['sms_text']) and strpos($mand['sms_text'], '{tan}') === false) $error[] = 'sms_text';

		// on error
		if($error) return self::response(400, $error);


		// if we have no tan to check against
		if(!isset($opt['check_tan'])){

			// load last tan
			$res = tan::get_tan([
				'mobileID'		=> $mand['mobileID'],
				'last_only'		=> true,
				]);

			// on unexpected error
			if(!in_array($res->status, [200, 404])) return $res;

			// if tan was found
			if($res->status == 200){

				// take tan
				$last_tan = $res->data;

				// define if retry is allowed
				$retry_allowed = ($last_tan->retry > 0 or (empty($opt['retry_expires']) and empty($opt['match_expires'])));

				// if retry is allowed and tan date has not expired yet
				if($retry_allowed and h::date($last_tan->createTime) > h::date('-'.$mand['expire'])){

					// return continue state (which means, user input of tan is needed)
					return self::response(100);
					}

				// the tan expired or has no retry left, so if allow_recreation is not defined (or the recreation_lock time has not expired yet)
				if(empty($opt['allow_recreation']) or (isset($opt['recreation_lock']) and h::date($last_tan->createTime) > h::date('-'.$opt['recreation_lock']))){

					// return forbidden state (which means, the complete tan process has failed and is locked until expire time or recreation_lock time is reached)
					return self::response(403);
					}

				// if previous condition not met, than a tan recreation is allowed (like we haven't found a tan before)
				}

			// create tan
			$res = tan::create_tan([
				'mobileID'		=> $mand['mobileID'],
				'persistID'		=> $mand['persistID'],
				'tan_length'	=> $opt['tan_length'] ?? null,
				'tan_char'		=> $opt['tan_char'] ?? null,
				'retry'			=> $opt['tan_retry'] ?? null,
				]);

			// on error
			if($res->status != 201) return $res;

			// take tan
			$new_tan = $res->data;

			// send sms with tan
			$res = self::send_sms([
				'mobileID'		=> $mand['mobileID'],
				'serviceID'		=> $mand['serviceID'],
				'text'			=> h::replace_in_str($mand['sms_text'], [
					'{tan}'	=>	$new_tan->tan,
					]),
				'senderString'	=> $opt['sms_senderString'] ?? null,
				'persistID'		=> $mand['persistID'],
				]);

			// on error
			if($res->status != 201) return $res;

			// return continue state (which means, user input of tan is needed)
			return self::response(100);
			}


		// check tan
		$res = tan::check_tan([
			'mobileID'		=> $mand['mobileID'],
			'tan'			=> $opt['check_tan'],
			'expire'		=> $mand['expire'],
			'retry_expires'	=> $opt['retry_expires'] ?? null,
			'match_expires'	=> $opt['match_expires'] ?? null,
			]);

		// on unexpected error
		if($res->status != 200) return self::response(570, $res);

		// if tan is expired, return 403 forbidden
		if($res->data->expired) return self::response(403);

		// if tan is invalid, return 401 unauthorized
		if(!$res->data->valid) return self::response(401);

		// return success
		return self::response(204);
		}

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
		if($error) return self::response(400, $error);

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
		$res = session::get_session([
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
			$res = session::update_session([
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


	/* Abo */
	public static function create_abo($req = []){

		/* possible abo error stati
			100 = waiting for input (e.g. tan)
			102 = pending (waiting for response of external service)
			200	= subscription done
			307 = user redirection needed
			400	= missing or invalid libcom param
			401 = user canceled subscription
			402 = subscription failed (temporary)
			403	= subscription failed (permanently)
			404	= process not found
			409	= conflict (another subscription already exists)
			500	= unexpected error (DB Error, Invalid Config, etc.)
			501	= not implemented
			502 = external service failed (or its intepretation)
			503	= temporary error on external service
			*/

		// mandatory
		$mand = h::eX($req, [
			'mobileID'			=> '~1,4294967295/i',
			'productID'			=> '~1,65535/i',
			'persistID'			=> '~1,4294967295/i',
			'submitted'			=> '~/b',
			], $error);

		// optional
		$opt = h::eX($req, [
			'aboID'				=> '~0,4294967295/i',
			'confirmationURL'	=> '~/s',
			'netm'				=> '~/l',
			'dimoco'			=> '~/l',
			'clickrequest'		=> '~/b',
			'apkflow'			=> '~/b',
			'tan'				=> '~1,11/s',
			'sandbox'			=> '~/b',
			], $error, true);

		// on error
		if($error){
			e::logtrigger('DEBUG: following 400 error with client::create_abo('.h::encode_php($req).')');
			return self::response(400, $error);
			}

		// load product
		$res = nexus_service::get_product([
			'type'		=> 'abo',
			'productID'	=> $mand['productID'],
			]);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// take product
		$product = $res->data;

		// if we have an aboID
		if(!empty($opt['aboID'])){

			// take aboID (not check it for performance reasons)
			$processed_aboID = $opt['aboID'];
			}

		// or if not
		else{

			// create new
			$res = abo::create([
				'mobileID'	=> $mand['mobileID'],
				'productID'	=> $product->productID,
				'persistID'	=> $mand['persistID'],
				]);
			if($res->status != 201) return self::response(570, $res);

			// take aboID
			$processed_aboID = $res->data->aboID;
			}

		// get associated service function
		$serviceFn = $product->serviceNS.'::create_abo';
		if(!is_callable($serviceFn)) return self::response(501, 'Service method '.$serviceFn.' unavailable');

		// call associated service
		$process = call_user_func($serviceFn, [
			'mobileID'			=> $mand['mobileID'],
			'aboID'				=> $processed_aboID,
			'productID'			=> $product->productID,
			'persistID'			=> $mand['persistID'],
			'submitted'			=> $mand['submitted'],
			] + $opt);

		// if data is not given
		if(!isset($process->data)){

			// create data in result with processed aboID
			$process->data = (object)[
				'aboID'	=> $processed_aboID,
				];
			}

		// else if is
		elseif(is_object($process->data)){

			// add processed aboID
			$process->data->aboID = $processed_aboID;
			}

		// special apk flow for redirection
		if(!empty($opt['apkflow']) and $process->status == 307){

			// get actual stack
			$res = reflector::get([
				'reflectorID'	=> $process->data->reflectorID,
				]);

			// on error
			if($res->status != 200){
				return self::response(500, 'Cannot load reflectorID '.$process->data->reflectorID.' for apkflow: '.$res->status);
				}

			// take reflector
			$reflector = $res->data;

			// load service url
			$res = self::get_mtservice_url();

			// on error
			if($res->status != 200){
				return self::response(500, 'Cannot load mtservice_url: '.$res->status);
				}

			// take service url
			$mtservice_url = $res->data->url;

			// if stack count is one
			if(count($reflector->stack) == 1){

				// append return stack
				$res = reflector::stack([
					'reflectorID' 	=> $reflector->reflectorID,
					'url'			=> $mtservice_url.'/endpoint/OK',
					]);

				// on error
				if($res->status != 201){
					return self::response(500, 'Cannot append return stack to reflectorID '.$process->data->reflectorID.' for apkflow: '.$res->status);
					}
				}

			// append url for redirection
			$process->data->url = $mtservice_url.'/reflector/'.$reflector->reflectorID;
			}

		// and return result (directly)
		return $process;
		}

	public static function terminate_abo($req = []){

		/* possible abo termination error stati
			102 = pending (waiting for response of external service)
			204	= termination done
			307 = user redirection needed
			400	= missing or invalid libcom param
			500	= unexpected error (DB Error, Invalid Config, etc.)
			501	= not implemented
			502 = external service failed (or its intepretation)
			503	= temporary error on external service
		/* */

		// mandatory
		$mand = h::eX($req, [
			'aboID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'apkflow'			=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load abo
		$res = abo::get([
			'aboID'		=> $mand['aboID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take abo
		$abo = $res->data;

		// abo is already terminated
		if($abo->terminated) return self::response(409);

		// load product
		$res = nexus_service::get_product([
			'type'		=> 'abo',
			'productID'	=> $abo->productID,
			]);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// take product
		$product = $res->data;

		// check service function
		if(!is_callable($product->serviceNS.'::terminate_abo')){
			return self::response(501, 'Service method '.$product->serviceNS.'::terminate_abo unavailable');
			}

		// call associated service
		$process = call_user_func($product->serviceNS.'::terminate_abo', [
			'aboID'		=> $abo->aboID,
			'productID'	=> $abo->productID,
			]);

		// special apk flow for redirection
		if(!empty($opt['apkflow']) and $process->status == 307){

			// get actual stack
			$res = reflector::get([
				'reflectorID'	=> $process->data->reflectorID,
				]);

			// on error
			if($res->status != 200){
				return self::response(500, 'Cannot load reflectorID '.$process->data->reflectorID.' '.$res->status);
				}

			// take reflector
			$reflector = $res->data;

			// load service url
			$res = self::get_mtservice_url();

			// on error
			if($res->status != 200){
				return self::response(500, 'Cannot load mtservice_url: '.$res->status);
				}

			// take service url
			$mtservice_url = $res->data->url;

			// if stack count is one
			if(count($reflector->stack) == 1){

				// append return stack
				$res = reflector::stack([
					'reflectorID' 	=> $reflector->reflectorID,
					'url'			=> $mtservice_url.'/endpoint/OK',
					]);

				// on error
				if($res->status != 201){
					return self::response(500, 'Cannot append return stack to reflectorID '.$process->data->reflectorID.' for apkflow: '.$res->status);
					}
				}

			// append url for redirection
			$process->data->url = $mtservice_url.'/reflector/'.$reflector->reflectorID;
			}

		// and return result (directly)
		return $process;
		}

	public static function refund_abo($req = []){

		/* possible abo refund error stati
			102 = pending (waiting for response of external service)
			204	= refund done
			400	= missing or invalid libcom param
			403	= refund failed
			500	= unexpected error (DB Error, Invalid Config, etc.)
			501	= not implemented
			502 = external service failed (or its intepretation)
			503	= temporary error on external service
		/* */

		// mandatory
		$mand = h::eX($req, [
			'aboID'		=> '~1,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load abo
		$res = abo::get([
			'aboID'		=> $mand['aboID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take product
		$abo = $res->data;

		// abo already refunded
		if($abo->refunded) return self::response(409);

		// load product
		$res = nexus_service::get_product([
			'type'		=> 'abo',
			'productID'	=> $abo->productID,
			]);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// take product
		$product = $res->data;

		// check service function
		if(!is_callable($product->serviceNS.'::refund_abo')){
			return self::response(501, 'Service method '.$product->serviceNS.'::refund_abo unavailable');
			}

		// call service function
		return call_user_func($product->serviceNS.'::refund_abo', [
			'aboID'		=> $abo->aboID,
			'productID'	=> $abo->productID,
			]);
		}

	public static function autocharge_abo($req = []){

		// alternativ
		$alt = h::eX($req, [
			'serviceID'			=> '~1,65535/i',
			'productID'			=> '~1,65535/i',
			'productID_list'	=> '~/a',
			'countryID'			=> '~1,255/i',
			'groupKey'			=> '~^[a-zA-Z0-9 \-\%\_]{1,32}$',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'filter_operatorID'	=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(empty($alt)) return self::response(400, 'need atleast serviceID|productID|productID_list|countryID param');


		// load product list
		$res = nexus_service::get_product([
			'type'	=> 'abo'
			] + $alt);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take list
		$product_list = is_array($res->data) ? $res->data : [$res->data];

		// define result
		$result = [];

		// for each product
		foreach($product_list as $product){

			// define stat
			$stat = (object)[
				'serviceID' => $product->serviceID,
				'productID'	=> $product->productID,
				];

			// check chargerule
			$chargerule = h::gX($product->param, 'autocharge_abo');
			if(!$chargerule) continue;

			// take product
			$result[] = $stat;

			// define service function
			$serviceFn = $product->serviceNS.'::charge_abo';
			if(!is_callable($serviceFn)){
				$stat->info = (object)['status'=>501,'error'=>'Service method unavailable'];
				continue;
				}

			// get list of chargeable abos
			$res = abo::get_rechargeable(['productID'=>$product->productID] + (isset($opt['filter_operatorID']) ? ['operatorID'=>$opt['filter_operatorID']] : []));
			if($res->status != 200){
				$stat->info = $res;
				continue;
				}
			$aboID_list = $res->data;

			// Abos verarbeiten
			$stat->entries = count($aboID_list);
			$stat->result = [];
			foreach($aboID_list as $entry){

				// check operator specific chargerule
				if(is_array($chargerule) and !in_array($entry->operatorID, $chargerule)){


					if(!isset($stat->result[$entry->operatorID][403])) $stat->result[$entry->operatorID][403] = 0;
					$stat->result[$entry->operatorID][403]++;

					// skip this operator/abo
					continue;
					}

				// charge
				$res = call_user_func($serviceFn, [
					'aboID'		=> $entry->aboID,
					'productID'	=> $product->productID,
					]);

				if(!isset($stat->result[$entry->operatorID][$res->status])) $stat->result[$entry->operatorID][$res->status] = 0;
				$stat->result[$entry->operatorID][$res->status]++;
				}
			}

		// return result
		return self::response(204, $result);
		}


	/* OTP */
	public static function submit_otp($req = []){

		/* possible otp error stati
			102 = pending (waiting for response of external service)
			200	= payment done
			307 = user redirection needed
			400	= missing or invalid libcom param
			401 = user canceled payment
			402 = payment failed (temporary)
			403	= payment failed (permanently)
			500	= unexpected error (DB Error, Invalid Config, etc.)
			501	= not implemented
			502 = external service failed (or its intepretation)
			503	= temporary error on external service
		/* */

		// mandatory
		$mand = h::eX($req, [
			'mobileID'			=> '~1,4294967295/i',
			'productID'			=> '~1,65535/i',
			'persistID'			=> '~1,4294967295/i',
			'submitted'			=> '~/b',
			], $error);

		// optional
		$opt = h::eX($req, [
			'otpID'				=> '~0,4294967295/i',
			'confirmationURL'	=> '~/s',
			'netm'				=> '~/l',
			'dimoco'			=> '~/l',
			'clickrequest'		=> '~/b',
			'apkflow'			=> '~/b',
			'tan'				=> '~1,11/s',
			'sandbox'			=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load product
		$res = nexus_service::get_product([
			'type'		=> 'otp',
			'productID'	=> $mand['productID'],
			]);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// take product
		$product = $res->data;

		// if we have an otpID
		if(!empty($opt['otpID'])){

			// take otpID (not check it for performance reasons)
			$processed_otpID = $opt['otpID'];
			}

		// or if not
		else{

			// create new
			$res = otp::create([
				'mobileID'	=> $mand['mobileID'],
				'productID'	=> $product->productID,
				'persistID'	=> $mand['persistID'],
				]);

			// on error
			if($res->status != 201) return self::response(570, $res);

			// take otpID
			$processed_otpID = $res->data->otpID;
			}

		// define service function
		$serviceFn = $product->serviceNS.'::submit_otp';

		// on error
		if(!is_callable($serviceFn)) return self::response(501, 'Service method '.$serviceFn.' unavailable');

		// call associated service
		$process = call_user_func($serviceFn, [
			'mobileID'			=> $mand['mobileID'],
			'otpID'				=> $processed_otpID,
			'productID'			=> $mand['productID'],
			'persistID'			=> $mand['persistID'],
			'submitted'			=> $mand['submitted'],
			] + $opt);

		// if data is not given
		if(!isset($process->data)){

			// create data in result with processed otpID
			$process->data = (object)[
				'otpID'	=> $processed_otpID,
				];
			}

		// else if is
		elseif(is_object($process->data)){

			// add processed otpID
			$process->data->otpID = $processed_otpID;
			}

		// special apk flow for redirection
		if(!empty($opt['apkflow']) and $process->status == 307){

			// get actual stack
			$res = reflector::get([
				'reflectorID'	=> $process->data->reflectorID,
				]);

			// on error
			if($res->status != 200){
				return self::response(500, 'Cannot load reflectorID '.$process->data->reflectorID.' for apkflow: '.$res->status);
				}

			// take reflector
			$reflector = $res->data;

			// load service url
			$res = self::get_mtservice_url();

			// on error
			if($res->status != 200){
				return self::response(500, 'Cannot load mtservice_url: '.$res->status);
				}

			// take service url
			$mtservice_url = $res->data->url;

			// if stack count is one
			if(count($reflector->stack) == 1){

				// append return stack
				$res = reflector::stack([
					'reflectorID' 	=> $reflector->reflectorID,
					'url'			=> $mtservice_url.'/endpoint/OK',
					]);

				// on error
				if($res->status != 201){
					return self::response(500, 'Cannot append return stack to reflectorID '.$process->data->reflectorID.' for apkflow: '.$res->status);
					}
				}

			// append url for redirection
			$process->data->url = $mtservice_url.'/reflector/'.$reflector->reflectorID;
			}

		// return service function result
		return $process;
		}


	/* Contingent */
	public static function change_contingent($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type' 		=> '~^(?:abo|otp)$',
			'by' 		=> '~-100000,-1/i', // Nur dekremtieren erlauben
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// Mobile+Produkt laden
		$res = self::get_mobile($req);
		if($res->status != 200) return $res;
		$mobile = $res->data;

		// Zu dekrementierender Wert in positiven umwandeln
		$dec = $org_dec = $mand['by'] * -1;

		// Abo verwenden
		if($mand['type'] == 'abo'){

			// Contingent Liste durchgehen (enthält nur verwendbare Charges mit Kontingent)
			foreach($mobile->charge_list as $charge){

				// Wenn es nichts zum dekrementieren gibt, gleich abbrechen
				if($dec <= 0) break;

				// Passenden Wert für Dekrementierung ermitteln
				$sub = min($charge->contingent, $dec);

				// Dieser Wert passt auf jedenfall in $charge->contingent und kann daher nicht unter 0 gehen
				if($sub){

					// Diesen Wert dekrementieren
					$res = abo::update_charge_contingent([
						'chargeID'	=> $charge->chargeID,
						'by'		=> $dec*-1,
						]);

					// Wenn Vorgang aus irgendein Grund nicht funktioniert, gleich komplett abbrechen
					if($res->status != 204){
						return self::response(570, $res);
						}

					// Den dekrementierten Wert vom Rest abziehen
					$dec -= $sub;

					}

				}

			}


		// OTP verwenden
		else{

			// OTP Liste durchgehen
			foreach($mobile->otp_list as $otp){

				// Wenn es nichts zum dekrementieren gibt, gleich abbrechen
				if($dec <= 0) break;

				// Ist dieses OTP überhaupt verwendbar
				if(!$otp->paid or $otp->refunded or $otp->expired or $otp->contingent <= 0) continue;

				// Passenden Wert für Dekrementierung ermitteln
				$sub = min($otp->contingent, $dec);

				// Dieser Wert passt auf jedenfall in $charge->contingent und kann daher nicht unter 0 gehen
				if($sub){

					// Diesen Wert dekrementieren
					$res = otp::update_contingent([
						'otpID'	=> $otp->otpID,
						'by'	=> $dec*-1,
						]);

					// Wenn Vorgang aus irgendein Grund nicht funktioniert, gleich komplett abbrechen
					if($res->status != 204){
						return self::response(570, $res);
						}

					// Den dekrementierten Wert vom Rest abziehen
					$dec -= $sub;
					}

				}
			}

		// Wenn nicht ausreichend dekrementiert werden konnte
		if($dec > 0){
			e::logtrigger('MobileID '.$mobile->mobileID.' ('.$mand['type'].') konnte nicht entsprechend dekrementiert werden. '.$dec.' von '.$org_dec.' blieb übrig.');

			// Wobei es hier (erstmal) nur ein Fehler ist, wenn gar nichts dekrementiert wurde
			if($dec == $org_dec) return self::response(406); // 406 Not Acceptable
			}

		// Erfolgreich
		return self::response(204);
		}


	/* SMS */
	public static function send_sms($req = []){

		// mandatory
		$mand = h::eX($req, [
			'serviceID'		=> '~1,65535/i',
			'mobileID'		=> '~1,4294967295/i',
			'text'			=> '~/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'senderString'	=> '~^[0-9]{1,16}$|^[a-zA-Z0-9 ]{1,11}$',
			'persistID'		=> '~1,4294967295/i',
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'operatorID'	=> '~1,65535/i',
			'smsgateID'		=> '~0,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load mobile
		$res = mobile::get_mobile([
			'mobileID'	=> $mand['mobileID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take mobile
		$mobile = $res->data;

		// take optional MSISDN, if not given in mobile entry
		if(!$mobile->msisdn and !empty($opt['msisdn'])) $mobile->msisdn = $opt['msisdn'][0];

		// take optional operatorID, if not given in mobile entry
		if(!$mobile->operatorID and !empty($opt['operatorID'])) $mobile->operatorID = $opt['operatorID'];

		// without an msisdn or operatorID, we cannot continue
		if(!$mobile->msisdn or !$mobile->operatorID) return self::response(423);

		// load service
		$res = nexus_service::get_service([
			'serviceID'	=> $mand['serviceID']
			]);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// take service
		$service = $res->data;

		// create sms
		$res = sms::create([
			'mobileID'	=> $mobile->mobileID,
			'serviceID'	=> $service->serviceID,
			'text'		=> $mand['text'],
			]);

		// on error
		if($res->status != 201) return $res;

		// take smsID
		$smsID = $res->data->ID;

		// check service function
		if(!is_callable($service->ns.'::send_sms')){

			// on error
			return self::response(501, 'Service method '.$service->ns.'::send_sms unavailable');
			}

		// call service function
		$res = call_user_func($service->ns.'::send_sms', [
			'msisdn'	=> $mobile->msisdn,
			'text'		=> $mand['text'],
			'serviceID'	=> $service->serviceID,
			'operatorID'=> $mobile->operatorID,
			] + $opt);

		// on error
		if($res->status != 204) return $res;

		// update sms
		$res = sms::sent([
			'ID'		=> $smsID,
			'sendTime'	=> date('Y-m-d H:i:s'),
			]);

		// on error
		if($res->status != 204) return $res;

		// return success
		return self::response(201, (object)['smsID'=>$smsID]);
		}


	/* SMS-Gate DEPRECATED */
	public static function process_smsgate_mo($req = []){

		// redirect
		return smsgate_service::service_smsgate_mo($req);
		}

	public static function process_smsgate_mt($req = []){

		// redirect
		return smsgate_service::service_smsgate_mt($req);
		}


	/* Other */
	public static function create_persistence($req = []){

		// optional
		$opt = h::eX($req, [
			'imsi'				=> '~^[1-9]{1}[0-9]{5,15}$',
			'pageID'			=> '~1,65535/i',
			'operatorID'		=> '~1,65535/i',
			'createTime'		=> '~Y-m-d H:i:s/d',
			'autocreate_mobile' => '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'createTime'		=> date('Y-m-d H:i:s'),
			'autocreate_mobile' => false,
			];

		// load persist data
		$res = persist::create([
			'createTime'	=> $opt['createTime'],
			]);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// take persistID as result
		$result = (object)[
			'persistID'	=> $res->data->persistID,
			];

		// define empty mobile object
		$mobile = (object)[
			'mobileID'		=> 0,
			'operatorID'	=> $opt['operatorID'] ?? 0,
			];

		// define empty adtarget object
		$adtarget = (object)[
			'domainID'		=> 0,
			'pageID'		=> 0,
			'publisherID'	=> 0,
			];

		// if imsi is given
		if(isset($opt['imsi'])){

			// load mobile with given value
			$res = self::get_mobile([
				'imsi' 			=> $opt['imsi'],
				'autocreate' 	=> $opt['autocreate_mobile'],
				]);

			// abort if status is not 404
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if found
			if($res->status == 200){

				// take mobile
				$mobile = $res->data;
				}

			// append mobileID to result
			$result->mobileID = $mobile->mobileID;
			}

		// if pageID is given
		if(isset($opt['pageID'])){

			// load adtarget
			$res = nexus_domain::get_adtarget([
				'pageID'	=> $opt['pageID'],
				]);

			// on error
			if($res->status !== 200) return self::response(570, $res);

			// take adtarget
			$adtarget = $res->data;

			// create session
			$res = session::create_session([
				'persistID'		=> $result->persistID,
				'createTime'	=> $opt['createTime'],
				'domainID'		=> $adtarget->domainID,
				'pageID'		=> $adtarget->pageID,
				'publisherID'	=> $adtarget->publisherID,
				'mobileID'		=> $mobile->mobileID, // could be 0
				'operatorID'	=> $mobile->operatorID, // could be 0
				]);

			// on error
			if(!in_array($res->status, [201, 409])){
				return self::response(500, 'Adding traffic session for persistID '.$result->persistID.' failed with: '.$res->status.' (pageID '.$adtarget->pageID.', mobileID '.$mobile->mobileID.')');
				}
			}

		// if mobileID is given
		if($mobile->mobileID){

			// add persistlink
			$res = mobile::update_mobile([
				'mobileID'		=> $mobile->mobileID,
				'persistID'		=> $result->persistID,
				'insertTime'	=> $opt['createTime'],
				'domainID'		=> $adtarget->domainID,
				'pageID'		=> $adtarget->pageID,
				]);

			// on error
			if($res->status != 204){
				return self::response(500, 'Adding persistlink for persistID '.$result->persistID.' failed with: '.$res->status.' (pageID '.$adtarget->pageID.', mobileID '.$mobile->mobileID.')');
				}
			}

		// return result
		return self::response(201, $result);
		}

	}