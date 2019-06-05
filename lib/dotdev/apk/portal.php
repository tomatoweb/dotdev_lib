<?php
/*****
 * Version 1.1.2019-01-16
**/
namespace dotdev\apk;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\nexus\base as nexus_base;
use \dotdev\nexus\service as nexus_service;
use \dotdev\apk\share as apk_share;
use \dotdev\reflector;
use \dotdev\mobile;
use \dotdev\mobile\tan;
use \dotdev\mobile\abo;
use \dotdev\mobile\otp;
use \dotdev\mobile\login as mobile_login;
use \dotdev\mobile\client as mobile_client;
use \dotdev\traffic\session as traffic_session;

class portal {
	use \tools\libcom_trait;

	/* helper */
	protected static function _patch_request_data($req = []){

		// convert request data to array
		$req = (array) $req;

		// log done patches here
		$patches = [];

		// if uniqueID param
		if(isset($req['uniqueID'])){

			// but only if it is a string and unique_hash does not exist
			if(is_string($req['uniqueID']) and !isset($req['unique_hash'])){

				// create SubscriberID
				$req['unique_hash'] = strtolower($req['uniqueID']);
				}

			// remove uniqueID
			//$patches[] = 'uniqueID';
			unset($req['uniqueID']);
			}

		// for imsi param
		if(isset($req['imsi'])){

			// if no hni given
			if(!isset($req['hni'])){

				// convert imsi to hni
				$req['hni'] = (int) substr((string) $req['imsi'], 0, 5);
				}

			// unset imsi
			//$patches[] = 'imsi';
			unset($req['imsi']);
			}

		// log patches
		if($patches) e::logtrigger('PATCH: Portal APK open_session request patch applied: '.implode(', ', $patches).' (persistID '.h::encode_php($req['persistID'] ?? null).')');

		// return patched request data
		return $req;
		}


	/* session and events */
	public static function open_session($req = []){

		/* patch request data */
		$req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($req, [
			'project'			=> '~^[a-z0-9_]{1,32}$',
			'pageID'			=> '~0,65535/i',
			'unique_hash'		=> '~^[a-z0-9]{40}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'persistID'			=> '~1,18446744073709551615/i',
			'hni'				=> '~20000,79999/i',
			'countryID'			=> '~1,255/i',
			'operatorID'		=> '~1,65535/i',
			'device'			=> '~1,255/s',
			'new_unique_hash'	=> '~^[a-z0-9]{40}$',
			'publisher_switch'	=> '~^(?:adjust|adzoona)$',
			'publisher_referer'	=> '~/s',
			'publisher_request'	=> '~/c',
			'runtime_update'	=> '~/b',
			'build'				=> '~0,16777215/i',
			], $error, true);

		// special check
		if(isset($mand['pageID']) and !$mand['pageID'] and !isset($opt['persistID'])) $error[] = 'pageID';

		// on error
		if($error) return self::response(400, $error);

		// forward request
		return apk_share::open_session([
			'project'			=> $mand['project'],
			'pageID'			=> $mand['pageID'],
			'unique_hash'		=> $mand['unique_hash'],
			'persistID'			=> $opt['persistID'] ?? null,
			'hni'				=> $opt['hni'] ?? null,
			'countryID'			=> $opt['countryID'] ?? null,
			'operatorID'		=> $opt['operatorID'] ?? null,
			'device'			=> $opt['device'] ?? null,
			'new_unique_hash'	=> $opt['new_unique_hash'] ?? null,
			'publisher_switch'	=> $opt['publisher_switch'] ?? null,
			'publisher_referer'	=> $opt['publisher_referer'] ?? null,
			'publisher_request'	=> $opt['publisher_request'] ?? null,
			'runtime_update'	=> $opt['runtime_update'] ?? null,
			'build'				=> $opt['build'] ?? null,
			]);
		}

	public static function trigger_event($req = []){

		// forward to shared function
		return apk_share::trigger_event($req);
		}


