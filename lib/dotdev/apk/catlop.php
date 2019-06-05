<?php
/*****
 * Version 1.0.2018-09-10
**/
namespace dotdev\apk;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \tools\http;
use \tools\crypt;
use \dotdev\nexus\base as nexus_base;
use \dotdev\nexus\catlop as nexus_catlop;

class catlop {
	use \tools\libcom_trait,
		\tools\redis_trait;

	/* Redis */
	protected static function redis(){

		return redis::load_resource('mt_livestat');
		}


	/* System */
	public static function process_catlop_fn($req){

		// mandatory
		$mand = h::eX($req, [
			'fn'		=> '~^[a-zA-Z0-9\.\_]{1,32}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'param'		=> '~/l',
			'secparam'	=> '~/l',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load key
		$res = nexus_catlop::get_catlop([
			'key'		=> $mand['fn'],
			]);

		// on error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if not found, return not implemented
		if($res->status == 404) return self::response(501, 'fn '.$mand['fn'].' is not implemented');

		// take catlop
		$catlop = $res->data;


		// if status > 0, return it with no execution
		if($catlop->fn_status > 0) return self::response($catlop->fn_status);

		// if secure function is needed, execute it first.
		if($catlop->secfn){

			// param for secure funtion
			$secparam = [];

			// add given param
			if(!empty($opt['secparam'])){

				// make object to assoc array
				if(is_object($opt['secparam'])) $opt['secparam'] = (array) $opt['secparam'];

				// if param isn't an array, the request is bad
				if(!is_array($opt['secparam'])) return self::response(400, ['secparam']);

				$secparam = $opt['secparam'];
				}

			// add default param
			if($catlop->secfn_default_param){

				// if param isn't an array, the db-entry is bad
				if(!is_array($catlop->secfn_default_param)) return self::response(500, 'secfn_default_param is not valid as param');

				$secparam += $catlop->secfn_default_param;
				}

			// check if function is callable
			if(!is_callable($catlop->secfn)) return self::response(500, 'secfn '.$catlop->secfn.' is not callable');

			// run secure function
			$secure = call_user_func_array($catlop->secfn, [$secparam]);

			// If status not good, no more execution
			if($secure->status != 200) return $secure;
			}

		// param for wanted funtion
		$param = [];

		// add given param
		if(!empty($opt['param'])){

			// make object to assoc array
			if(is_object($opt['param'])) $opt['param'] = (array) $opt['param'];

			// if param isn't an array, the request is bad
			if(!is_array($opt['param'])) return self::response(400, ['param']);

			$param = $opt['param'];
			}

		// add default param
		if($catlop->fn_default_param){

			// if param isn't an array, the db-entry is bad
			if(!is_array($catlop->fn_default_param)) return self::response(500, 'fn_default_param is not valid as param');

			$param += $catlop->fn_default_param;
			}

		// check if function is callable
		if(!is_callable($catlop->fn)) return self::response(500, 'fn '.$catlop->fn.' is not callable');

		// run and direct return wanted function
		return call_user_func_array($catlop->fn, [$param]);
		}


	/* API */
	public static function send_request($req){

		// mandatory
		$mand = h::eX($req, [
			'fn'			=> '~^[a-zA-Z0-9\.\_]{1,32}$',
			], $error);

		// alternative (one is mandatory)
		$alt = h::eX($req, [
			'firmID'		=> '~1,255/i',
			'mtservice_fqdn'=> '~1,60/s',
			], $error, true);

		// optional param
		$param = h::eX($req, [
			'param'			=> '~/l',
			'secparam'		=> '~/l',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if not exactly one of the alternative param is given, return error
		if(count($alt) != 1) return self::response(400, 'need firmID or mtservice_fqdn param');

		// add fn to params
		$param['fn'] = $mand['fn'];


		// load firm
		$res = nexus_base::get_firm([
			'firmID'		=> $alt['firmID'] ?? null,
			'mtservice_fqdn'=> $alt['mtservice_fqdn'] ?? null,
			]);

		// on error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if not found or mtservice_fqdn not set, return forbidden
		if($res->status == 404 or empty($res->data->mtservice_fqdn)) return self::response(403);

		// take firm
		$firm = $res->data;


		// load sslcert
		$res = nexus_catlop::get_sslcert([
			'firmID'	=> $firm->firmID,
			'default'	=> true,
			]);

		// on error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if not found, return forbidden
		if($res->status == 404) return self::response(403);

		// take sslcert
		$sslcert = $res->data;

		// if public_key is not defined, return forbidden
		if(!$sslcert->public_key) return self::response(403);


		// encrypt request param (generated aes_key is saved to $aes_key)
		$enc_param = crypt::openssl_public_encrypt($param, $sslcert->public_key, $aes_key, $error);

		// on error
		if($enc_param === false) return $error;

		// send request
		$curl_obj = http::curl_obj([
			'url' 		=> 'http://'.$firm->mtservice_fqdn.'/catlop/v1.json',
			'method'	=> 'POST',
			'post' 		=> [
				'd'		=> $enc_param->data,
				'k'		=> $enc_param->key,
				'h'		=> $enc_param->hmac,
				],
			'urlencode'	=> true,
			]);

		// if response seems not normal
		if($curl_obj->httpcode != 200 or strpos($curl_obj->content, '{') !== 0){

			// return error
			return self::response(503, $curl_obj->httpcode != 200 ? 'Servers httpcode is '.$curl_obj->httpcode : 'Server httpcode is 200, but data seems to be no json');
			}

		// convert data from json and prepare response to be a normal libcom object
		$enc_response = json_decode($curl_obj->content);

		// if response is not valid
		if(!$enc_response or !isset($enc_response->data) or !isset($enc_response->signature)){

			// return error
			return self::response(503, 'Server httpcode is 200, but does not return an encrypted response: '.gettype($enc_response));
			}

		// decrypt response param
		$response = crypt::openssl_public_decrypt($enc_response, $sslcert->public_key, $aes_key, $error);

		// on error
		if($response === false){

			// return error
			return self::response(503, 'Server httpcode is 200, but decrypting response failed with: '.$error);
			}

		// return response
		return self::response(200, $response->data);
		}

	public static function proceed_request($req){

		// mandatory
		$mand = h::eX($req, [
			'data'			=> '~/s',
			'key'			=> '~/s',
			'hmac'			=> '~/s',
			], $error);

		// alternative (one is mandatory)
		$alt = h::eX($req, [
			'firmID'		=> '~1,255/i',
			'mtservice_fqdn'=> '~1,60/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if not exactly one of the alternative param is given, return error
		if(count($alt) != 1) return self::response(400, 'need firmID or mtservice_fqdn param');


		// load firm
		$res = nexus_base::get_firm([
			'firmID'		=> $alt['firmID'] ?? null,
			'mtservice_fqdn'=> $alt['mtservice_fqdn'] ?? null,
			]);

		// on error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if not found, return forbidden
		if($res->status == 404) return self::response(403);

		// take firm
		$firm = $res->data;


		// load sslcert
		$res = nexus_catlop::get_sslcert([
			'firmID'	=> $firm->firmID,
			'default'	=> true,
			]);

		// on error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if not found, return forbidden
		if($res->status == 404) return self::response(403);

		// take sslcert
		$sslcert = $res->data;

		// if private_key is not defined, return forbidden
		if(!$sslcert->private_key) return self::response(403);


		// decrypt request
		$res = self::decrypt_request([
			'firmID'	=> $firm->firmID,
			'data'		=> $mand['data'],
			'key'		=> $mand['key'],
			'hmac'		=> $mand['hmac'],
			'raw'		=> true,
			]);

		// on error
		if($res->status != 200) return $res;

		// take decrypted request
		$decrypted_request = $res->data;


		// process request
		$res = self::process_catlop_fn($decrypted_request->data);

		// encrypt response
		$encrypted_response = crypt::openssl_private_encrypt($res, $sslcert->private_key, $decrypted_request->key, $error);

		// on error
		if($encrypted_response === false) return self::response(500, $error);


		// return result
		return self::response(200, $encrypted_response);
		}

	public static function decrypt_request($req){

		// mandatory
		$mand = h::eX($req, [
			'data'			=> '~^[a-zA-Z0-9\+\/]*[\=]{0,2}$', // base64
			'key'			=> '~^[a-zA-Z0-9\+\/]*[\=]{0,2}$', // base64
			'hmac'			=> '~^[a-fA-F0-9]{64}$', // Hex
			], $error);

		// alternative (one is mandatory)
		$alt = h::eX($req, [
			'firmID'		=> '~1,255/i',
			'mtservice_fqdn'=> '~1,60/s',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'raw'			=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if not exactly one of the alternative param is given, return error
		if(count($alt) != 1) return self::response(400, 'need firmID or mtservice_fqdn param');

		// if mtservice_fqdn is given
		if(isset($alt['mtservice_fqdn'])){

			// load firm
			$res = nexus_base::get_firm([
				'mtservice_fqdn'	=> $alt['mtservice_fqdn'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if not found, return forbidden
			if($res->status == 404) return self::response(403);

			// take firmID
			$alt['firmID'] = $res->data->firmID;
			}

		// if firmID is not given, return error
		if(!isset($alt['firmID'])) return self::response(400, 'need firmID or mtservice_fqdn param');


		// load sslcert
		$res = nexus_catlop::get_sslcert([
			'firmID'	=> $alt['firmID'],
			'default'	=> true,
			]);

		// on error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if not found, return forbidden
		if($res->status == 404) return self::response(403);

		// take sslcert
		$sslcert = $res->data;

		// if private_key is not defined, return forbidden
		if(!$sslcert->private_key) return self::response(403);

		// encrypt message
		$message = crypt::openssl_private_decrypt($mand, $sslcert->private_key, $error);

		// on error
		if($message === false){

			// log error
			//e::logtrigger('CATLOP request error: '.$error);

			// return bad request without error
			return self::response(400);
			}

		// return result
		return self::response(200, !empty($opt['raw']) ? $message : $message->data);
		}


	/* Test */
	public static function test_fn($req){

		// this test function returns the request param as response
		return self::response(200, $req);
		}

	public static function test_secfn($req){

		// mandatory
		$mand = h::eX($req, [
			'valid'		=> '/b',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// this test function returns if param 'valid' is true
		return self::response(!empty($mand['valid']) ? 200 : 403);
		}

	}
