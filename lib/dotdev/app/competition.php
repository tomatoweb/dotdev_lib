<?php
/*****
 * Version	 	1.0.2015-07-21
**/
namespace dotdev\app;

use \tools\error as e;
use \tools\helper as h;

class competition {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	protected static function pdo_config(){
		return ['app_competition', [
			's_competition_by_ID'			=> "SELECT * FROM `competition` WHERE `ID` = ? LIMIT 1",
			's_competition_by_persistID'	=> "SELECT * FROM `competition` WHERE `persistID` = ?",
			'i_competition' 				=> "INSERT INTO `competition` (`IP`,`persistID`,`unlockTime`) VALUES (?,?,?)",
			]];
		}

	public static function get($req){
		$alt1 = h::eX($req, ['ID'=>'~1,16777215/i'], $e1, true); // mandatory alt 1
		$alt2 = h::eX($req, ['persistID'=>'~1,4294967295/i'], $e2, true); // mandatory alt 2
		if($e1 or $e2 or $e3) return self::response(400, array_merge($e1, $e2, $e3));

		if(isset($alt1['ID'])){
			$entry = self::pdo('s_competition_by_ID', $alt1['ID']);
			if(!$entry) return self::response($entry === false ? 560 : 404);
			return self::response(200, $entry);
			}
		elseif(isset($alt2['persistID'])){
			$list = self::pdo('s_competition_by_persistID', $alt2['persistID']);
			if($list === false) return self::response(560);
			return self::response(200, $list);
			}
		else return self::response(400, 'Need at least one parameter: ID|persistID|mobileID');
		}

	public static function add($req){
		$mand = h::eX($req, ['IP'=>'~^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$','persistID'=>'~1,4294967295/i','unlockTime'=>'~Y-m-d H:i:s/d'], $e1); // mandatory
		if($e1) return self::response(400, $e1);

		$insID = self::pdo('i_competition', [$mand['IP'],$mand['persistID'],$mand['unlockTime']]);
		if(!$insID) return self::response(560);

		return self::response(201, (object)['ID'=>$insID]);
		}

	}
