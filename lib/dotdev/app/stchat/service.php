<?php
/*****
 * Version 1.0.2018-08-15
**/
namespace dotdev\app\stchat;

use \tools\error as e;
use \tools\helper as h;
use \tools\http;
use \dotdev\nexus\service as nexus_service;
use \dotdev\smsgate\service as smsgate_service;
use \dotdev\cronjob;

class service {
	use \tools\libcom_trait;

	/* Internal helper  */
	public static function _translate_to($key, $val, $error_reporting = true){

		// operatorID to Chattool Carrier
		if($key == 'operatorID' or $key == 'stchat_carrier'){

			// set (TEMPORARY until chattool uses mt operator list)
			$list = [

				// Unknown
				0	=> 'Unknown',

				// DE
				4	=> 'T-Mobile',
				5	=> 'D2 Vodafone',
				6	=> 'E-Plus',
				7	=> 'O2',
				8	=> 'debitel',
				9	=> 'mobilcom',

				// AT
				10	=> 'AT mobilkom',
				11	=> 'AT T-Mobile',
				12	=> 'AT ONE', // Orange
				19	=> 'AT drei', // H3G

				// CH
				13	=> "CH_SWISSCOM",
				14	=> "CH_SUNRISE",
				15	=> "CH_ORANGE",

				// HU
				16	=> 'HU_PANNON',
				17	=> 'HU_TMOBILE',
				18	=> 'HU_VODAFONE',
				209	=> 'HU_UPC',

				// CZ
				26	=> 'CZ_O2',
				27	=> 'CZ_TMOBILE',
				28	=> 'CZ_VODAFONE',

				// UK
				171	=> 'UK_VODAFONE',
				172	=> 'UK_TMOBILE',
				173	=> 'UK_ORANGE',
				210	=> 'UK_O2',
				212	=> 'UK_THREE',
				214	=> 'UK_VIRGIN',

				// IE
				180	=> 'IE_EIRCOM',
				181	=> 'IE_VODAFONE',
				182	=> 'IE_VIRGIN',
				216	=> 'IE_METEOR',
				217	=> 'IE_O2',
				218	=> 'IE_TESCO',
				219	=> 'IE_THREE',

				// ZA
				222	=> 'ZA_CELL',
				223	=> 'ZA_MTN',
				224	=> 'ZA_TELKOM',
				225	=> 'ZA_VODACOM',
				226	=> 'ZA_YEBO',


				];

			// flip key-value if searching for ID
			if($key == 'operatorID'){
				$list = array_flip($list);
				}

			// check existance
			if(!isset($list[$val])){

				// log error, if not found
				if($error_reporting) e::logtrigger('DEBUG: No '.h::encode_php($key).' for '.h::encode_php($val).' found');

				// return fallback
				return ($key == 'operatorID') ? 0 : 'unknown';
				}

			// return value
			return $list[$val];
			}

		// no result, trigger error
		e::logtrigger('DEBUG: Translation of '.h::encode_php($key).' with '.h::encode_php($val).' failed');
		return null;
		}


	/* Service: MO gate function */
	public static function mo_redirect_to_chattool($req = []){

		// define static for service url
		static $service_url = null;

		// mandatory
		$mand = h::eX($req, [
			'smsgateID'		=> '~1,65535/i',
			'persistID' 	=> '~0,18446744073709551615/i',
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,20})$',
			'operatorID'	=> '~0,65535/i',
			'message'		=> '~/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'processTime'	=> '~Y-m-d H:i:s/d',
			'receiveTime'	=> '~Y-m-d H:i:s/d', // DEPRECATED
			'delayed_recall'=> '~/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load smsgate
		$res = nexus_service::get_smsgate([
			'smsgateID' 	=> $mand['smsgateID'],
			]);

		// on error
		if($res->status != 200) return self::response(500, 'Loading SMS-Gate with smsgateID '.$mand['smsgateID'].' failed with: '.$res->status);

		// take smsgate
		$smsgate = $res->data;

		// convert and check/append leading 00
		$mand['msisdn'] = $mand['msisdn'][0];
		if(substr($mand['msisdn'], 0, 2) != '00') $mand['msisdn'] = '00'.$mand['msisdn'];

		// if service_url is not defined
		if(!$service_url){

			// load service url
			$service_url = include($_SERVER['ENV_PATH'].'/config/service/stchat/server.php');
			}

		// redirect request
		$curl_obj = http::curl_obj([
			'url' 		=> $service_url.'/app/gate_web',
			'method'	=> 'GET',
			'get' 		=> [
				'recipient'		=>	$smsgate->number, // 33366
				'forcekeyword'	=>	$smsgate->keyword, // CHERRY
				'sender'		=>	$mand['msisdn'], // 00491739005337000062
				'operatorID'	=>	$mand['operatorID'],
				'text'			=>	$mand['message'], // CHERRY+An+Fibi%3A+Bock+Essen+zugehen+
				'timestamp'		=>	h::dtstr('Y-m-d\+H:i:s', $opt['processTime'] ?? $opt['receiveTime'] ?? null), // 2018-02-06+00%3A00%3A00
				// DEPRECATED
				'carrier'		=>	self::_translate_to('stchat_carrier', $mand['operatorID']), // D2+Vodafone
				],
			'urlencode'	=> true,
			]);

		// if httpcode is anything else 200
		if($curl_obj->httpcode != 200){

			// define delay (0:00 -> 0:05 -> 0:20 -> 1:05 -> 3:20 -> 7:30 -> 11:40 -> 15:50 -> 20:00 -> 24:10 -> 28:20)
			$opt['delayed_recall'] = !empty($opt['delayed_recall']) ? min($opt['delayed_recall'] * 3, 250) : 5;

			// add redis job to retry later
			$res = cronjob::add_redisjob([
				'fn'		=> '\\'.__METHOD__,
				'param'		=> $opt + (array) $req,
				'start_at'	=> '+'.$opt['delayed_recall'].' min',
				]);

			// return external error
			return self::response(502, 'Forward MO of MSISDN '.($mand['msisdn'] ?? 'null').' to STChat ends in httpcode '.h::encode_php($curl_obj->httpcode).' (Retry in '.$opt['delayed_recall'].' min)');
			}

		// return success
		return self::response(204);
		}

