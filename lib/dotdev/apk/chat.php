<?php
/*****
 * Version 1.1.2018-12-18
**/
namespace dotdev\apk;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\nexus\base as nexus_base;
use \dotdev\nexus\catlop as nexus_catlop;
use \dotdev\nexus\service as nexus_service;
use \dotdev\apk\share as apk_share;
use \dotdev\reflector;
use \dotdev\mobile;
use \dotdev\mobile\tan;
use \dotdev\mobile\webmo;
use \dotdev\mobile\abo;
use \dotdev\mobile\otp;
use \dotdev\mobile\client as mobile_client;
use \dotdev\smsgate\service as smsgate_service;
use \dotdev\traffic\session as traffic_session;

class chat {
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

		// return patched request data
		return $req;
		}


	/* session and events */
	public static function open_session($req = []){

		// patch request data
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
			'imsi'				=> '~^[1-7]{1}[0-9]{5,15}$',
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

		// on error
		if($error) return self::response(400, $error);

		// forward request
		return apk_share::open_session([
			'project'			=> $mand['project'],
			'pageID'			=> $mand['pageID'],
			'unique_hash'		=> $mand['unique_hash'],
			'persistID'			=> $opt['persistID'] ?? null,
			'imsi'				=> $opt['imsi'] ?? null,
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
	public static function open_ibi_process($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'persistID'		=> '~1,18446744073709551615/i',
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'operatorID'	=> '~1,65535/i',
			'imsi'			=> '~^[1-7]{1}[0-9]{5,15}$',
			'tan'			=> '~4,12/s',
			'tan_smstext'	=> '~5,160/s',
			'sender'		=> '~^[a-zA-Z0-9]{1,11}$',
			], $error, true);

		// additional check
		if(isset($opt['tan_text']) and strpos($opt['tan_text'], '{tan}') === false) $error[] = 'tan_text';

		// on error
		if($error) return self::response(400, $error);

		// extract msisdn
		$mand['msisdn'] = $mand['msisdn'][0];



		// load apk
		$res = nexus_catlop::get_apk([
			'project'	=> $mand['project'],
			]);

		// on error
		if(!in_array($res->status, [200, 404])) return $res;

		// if not found, return precondition failed
		if($res->status == 404) return self::response(412);

		// take apk config
		$config = $res->data->config;

		// define server url
		$ibi_serviceID = h::gX($config, 'connect:ibi:tan_serviceID');
		$hlrlookup_serviceID = h::gX($config, 'connect:ibi:hlrlookup_serviceID');

		// if IBI mode is not enabled, return forbidden
		if(!$ibi_serviceID) return self::response(403);


		// try to load mobile with persistID
		$res = mobile::get_mobile([
			'persistID'	=> $mand['persistID'],
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// define persistlink mobile, if found
		$persistlink_mobile = ($res->status == 200) ? $res->data : null;


		// if there is already a mobile loaded with persistID
		if($persistlink_mobile){

			// if msisdn does not match, return conflict
			if($persistlink_mobile->msisdn and $persistlink_mobile->msisdn != $mand['msisdn']) return self::response(409);

			// load payment status
			$res = self::get_payment_status([
				'project'	=> $mand['project'],
				'persistID'	=> $mand['persistID'],
				]);

			// on unexpected error
			if($res->status != 200) return self::response(570, $res);

			// return payment status as result
			return self::response(200, $res->data);
			}


		// define check_mobile
		$check_mobile = $persistlink_mobile ? $persistlink_mobile : null;

		// if check_mobile is not defined
		if(!$check_mobile){

			// try to load mobile with msisdn
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
				$operatorID = $opt['operatorID'] ?? null;

				// if no operatorID defined, but IMSI is given
				if(!$operatorID and !empty($opt['imsi'])){

					// load operator of hni
					$res = nexus_base::get_operator([
						'hni'	=> (int) substr((string) $opt['imsi'], 0, 5),
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


		// define (default) tan options
		$tan_option = [
			'serviceID'			=> $ibi_serviceID,
			'expire'			=> '4 hour',
			'tan_retry'			=> 3,
			'retry_expires'		=> true,
			'match_expires'		=> true,
			'allow_recreation'	=> true,
			'recreation_lock'	=> '20 min',
			'tan_length'		=> 6,
			'sms_senderString'	=> null,
			'sms_text'			=> '{tan}',
			];

		// define fields loadable from config
		$tan_option_fields = [
			'expire'			=> ['connect:ibi:tan_expire', '~/s'],
			'tan_retry'			=> ['connect:ibi:tan_retry', '~1,20/i'],
			'recreation_lock'	=> ['connect:ibi:tan_lock', '~/s'],
			'sms_senderString'	=> ['connect:ibi:tan_sender', '~1,16/s'],
			'sms_text'			=> ['connect:ibi:tan_text', '~1,160/s'],
			];

		// define country code
		$cc = $check_mobile->code ?: 'default';

		// for each field
		foreach($tan_option_fields as $option_key => list($config_key, $regex)){

			// for country specific config keys
			if(h::cX($config, $config_key, '~/c')){

				// if config key is defined
				if(h::cX($config, $config_key.':'.$cc, $regex) or h::cX($config, $config_key.':default', $regex)){

					// define country specific or default value
					$tan_option[$option_key] = h::gX($config, $config_key.':'.$cc) ?: h::gX($config, $config_key.':default');
					}
				}

			// if normal config keys
			elseif(h::cX($config, $config_key, $regex)){

				// define value
				$tan_option[$option_key] = h::gX($config, $config_key);
				}
			}

		// if sender or tan_text is defined, overwrite option
		if(isset($opt['sender'])) $tan_option['sms_senderString'] = $opt['sender'];
		if(isset($opt['tan_smstext'])) $tan_option['sms_text'] = $opt['tan_smstext'];


		// if we have no tan to check against
		if(!isset($opt['tan'])){

			// load last tan
			$res = tan::get_tan([
				'mobileID'		=> $check_mobile->mobileID,
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
				'mobileID'		=> $check_mobile->mobileID,
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
				'mobileID'		=> $check_mobile->mobileID,
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
			'mobileID'		=> $check_mobile->mobileID,
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


		// update mobile (adding persistlink)
		$res = mobile::update_mobile([
			'mobileID'		=> $check_mobile->mobileID,
			'persistID'		=> $mand['persistID'],
			]);

		// on unexpected error
		if($res->status != 204) return self::response(570, $res);

		// add redisjob for delayed session update (don't check $res for failures)
		$res = traffic_session::delayed_update_session([
			'persistID'		=> $mand['persistID'],
			'mobileID'		=> $check_mobile->mobileID,
			'operatorID'	=> $check_mobile->operatorID ?: null,
			'countryID'		=> $check_mobile->countryID ?: null,
			]);


		// load payment status
		$res = self::get_payment_status([
			'project'	=> $mand['project'],
			'persistID'	=> $mand['persistID'],
			]);

		// on unexpected error
		if($res->status != 200) return self::response(570, $res);

		// return payment status as result
		return self::response(200, $res->data);
		}


	/* payment */
	public static function get_payment_status($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($patched_req, [
			'project'	=> '~^[a-z0-9_]{1,32}$',
			'persistID'	=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($patched_req, [
			'imsi'		=> '~^[1-7]{1}[0-9]{5,15}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load payment status
		$res = apk_share::get_payment_data([
			'project'	=> $mand['project'],
			'persistID'	=> $mand['persistID'],
			'imsi'		=> $opt['imsi'] ?? null,
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$payment_data = $res->data;

		// define result
		$result = (object)[
			'persistID'				=> $mand['persistID'],
			'product_access'		=> $payment_data->product_access,
			'product_contingent'	=> $payment_data->product_contingent,
			'msisdn'				=> $payment_data->msisdn,
			'imsi'					=> $payment_data->imsi,
			'operatorID'			=> $payment_data->operatorID,
			'countryID'				=> $payment_data->countryID,
			'blacklisted'			=> $payment_data->blacklisted,
			'smspay_possible'		=> ($payment_data->mp_status and $payment_data->mp_status >= 400) ? false : true,
			'webmo_payment_possible'=> (!$payment_data->mobileID or !$payment_data->msisdn or !$payment_data->operatorID) ? false : true,
			];

		// return result
		return self::response(200, $result);
		}

	public static function open_payment_process($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($patched_req, [
			'project'	=> '~^[a-z0-9_]{1,32}$',
			'persistID'	=> '~1,18446744073709551615/i',
			'type' 		=> 'otp',
			'productID'	=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($patched_req, [
			'paymentID'	=> '~1,4294967295/i',
			'submitted'	=> '~/b',
			'imsi'		=> '~^[1-7]{1}[0-9]{5,15}$',
			'sandbox'	=> '~/b',
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

		// define if persistlink exist
		$persistlink_exists = ($res->status == 200);

		// if mobile not found
		if($res->status == 404 and isset($opt['imsi'])){

			// load mobile with IMSI
			$res = mobile::get_mobile([
				'imsi'	=> $opt['imsi'],
				]);
			}

		// if mobile not found, precondition failed
		if($res->status == 404) return self::response(424);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take entry
		$mobile = $res->data;

		// if mobile has no MSISDN or operatorID, return failed dependency
		if(!$mobile->msisdn or !$mobile->operatorID) return self::response(424);


		// if no paymentID given
		if(empty($opt['paymentID'])){

			// for abo payment
			if($mand['type'] == 'otp'){

				// create new
				$res = otp::create([
					'mobileID'	=> $mobile->mobileID,
					'productID'	=> $product->productID,
					'persistID'	=> $mand['persistID'],
					]);

				// on error
				if($res->status != 201) return self::response(570, $res);

				// take paymentID
				$opt['paymentID'] = $res->data->otpID;
				}

			// for any other type
			else {

				// return not supported
				return self::response(500, 'Unsupported payment type Portal APK open_payment(): '.$mand['type']);
				}
			}


		// define service function param
		$param = [
			'mobileID'	=> $mobile->mobileID,
			'productID'	=> $product->productID,
			'persistID'	=> $mand['persistID'],
			'submitted'	=> $opt['submitted'] ?? false,
			'tan'		=> $opt['tan'] ?? null,
			'sandbox'	=> $opt['sandbox'] ?? null,
			];


		// for otp payment
		if($mand['type'] == 'otp'){

			// get associated service function
			$serviceFn = $product->serviceNS.'::submit_otp';

			// define payment param
			$param['otpID'] = $opt['paymentID'];
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
		if(!in_array($process->status, [100,102,200,307,401,402,403])) return $process;


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

		// if process was successful
		if($process->status == 200 and !$persistlink_exists){

			// add persistlink
			$res = mobile::update_mobile([
				'mobileID'		=> $mobile->mobileID,
				'persistID'		=> $mand['persistID'],
				]);

			// on error
			if($res->status != 204) return self::response(570, $res);

			// add redisjob for delayed user integration into session (don't check $res for failures)
			$res = traffic_session::delayed_update_session([
				'persistID'		=> $mand['persistID'],
				'mobileID'		=> $mobile->mobileID,
				'operatorID'	=> $mobile->operatorID ?: null,
				'countryID'		=> $mobile->countryID ?: null,
				]);
			}

		// return process status and result
		return self::response($process->status, $result);
		}


	/* send message */
	public static function send_message($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($patched_req, [
			'project'	=> '~^[a-z0-9_]{1,32}$',
			'persistID'	=> '~1,18446744073709551615/i',
			'shortcode'	=> '~^[0-9]{3,15}$',
			'message'	=> '~1,160/s',
			], $error);

		// optional
		$opt = h::eX($patched_req, [
			'imsi'		=> '~^[1-7]{1}[0-9]{5,15}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// load config
		$res = nexus_catlop::get_apk([
			'project'	=> $mand['project'],
			]);

		// if configuration could not be found, return precondition failed
		if($res->status == 404) return self::response(412);

		// on other error
		if($res->status != 200) return $res;

		// take apk
		$apk = $res->data;

		// define product configuration
		$webmo_contingent_cost = h::cX($apk->config, 'setting:webmo_contingent_cost', '~1,9999/i') ? h::gX($apk->config, 'setting:webmo_contingent_cost') : null;

		// if contingent cost is not set, return precondition failed
		if(!$webmo_contingent_cost) return self::response(412);


		// load payment status
		$res = apk_share::get_payment_data([
			'project'	=> $mand['project'],
			'persistID'	=> $mand['persistID'],
			'imsi'		=> $opt['imsi'] ?? null,
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$payment_data = $res->data;


		// if no mobile given, mobile has no MSISDN or operatorID, return failed dependency
		if(!$payment_data->mobileID or !$payment_data->msisdn or !$payment_data->operatorID) return self::response(424);

		// if contingent is not enough, return forbidden
		if($payment_data->product_contingent < $webmo_contingent_cost) return self::response(403);


		// checking for keyword
		$keyword = preg_match('/^([a-zA-Z0-9]{1,10})([ ].*|)$/', $mand['message'], $match) ? $match[1] : '';

		// load smsgate
		$res = nexus_service::get_smsgate([
			'number'				=> $mand['shortcode'],
			'keyword'				=> $keyword,
			'operatorID'			=> $payment_data->operatorID,
			'ignore_archive'		=> true,
			'fallback_keyword'		=> '',
			'fallback_operatorID'	=> 0,
			]);

		// if still no route found, return precondition failed
		if($res->status == 404) return self::response(412);

		// on other error
		if($res->status != 200) return self::response(500, 'Loading SMS-Gate for ShortNumber '.h::encode_php($mand['shortcode']).' and Keyword '.h::encode_php($keyword).' failed with: '.$res->status);

		// take smsgate
		$smsgate = $res->data;

		// define how much to decrement
		$decrement_contingent = $webmo_contingent_cost;

		// decrement contigent
		foreach(['otp','abo'] as $type){

			// for each entry
			foreach($payment_data->contingent_list[$type] as $paymentID){

				// for otp products
				if($type == 'otp'){

					// decrement
					$res = otp::update_contingent([
						'otpID'		=> $paymentID,
						'up_to'		=> '-'.$decrement_contingent,
						]);
					}

				// for abo products
				elseif($type == 'abo'){

					// decrement
					$res = abo::update_charge_contingent([
						'chargeID'	=> $paymentID,
						'up_to'		=> '-'.$decrement_contingent,
						]);
					}

				// on error
				if(!in_array($res->status, [204, 403, 406])) return self::response(570, $res);

				// on success
				if($res->status == 204){

					// take missing as new contingent to decrement
					$decrement_contingent = $res->data->missing;
					}

				// if nothing left to decrement, skip further processing of paymentID
				if($decrement_contingent <= 0) break 2;
				}
			}

		// if decremention was not successful (but there was enough before)
		if($decrement_contingent > 0){

			// log error
			e::logtrigger('DEBUG: WebMO decremention failed, '.$decrement_contingent.' contingent left for decremention. WebMO allowed nevertheless. ('.h::encode_php($mand + $opt).')');
			}


		// define createTime for webmo
		$webmoTime = h::dtstr($_SERVER['REQUEST_TIME']);


		// connect mobile (associating persistID and IMSI to mobile)
		$res = smsgate_service::mo_connect_mobile([
			'smsgateID'		=> $smsgate->smsgateID,
			'msisdn'		=> $payment_data->msisdn,
			'operatorID'	=> $payment_data->operatorID,
			'processTime'	=> $webmoTime,
			'persistID'		=> $mand['persistID'],
			'imsi'			=> $opt['imsi'] ?? null,
			'message'		=> $mand['message'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take mobile
		$mobile = $res->data;


		// create webmo
		$res = webmo::create_webmo([
			'mobileID'		=> $payment_data->mobileID,
			'message'		=> $mand['message'],
			'apkID'			=> $payment_data->apkID,
			'operatorID'	=> $payment_data->operatorID,
			'smsgateID'		=> $smsgate->smsgateID,
			'status'		=> null,
			'createTime'	=> $webmoTime,
			'persistID'		=> $mand['persistID'],
			]);

		// on error
		if($res->status != 201) return self::response(570, $res);

		// take webmoID
		$webmoID = $res->data->webmoID;


		// finally process message
		$res = smsgate_service::mo_forward_message([
			'smsgateID'		=> $smsgate->smsgateID,
			'persistID'		=> $mand['persistID'],
			'msisdn'		=> $payment_data->msisdn,
			'operatorID'	=> $payment_data->operatorID,
			'message'		=> $mand['message'],
			'processTime'	=> $webmoTime,
			]);

		// update webmo (without checking result)
		$res_upd = webmo::update_webmo([
			'webmoID'		=> $webmoID,
			'status'		=> $res->status,
			]);

		// on error
		if($res->status != 204) return $res;

		// define result
		$result = (object)[
			'persistID'				=> $mand['persistID'],
			'product_access'		=> $payment_data->product_access,
			'product_contingent'	=> $decrement_contingent ? 0 : $payment_data->product_contingent - $webmo_contingent_cost,
			'msisdn'				=> $payment_data->msisdn,
			'imsi'					=> $payment_data->imsi,
			'operatorID'			=> $payment_data->operatorID,
			'countryID'				=> $payment_data->countryID,
			'blacklisted'			=> $payment_data->blacklisted,
			'smspay_possible'		=> ($payment_data->mp_status and $payment_data->mp_status >= 400) ? false : true,
			'webmo_payment_possible'=> (!$payment_data->mobileID or !$payment_data->msisdn or !$payment_data->operatorID) ? false : true,
			];

		// return result
		return self::response(200, $result);
		}


	/* special error */
	public static function trigger_mo_error($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($patched_req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'persistID'		=> '~1,18446744073709551615/i',
			], $error);

		// optional
		//$opt = h::eX($req, [
		//	'android_error'	=> '~0,255/i',
		//	'cms_error'		=> '~-1,9999/i',
		//	], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load mobile with given value
		$res = mobile::get_mobile([
			'persistID'	=> $mand['persistID'],
			]);

		// if mobile not found, failed dependency
		if($res->status == 404) return self::response(424);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take entry
		$mobile = $res->data;

		// update mobile
		$res = mobile::update_mobile([
			'mobileID'		=> $mobile->mobileID,
			'mp_status'		=> 402,
			'mp_statusTime'	=> $_SERVER['REQUEST_TIME'],
			]);

		// on error
		if($res->status != 204) return self::response(570, $res);

		// return success
		return self::response(204);
		}

	}