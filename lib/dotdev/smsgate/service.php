<?php
/*****
 * Version 1.0.2018-09-10
**/
namespace dotdev\smsgate;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\nexus\service as nexus_service;
use \dotdev\persist;
use \dotdev\mobile;
use \dotdev\mobile\abo;
use \dotdev\mobile\otp;
use \dotdev\mobile\sms;
use \dotdev\mobile\smspay;
use \dotdev\traffic\session as traffic_session;

class service {
	use \tools\libcom_trait;


	/* service */
	public static function service_smsgate_mo($req = []){

		// mandatory
		$mand = h::eX($req, [
			'smsgateID'		=> '~1,65535/i',
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'operatorID'	=> '~0,65535/i',
			'message'		=> '~/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'fallback_persistID' => '~1,18446744073709551615/i',
			'processTime'	=> '~Y-m-d H:i:s/d',
			'receiveTime'	=> '~Y-m-d H:i:s/d', // DEPRECATED
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert msisdn
		$mand['msisdn'] = $mand['msisdn'][0];

		// DEPRECATED
		if(!isset($opt['processTime']) and isset($opt['receiveTime'])) $opt['processTime'] = $opt['receiveTime'];

		// define defaults
		$opt += [
			'processTime'	=> h::dtstr('now'),
			];


		// load smsgate
		$res = nexus_service::get_smsgate([
			'smsgateID'		=> $mand['smsgateID'],
			]);

		// on error
		if($res->status != 200) return self::response(500, 'Loading smsgate failed with: '.$res->status.' (smsgateID '.$mand['smsgateID'].')');

		// take smsgate
		$smsgate = $res->data;


		// connect mobile
		$res = self::mo_connect_mobile([
			'smsgateID'		=> $smsgate->smsgateID,
			'msisdn'		=> $mand['msisdn'],
			'operatorID'	=> $mand['operatorID'],
			'processTime'	=> $opt['processTime'],
			'message'		=> $mand['message'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take mobile
		$mobile = $res->data;


		// set result
		$result = (object)[
			// take persistID from connected mobile entry, fallback to last found persistID in API, or 0
			'persistID' 	=> $mobile->persistID ?? $opt['fallback_persistID'] ?? 0,
			'is_stop_mo'	=> false,
			'mo_count'		=> 0,
			];



		// split message in words
		$words = explode(" ", $mand['message']);

		// if there are at least two words and first word is keyword, remove it from word list
		if($smsgate->keyword and count($words) > 1 and $smsgate->keyword == strtoupper($words[0])) array_shift($words);

		// check if first word is STOP keyword
		$result->is_stop_mo = h::is($words[0], '~^(?i)stop(?:p|pen|)(?:\!+|)(?:[ ].*|)$') ? true : false;

		// correct STOP messages to correct spelling
		if($result->is_stop_mo) $mand['message'] = 'STOP';



		// if payment is defined
		if(in_array($smsgate->type, ['smspay', 'smsabo', 'otp']) and $smsgate->productID){

			// define param
			$param = [
				'smsgateID'		=> $smsgate->smsgateID,
				'mobileID'		=> $mobile->mobileID,
				'operatorID'	=> $mobile->operatorID,
				'is_stop_mo'	=> $result->is_stop_mo,
				'persistID'		=> $result->persistID ?: null,
				'processTime'	=> $opt['processTime'],
				];

			// call specific payment helper function
			if($smsgate->type == 'smspay') $res = self::mo_payment_smspay($param);
			elseif($smsgate->type == 'smsabo') $res = self::mo_payment_smsabo($param);
			else $res = self::mo_payment_otp($param);

			// on error
			if($res->status != 200) return $res;

			// for each new data key
			foreach($res->data as $key => $val){

				// take/overwrite data
				$result->{$key} = $val;
				}
			}


		// if this is a special longnumber route for STOP messages
		if($result->is_stop_mo and !$smsgate->type and h::gX($smsgate->param, 'stop_route:number')){

			// call specific payment helper function
			$res = self::mo_longnumber_stop([
				'number'		=> h::gX($smsgate->param, 'stop_route:number'),
				'keyword'		=> h::gX($smsgate->param, 'stop_route:keyword') ?: '',
				'mobileID'		=> $mobile->mobileID,
				'operatorID'	=> $mobile->operatorID,
				'persistID'		=> $result->persistID ?: null,
				'processTime'	=> $opt['processTime'],
				]);

			// on error
			if($res->status != 204) return $res;
			}


		// finally process message
		$res = self::mo_forward_message([
			'smsgateID'		=> $smsgate->smsgateID,
			'persistID'		=> $result->persistID,
			'msisdn'		=> $mobile->msisdn,
			'operatorID'	=> $mobile->operatorID,
			'message'		=> $mand['message'],
			'processTime'	=> $opt['processTime'],
			'is_stop_mo'	=> $result->is_stop_mo,
			]);

		// on error
		if($res->status != 204) return $res;


		// return success (for every other process)
		return self::response(200, $result);
		}

	public static function service_smsgate_mt($req = []){

		// mandatory
		$mand = h::eX($req, [
			'recipient'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,20})$',
			'sender'		=> '~^(?:\+|00|)([0-9]{3,15})$',
			'keyword'		=> '~^(?:|\*|[A-Z0-9]{1,32})$',
			'message'		=> '~/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'processTime'	=> '~Y-m-d H:i:s/d',
			'receiveTime'	=> '~Y-m-d H:i:s/d', // DEPRECATED
			'extra'			=> '~/c',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// DEPRECATED
		if(!isset($opt['processTime']) and isset($opt['receiveTime'])) $opt['processTime'] = $opt['receiveTime'];

		// convert values
		$mand['recipient'] = $mand['recipient'][0];
		$mand['sender'] = $mand['sender'][0];
		if($mand['keyword'] == '*') $mand['keyword'] = '';
		if(isset($opt['extra']) and is_object($opt['extra'])) $opt['extra'] = (array) $opt['extra'];

		// define defaults
		$opt += [
			'processTime'	=> h::dtstr('now'),
			];

		// get real msisdn (cut extended version)
		$msisdn = (strlen($mand['recipient']) > 15) ? substr($mand['recipient'], 0, -6) : $mand['recipient'];
		$msisdn_extension = (strlen($mand['recipient']) > 15) ? substr($mand['recipient'], -6) : null;

		// load mobile
		$res = mobile::get_mobile([
			'msisdn'	=> $msisdn,
			]);

		// on error
		if($res->status != 200){

			// log error
			e::logtrigger('DEBUG: mobile user with msisdn '.$msisdn.' could not be loaded for proceed_smsgate_mt: '.$res->status);

			// return success with special info
			return self::response(204);
			}

		// take mobile
		$mobile = $res->data;


		// load smsgate
		$res = nexus_service::get_smsgate([
			'number' 				=> $mand['sender'],
			'keyword'				=> $mand['keyword'],
			'operatorID'			=> $mobile->operatorID,
			'ignore_archive'		=> true,
			'fallback_keyword'		=> '',
			'fallback_operatorID'	=> 0,
			]);

		// on error
		if($res->status != 200){

			// return error
			return self::response(500, 'Loading SMS-Gate with MSISDN '.h::encode_php($mand['sender']).' and keyword '.h::encode_php($mand['keyword']).' failed with: '.$res->status);
			}

		// take smsgate
		$smsgate = $res->data;

		// check serviceID
		if(!$smsgate->serviceID){

			// log error
			e::logtrigger('DEBUG: Processing MT for SMS-Gate '.$smsgate->smsgateID.' has no serviceID: '.$smsgate->serviceID);

			// return success with special info
			return self::response(204);
			}

		// load service
		$res = nexus_service::get_service([
			'serviceID'	=> $smsgate->serviceID,
			]);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// take service
		$service = $res->data;

		// define persistID
		$persistID = 0;

		// for smspay products
		if($smsgate->type == 'smspay' and $smsgate->productID){

			// load last smspay for this productID and mobileID
			$res = smspay::get_smspay([
				'mobileID'	=> $mobile->mobileID,
				'productID'	=> $smsgate->productID,
				'last_only'	=> true,
				'no_stat'	=> true,
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// only if call is successful
			if($res->status == 200){

				// take persistID
				$persistID = $res->data->persistID;
				}
			}

		// if no persistID given, check if we can load previous persistID from service
		if($persistID == 0 and is_callable($service->ns.'::get_mo_last_persistID')){

			// call service fn
			$res = call_user_func($service->ns.'::get_mo_last_persistID', [
				'msisdn'	=> $mobile->msisdn,
				'smsgateID'	=> $smsgate->smsgateID,
				]);

			// only if call is successful
			if($res->status == 200){

				// take persistID
				$persistID = $res->data->persistID;
				}
			}


		// get function for mt preprocessing
		$preprocessing_fn = h::gX($smsgate->param, 'mt_preprocessing_fn');

		// if function for mt preprocessing is defined
		if($preprocessing_fn){

			// check if function is callable
			if(is_callable($preprocessing_fn)){

				// Call function
				$res = call_user_func($preprocessing_fn, [
					'smsgateID'		=> $smsgate->smsgateID,
					'persistID'		=> $persistID,
					'mobileID'		=> $mobile->mobileID,
					'operatorID'	=> $mobile->operatorID,
					'recipient'		=> $mand['recipient'], // forward recipient to allow process extended msisdn, if given
					'sender'		=> $mand['sender'],
					'message'		=> $mand['message'],
					'processTime'	=> $opt['processTime'],
					'receiveTime'	=> $opt['processTime'], // DEPRECATED
					] + ($opt['extra'] ?? []));

				// if function does not responses with positiv statuscode
				if(!in_array($res->status, [200,201,204])){

					// log it
					e::logtrigger('Call mt_preprocessing_fn '.h::encode_php($preprocessing_fn).' for smsgateID '.$smsgate->smsgateID.' results in unexpected status '.$res->status.' (mobileID '.$mobile->mobileID.', persistID '.$persistID.')');
					}

				// check if there is an overwrite definition
				if(isset($res->data) and h::cX($res->data, 'overwrite:message')){

					// overwrite message
					$mand['message'] = h::gX($res->data, 'overwrite:message');
					}
				}

			// if not
			else{

				// log it
				e::logtrigger('Cannot call mt_preprocessing_fn '.h::encode_php($preprocessing_fn).' in smsgateID '.$smsgate->smsgateID);
				}
			}


		// define skip situation for MT (none, all or only for extended MSISDN)
		$option = h::gX($smsgate->param, 'skip_sending_smsgate_mt');
		$skip_sending_mt = ($option and ($option != 'extended_msisdn_only' or $msisdn_extension));


		// define default send_sms service function
		$service_fn = $service->ns.'::send_sms';

		// define extra param for service function
		$extra_param = [];


		// if smsgate is set to SMSPay product
		if($smsgate->type == 'smspay'){

			// load product
			$res = nexus_service::get_product([
				'type'		=> $smsgate->type,
				'productID'	=> $smsgate->productID,
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// take product
			$product = $res->data;

			// define send type
			$billing_type = in_array($mobile->operatorID, h::gX($product->param, 'mt_billing_operator') ?: []) ? 'mt' : 'mo';

			// for MT Billing
			if($billing_type == 'mt'){

				// define send_smspay_mt service function
				$service_fn = $service->ns.'::send_smspay_mt';

				// if sending of MT should be skipped
				if($skip_sending_mt){

					// ignore MT skipping, but define for API to skip free MT
					$extra_param['skip_free_mt'] = true;
					$skip_sending_mt = false;
					}
				}
			}

		// if sending of MT should be skipped
		if($skip_sending_mt){

			// return success
			return self::response(204);
			}

		// check if mt should have a text suffix
		$mt_suffix = h::gX($smsgate->param, 'mt_suffix');

		// if suffix is defined
		if($mt_suffix){

			// replace or append suffix in sms text
			$mand['message'] = substr($mand['message'], 0, 160 - strlen($mt_suffix)).$mt_suffix;
			}

		// check if we can send sms with service
		if(!is_callable($service_fn)){

			// if not, abort here with error
			return self::response(500, 'SMS-Gate '.$smsgate->smsgateID.' cannot send MT, because service function is not implemented: '.$service_fn);
			}

		// forward sms
		$res = call_user_func($service_fn, [
			'msisdn'		=> $mobile->msisdn,
			'text'			=> $mand['message'],
			'serviceID'		=> $smsgate->serviceID,
			'operatorID'	=> $mobile->operatorID,
			'persistID'		=> $persistID,
			'smsgateID'		=> $smsgate->smsgateID,
			'mobileID'		=> $mobile->mobileID,
			'processTime'	=> $opt['processTime'] ?? null,
			] + $extra_param);

		// on error
		if(!in_array($res->status, [204, 402, 403])){
			return self::response(500, 'Forwarding mt-sms failed with: '.$res->status);
			}

		// return success
		return self::response(204);
		}


	/* service helper for service_smsgate_mo */
	public static function mo_connect_mobile($req = []){

		// mandatory
		$mand = h::eX($req, [
			'smsgateID'			=> '~1,65535/i',
			'msisdn'			=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'operatorID'		=> '~0,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'processTime'		=> '~Y-m-d H:i:s/d',
			'persistID'			=> '~1,18446744073709551615/i',
			'imsi'				=> '~^[1-7]{1}[0-9]{5,15}$',
			'message'			=> '~/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert msisdn
		$mand['msisdn'] = $mand['msisdn'][0];

		// define defaults
		$opt += [
			'processTime'		=> h::dtstr('now'),
			];


		// load smsgate
		$res = nexus_service::get_smsgate([
			'smsgateID'	=> $mand['smsgateID'],
			]);

		// on error
		if($res->status != 200) return self::response(500, 'Loading smsgate failed with: '.$res->status.' (smsgateID '.$mand['smsgateID'].')');

		// take smsgate
		$smsgate = $res->data;


		// define regex for special message codes "KEYWORD #l189728234i262026045457401p1482:Hello messagetext"
		$persistID_check = '/^'.($smsgate->keyword ? '(?:(?i)'.$smsgate->keyword.' )' : '').'(?:#[a-km-z0-9]*l([1-9]{1}[0-9]{0,19})[a-km-z0-9]*\:).*$/';
		$imsi_check = '/^'.($smsgate->keyword ? '(?:(?i)'.$smsgate->keyword.' )' : '').'(?:#[a-hj-z0-9]*i([2-7]{1}[0-9]{5,15})[a-hj-z0-9]*\:).*$/';

		// if no persistID defined, but message is given, check message for persistID
		if(isset($opt['message']) and !isset($opt['persistID']) and preg_match($persistID_check, $opt['message'], $match)){

			// take persistID
			$opt['persistID'] = $match[1];
			}

		// if no imsi defined, but message is given, check message for imsi
		if(isset($opt['message']) and !isset($opt['imsi']) and preg_match($imsi_check, $opt['message'], $match)){

			// take imsi
			$opt['imsi'] = $match[1];
			}


		// define some reused values
		$imsi_mobile = null;
		$persist_mobile = null;
		$session_created = false;


		// if IMSI is given
		if(isset($opt['imsi'])){

			// load mobile
			$res = mobile::get_mobile([
				'imsi'		=> $opt['imsi'],
				]);

			// on unexpected error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// take mobile
			$imsi_mobile = ($res->status == 200) ? $res->data : null;

			// if imsi_mobile has no MSISDN
			if($imsi_mobile and !$imsi_mobile->msisdn){

				// update mobile (migration)
				$res = mobile::update_mobile([
					'mobileID'	=> $imsi_mobile->mobileID,
					'msisdn'	=> $mand['msisdn'],
					]);

				// on conflict
				if($res->status == 409){

					// log error for deeper analysis
					e::logtrigger('DEBUG: Migrating mobileID '.$imsi_mobile->mobileID.' failed with: '.$res->status.' (persistID '.($opt['persistID'] ?? 'null').')');
					}

				// on unexpected error
				if(!in_array($res->status, [204, 409])) return self::response(570, $res);
				}

			// if imsi_mobile has MSISDN, but it is different from given (means IMSI is not trustable)
			if($imsi_mobile and $imsi_mobile->msisdn and $imsi_mobile->msisdn != $mand['msisdn']){

				// log error for deeper analysis
				e::logtrigger('DEBUG: Cannot migrate IMSI '.$opt['imsi'].' to MSISDN '.$mand['msisdn'].', because it already has MSISDN '.$imsi_mobile->msisdn.' (persistID '.($opt['persistID'] ?? 'null').')');

				// unset IMSI to prevent further processing of it
				unset($opt['imsi']);

				// unset imsi_mobile (as it is untrusted)
				$imsi_mobile = null;
				}
			}


		// if persistID is given
		if(isset($opt['persistID'])){

			// load mobile
			$res = mobile::get_mobile([
				'persistID'	=> $opt['persistID'],
				]);

			// on unexpected error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// take mobile
			$persist_mobile = ($res->status == 200) ? $res->data : null;

			// if persist_mobile has no MSISDN
			if($persist_mobile and !$persist_mobile->msisdn){

				// update mobile (migration)
				$res = mobile::update_mobile([
					'mobileID'	=> $persist_mobile->mobileID,
					'msisdn'	=> $mand['msisdn'],
					]);

				// on conflict
				if($res->status == 409){

					// log error for deeper analysis
					e::logtrigger('DEBUG: Migrating mobileID '.$persist_mobile->mobileID.' failed with: '.$res->status.' (persistID '.$opt['persistID'].')');
					}

				// on unexpected error
				if(!in_array($res->status, [204, 409])) return self::response(570, $res);
				}

			// if persist_mobile has MSISDN, but it is different from given (means persistID is not trustable)
			if($persist_mobile and $persist_mobile->msisdn and $persist_mobile->msisdn != $mand['msisdn']){

				// create new persistID
				$res = persist::create([
					'createTime'	=> $opt['processTime'],
					]);

				// on error
				if($res->status != 200) return self::response(570, $res);

				// save of persistID and replace with new persistID
				$opt['replaced_persistID'] = $opt['persistID'];
				$opt['persistID'] = $res->data->persistID;

				// load source session
				$res = traffic_session::get_session([
					'persistID'		=> $opt['replaced_persistID'],
					]);

				// on unexpected error
				if(!in_array($res->status, [200, 404])) return self::response(570, $res);

				// define source session
				$source_session = ($res->status == 200) ? $res->data : null;

				// if pageID is defined, create session
				if($source_session or h::cX($smsgate->param, 'session:fallback_pageID', '~1,65535/i')){

					// create new session
					$res = traffic_session::create_session([
						'persistID'		=> $opt['persistID'],
						'createTime'	=> $opt['processTime'],
						'domainID'		=> $source_session ? $source_session->domainID : null,
						'pageID'		=> $source_session ? $source_session->pageID : h::gX($smsgate->param, 'session:fallback_pageID'),
						'publisherID'	=> $source_session ? ($source_session->publisherID ?: null) : null,
						]);

					// on error
					if($res->status != 201) return self::response(570, $res);

					// define session was created
					$session_created = true;
					}

				// DEBUG for deeper analysis
				e::logtrigger('DEBUG: Persistlink for persistID '.$opt['replaced_persistID'].' has different MSISDN '.$persist_mobile->msisdn.'. New persistID '.$opt['persistID'].' created.'.($session_created ? ($source_session ? ' Existing session copied.' : ' New session created.') : 'No (fallback) pageID for session defined.'));

				// unset persistlink (as it is untrusted)
				$persist_mobile = null;
				}
			}


		// finally load mobile with MSISDN (existing IMSI mobiles are already migrated to MSISDN)
		$res = mobile::get_mobile([
			'msisdn'	=> $mand['msisdn'],
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// take mobile
		$mobile = ($res->status == 200) ? $res->data : null;


		// define persistlink creation
		$persistlink_creation = (isset($opt['persistID']) and !$persist_mobile);


		// if mobile not found
		if(!$mobile){

			// create mobile
			$res = mobile::create_mobile([
				'msisdn' 		=> $mand['msisdn'],
				'operatorID'	=> $mand['operatorID'] ?: null,
				'confirmTime'	=> $opt['processTime'],
				'imsi'			=> $opt['imsi'] ?? null,
				'persistID'		=> $persistlink_creation ? $opt['persistID'] : null,
				]);

			// on unexpected error
			if(!in_array($res->status, [201, 409])) return self::response(500, 'Creating mobile entry with MSISDN '.$mand['msisdn'].' failed with: '.$res->status.' (opt = '.h::encode_php($opt).')');

			// reload mobile
			$res = mobile::get_mobile([
				'mobileID'	=> $res->data->mobileID ?? null, // exists if 201
				'msisdn'	=> $mand['msisdn'], // fallback for 409
				]);

			// on error
			if($res->status != 200) return self::response(500, 'Loading MSISDN '.$mand['msisdn'].' after creation failed with: '.$res->status.' (opt = '.h::encode_php($opt).')');

			// take mobile
			$mobile = $res->data;
			}

		// if mobile data should be updated
		elseif(!$mobile->confirmed or ($mand['operatorID'] and $mobile->operatorID != $mand['operatorID']) or (isset($opt['imsi']) and $mobile->imsi != $opt['imsi']) or $persistlink_creation){

			// update mobile
			$res = mobile::update_mobile([
				'mobileID'		=> $mobile->mobileID,
				'operatorID'	=> $mand['operatorID'] ?: null,
				'confirmTime'	=> !$mobile->confirmed ? $opt['processTime'] : null,
				'imsi'			=> $opt['imsi'] ?? null,
				'persistID'		=> $persistlink_creation ? $opt['persistID'] : null,
				]);

			// on conflict
			if($res->status == 409){

				// log error for deeper analysis
				e::logtrigger('DEBUG: Updating mobileID '.$mobile->mobileID.' failed with: '.$res->status.' (MSISDN '.$mand['msisdn'].', opt = '.h::encode_php($opt).')');
				}

			// on unexpected error
			if(!in_array($res->status, [204, 409])) return self::response(500, 'Updating mobileID '.$mobile->mobileID.' failed with: '.$res->status.' (MSISDN '.$mand['msisdn'].', opt = '.h::encode_php($opt).')');

			// reloading mobile
			$res = mobile::get_mobile([
				'mobileID'	=> $mobile->mobileID,
				]);

			// on error
			if($res->status != 200) return self::response(500, 'Reloading mobileID '.$mobile->mobileID.' after updating failed with: '.$res->status.' (opt = '.h::encode_php($opt).')');

			// define mobile
			$mobile = $res->data;
			}


		// if persistlink was created
		if($persistlink_creation){

			// add job for delayed session update
			$res = traffic_session::delayed_update_session([
				'persistID'		=> $opt['persistID'],
				'mobileID'		=> $mobile->mobileID,
				'operatorID'	=> $mobile->operatorID ?: null,
				'countryID'		=> $mobile->countryID ?: null,
				]);


			// on error
			if($res->status != 204) return self::response(570, $res);
			}

		// append persistID
		$mobile->persistID = $opt['persistID'] ?? 0;

		// return mobile
		return self::response(200, $mobile);
		}

	public static function mo_payment_smspay($req = []){

		// mandatory
		$mand = h::eX($req, [
			'smsgateID'		=> '~1,65535/i',
			'mobileID'		=> '~1,4294967295/i',
			'operatorID'	=> '~0,65535/i',
			'is_stop_mo'	=> '~/b',
			], $error);

		// optional
		$opt = h::eX($req, [
			'persistID'		=> '~1,18446744073709551615/i',
			'processTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// define defaults
		$opt += [
			'processTime'	=> h::dtstr('now'),
			];


		// load smsgate
		$res = nexus_service::get_smsgate([
			'smsgateID'	=> $mand['smsgateID'],
			]);

		// on error
		if($res->status != 200) return self::response(500, 'Loading smsgate failed with: '.$res->status.' (smsgateID '.$mand['smsgateID'].')');

		// take smsgate
		$smsgate = $res->data;


		// define result
		$result = (object)[
			'persistID'	=> $opt['persistID'] ?? 0,
			'mo_count'	=> 0,
			];


		// load smspay or last smspay for this productID and mobileID
		$res = smspay::get_smspay([
			'mobileID'	=> $mand['mobileID'],
			'productID'	=> $smsgate->productID,
			'persistID'	=> $result->persistID ?: null,
			'last_only'	=> $result->persistID ? null : true,
			]);

		// on error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if we found an entry
		if($res->status == 200){

			// take persistID from smspay, if given (persistID could still be 0)
			if(!$result->persistID and $res->data->persistID) $result->persistID = $res->data->persistID;

			// take mo count
			$result->mo_count = $res->data->mo;
			}

		// else if we have not found a smspay and have no persistID
		elseif(!$result->persistID){

			// create one
			$res = persist::create();

			// on error
			if($res->status != 200) return self::response(570, $res);

			// and take it
			$result->persistID = $res->data->persistID;

			// if fallback pageID is defined
			if(h::cX($smsgate->param, 'session:fallback_pageID', '~1,65535/i')){

				// load mobile
				$res = mobile::get_mobile([
					'mobileID'		=> $mand['mobileID'],
					]);

				// on error
				if($res->status != 200) return self::response(570, $res);

				// take mobile
				$mobile = $res->data;

				// create new session
				$res = traffic_session::create_session([
					'persistID'		=> $result->persistID,
					'createTime'	=> $opt['processTime'],
					'pageID'		=> h::gX($smsgate->param, 'session:fallback_pageID'),
					'mobileID'		=> $mobile->mobileID,
					'operatorID'	=> $mobile->operatorID,
					]);

				// on error
				if($res->status != 201) return self::response(570, $res);
				}

			// add persistlink (because persistID is new)
			$res = mobile::update_mobile([
				'mobileID'		=> $mand['mobileID'],
				'persistID'		=> $result->persistID,
				]);

			// on error
			if($res->status != 204){
				return self::response(500, 'Adding persistlink for persistID '.$result->persistID.' and mobileID '.$mand['mobileID'].' failed with: '.$res->status.' (mobileID '.$mand['mobileID'].', smsgateID '.$mand['smsgateID'].')');
				}
			}

		// now trigger smspay
		$res = smspay::create_mo([
			'mobileID'		=> $mand['mobileID'],
			'productID'		=> $smsgate->productID,
			'persistID'		=> $result->persistID,
			'createTime'	=> $opt['processTime'],
			]);

		// on error
		if($res->status != 201){
			return self::response(500, 'Creating smspay_mo with persistID '.h::encode_php($result->persistID).' failed with: '.$res->status.' (mobileID '.$mand['mobileID'].', productID '.$smsgate->productID.', smsgateID '.$smsgate->smsgateID.')');
			}

		// append smspayID, moID and mo_count to result
		$result->smspayID = $res->data->smspayID;
		$result->moID = $res->data->moID;
		$result->mo_count++;

		// if this is a STOP MO
		if($mand['is_stop_mo']){

			// trigger stop
			$res = smspay::stop_smspay([
				'mobileID'		=> $mand['mobileID'],
				'productID'		=> $smsgate->productID,
				'createTime'	=> $opt['processTime'],
				]);

			// on error
			if($res->status != 204){
				return self::response(500, 'Stopping smspay failed with: '.$res->status.' (mobileID '.$mand['mobileID'].', productID '.$smsgate->productID.', smsgateID '.$smsgate->smsgateID.')');
				}
			}

		// if this is the first mo (but not STOP)
		if(!$mand['is_stop_mo'] and $result->mo_count == 1){

			// check for configured welcome mt
			$welcome_mt = h::gX($smsgate->param, 'welcome_mt:'.$mand['operatorID']) ?: h::gX($smsgate->param, 'welcome_mt:default');

			// check if welcome rule matches
			if($welcome_mt){

				// send welcome mt (do not process result, errors are already logged)
				$res = self::send_sms([
					'mobileID'	=> $mand['mobileID'],
					'operatorID'=> $mand['operatorID'],
					'text'		=> $welcome_mt,
					'serviceID'	=> $smsgate->serviceID,
					'smsgateID'	=> $smsgate->smsgateID,
					'persistID'	=> $result->persistID,
					]);
				}
			}

		// if this is at least the second mo (but not STOP)
		if(!$mand['is_stop_mo'] and $result->mo_count > 1){

			// define (operator specific) rule key
			$reminder_mt_rule = h::cX($smsgate->param, 'reminder_mt:'.$mand['operatorID'].':text') ? 'reminder_mt:'.$mand['operatorID'] : 'reminder_mt:default';
			$reminder_mt = [
				'text'		=> h::gX($smsgate->param, $reminder_mt_rule.':text'),
				'start'		=> h::gX($smsgate->param, $reminder_mt_rule.':start'),
				'interval'	=> h::gX($smsgate->param, $reminder_mt_rule.':interval'),
				];

			// check if reminder rule matches
			if($reminder_mt['text'] and ($result->mo_count == $reminder_mt['start'] or ($reminder_mt['interval'] and $result->mo_count > $reminder_mt['start'] and ($result->mo_count - $reminder_mt['start']) % $reminder_mt['interval'] == 0))){

				// send reminder mt (do not process result, errors are already logged)
				$res = self::send_sms([
					'mobileID'	=> $mand['mobileID'],
					'operatorID'=> $mand['operatorID'],
					'text'		=> h::replace_in_str($reminder_mt['text'], [
						'{mo_count}'	=> $result->mo_count,
						]),
					'serviceID'	=> $smsgate->serviceID,
					'smsgateID'	=> $smsgate->smsgateID,
					'persistID'	=> $result->persistID,
					]);
				}
			}

		// return result
		return self::response(200, $result);
		}

	public static function mo_payment_smsabo($req = []){

		// mandatory
		$mand = h::eX($req, [
			'smsgateID'		=> '~1,65535/i',
			'mobileID'		=> '~1,4294967295/i',
			'operatorID'	=> '~0,65535/i',
			'productID'		=> '~1,65535/i',
			'is_stop_mo'	=> '~/b',
			], $error);

		// optional
		$opt = h::eX($req, [
			'persistID'		=> '~1,18446744073709551615/i',
			'processTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// define defaults
		$opt += [
			'processTime'	=> h::dtstr('now'),
			];


		// load smsgate
		$res = nexus_service::get_smsgate([
			'smsgateID'	=> $mand['smsgateID'],
			]);

		// on error
		if($res->status != 200) return self::response(500, 'Loading smsgate failed with: '.$res->status.' (smsgateID '.$mand['smsgateID'].')');

		// take smsgate
		$smsgate = $res->data;


		// define result
		$result = (object)[
			'persistID'	=> $opt['persistID'] ?? 0,
			];


		// load list of abos for this productID
		$res = abo::get([
			'mobileID'		=> $mand['mobileID'],
			'productID'		=> $smsgate->productID,
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take list
		$abo_list = $res->data;

		// if this is a STOP MO
		if($mand['is_stop_mo']){

			// terminate all abos on this productID
			foreach($abo_list as $abo){

				// for each confirmed and not terminated abo (it should never be more than one)
				if($abo->confirmed and !$abo->terminated){

					// append persistID for comlog
					$result->persistID = $abo->persistID;

					// terminate it
					$res = abo::terminate([
						'aboID'	=> $abo->aboID,
						]);

					// on unexpected error
					if(!in_array($res->status, [204,409])) return self::response(570, $res);
					}
				}

			// return success
			return self::response(200, $result);
			}

		// from here, we are in the abo creation process, so check each abo
		foreach($abo_list as $abo){

			// if a confirmed and not terminated abo is found
			if($abo->confirmed and !$abo->terminated){

				// log this for debugging
				e::logtrigger('DEBUG: Cannot create new abo, because a previously ('.$abo->confirmTime.') created aboID '.$abo->aboID.' is not terminated. (mobileID '.$mand['mobileID'].', productID '.$smsgate->productID.', smsgateID '.$smsgate->smsgateID.')');

				// append persistID for comlog
				$result->persistID = $abo->persistID;

				// return success
				return self::response(200, $result);
				}
			}

		// if there is no persistID
		if(!$result->persistID){

			// create one
			$res = persist::create();

			// on error
			if($res->status != 200) return self::response(570, $res);

			// and take it
			$result->persistID = $res->data->persistID;

			// if fallback pageID is defined
			if(h::cX($smsgate->param, 'session:fallback_pageID', '~1,65535/i')){

				// create new session
				$res = traffic_session::create_session([
					'persistID'		=> $result->persistID,
					'createTime'	=> $opt['processTime'],
					'pageID'		=> h::gX($smsgate->param, 'session:fallback_pageID'),
					]);

				// on error
				if($res->status != 201) return self::response(570, $res);
				}

			// add persistlink (because persistID is new)
			$res = mobile::update_mobile([
				'mobileID'		=> $mand['mobileID'],
				'persistID'		=> $result->persistID,
				]);

			// on error
			if($res->status != 204){
				return self::response(500, 'Adding persistlink for persistID '.$result->persistID.' and mobileID '.$mand['mobileID'].' failed with: '.$res->status.' (mobileID '.$mand['mobileID'].',  productID '.$smsgate->productID.', smsgateID '.$smsgate->smsgateID.')');
				}
			}

		// now create confirmed abo
		$res = abo::create([
			'mobileID'		=> $mand['mobileID'],
			'productID'		=> $smsgate->productID,
			'persistID'		=> $result->persistID,
			'confirmTime'	=> $opt['processTime'],
			]);

		// on error
		if($res->status != 201) return self::response(570, $res);

		// append aboID to result
		$result->aboID = $res->data->aboID;


		// load product
		$res = nexus_service::get_product([
			'type'		=> 'abo',
			'productID'	=> $smsgate->productID,
			]);

		// on error
		if($res->status !== 200) return self::response(570, $res);

		// take product
		$product = $res->data;


		// check for configured welcome mt (in smsgate and product)
		$welcome_mt = h::gX($smsgate->param, 'welcome_mt:'.$mand['operatorID'])
			?: h::gX($smsgate->param, 'welcome_mt:default')
			?: h::gX($product->param, 'welcome_mt:'.$mand['operatorID'])
			?: h::gX($product->param, 'welcome_mt:default');

		// if we have one
		if($welcome_mt){

			// send welcome mt
			$res = self::send_sms([
				'mobileID'	=> $mand['mobileID'],
				'operatorID'=> $mand['operatorID'],
				'text'		=> $welcome_mt,
				'serviceID'	=> $product->serviceID,
				'smsgateID'	=> $smsgate->smsgateID,
				'persistID'	=> $result->persistID,
				]);

			// on error
			if($res->status != 201) return self::response(570, $res);
			}


		// define service function
		$serviceFn = $product->serviceNS.'::charge_abo';

		// on error
		if(!is_callable($serviceFn)) return self::response(501, 'Service method '.$serviceFn.' unavailable');

		// add call it
		$res = call_user_func($serviceFn, [
			'aboID'		=> $result->aboID,
			]);

		// if process was not okay (but not 500, because error was already triggered)
		if(!in_array($res->status, [204, 423, 500])){

			// log this for debugging
			e::logtrigger('Charging just created (SMS)Abo failed with: '.$res->status.' (aboID '.$result->aboID.', mobileID '.$mand['mobileID'].', productID '.$smsgate->productID.', smsgateID '.$smsgate->smsgateID.')');
			}


		// return result
		return self::response(200, $result);
		}

	public static function mo_payment_otp($req = []){

		// mandatory
		$mand = h::eX($req, [
			'smsgateID'		=> '~1,65535/i',
			'mobileID'		=> '~1,4294967295/i',
			'operatorID'	=> '~0,65535/i',
			'is_stop_mo'	=> '~/b',
			], $error);

		// optional
		$opt = h::eX($req, [
			'persistID'		=> '~1,18446744073709551615/i',
			'processTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// define defaults
		$opt += [
			'processTime'	=> h::dtstr('now'),
			];


		// load smsgate
		$res = nexus_service::get_smsgate([
			'smsgateID'	=> $mand['smsgateID'],
			]);

		// on error
		if($res->status != 200) return self::response(500, 'Loading smsgate failed with: '.$res->status.' (smsgateID '.$mand['smsgateID'].')');

		// take smsgate
		$smsgate = $res->data;


		// define result
		$result = (object)[
			'persistID'	=> $opt['persistID'] ?? 0,
			];


		// if there is no persistID
		if(!$result->persistID){

			// create one
			$res = persist::create();

			// on error
			if($res->status != 200) return self::response(570, $res);

			// and take it
			$result->persistID = $res->data->persistID;

			// if fallback pageID is defined
			if(h::cX($smsgate->param, 'session:fallback_pageID', '~1,65535/i')){

				// create new session
				$res = traffic_session::create_session([
					'persistID'		=> $result->persistID,
					'createTime'	=> $opt['processTime'],
					'pageID'		=> h::gX($smsgate->param, 'session:fallback_pageID'),
					]);

				// on error
				if($res->status != 201) return self::response(570, $res);
				}

			// add persistlink (because persistID is new)
			$res = mobile::update_mobile([
				'mobileID'		=> $mand['mobileID'],
				'persistID'		=> $result->persistID,
				]);

			// on error
			if($res->status != 204){
				return self::response(500, 'Adding persistlink for persistID '.$result->persistID.' and mobileID '.$mand['mobileID'].' failed with: '.$res->status.' (mobileID '.$mand['mobileID'].', smsgateID '.$mand['smsgateID'].')');
				}
			}

		// now created payed otp
		$res = otp::create([
			'mobileID'		=> $mand['mobileID'],
			'productID'		=> $smsgate->productID,
			'persistID'		=> $result->persistID,
			'paidTime'		=> $opt['processTime'],
			]);

		// on error
		if($res->status != 201) return self::response(570, $res);

		// append otpID to result
		$result->otpID = $res->data->otpID;

		// check for configured welcome mt
		$welcome_mt = h::gX($smsgate->param, 'welcome_mt:'.$mand['operatorID']) ?: h::gX($smsgate->param, 'welcome_mt:default');

		// if we have one
		if($welcome_mt){

			// send welcome mt
			$res = self::send_sms([
				'mobileID'	=> $mand['mobileID'],
				'operatorID'=> $mand['operatorID'],
				'text'		=> $welcome_mt,
				'serviceID'	=> $smsgate->serviceID,
				'smsgateID'	=> $smsgate->smsgateID,
				'persistID'	=> $result->persistID,
				]);

			// on error
			if($res->status != 201) return self::response(570, $res);
			}


		// return result
		return self::response(200, $result);
		}

	public static function mo_longnumber_stop($req = []){

		// mandatory
		$mand = h::eX($req, [
			'number'		=> '~^(?:\+|00|)([0-9]{3,15})$',
			'keyword'		=> '~^(?:|[A-Za-z0-9]{1,32})$',
			'mobileID'		=> '~1,4294967295/i',
			'operatorID'	=> '~0,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'persistID'		=> '~1,18446744073709551615/i',
			'processTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// define defaults
		$opt += [
			'processTime'	=> h::dtstr('now'),
			];


		// load smsgate
		$res = nexus_service::get_smsgate([
			'number'				=> $mand['number'][0],
			'keyword'				=> $mand['keyword'],
			'operatorID'			=> $mand['operatorID'],
			'ignore_archive'		=> true,
			'fallback_operatorID'	=> 0,
			]);

		// on error
		if($res->status != 200) return self::response(500, 'Loading SMS-Gate triggered over longnumber STOP failed with: '.$res->status.' (mobileID '.$mand['mobileID'].', persistID '.($opt['persistID'] ?? 0).')');

		// take smsgate
		$smsgate = $res->data;


		// if product configuration is useable
		if($smsgate->type == 'smspay' and $smsgate->productID){

			// trigger stop for associated product
			$res = smspay::stop_smspay([
				'mobileID'		=> $mand['mobileID'],
				'productID'		=> $smsgate->productID,
				'createTime'	=> $opt['processTime'],
				]);

			// on error
			if($res->status != 204){
				return self::response(500, 'Stopping SMSPay triggered over longnumber STOP failed with: '.$res->status.' (mobileID '.$mand['mobileID'].', smsgateID '.$smsgate->smsgateID.')');
				}
			}

		// else this seems wrong configured
		else {

			// log error
			e::logtrigger('DEBUG: SMS-Gate configuration triggered over longnumber STOP has no useable product. (mobileID '.$mand['mobileID'].', smsgateID '.$smsgate->smsgateID.')');
			}

		// return success
		return self::response(204);
		}

	public static function mo_forward_message($req = []){

		// mandatory
		$mand = h::eX($req, [
			'smsgateID'		=> '~1,65535/i',
			'persistID'		=> '~0,18446744073709551615/i',
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'operatorID'	=> '~0,65535/i',
			'message'		=> '~/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'processTime'	=> '~Y-m-d H:i:s/d',
			'is_stop_mo'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// define defaults
		$opt += [
			'processTime'	=> h::dtstr('now'),
			];


		// load smsgate
		$res = nexus_service::get_smsgate([
			'smsgateID'		=> $mand['smsgateID'],
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take smsgate
		$smsgate = $res->data;


		// create param array
		$param = [
			'smsgateID'		=> $mand['smsgateID'],
			'persistID' 	=> $mand['persistID'],
			'msisdn'		=> $mand['msisdn'][0],
			'operatorID'	=> $mand['operatorID'],
			'message'		=> $mand['message'],
			'processTime'	=> $opt['processTime'],
			'receiveTime'	=> $opt['processTime'], // DEPRECATED
			'is_stop_mo'	=> !empty($opt['is_stop_mo']),
			];

		// define overwriteable keys and data
		$overwrite_keys = ['msisdn','message'];
		$overwrite_data = [];

		// get function for mo preprocessing
		$preprocessing_fn = h::gX($smsgate->param, 'mo_preprocessing_fn');

		// if function for mo preprocessing is defined
		if($preprocessing_fn){

			// check if function is callable
			if(is_callable($preprocessing_fn)){

				// call function
				$res = call_user_func($preprocessing_fn, $param);

				// if function does not responses with positiv statuscode
				if(!in_array($res->status, [200,201,204])){

					// return failure
					return self::response(570, $res);
					}

				// check if there is an overwrite definition
				if(isset($res->data->overwrite) and h::is($res->data->overwrite, '~/l')){
					$overwrite_data = (array) $res->data->overwrite;
					}
				}

			// if not
			else{

				// log it
				e::logtrigger('Cannot call mo_preprocessing_fn '.h::encode_php($preprocessing_fn).' in smsgateID '.$smsgate->smsgateID);
				}
			}


		// get function for mo redirection
		$redirection_fn = h::gX($smsgate->param, 'mo_redirection_fn');

		// if function for mo redirection is defined
		if($redirection_fn){

			// check if function is callable
			if(is_callable($redirection_fn)){

				// for each overwriteable key
				foreach($overwrite_keys as $key){

					// if data is given, overwrite it
					if(isset($overwrite_data[$key])) $param[$key] = $overwrite_data[$key];
					}

				// call function
				$res = call_user_func($redirection_fn, $param);

				// if function does not responses with positiv statuscode
				if(!in_array($res->status, [200,201,204])){

					// return failure
					return self::response(570, $res);
					}
				}

			// if not
			else{

				// log it
				e::logtrigger('Cannot call mo_redirection_fn '.h::encode_php($redirection_fn).' in smsgateID '.$smsgate->smsgateID);
				}
			}

		// return success
		return self::response(204);
		}


	/* other */
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
			'operatorID'	=> '~0,65535/i',
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

	}
