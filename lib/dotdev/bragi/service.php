<?php
/*****
 * Version 1.0.2018-08-15
**/
namespace dotdev\bragi;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\nexus\service as nexus_service;
use \dotdev\mobile;

// DEPRECATED
use \dotdev\app\bragi\profile as old_profile;
use \dotdev\app\bragi\message as old_message;


class service {
	use \tools\libcom_trait;


	// service functions for bragi <=> chattool
	public static function service_apk_mo($req){

		// mandatory
		$mand = h::eX($req, [
			'smsgateID'		=> '~1,65535/i',
			'msisdn' 		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'message' 		=> '~/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'persistID'		=> '~0,18446744073709551615/i',
			'processTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert msisdn
		$mand['msisdn'] = $mand['msisdn'][0];

		// define defaults
		$opt += [
			'persistID'		=> 0,
			'processTime'	=> h::dtstr('now'),
			];


		// load smsgate
		$res = nexus_service::get_smsgate([
			'smsgateID'	=> $mand['smsgateID'],
			]);

		// on error
		if($res->status != 200) return self::response(500, 'Cannot proceed apk MO, error loading smsgateID '.$mand['smsgateID'].': '.$res->status.' (persistID '.$opt['persistID'].')');

		// take smsgate
		$smsgate = $res->data;


		// load mobile
		$res = mobile::get_mobile([
			'msisdn' => $mand['msisdn'],
			]);

		// on unexpected error
		if($res->status != 200) return self::response(500, 'Cannot proceed bragi MO, error loading MSISDN '.$mand['msisdn'].': '.$res->status.' (persistID '.$opt['persistID'].')');

		// take mobile
		$mobile = $res->data;


		// define regex for special message codes "KEYWORD #l189728234i262026045457401p1482:Hello messagetext"
		$profileID_check = '/^'.($smsgate->keyword ? '(?:(?i)'.$smsgate->keyword.' )' : '').'(?:#[a-oq-z0-9]*p([1-9]{1}[0-9]{0,7})[a-oq-z0-9]*\:)(.*)$/';

		// if profileID is not found in message
		if(!preg_match($profileID_check, $mand['message'], $match)){

			// return success (no alteration/processing)
			return self::response(204);
			}

		// assign variables
		list(, $profileID, $message_text) = $match;


		// load profile
		$res = old_profile::get([
			'profileID' => $profileID,
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if profile not found
		if($res->status == 404){

			// return success (no alteration/processing)
			return self::response(204);
			}

		// take profile
		$profile = $res->data;


		// create bragi message
		$res = old_message::create([
			'mobileID'		=> $mobile->mobileID,
			'profileID'		=> $profile->profileID,
			'text'			=> $message_text,
			'from'			=> 1,
			'smsgateID'		=> $mand['smsgateID'],
			'persistID'		=> $opt['persistID'],
			'receiveTime'	=> $opt['processTime'],
			]);

		// on error
		if($res->status != 201) return self::response(500, 'Cannot proceed bragi MO, creating message failed with: '.$res->status.' (persistID '.$opt['persistID'].')');


		// if profileID is to long for actual extended msisdn feature
		if($profile->profileID > 999999){

			// return error
			return self::response(500, 'Cannot proceed bragi MO, extended msisdn limit reached with profileID '.$profile->profileID.': '.$res->status.' (persistID '.$opt['persistID'].')');
			}


		// define extended msisdn
		$ext_msisdn = $mobile->msisdn.str_pad($profileID, 6, '0', STR_PAD_LEFT);

		// define altered message
		// $altered_message = ($smsgate->keyword ? $smsgate->keyword.' ' : '').$message_text;
		$altered_message = ($smsgate->keyword ? $smsgate->keyword.' ' : '').'@'.$profile->profileName.': '.$message_text;

		// return result
		return self::response(200, (object)[
			'overwrite' => [
				'msisdn'	=> $ext_msisdn,
				'message'	=> $altered_message,
				],
			]);
		}

	public static function service_chattool_mt($req){

		// mandatory
		$mand = h::eX($req, [
			'recipient'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,20})$',
			'message'		=> '~/s',
			'mobileID'		=> '~1,4294967295/i',
			'smsgateID'		=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'persistID'		=> '~0,4294967295/i',
			'processTime'	=> '~Y-m-d H:i:s/d',
			'ctmsgtype'		=> '~0,16/s',
			'ctuserID'		=> '~0,65535/i',
			'ctpoolID'		=> '~0,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'persistID'		=> 0,
			'processTime'	=> h::dtstr('now'),
			'ctmsgtype'		=> 'sms_out',
			'ctuserID'		=> 0,
			'ctpoolID'		=> 0,
			];


		// check if recipient isn't a extended msisdn, return success (no processing)
		if(strlen($mand['recipient'][0]) < 15) return self::response(204);


		// extract msisdn and profileID from recipient
		$msisdn = substr($mand['recipient'][0], 0, -6);
		$profileID = substr($mand['recipient'][0], -6);

		// load profile
		$res = old_profile::get([
			'profileID' => $profileID,
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(500, 'Cannot proceed bragi MT, error loading profileID '.$profileID.': '.$res->status.' (persistID '.$opt['persistID'].')');

		// if profile not found, return success (no processing)
		if($res->status != 200) return self::response(204);

		// take profile
		$profile = $res->data;

		// create bragi message
		$res = old_message::create([
			'mobileID'		=> $mand['mobileID'],
			'profileID'		=> $profile->profileID,
			'text'			=> $mand['message'],
			'from'			=> 2,
			'smsgateID'		=> $mand['smsgateID'],
			'persistID'		=> $opt['persistID'],
			'receiveTime'	=> $opt['processTime'],
			]);

		// on error
		if($res->status != 201) return self::response(500, 'Cannot proceed bragi MT, error creating message: '.$res->status.' (persistID '.$opt['persistID'].')');


		// return success
		return self::response(204);
		}

	}
