<?php
/*****
 * Version: 	1.1.2014-06-12
**/
namespace dotdev\app;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\mobile;

class geolocate {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	public static $locateOpenTimeout = '2 hour';
	public static $locateSuccessValid = '5 min';
	public static $locateFailedValid = '5 min';

	private static function pdo_config(){
		return ['app_ortung', [
			's_locate'				=> "SELECT * FROM `locate` WHERE `ID` = ? LIMIT 1",
			'c_locate_ID'			=> "SELECT `ID`,`hash` FROM `locate` WHERE `ID` = ? LIMIT 1",
			'c_locate_open_byHash'	=> "SELECT `ID`,`hash` FROM `locate` WHERE `hash` = ? ORDER BY `createTime` DESC LIMIT 1",
			'c_locate_last'			=> "SELECT `ID`,`hash`,`status`,`createTime`,`positionTime` FROM `locate` WHERE `mobileID` = ? AND `locateMobileID` = ? ORDER BY `createTime` DESC LIMIT 1",
			'c_blacklist'			=> "SELECT `ID` FROM `blacklist` WHERE `mobileID` = ? AND `disallowMobileID` = ? LIMIT 1",

			'i_locate'				=> "INSERT INTO `locate` (`mobileID`,`locateMobileID`,`hash`,`status`) VALUES (?,?,?,?)",
			'i_blacklist'			=> "INSERT INTO `blacklist` (`mobileID`,`disallowMobileID`) VALUES (?,?)",

			'u_locate_requestTime'	=> "UPDATE `locate` SET `requestTime` = ? WHERE `ID` = ?",
			'u_locate_position'		=> "UPDATE `locate` SET `positionTime` = ?, `status` = ?, `longitude` = ?, `latitude` = ? WHERE `ID` = ?"
			]];
		}

	public static function get($req){
		$res = self::exists($req);
		if($res->status != 200) return $res;
		$entry = self::pdo('s_locate', $res->data->ID);
		if(!$entry) return self::response($entry === null ? 404 : 560);
		$entry->requested = ($entry->requestTime !== '0000-00-00 00:00:00');
		if($entry->status == 307 and $entry->requested and h::date($entry->requestTime) < h::date('-'.self::$locateOpenTimeout)) $entry->status = 423;
		return self::response(200, $entry);
		}

	public static function open($req){
		$mand = h::eX($req, [
			'mobileID'		=> '~1,4294967295/i',
			'locate_msisdn'	=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			], $e); // mandatory
		if($e) return self::response(400, $e);

		// Suche und erstelle ggf. gesuchte MSISDN
		$res = mobile::get(['msisdn'=>$mand['locate_msisdn'][0]]);
		if($res->status == 404){
			$res = mobile::create(['msisdn'=>$mand['locate_msisdn'][0], 'operatorID'=>0]);
			if($res->status != 201) return self::response(570, $res);
			}
		elseif($res->status != 200) return self::response(570, $res);

		$res = mobile::get(['msisdn'=>$mand['locate_msisdn'][0]]);
		if($res->status != 200) return self::response(570, $res);
		$locate_mobile = $res->data;

		// Blacklistcheck
		$bl = self::pdo('c_blacklist', [$mand['mobileID'], $locate_mobile->ID]);
		if($bl === false) return self::response(560);
		elseif($bl) return self::response(403);


		// Letzten Eintrag suchen, prüfen, ggf. neuen erstellen
		$openID = 0;
		$last = self::pdo('c_locate_last', [$mand['mobileID'], $locate_mobile->ID]);

		if($last === false) return self::response(560);
		elseif($last){
			if($last->status == 307 and h::date($last->createTime) > h::date('-'.self::$locateOpenTimeout)) $openID = $last->ID;
			elseif($last->status == 200 and h::date($last->positionTime) > h::date('-'.self::$locateSuccessValid)) $openID = $last->ID;
			elseif(in_array($last->status, [403,405]) and h::date($last->positionTime) > h::date('-'.self::$locateFailedValid)) $openID = $last->ID;
			}

		if(!$openID){
			$hash = \hash('crc32', $mand['mobileID'].':'.$locate_mobile->ID, false);
			$openID = self::pdo('i_locate', [$mand['mobileID'], $locate_mobile->ID, $hash, 307]);
			if(!$openID) return self::response(560);
			}


		// Eintrag laden und zurückgeben
		$get = self::get(['ID'=>$openID]);
		return ($get->status == 200) ? self::response(200, $get->data) : self::response(570, $get);
		}

	public static function set_requested($req){
		$res = self::get($req);
		if($res->status != 200) return $res;

		if($res->data->requestTime != '0000-00-00 00:00:00') return self::response(409);

		$upd = self::pdo('u_locate_requestTime', [date('Y-m-d H:i:s'), $res->data->ID]);
		return self::response($upd === false ? 560 : 204);
		}

	public static function set_position($req){
		$res = self::get($req);
		if($res->status != 200) return $res;

		$mand = h::eX($req, [
			'status'	=> '~^(?:200|403|405|423)$',
			'longitude'	=> '~^[\-]?[0-9]{1,3}(?:\.[0-9]{0,16}|)$',
			'latitude'	=> '~^[\-]?[0-9]{1,2}(?:\.[0-9]{0,16}|)$',
			], $error); // mandatory
		if($error) self::response(400, $error);

		if($res->data->positionTime != '0000-00-00 00:00:00') return self::response(409);

		return self::pdo('u_locate_position', [date('Y-m-d H:i:s'), $mand['status'], $mand['longitude'], $mand['latitude'], $res->data->ID]) ? self::response(204) : self::response(560);
		}

	public static function exists($req){
		$alt = h::eX($req, [
			'ID' 	=> '~1,4294967295/i',
			'hash'	=> '~^[0-9a-f]{8}$',
			], $error, true); // Alternativ
		if($error) return self::response(400, $error);
		elseif(empty($alt)) return self::response(400, ['ID|hash']);

		$entry = isset($alt['ID'])
			? self::pdo('c_locate_ID', $alt['ID'])
			: self::pdo('c_locate_open_byHash', $alt['hash']);

		if(!$entry) return self::response($entry === false ? 560 : 404);
		return self::response(200, $entry);
		}

	public static function blacklist($req){
		$mand = h::eX($req, [
			'mobileID'			=> '~1,4294967295/i',
			'disallowMobileID'	=> '~1,4294967295/i',
			], $error); // mandatory
		if($error) return self::response(400, $error);

		$bl = self::pdo('c_blacklist', [$mand['mobileID'], $mand['disallowMobileID']]);
		if($bl === false) return self::response(570, $bl);
		elseif($bl) return self::response(409); // Bereits geblacklisted

		$newID = self::pdo('i_blacklist', [$mand['mobileID'], $mand['disallowMobileID']]);
		if(!$newID) return self::response(560);
		return self::response(201, (object)['ID'=>$newID]);
		}

	}