	public static function mo_redirect_longnumber_to_chattool($req = []){

		// mandatory
		$mand = h::eX($req, [
			'smsgateID'		=> '~1,65535/i',
			'persistID' 	=> '~0,18446744073709551615/i',
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,20})$',
			'operatorID'	=> '~0,65535/i',
			'message'		=> '~/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'processTime'	=> '~Y-m-d H:i:s/d',
			'receiveTime'	=> '~Y-m-d H:i:s/d', // DEPRECATED
			'is_stop_mo'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load smsgate
		$res = nexus_service::get_smsgate([
			'smsgateID'		=> $mand['smsgateID'],
			]);

		// on error
		if($res->status != 200) return self::response(500, 'Loading smsgateID '.$mand['smsgateID'].' failed with: '.$res->status);

		// take smsgate
		$smsgate = $res->data;

		// convert msisdn
		$mand['msisdn'] = $mand['msisdn'][0];

		// if this is a longnumber route for STOP messages
		if(h::gX($smsgate->param, 'stop_route:number')){

			// if this is not a STOP MO
			if(empty($opt['is_stop_mo'])){

				// do nothing (to block it)
				e::logtrigger('DEBUG: Longnumber MO blocked, because user does not send STOP like message. (MSISDN '.h::encode_php(h::gX($mand, 'msisdn')).')');
				return self::response(204);
				}

			// load smsgate
			$res = nexus_service::get_smsgate([
				'number'				=> h::gX($smsgate->param, 'stop_route:number'),
				'keyword'				=> h::gX($smsgate->param, 'stop_route:keyword') ?: '',
				'operatorID'			=> $mand['operatorID'],
				'ignore_archive'		=> true,
				'fallback_operatorID'	=> 0,
				]);

			// on error
			if($res->status != 200) return self::response(500, 'Loading other SMS-Gate for longnumber route change failed with: '.$res->status.' (smsgateID '.$mand['smsgateID'].')');

			// overwrite smsgateID
			$mand['smsgateID'] = $res->data->smsgateID;
			}

		// redirect to chattool
		return self::mo_redirect_to_chattool($mand + $opt);
		}


	/* Service: MT service function */
	public static function service_mt($req = []){

		// mandatory
		$mand = h::eX($req, [
			'number'		=> '~^(?:\+|00|)([0-9]{3,15})$',
			'keyword'		=> '~^(?:|[A-Z0-9]{1,32})$',
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,20})$',
			'message'		=> '~/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'processTime'	=> '~Y-m-d H:i:s/d',
			'receiveTime'	=> '~Y-m-d H:i:s/d', // DEPRECATED
			], $error, true);

		// extra
		$extra = h::eX($req, [
			'ctmsgtype'		=> '~0,16/s',
			'ctuserID'		=> '~0,65535/i',
			'ctpoolID'		=> '~0,65535/i',
			'type'			=> '~0,16/s', // DEPRECATED
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// DEPRECATED
		if(!isset($opt['processTime']) and isset($opt['receiveTime'])) $opt['processTime'] = $opt['receiveTime'];

		// DEPRECATED
		if(isset($extra['type'])){
			if(!isset($extra['ctmsgtype'])) $extra['ctmsgtype'] = $extra['type'];
			unset($extra['type']);
			}

		// convert MT request to match smsgate service
		return smsgate_service::service_smsgate_mt([
			'recipient'		=> $mand['msisdn'][0],
			'sender'		=> $mand['number'][0],
			'keyword'		=> $mand['keyword'],
			'message'		=> $mand['message'],
			'processTime'	=> $opt['processTime'] ?? h::dtstr($_SERVER['REQUEST_TIME']),
			'extra'			=> $extra,
			]);
		}

	}