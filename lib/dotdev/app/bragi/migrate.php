<?php
/*****
 * Version 1.0.2018-06-28
**/
namespace dotdev\app\bragi;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\app\bragi\profile;

class migrate {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['app_bragi:migrate', [
			'u_migrate_message'			=> "UPDATE `message` SET `mobileID` = ? WHERE `mobileID` = ?",
			'u_migrate_profile_mobile'	=> "UPDATE `profile_mobile` SET `mobileID` = ? WHERE `mobileID` = ?",
			'u_migrate_event'			=> "UPDATE `event` SET `mobileID` = ? WHERE `mobileID` = ?",
			'd_migrate_profile_mobile'	=> "DELETE FROM `profile_mobile` WHERE `mobileID` = ?",
			]];
		}


	/* helper */
	public static function migrate_mobile($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from_mobileID'	=>	'~1,4294967295/i',
			'to_mobileID'	=>	'~1,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// for each table
		foreach(['message','event'] as $table){

			// update entries
			$upd = self::pdo('u_migrate_'.$table, [$mand['to_mobileID'], $mand['from_mobileID']]);

			// on error
			if($upd === false) return self::response(560);
			}

		// Get profile for to_mobileID
		$res = profile::get(['mobileID' => $mand['to_mobileID']]);

		// on error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// to_mobileID has a profile
		elseif($res->status == 200){

			// delete from_mobileID profile associations
			$del = self::pdo('d_migrate_profile_mobile', $mand['from_mobileID']);

			// on error
			if($del === false) return self::response(560);

			}

		// no profile for to_mobileID
		elseif($res->status == 404){

			// update entry
			$upd = self::pdo('u_migrate_profile_mobile', [$mand['to_mobileID'], $mand['from_mobileID']]);

			// on error
			if($upd === false) return self::response(560);
			}

		// return success
		return self::response(204);
		}

	}
