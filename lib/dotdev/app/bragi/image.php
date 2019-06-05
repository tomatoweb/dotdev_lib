<?php
/*****
 * Version	 	1.0.2018-02-20
**/
namespace dotdev\app\bragi;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class image {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	protected static function pdo_config(){

		return ['app_bragi', [

			'l_all_images_by_profileID'		=> "SELECT imageID, name as imageName, moderator, fsk FROM `image` WHERE `profileID` = ?",
			'l_images_by_profileID_and_fsk'	=> "SELECT imageID, name as imageName, moderator, fsk FROM `image` WHERE `profileID` = ? AND `fsk` <= ? AND `moderator` = 0 ",
			'l_chat_images_by_profileID'	=> "SELECT imageID, name as imageName, moderator, fsk FROM `image` WHERE `profileID` = ? AND `moderator` = 1",
			'l_thumbs_by_profileID'			=> "SELECT imageID, name as imageName, moderator, fsk FROM `image` WHERE `profileID` = ? AND `moderator` = 2",
			'l_copies'						=> "SELECT * FROM `image` WHERE `name` LIKE ? ",
			'l_active_images'				=> "SELECT i.imageID, i.name, p.profileID, i.fsk, p.poolID  FROM `image` i
												LEFT JOIN `profile` p ON p.profileID = i.profileID
												WHERE p.hidden = 0 AND i.moderator !=1 AND i.name NOT LIKE '%WVGA%' AND i.name NOT LIKE '%thumb%'",
			's_image_by_name'				=> "SELECT * FROM `image` WHERE `name` = ? LIMIT 1",
			's_image_by_imageID'			=> "SELECT * FROM `image` WHERE `imageID` = ? LIMIT 1",

			'i_image'						=> "INSERT INTO `image` (`name`,`profileID`) VALUES (?,?)",

			'u_image_name'					=> "UPDATE `image` SET `name`		= ? WHERE `imageID` = ?",
			'u_image_profileID'				=> "UPDATE `image` SET `profileID` 	= ? WHERE `imageID` = ?",
			'u_image_mod'					=> "UPDATE `image` SET `moderator` 	= ? WHERE `imageID` = ?",
			'u_image_fsk'					=> "UPDATE `image` SET `fsk` 		= ? WHERE `imageID` = ?",

			'd_image'						=> "DELETE FROM `image` WHERE `imageID` = ?",

			]];
		}


	public static function get($req){
		// Alternativ
		$alt = h::eX($req, [
			'imageID'	=> '~1,16777215/i',
			'name'		=> '~^.{1,60}$',
			], $error, true);
		if($error) return self::response(400, $error);
   		elseif(empty($alt)) return self::response(400, ['imageID|name']);

		if(!empty($alt['imageID'])){
			$res = self::pdo('s_image_by_imageID', $alt['imageID']);
			if(!$res) return self::response($res === false ? 560 : 404);
			return self::response(200, $res);
			}

		if(!empty($alt['name'])){
			$res = self::pdo('s_image_by_name', $alt['name']);
			if(!$res) return self::response($res === false ? 560 : 404);
			return self::response(200, $res);
			}

		}

	/* Redis */
	public static function redis(){

		return redis::load_resource('app_bragi');
		}

	// A profile pics list
	public static function get_list($req){

		// mandatory
		$mand = h::eX($req, [
			'profileID'	=> '~1,16777215/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'moderator'	=> '~0,255/i',
			'fsk'		=> '~0,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// first opt param
		if(!empty($opt['moderator'])){

			if($opt['moderator'] == 1){

				// load Chat images
				$res = self::pdo('l_chat_images_by_profileID', $mand['profileID']);

				// on error
				if($res === false) return self::response(560);
				}
			elseif($opt['moderator'] == 2){

				//load thumbnails
				$res = self::pdo('l_thumbs_by_profileID', $mand['profileID']);

				// on error
				if($res === false) return self::response(560);
				}


			}

		// second opt param
		elseif(isset($opt['fsk'])){

			// load images by fsk limit
			$res = self::pdo('l_images_by_profileID_and_fsk', [$mand['profileID'], $opt['fsk']]);

			// on error
			if($res === false) return self::response(560);

			}

		// no opt param
		else{

			// load images
			$res = self::pdo('l_all_images_by_profileID', $mand['profileID']);

			// on error
			if($res === false) return self::response(560);

			}

		// Remove _WVGA copies
		if(!empty($res)){
			foreach($res as $key => $image){
				if(strpos($image->imageName, '_') !== false){
					unset($res[$key]);
					}
				}
			}

		// re-index array
		$res = array_values($res);

		// return found images
		return self::response(200, $res);
		}

	// This fct returns a picture's copies (123_otherSize.jpg)
	public static function get_copies($req){

		// mandatory
		$mand = h::eX($req, [
			'imageID'	=> '~1,16777215/i',
			], $error);
		if($error) return self::response(400, $error);

		$mand['imageID'] = $mand['imageID'].'\_';
		//return $mand['imageID'];

		$res = self::pdo('l_copies', $mand['imageID'].'%');
		if($res === false) return self::response(560);

		return self::response(200, $res);
		}

	// All active pictures
	public static function get_active_list($req){

		// optional
		$opt = h::eX($req, [
			'imageID'	=> '~1,16777215/i',
			'name'		=> '~^.{1,60}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load active pictures
		$res = self::pdo('l_active_images');

		// on error
		if($res === false) return self::response(560);


		return self::response(200, $res);

		}


	public static function create($req = []){

		$opt = h::eX($req, [
			'profileID'		=> '~1,16777215/i',
			'name' 			=> '~^.{1,160}$',
			], $error, true);

		if($error) return self::response(400, $error);

		$imageID = self::pdo('i_image', [
			isset($opt['name']) 		? $opt['name'] 		: '', // allow anonym
			isset($opt['profileID']) 	? $opt['profileID']	: 0, // allow profileless
			]);

		if($imageID === false) return self::response(560);

		return self::response(201, (object)['imageID'=>$imageID]);

		}


	public static function update($req){

		// mandatory
		$mand = h::eX($req, [
			'imageID'		=> '~1,16777215/i',
			], $error);
		// optionale
		$opt = h::eX($req, [
			'profileID'		=> '~1,16777215/i',
			'name' 			=> '~^.{1,160}$',
			'mod'			=> '~0,255/i',
			'fsk'			=> '~0,255/i',
			], $error, true);
		if($error) return self::response(400, $error);

		foreach($opt as $k=>$v){
			$res = self::pdo('u_image_'.$k,[$v, $mand['imageID']]);
			if($res === false){
				return self::response(560);
				break;
				}
			}

		// get profileID if not supplied
		if(!isset($opt['profileID'])){
			$res = self::pdo('s_image_by_imageID', $mand['imageID']);
			if(!$res) return self::response($res === false ? 560 : 404);
			$opt['profileID'] = $res->profileID;
			}

		// Instantiate Redis
		$redis = self::redis();

		// define cache key
		$cache_key = 'profile_image_list:'.$opt['profileID'];

		// if redis is accessable
		if($redis){

			// expire entry images list
			$redis->setTimeout($cache_key, 0);
			}


		return self::response(204);

		}


	public static function delete($req){

		// mandatory
		$mand = h::eX($req, [
			'imageID'	=> '~1,16777215/i',
			], $error);
		if($error) return self::response(400, $error);

		$res = self::pdo('d_image', [$mand['imageID']]);
		if($res === false) return self::response(560);

		return self::response(200, $res);
		}


	}
