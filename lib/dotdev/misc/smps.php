<?php
/*****
 * Version 1.1.2018-11-19
**/
namespace dotdev\misc;

use \tools\error as e;
use \tools\helper as h;
use \tools\http;
use \dotdev\nexus\service;
use \dotdev\mobile;
use \dotdev\mobile\abo;
use \dotdev\mobile\otp;

class smps {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['ext_smps', [

			// Mobile Payment Service
			's_mp_identify'				=> "SELECT * FROM `mp_identify` WHERE `persistID` = ? ORDER BY `createTime` DESC LIMIT 1",
			'i_mp_identify'				=> "INSERT INTO `mp_identify` (`persistID`,`createTime`,`mobileID`) VALUES (?,?,?)",

			's_mp_abo'					=> "SELECT * FROM `mp_abo` WHERE `aboID` = ? ORDER BY `createTime` DESC LIMIT 1",
			'i_mp_abo'					=> "INSERT INTO `mp_abo` (`persistID`,`createTime`,`aboID`) VALUES (?,?,?)",

			's_mp_otp'					=> "SELECT * FROM `mp_otp` WHERE `otpID` = ? ORDER BY `createTime` DESC LIMIT 1",
			'i_mp_otp'					=> "INSERT INTO `mp_otp` (`persistID`,`createTime`,`otpID`) VALUES (?,?,?)",


			// Premium SMS Service
			's_simple_comlog'			=> "SELECT * FROM `simple_comlog` WHERE `ID` = ? LIMIT 1",
			'l_simple_comlog_last'		=> "SELECT * FROM `simple_comlog` ORDER BY `ID` DESC LIMIT ?",
			'l_simple_comlog_intime'	=> "SELECT * FROM `simple_comlog` WHERE `createTime` BETWEEN ? AND ?",
			'i_simple_comlog' 			=> "INSERT INTO `simple_comlog` (`createTime`,`type`,`from`,`request`,`to`,`httpcode`,`response`) VALUES (?,?,?,?,?,?,?)",

			]];
		}



	/* Object: simple_comlog */
	public static function get_comlog($req){

		// alt
		$opt = h::eX($req, [
			'comlogID'		=> '~1,16777215/i',
			'last'			=> '~1,100/i',
			'from'			=> '~Y-m-d H:i:s/d',
			'to'			=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: comlogID
		if(isset($opt['comlogID'])){

			$entry = self::pdo('s_simple_comlog', $opt['comlogID']);
			if(!$entry) return self::response($entry === false ? 560 : 404);

			return self::response(200, $entry);
			}

		// param order 2: last
		if(isset($opt['last'])){

			$list = self::pdo('l_simple_comlog_last', $opt['last']);
			if($list === false) return self::response(560);

			return self::response(200, $list);
			}

		// param order 3: from, to
		if(isset($opt['from']) and isset($opt['to'])){

			$list = self::pdo('l_simple_comlog_intime', [$opt['from'], $opt['to']]);
			if($list === false) return self::response(560);

			return self::response(200, $list);

			}

		// other request param invalid
		return self::response(400, 'need comlogID or last or from+to as parameter');
		}

	public static function create_comlog($req){

		// mandatory
		$mand = h::eX($req, [
			'type'		=> '~1,64/s',
			'httpcode'	=> '~0,999/i',
			'from'		=> '~1,255/s',
			'to'		=> '~1,255/s',
			'request',
			'response',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// save log
		$comlogID = self::pdo('i_simple_comlog', [h::dtstr('now'), $mand['type'], $mand['from'], json_encode($mand['request']), $mand['to'], $mand['httpcode'], json_encode($mand['response'])]);
		if(!$comlogID) return self::response(560);

		// return success
		return self::response(200, ['comlogID'=>$comlogID]);
		}



	/* Mobile Payment API Simulation */
	public static function identify_mobile($req){

		// mandatory
		$mand = h::eX($req, [
			'persistID'	=> '~1,4294967295/i',
			'type' 		=> '~^(?:abo|smsabo|otp|smspay)$',
			'productID'	=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'restart'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load product
		$res = service::get_product([
			'type'		=> $mand['type'],
			'productID'	=> $mand['productID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take product
		$product = $res->data;

		// define process
		$process = null;

		// if restart is not set
		if(empty($opt['restart'])){

			// load last process
			$process = self::pdo('s_mp_identify', $mand['persistID']);
			if($process === false) return self::response(560);
			}

		// if not process is given, start new process
		if(!$process){

			// create dummy mobile
			$create = mobile::create_dummy([
				'operatorID'	=> h::gX($product, 'param:identify_operatorID'),
				]);

			// on error
			if($create->status !== 201) return self::response(570, $create);

			// save process
			$processID = self::pdo('i_mp_identify', [$mand['persistID'], date('Y-m-d H:i:s'), $create->data->ID]);
			if($processID === false) return self::response(560);

			// and reload process
			$process = self::pdo('s_mp_identify', $mand['persistID']);
			if($process === false) return self::response(560);
			}

		// if still no process is given
		if(!$process){
			return self::response(500, 'identify_mobile() failed to load process for persistID '.$mand['persistID']);
			}

		// return success
		return self::response(200, (object)['mobileID'=>$process->mobileID]);
		}

	public static function create_abo($req){

		// mandatory
		$mand = h::eX($req, [
			'persistID'	=> '~1,4294967295/i',
			'aboID'		=> '~1,4294967295/i',
			'productID'	=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'restart'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load product
		$res = service::get_product([
			'type'		=> 'abo',
			'productID'	=> $mand['productID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take product
		$product = $res->data;

		// define process
		$process = null;

		// if restart is not set
		if(empty($opt['restart'])){

			// load process
			$process = self::pdo('s_mp_abo', $mand['aboID']);
			if($process === false) return self::response(560);
			}

		// if no process is given
		if(!$process){

			// confirm abo
			$res = abo::confirm([
				'aboID'			=> $mand['aboID'],
				'confirmTime'	=> date('Y-m-d H:i:s'),
				]);

			// on error
			if($res->status != 204 and $res->status != 409) return $res;

			// load confirmed abo
			$res = abo::get([
				'aboID'			=> $mand['aboID'],
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// take abo
			$abo = $res->data;

			// set first charge on paid
			$res = abo::update_charge_status([
				'chargeID'	=> $abo->charges[0]->chargeID,
				'status'	=> 'paid',
				]);

			// on error
			if($res->status != 204) return $res;

			// save process
			$processID = self::pdo('i_mp_abo', [$mand['persistID'], date('Y-m-d H:i:s'), $mand['aboID']]);
			if(!$processID) return self::response(560);

			// reload process
			$process = self::pdo('s_mp_abo', $mand['aboID']);
			if($process === false) return self::response(560);
			}

		// if still no process is given
		if(!$process){
			return self::response(500, 'create_abo() failed to load process for persistID '.$mand['persistID'].' and aboID '.$mand['aboID']);
			}

		// return success
		return self::response(200, (object)['aboID'=>$process->aboID]);
		}

	public static function terminate_abo($req){

		// mandatory
		$mand = h::eX($req, [
			'aboID'		=> '~1,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// terminate abo
		$res = abo::terminate([
			'aboID'			=> $mand['aboID'],
			'terminateTime'	=> date('Y-m-d H:i:s'),
			]);

		// on error
		if(!in_array($res->status, [204, 409])) return self::response(570, $res);

		// return sucess
		return self::response($res->status);
		}

	public static function charge_abo($req){

		// mandatory
		$mand = h::eX($req, [
			'aboID'		=> '~1,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// TODO: add missing charge feature

		// return 423 locked
		return self::response(423);
		}

	public static function ob_postprocess_abo_confirmation_template($template){

		// return unchanged template
		return $template;
		}

	public static function submit_otp($req){

		// mandatory
		$mand = h::eX($req, [
			'persistID'	=> '~1,4294967295/i',
			'otpID'		=> '~1,4294967295/i',
			'productID'	=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'restart'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load product
		$res = service::get_product([
			'type'		=> 'otp',
			'productID'	=> $mand['productID'],
			]);

		// on error
		if($res->status !== 200) return $res;

		// take product
		$product = $res->data;

		// define process
		$process = null;

		// if restart is not set
		if(empty($opt['restart'])){

			// load process
			$process = self::pdo('s_mp_otp', $mand['otpID']);
			if($process === false) return self::response(560);
			}

		// if no process is given
		if(!$process){

			// confirm otp and set it to paid
			$res = otp::pay([
				'otpID'		=> $mand['otpID'],
				'paidTime'	=> date('Y-m-d H:i:s'),
				]);

			// on error
			if(!in_array($res->status, [204, 409])) return self::response(570, $res);

			// save process
			$processID = self::pdo('i_mp_otp', [$mand['persistID'], date('Y-m-d H:i:s'), $mand['otpID']]);
			if(!$processID) return self::response(560);

			// reload process
			$process = self::pdo('s_mp_otp', $mand['otpID']);
			if($process === false) return self::response(560);
			}

		// if still no process is given
		if(!$process){
			return self::response(500, 'submit_otp() failed to load process for persistID '.$mand['persistID'].' and otpID '.$mand['otpID']);
			}

		// return success
		return self::response(200);
		}

	public static function ob_postprocess_otp_confirmation_template($template){

		// return unchanged template
		return $template;
		}



	/* MO Simulation */
	public static function generate_fake_mo($req){

		// mandatory
		$mand = h::eX($req, [

			// service
			'service_url'	=> '~/s',
			'api'			=> '~/s',

			// base param
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,22})$',
			'operatorID'	=> '~1,65535/i',
			'shortcode'		=> '~^[0-9]{3,16}$',
			'keyword'		=> '~^[a-zA-Z0-9]{1,16}$',
			'smstext'		=> '~1,960/s',

			// api specific
			'apiparam'		=> '~/c',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// define api function
		$fn = $mand['api'].'::generate_fake_mo';

		// check api function
		if(@!is_callable($fn)){
			return (object)['status'=>501,'error'=>'API method unavailable'];
			}

		// call api test function
		$res = call_user_func($fn, [
			'service_url'	=> $mand['service_url'],
			'msisdn'		=> $mand['msisdn'][0],
			'operatorID'	=> $mand['operatorID'],
			'shortcode'		=> $mand['shortcode'],
			'keyword'		=> $mand['keyword'],
			'smstext'		=> $mand['smstext'],
			] + (array) $mand['apiparam']);

		// on error
		if($res->status != 200){

			// return error without creating a log
			return self::response(500, 'Calling apt test function '.$fn.' failed with: '.$res->status);
			}

		// take curl_obj
		$curl_obj = $res->data->curl_obj;

		// save log and return its status
		return self::create_comlog([
			'type'		=> $mand['api'],
			'from'		=> $_SERVER['SERVER_ADDR'],
			'request'	=> (object)[
				'method'	=> $curl_obj->method,
				'url'		=> $curl_obj->url,
				'get'		=> $curl_obj->get,
				'post' 		=> $curl_obj->post,
				],
			'to'		=> $mand['service_url'],
			'httpcode'	=> $curl_obj->httpcode,
			'response'	=> $curl_obj->content,
			]);
		}

	}