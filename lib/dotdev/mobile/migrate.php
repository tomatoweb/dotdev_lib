<?php
/*****
 * Version 1.0.2019-01-22
**/
namespace dotdev\mobile;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\traffic\session as traffic_migrate;
use \dotdev\app\bragi\migrate as bragi_migrate;

class migrate {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_mobile', [
			's_mobile_migrate'				=> 'SELECT * FROM `mobile_migrate` WHERE `mobileID` = ? LIMIT 1',
			'i_mobile_migrate'				=> 'INSERT INTO `mobile_migrate` (`mobileID`,`referID`,`createTime`) VALUES (?,?,?)',
			'd_mobile_migrate_mobile'		=> 'DELETE FROM `mobile` WHERE `ID` = ? LIMIT 1',
			'd_mobile_migrate_mobile_info'	=> 'DELETE FROM `mobile_info` WHERE `mobileID` = ? LIMIT 1',

			'u_mobile_migrate_abo'			=> 'UPDATE `abo` SET `mobileID` = ? WHERE `mobileID` = ?',
			'u_mobile_migrate_imsi'			=> 'UPDATE `imsi` SET `mobileID` = ? WHERE `mobileID` = ?',
			'u_mobile_migrate_mobile_login'	=> 'UPDATE `mobile_login` SET `mobileID` = ? WHERE `mobileID` = ?',
			'u_mobile_migrate_otp'			=> 'UPDATE `otp` SET `mobileID` = ? WHERE `mobileID` = ?',
			'u_mobile_migrate_persistlink'	=> 'UPDATE `persistlink` SET `mobileID` = ? WHERE `mobileID` = ?',
			'u_mobile_migrate_sms'			=> 'UPDATE `sms` SET `mobileID` = ? WHERE `mobileID` = ?',
			'u_mobile_migrate_smspay'		=> 'UPDATE `smspay` SET `mobileID` = ? WHERE `mobileID` = ?',
			'u_mobile_migrate_tan'			=> 'UPDATE `tan` SET `mobileID` = ? WHERE `mobileID` = ?',
			'u_mobile_migrate_webmo'		=> 'UPDATE `webmo` SET `mobileID` = ? WHERE `mobileID` = ?',
			]];
		}


	/* helper */
	public static function migrate_mobile($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from_mobileID'	=> '~1,4294967295/i',
			'to_mobileID'	=> '~1,4294967295/i',
			'to_operatorID'	=> '~0,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'createTime'	=> '~Y-m-d H:i:s/d',
			'force'			=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'createTime'	=> h::dtstr('now'),
			'force'			=> false,
			];

		// check if identical
		if($mand['from_mobileID'] == $mand['to_mobileID']) return self::response(204);

		// try to load already made migration
		$migrate_entry = self::pdo('s_mobile_migrate', $mand['from_mobileID']);

		// on error
		if($migrate_entry === false) return self::response(560);

		// if migration entry found
		if($migrate_entry and !$opt['force']){

			// log error
			e::logtrigger('Mobile migration conflict, already migrated to mobileID '.$migrate_entry->referID.' ('.h::encode_php($mand).')');

			// return conflict
			return self::response(409);
			}

		// if no migration entry found
		if(!$migrate_entry){

			// insert mobile migration
			$ins = self::pdo('i_mobile_migrate', [$mand['from_mobileID'], $mand['to_mobileID'], $opt['createTime']]);

			// on error
			if($ins === false) return self::response(560);
			}

		// for each table
		foreach(['abo','imsi','mobile_login','otp','persistlink','sms','smspay','tan','webmo'] as $table){

			// update entries
			$upd = self::pdo('u_mobile_migrate_'.$table, [$mand['to_mobileID'], $mand['from_mobileID']]);

			// on error
			if($upd === false) return self::response(560);
			}


		// delete migrated mobile entry
		$del = self::pdo('d_mobile_migrate_mobile', $mand['from_mobileID']);

		// on error
		if($del === false) return self::response(560);


		// delete migrated mobile info entry
		$del = self::pdo('d_mobile_migrate_mobile_info', $mand['from_mobileID']);

		// on error
		if($del === false) return self::response(560);


		// update traffic-tables
		$res = traffic_migrate::delayed_migrate_mobile([
			'from_mobileID'	=> $mand['from_mobileID'],
			'to_mobileID'	=> $mand['to_mobileID'],
			'to_operatorID'	=> $mand['to_operatorID'],
			]);

		// on error
		if($res->status != 204){
			return self::response(500, 'Mobile migration for traffic-tables failed with: '.$res->status.' ('.h::encode_php($mand).')');
			}

		// update bragi-tables
		$res = bragi_migrate::migrate_mobile([
			'from_mobileID'	=> $mand['from_mobileID'],
			'to_mobileID'	=> $mand['to_mobileID'],
			]);

		// on error, log only error
		if($res->status != 204) e::logtrigger('Mobile migration for bragi-tables failed with: '.$res->status.' ('.h::encode_php($mand).')');

		// return success
		return self::response(204);
		}

	}