	/* identification */
	public static function open_ibr_process($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'persistID'		=> '~1,18446744073709551615/i',
			'type' 			=> '~^(?:abo|otp)$',
			'productID'		=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'restart'		=> '~/b',
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
		$process = call_user_func($product->serviceNS.'::identify_mobile', [
			'persistID'	=> $mand['persistID'],
			'productID'	=> $product->productID,
			'type'		=> $mand['type'],
			'restart'	=> !empty($opt['restart']),
			]);

		// on error, directly return process error
		if(!in_array($process->status, [200,307])) return $process;


		// if redirection is needed
		if($process->status == 307){

			// get actual stack
			$res = reflector::get([
				'reflectorID'	=> $process->data->reflectorID,
				]);

			// on error
			if($res->status != 200){
				return self::response(500, 'Cannot load reflectorID '.$process->data->reflectorID.' for Portal APK open_ibr_process(): '.$res->status);
				}

			// take reflector
			$reflector = $res->data;

			// load service url
			$res = mobile_client::get_mtservice_url();

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
					return self::response(500, 'Cannot append return stack to reflectorID '.$process->data->reflectorID.' for Portal APK open_ibr_process(): '.$res->status);
					}
				}

			// return status with generated redirection url
			return self::response(307, (object)[
				'url'	=> $mtservice_url.'/reflector/'.$reflector->reflectorID,
				]);
			}

		// if process is not successful finished (yet)
		if($process->status != 200){

			// remove mobileID
			unset($process->mobileID);

			// return call response
			return $process;
			}


		// load payment status
		$res = apk_share::get_payment_data([
			'project'	=> $mand['project'],
			'mobileID'	=> $process->data->mobileID,
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$payment_data = $res->data;


		// if MSISDN is needed for this service
		if(h::cX($product->param, 'need_msisdn', true) and !$payment_data->msisdn){

			// return 403 forbidden
			return self::response(403);
			}


		// check if persistlink was already added
		$res = mobile::get_persistlink([
			'persistID'		=> $mand['persistID'],
			'addtimeonly'	=> true,
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// define persistlink found
		$persistlink_found = ($res->status == 200);


		// if persistlink wasn't made yet
		if(!$persistlink_found){

			// add persistlink
			$res = mobile::update_mobile([
				'mobileID'		=> $payment_data->mobileID,
				'persistID'		=> $mand['persistID'],
				]);

			// on error
			if($res->status != 204) return self::response(570, $res);

			// add redisjob for delayed user integration into session (dont check $res for failures)
			$res = traffic_session::delayed_update_session([
				'persistID'		=> $mand['persistID'],
				'mobileID'		=> $payment_data->mobileID,
				'operatorID'	=> $payment_data->operatorID ?: null,
				'countryID'		=> $payment_data->countryID ?: null,
				]);
			}


		// define result
		$result = (object)[
			'persistID'			=> $mand['persistID'],
			'product_access'	=> $payment_data->product_access,
			'product_contingent'=> $payment_data->product_contingent,
			'msisdn'			=> $payment_data->msisdn,
			'imsi'				=> $payment_data->imsi,
			'operatorID'		=> $payment_data->operatorID,
			'countryID'			=> $payment_data->countryID,
			'blacklisted'		=> $payment_data->blacklisted,
			];

		// return result
		return self::response(200, $result);
		}

	public static function open_ibi_process($req = []){

		/* patch request data */
		$req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'persistID'		=> '~1,18446744073709551615/i',
			'type'			=> '~^(?:abo|otp)$',
			'productID'		=> '~1,65535/i',
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'hni'			=> '~20000,79999/i',
			'tan'			=> '~4,12/s',
			'tan_smstext'	=> '~5,160/s',
			'sender'		=> '~^[a-zA-Z0-9]{1,11}$',
			'switch'		=> '~/b',
			], $error, true);

		// additional check
		if(isset($opt['tan_text']) and strpos($opt['tan_text'], '{tan}') === false) $error[] = 'tan_text';

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

		// if IBI mode is not allowed, return forbidden
		if(!$ibi_enabled) return self::response(403);


		// try to load mobile with persistID
		$res = mobile::get_mobile([
			'persistID'		=> $mand['persistID'],
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// define persistlink mobile, if found
		$persistlink_mobile = ($res->status == 200) ? $res->data : null;


		// if persistlink mobile was found, given MSISDN does not match and switch param is not set to true
		if($persistlink_mobile and $persistlink_mobile->msisdn != $mand['msisdn'] and empty($opt['switch'])){

			// return forbidden
			return self::response(403);
			}


		// define check_mobile (only take persistlink mobile, if MSISDN matches)
		$check_mobile = ($persistlink_mobile and $persistlink_mobile->msisdn == $mand['msisdn']) ? $persistlink_mobile : null;

		// if no check_mobile is defined
		if(!$check_mobile){

			// try to load mobile
			$res = mobile::get_mobile([
				'msisdn'	=> $mand['msisdn'],
				]);

			// on unexpected error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// define check mobile, if found
			$check_mobile = ($res->status == 200) ? $res->data : null;


			// if hlrlookup_serviceID is given, it can be used for unconfirmed or not found mobile entry
			if($hlrlookup_serviceID and (!$check_mobile or !$check_mobile->confirmed)){

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

				// if lookup failed, return forbidden
				if(in_array($hlr->status, [404, 410])) return self::response(403);

				// if lookup has unexpected error
				if($hlr->status != 200) return self::response(570, $hlr);


				// if mobile entry not exists
				if(!$check_mobile){

					// create mobile
					$res = mobile::create_mobile([
						'msisdn'	=> $hlr->data->msisdn,
						'operatorID'=> $hlr->data->operatorID,
						'confirmed'	=> true,
						]);

					// on unexpected error
					if(!in_array($res->status, [201, 409])) return self::response(570, $res);
					}

				// else
				else{

					// update mobile
					$res = mobile::update_mobile([
						'mobileID'	=> $check_mobile->mobileID,
						'operatorID'=> $hlr->data->operatorID,
						'confirmed'	=> true,
						]);

					// on error
					if($res->status != 204) return self::response(570, $res);
					}

				// reload mobile entry
				$res = mobile::get_mobile([
					'msisdn'	=> $mand['msisdn'],
					]);

				// on unexpected error
				if($res->status != 200) return self::response(570, $res);

				// define check mobile
				$check_mobile = $res->data;
				}


			// if mobile is not found
			if(!$check_mobile){

				// define some value
				$operatorID = null;

				// if hni is given
				if(!empty($opt['hni'])){

					// load operator of hni
					$res = nexus_base::get_operator([
						'hni'	=> $opt['hni'],
						]);

					// on success
					if($res->status == 200){

						// take data
						$operatorID = $res->data->operatorID;
						}
					}

				// create entry with MSISDN (and operatorID, if given)
				$res = mobile::create_mobile([
					'msisdn'		=> $mand['msisdn'],
					'operatorID'	=> $operatorID ?: 0,
					]);

				// on error
				if(!in_array($res->status, [201, 409])) return self::response(570, $res);

				// reload mobile entry
				$res = mobile::get_mobile([
					'msisdn'	=> $mand['msisdn'],
					]);

				// on unexpected error
				if($res->status != 200) return self::response(570, $res);

				// define check mobile
				$check_mobile = $res->data;
				}
			}


		// load payment status
		$res = apk_share::get_payment_data([
			'project'	=> $mand['project'],
			'mobileID'	=> $check_mobile->mobileID,
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$payment_data = $res->data;


		// define persistlink deletion
		$delete_persistlink = false;

		// if there is already a mobile loaded with persistID, but isn't the same as the given MSISDN
		if($persistlink_mobile and $persistlink_mobile->msisdn != $check_mobile->msisdn){

			// define to delete persistlink later
			$delete_persistlink = true;

			// unset persistlink loaded mobile
			$persistlink_mobile = null;
			}


		// if no mobile with given persistID was found before and product access or active subscriptions defines the need of a TAN verification
		if(!$persistlink_mobile and ($payment_data->product_access or $payment_data->active_subscriptions)){

			// define tan option
			$tan_option = [
				'serviceID'			=> h::gX($product->param, 'identify:tan_serviceID'),
				'expire'			=> '4 hour',
				'tan_retry'			=> 3,
				'retry_expires'		=> true,
				'match_expires'		=> true,
				'allow_recreation'	=> true,
				'recreation_lock'	=> '20 min',
				'tan_length'		=> 6,
				'sms_senderString'	=> $opt['sender'] ?? h::gX($product->param, 'identify:tan_senderString'),
				'sms_text'			=> $opt['tan_smstext'] ?? (h::gX($product->param, 'identify:tan_text') ?: '{tan}'),
				];


			// if we have no tan to check against
			if(!isset($opt['tan'])){

				// load last tan
				$res = tan::get_tan([
					'mobileID'		=> $payment_data->mobileID,
					'last_only'		=> true,
					]);

				// on unexpected error
				if(!in_array($res->status, [200, 404])) return self::response(570, $res);

				// if tan was found
				if($res->status == 200){

					// take tan
					$last_tan = $res->data;

					// define if retry is allowed
					$retry_allowed = ($last_tan->retry > 0 or (empty($tan_option['retry_expires']) and empty($tan_option['match_expires'])));

					// if retry is allowed and tan date has not expired yet
					if($retry_allowed and h::date($last_tan->createTime) > h::date('-'.$tan_option['expire'])){

						// return continue state (which means, user input of tan is needed)
						return self::response(100);
						}

					// if tan recreation is not allowed or a tan recreation lock is still active
					if(!$tan_option['allow_recreation'] or ($tan_option['recreation_lock'] and h::date($last_tan->createTime) > h::date('-'.$tan_option['recreation_lock']))){

						// return locked state (which means, the complete tan process has failed and is locked until expire time or recreation_lock time is reached)
						return self::response(423);
						}

					// if previous condition not met, than a tan recreation is allowed (like we haven't found a tan before)
					}

				// create tan
				$res = tan::create_tan([
					'mobileID'		=> $payment_data->mobileID,
					'persistID'		=> $mand['persistID'],
					'tan_length'	=> $tan_option['tan_length'],
					'retry'			=> $tan_option['tan_retry'],
					]);

				// on error
				if($res->status != 201) return self::response(570, $res);

				// take tan
				$new_tan = $res->data;

				// send sms with tan
				$res = mobile_client::send_sms([
					'mobileID'		=> $payment_data->mobileID,
					'serviceID'		=> $tan_option['serviceID'],
					'text'			=> h::replace_in_str($tan_option['sms_text'], [
						'{tan}'		=> $new_tan->tan,
						]),
					'senderString'	=> $tan_option['sms_senderString'],
					'persistID'		=> $mand['persistID'],
					]);

				// on error
				if($res->status != 201) return self::response(570, $res);

				// return continue state (which means, user input of tan is needed)
				return self::response(100);
				}


			// check tan
			$res = tan::check_tan([
				'mobileID'		=> $payment_data->mobileID,
				'tan'			=> $opt['tan'],
				'expire'		=> $tan_option['expire'],
				'retry_expires'	=> $tan_option['retry_expires'],
				'match_expires'	=> $tan_option['match_expires'],
				]);

			// on unexpected error
			if($res->status != 200) return self::response(570, $res);

			// if tan is expired, return 410 gone
			if($res->data->expired) return self::response(410);

			// if tan is invalid, return 401 unauthorized
			if(!$res->data->valid) return self::response(401);
			}


		// if persistlink should be deleted
		if($delete_persistlink){

			// detach persistID from previous mobile
			$res = mobile::delete_persistlink([
				'persistID' 	=> $mand['persistID'],
				]);

			// on unexpected error
			if($res->status != 204) return self::response(570, $res);
			}

		// if no persistlink mobile is defined
		if(!$persistlink_mobile){

			// add persistlink
			$res = mobile::update_mobile([
				'mobileID'		=> $payment_data->mobileID,
				'persistID'		=> $mand['persistID'],
				]);

			// on unexpected error
			if($res->status != 204) return self::response(570, $res);

			// add redisjob for delayed session update (dont check $res for failures)
			$res = traffic_session::delayed_update_session([
				'persistID'		=> $mand['persistID'],
				'mobileID'		=> $payment_data->mobileID,
				'operatorID'	=> $payment_data->operatorID ?: null,
				'countryID'		=> $payment_data->countryID ?: null,
				]);
			}


		// define result
		$result = (object)[
			'persistID'			=> $mand['persistID'],
			'product_access'	=> $payment_data->product_access,
			'product_contingent'=> $payment_data->product_contingent,
			'msisdn'			=> $payment_data->msisdn,
			'imsi'				=> $payment_data->imsi,
			'operatorID'		=> $payment_data->operatorID,
			'countryID'			=> $payment_data->countryID,
			'blacklisted'		=> $payment_data->blacklisted,
			];

		// return result
		return self::response(200, $result);
		}

	public static function open_ibl_process($req = []){

		/* patch request data */
		$req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($req, [
			'project'	=> '~^[a-z0-9_]{1,32}$',
			'persistID'	=> '~1,18446744073709551615/i',
			'loginstr'	=> '~^[a-zA-Z0-9]{12}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'hni'			=> '~20000,79999/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// load payment status
		$res = apk_share::get_payment_data([
			'project'	=> $mand['project'],
			'persistID'	=> $mand['persistID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$payment_data = $res->data;


		// if persistlink mobile has already a MSISDN or product access over a previous payment
		if($payment_data->msisdn or $payment_data->product_access){

			// return forbidden
			return self::response(403);
			}


		// load login
		$res = mobile_login::get_login([
			'loginstr'	=> $mand['loginstr'],
			]);

		// if not found or unexpected error
		if($res->status != 200){

			// return forbidden or error
			return $res->status == 404 ? self::response(401) : self::response(570, $res);
			}

		// take login
		$login = $res->data;

		// if persistlink does not match
		if($payment_data->mobileID != $login->mobileID){

			// if persistlink should be deleted
			if($payment_data->mobileID){

				// detach persistID from previous mobile
				$res = mobile::delete_persistlink([
					'persistID' 	=> $mand['persistID'],
					]);

				// on unexpected error
				if($res->status != 204) return self::response(570, $res);
				}

			// define new operatorID
			$new_operatorID = null;

			// if no operatorID, but hni is given
			if(!$login->operatorID and !empty($opt['hni'])){

				// load operator of hni
				$res = nexus_base::get_operator([
					'hni'	=> $opt['hni'],
					]);

				// on success
				if($res->status == 200){

					// take data
					$new_operatorID = $res->data->operatorID;
					}
				}

			// update mobile and add persistlink
			$res = mobile::update_mobile([
				'mobileID'		=> $login->mobileID,
				'operatorID'	=> $new_operatorID ?: null,
				'persistID'		=> $mand['persistID'],
				]);

			// on unexpected error
			if($res->status != 204) return self::response(570, $res);


			// reload payment status
			$res = apk_share::get_payment_data([
				'project'	=> $mand['project'],
				'persistID'	=> $mand['persistID'],
				]);

			// on error
			if($res->status != 200) return $res;

			// take entry
			$payment_data = $res->data;

			// add redisjob for delayed session update (dont check $res for failures)
			$res = traffic_session::delayed_update_session([
				'persistID'		=> $mand['persistID'],
				'mobileID'		=> $payment_data->mobileID,
				'operatorID'	=> $payment_data->operatorID ?: null,
				'countryID'		=> $payment_data->countryID ?: null,
				]);
			}

		// define result
		$result = (object)[
			'persistID'			=> $mand['persistID'],
			'product_access'	=> $payment_data->product_access,
			'product_contingent'=> $payment_data->product_contingent,
			'msisdn'			=> $payment_data->msisdn,
			'imsi'				=> $payment_data->imsi,
			'operatorID'		=> $payment_data->operatorID,
			'countryID'			=> $payment_data->countryID,
			'blacklisted'		=> $payment_data->blacklisted,
			];

		// return result
		return self::response(200, $result);
		}


	/* payment */
	public static function get_payment_status($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'	=> '~^[a-z0-9_]{1,32}$',
			'persistID'	=> '~1,18446744073709551615/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load payment status
		$res = apk_share::get_payment_data([
			'project'	=> $mand['project'],
			'persistID'	=> $mand['persistID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$payment_data = $res->data;

		// define result
		$result = (object)[
			'persistID'			=> $mand['persistID'],
			'product_access'	=> $payment_data->product_access,
			'product_contingent'=> $payment_data->product_contingent,
			'msisdn'			=> $payment_data->msisdn,
			'imsi'				=> $payment_data->imsi,
			'operatorID'		=> $payment_data->operatorID,
			'countryID'			=> $payment_data->countryID,
			'blacklisted'		=> $payment_data->blacklisted,
			];

		// return result
		return self::response(200, $result);
		}

	public static function open_payment_process($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'			=> '~1,18446744073709551615/i',
			'type' 				=> 'abo',
			'productID'			=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'paymentID'			=> '~1,4294967295/i',
			'tan'				=> '~1,11/s',
			'submitted'			=> '~/b',
			'sandbox'			=> '~/b',
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

		// load mobile with given value
		$res = mobile::get_mobile([
			'persistID'	=> $mand['persistID'],
			]);

		// if mobile not found, precondition failed
		if($res->status == 404) return self::response(424);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take entry
		$mobile = $res->data;


		// if we no paymentID
		if(empty($opt['paymentID'])){

			// for abo payment
			if($mand['type'] == 'abo'){

				// create new
				$res = abo::create([
					'mobileID'	=> $mobile->mobileID,
					'productID'	=> $product->productID,
					'persistID'	=> $mand['persistID'],
					]);

				// on error
				if($res->status != 201) return self::response(570, $res);

				// take paymentID
				$opt['paymentID'] = $res->data->aboID;
				}

			// for any other type
			else {

				// return not supported
				return self::response(500, 'Unsupported payment type Portal APK open_payment(): '.$mand['type']);
				}
			}


		// define service function param
		$param = [
			'mobileID'			=> $mobile->mobileID,
			'productID'			=> $product->productID,
			'persistID'			=> $mand['persistID'],
			'submitted'			=> $opt['submitted'] ?? false,
			'tan'				=> $opt['tan'] ?? null,
			'sandbox'			=> $opt['sandbox'] ?? null,
			];


		// for abo payment
		if($mand['type'] == 'abo'){

			// get associated service function
			$serviceFn = $product->serviceNS.'::create_abo';

			// define payment param
			$param['aboID'] = $opt['paymentID'];
			}

		// for any other type
		else {

			// return not supported
			return self::response(500, 'Unsupported payment type Portal APK open_payment(): '.$mand['type']);
			}

		// on error
		if(!is_callable($serviceFn)) return self::response(501, 'Service method '.$serviceFn.' unavailable');

		// call associated service
		$process = call_user_func($serviceFn, $param);

		// on error, directly return process error
		if(!in_array($process->status, [100,102,200,307,401,402,403,409])) return $process;


		// define result
		$result = (object)[
			'paymentID'	=> $opt['paymentID'],
			];

		// if redirection is needed
		if($process->status == 307){

			// get actual stack
			$res = reflector::get([
				'reflectorID'	=> $process->data->reflectorID,
				]);

			// on error
			if($res->status != 200){
				return self::response(500, 'Cannot load reflectorID '.$process->data->reflectorID.' for Portal APK open_payment(): '.$res->status);
				}

			// take reflector
			$reflector = $res->data;

			// load service url
			$res = mobile_client::get_mtservice_url();

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
					return self::response(500, 'Cannot append return stack to reflectorID '.$process->data->reflectorID.' for Portal APK open_payment(): '.$res->status);
					}
				}

			// append url for redirection
			$result->url = $mtservice_url.'/reflector/'.$reflector->reflectorID;
			}

		// return process status and result
		return self::response($process->status, $result);
		}


	/* other */
	public static function set_product_access_msisdn($req = []){

		/* patch request data */
		$req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'persistID'		=> '~1,18446744073709551615/i',
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'hni'			=> '~20000,79999/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// extract msisdn
		$mand['msisdn'] = $mand['msisdn'][0];


		// load payment status
		$res = apk_share::get_payment_data([
			'project'	=> $mand['project'],
			'persistID'	=> $mand['persistID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$payment_data = $res->data;

		// for security reasons, if persistID has no product access, return forbidden
		if(!$payment_data->product_access) return self::response(403);

		// if persistID is already attached to a different MSISDN, return conflict
		if($payment_data->msisdn and $payment_data->msisdn != $mand['msisdn']) return self::response(409);


		// if no MSISDN is known
		if(!$payment_data->msisdn){

			// define new operatorID
			$new_operatorID = null;

			// if no operatorID, but hni is given
			if(!$payment_data->operatorID and !empty($opt['hni'])){

				// load operator of hni
				$res = nexus_base::get_operator([
					'hni'	=> $opt['hni'],
					]);

				// on success
				if($res->status == 200){

					// take data
					$new_operatorID = $res->data->operatorID;
					}
				}

			// update mobile
			$res = mobile::update_mobile([
				'mobileID'		=> $payment_data->mobileID,
				'msisdn'		=> $mand['msisdn'],
				'operatorID'	=> $new_operatorID ?: null,
				]);

			// on unexpected error
			if($res->status != 204) return self::response(570, $res);


			// reload payment status
			$res = apk_share::get_payment_data([
				'project'	=> $mand['project'],
				'persistID'	=> $mand['persistID'],
				]);

			// on error
			if($res->status != 200) return $res;

			// take entry
			$payment_data = $res->data;
			}


		// define result
		$result = (object)[
			'persistID'			=> $mand['persistID'],
			'product_access'	=> $payment_data->product_access,
			'product_contingent'=> $payment_data->product_contingent,
			'msisdn'			=> $payment_data->msisdn,
			'imsi'				=> $payment_data->imsi,
			'operatorID'		=> $payment_data->operatorID,
			'countryID'			=> $payment_data->countryID,
			'blacklisted'		=> $payment_data->blacklisted,
			];

		// return result
		return self::response(200, $result);
		}

	}