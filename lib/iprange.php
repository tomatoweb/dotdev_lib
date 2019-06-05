<?php
/*****
 * Version 		1.0.2016-11-22
 *
**/
namespace dotdev\app;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class iprange {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	protected static function pdo_config(){

		return ['mt_tracking', [


			]];
		}

	/*****
	 *	To store IPv4 addresses, an UNSIGNED INT is enough:
	 *
	 * String 	255.255.255.255
	 * Binary	11111111 . 11111111 . 11111111 . 11111111
	 * Integer	4294967295
	 *
	**/
	public static function create($req){

		$mand = h::eX($req, ['name'=>'~^[a-zA-Z0-9\-\_]{6,64}$'], $error);

		$opt = h::eX($req, ['age'=>'~18,65/i'], $error, true);

		if($error) return self::response(400, $error);

		$ID = self::pdo('i_range', [$mand['range'], $cidr]);

		if(!$ID) return self::response(560);

		return self::response(201, (object)['ID'=>$ID]);

		}


	public static function update($req){

		$mand = h::eX($req, ['ID'=>'~1,16777215/i', 'age'=>'~18,65/i'], $error);

		$opt = h::eX($req, ['age'=>'~18,65/i'], $error, true);

		if($error) return self::response(400, $error);

		// suchen
		$range = self::pdo('s_range', $mand['ID']);

		// Fehler
		if($range === false) return self::response(560);

		// not found
		if(!$range) return self::response(404);

		// Alter aktualisieren
		$upd = self::pdo('u_range', [$mand['cidr'], $range->ID]);

		// Fehler
		if($upd === false) return self::response(560);

		// Error 500 (Internal Server Error)
		if(!$upd) return self::response(500, 'Range konnte nicht aktualisert werden');

		return self::response(204);

		}


	public static function delete($req){

		// Mandatory
		$mand = h::eX($req, ['ID'=>'~1,16777215/i'], $error);
		if($error) return self::response(400, $error);

		// suchen
		$range = self::pdo('s_range', $mand['ID']);
		if(!$range) return self::response($range === false ? 560 : 404);

		// löschen
		$del = self::pdo('d_range', $range->ID);

		// Fehler
		if($upd === false) return self::response(560);

		// error 500 internal server error
		if(!$upd) return self::response(500, 'Range konnte nicht gelöscht werden');

		// Profil erfolgreich gelöscht
		return self::response(204);

		}


	public static function list(){

		$range_list = self::pdo('l_range');

		if($range_list === false) return self::response(560);

		return self::response(200, $range_list);

		}


	public static function get($req){

		// Mandatory Parameter
		$mand = h::eX($req, ['ID'=>'~1,16777215/i'], $error);
		if($error) return self::response(400, $error);

		// suchen
		$range = self::pdo('s_range', $mand['ID']);
		if($range === false) return self::response(560);
		if(!$range) return self::response(404);

		return self::response(200, $range);

		}

	}
