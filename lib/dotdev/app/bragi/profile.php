<?php
/*****
 * Version	 	1.0.2018-06-28
**/
namespace dotdev\app\bragi;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \dotdev\app\bragi\message;
use \dotdev\app\bragi\image;
use \dotdev\mobile;
use \dotdev\nexus\base;

class profile {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	protected static function pdo_config(){

			return ['app_bragi', [

				's_profile_by_name'			=> "SELECT p.profileID, p.name as profileName, p.age, p.plz, p.height, p.weight, p.description, p.createTime, p.imageID, p.hidden, p.countryID, p.poolID, p.gender, p.orientation, i.name as imageName
												FROM `profile` p
												LEFT JOIN `image` i ON p.imageID = i.imageID
												WHERE p.name = ? LIMIT 1",
				's_profile_by_profileID'	=> "SELECT p.profileID, p.name as profileName, p.age, p.plz, p.height, p.weight, p.description, p.createTime, p.imageID, p.hidden, p.countryID, p.poolID, p.gender, p.orientation, i.name as imageName
												FROM `profile` p
												LEFT JOIN `image` i ON p.imageID = i.imageID
												WHERE p.profileID = ? LIMIT 1",
				's_latest_profileID'		=> "SELECT `profileID` FROM `profile` ORDER BY `profileID` DESC LIMIT 1",
				'l_profiles_by_countryID'	=> "SELECT p.profileID, p.name as profileName, p.age, p.plz, p.height, p.weight, p.description, p.createTime, p.imageID, p.hidden, p.countryID, p.poolID, p.gender, p.orientation, i.name as imageName, pm.mobileID
												FROM `profile` p
												LEFT JOIN `image` i ON p.imageID = i.imageID
												LEFT JOIN `profile_mobile` pm ON p.profileID = pm.profileID
												LEFT JOIN `profile_persist` pp ON p.profileID = pp.profileID
												WHERE pm.mobileID IS NULL AND pp.persistID IS NULL AND p.hidden = ? AND p.countryID = ?",
				'l_profiles_by_poolID'		=> "SELECT p.profileID, p.name as profileName, p.age, p.plz, p.height, p.weight, p.description, p.createTime, p.imageID, p.hidden, p.countryID, p.poolID, p.gender, p.orientation, i.name as imageName
												FROM `profile` p
												LEFT JOIN `image` i ON p.imageID = i.imageID
												LEFT JOIN `profile_mobile` pm ON p.profileID = pm.profileID
												LEFT JOIN `profile_persist` pp ON p.profileID = pp.profileID
												WHERE pm.mobileID IS NULL AND pp.persistID IS NULL AND p.hidden = ? AND p.poolID = ?",

				'l_profileIDs_by_pools'		=> "SELECT p.profileID, p.name as profileName, p.age, p.plz, p.height, p.weight, p.description, p.createTime, p.imageID, p.hidden, p.countryID, p.poolID, p.gender, p.orientation, i.name as imageName
												FROM `profile` p
												LEFT JOIN `image` i ON p.imageID = i.imageID
												LEFT JOIN `profile_mobile` pm ON p.profileID = pm.profileID
												WHERE pm.mobileID IS NULL AND p.hidden = 0 AND p.poolID IN (?)",

				'l_profiles_since'			=> "SELECT p.profileID, p.name as profileName, p.age, p.plz, p.height, p.weight, p.description, p.createTime, p.imageID, p.hidden, p.countryID, p.poolID, p.gender, p.orientation, i.name as imageName, pm.mobileID
												FROM `profile` p
												LEFT JOIN `image` i ON p.imageID = i.imageID
												LEFT JOIN `profile_mobile` pm ON p.profileID = pm.profileID
												LEFT JOIN `profile_persist` pp ON p.profileID = pp.profileID
												WHERE pm.mobileID IS NULL AND pp.persistID IS NULL AND p.hidden = ? AND createTime > DATE_SUB(NOW(), INTERVAL ? MONTH) ORDER BY `profileID` DESC",
				'l_profiles_by_poolID_since'=> "SELECT p.profileID, p.name as profileName, p.age, p.plz, p.height, p.weight, p.description, p.createTime, p.imageID, p.hidden, p.countryID, p.poolID, p.gender, p.orientation, i.name as imageName, pm.mobileID
												FROM `profile` p
												LEFT JOIN `image` i ON p.imageID = i.imageID
												LEFT JOIN `profile_mobile` pm ON p.profileID = pm.profileID
												LEFT JOIN `profile_persist` pp ON p.profileID = pp.profileID
												WHERE pm.mobileID IS NULL AND pp.persistID IS NULL AND p.hidden = ? AND p.poolID = ? AND createTime > DATE_SUB(NOW(), INTERVAL ? MONTH) ORDER BY `profileID` DESC",
				'l_profiles'				=> "SELECT p.profileID, p.name as profileName, p.age, p.plz, p.height, p.weight, p.description, p.createTime, p.imageID, p.hidden, p.countryID, p.poolID, p.gender, p.orientation
												FROM `profile` p
												LEFT JOIN `profile_mobile` pm ON p.profileID = pm.profileID
												LEFT JOIN `profile_persist` pp ON p.profileID = pp.profileID
												WHERE pm.mobileID IS NULL AND pp.persistID IS NULL AND p.hidden = 0",
				'l_hidden_profiles'			=> "SELECT p.profileID, p.name as profileName, p.age, p.plz, p.height, p.weight, p.description, p.createTime, p.imageID, p.hidden, p.countryID, p.poolID, p.gender, p.orientation, i.name as imageName, pm.mobileID
												FROM `profile` p
												LEFT JOIN `image` i ON p.imageID = i.imageID
												LEFT JOIN `profile_mobile` pm ON p.profileID = pm.profileID
												LEFT JOIN `profile_persist` pp ON p.profileID = pp.profileID
												WHERE pm.mobileID IS NULL AND pp.persistID IS NULL AND p.hidden = ?",
				'l_users_with_pics'			=> "SELECT p.profileID, p.name as profileName, p.age, p.plz, p.height, p.weight, p.description, p.createTime, p.imageID, p.hidden, p.countryID, p.poolID, p.gender, p.orientation, i.name as imageName, pm.mobileID
												FROM profile as p
												LEFT JOIN `image` i ON p.imageID = i.imageID
												LEFT JOIN `profile_mobile` pm ON pm.profileID = p.profileID
												LEFT JOIN `profile_persist` pp ON p.profileID = pp.profileID
												WHERE (pm.mobileID IS NOT NULL OR pp.persistID IS NOT NULL) AND p.imageID !=0",
				'i_profile'					=> "INSERT INTO `profile` (`name`,`age`,`plz`,`height`,`weight`,`description`,`imageID`,`hidden`,`countryID`,`poolID`,`gender`,`orientation`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
				'u_profile_name'				=> "UPDATE `profile` SET `name` 		= ? WHERE `profileID` = ?",
				'u_profile_age'					=> "UPDATE `profile` SET `age` 			= ? WHERE `profileID` = ?",
				'u_profile_plz'					=> "UPDATE `profile` SET `plz` 			= ? WHERE `profileID` = ?",
				'u_profile_height'				=> "UPDATE `profile` SET `height` 		= ? WHERE `profileID` = ?",
				'u_profile_weight'				=> "UPDATE `profile` SET `weight` 		= ? WHERE `profileID` = ?",
				'u_profile_description'			=> "UPDATE `profile` SET `description` 	= ? WHERE `profileID` = ?",
				'u_profile_imageID'				=> "UPDATE `profile` SET `imageID`	 	= ? WHERE `profileID` = ?",
				'u_profile_hidden'				=> "UPDATE `profile` SET `hidden`	 	= ? WHERE `profileID` = ?",
				'u_profile_countryID'			=> "UPDATE `profile` SET `countryID` 	= ? WHERE `profileID` = ?",
				'u_profile_poolID'				=> "UPDATE `profile` SET `poolID` 		= ? WHERE `profileID` = ?",
				'u_profile_gender'				=> "UPDATE `profile` SET `gender` 		= ? WHERE `profileID` = ?",
				'u_profile_orientation'			=> "UPDATE `profile` SET `orientation`	= ? WHERE `profileID` = ?",
				'u_profile_name_poolID'			=> "UPDATE `profile` SET `name`	= ?, `poolID` = ? WHERE `profileID` = ?",
				's_profile_by_name_poolID'		=> "SELECT * FROM `profile` WHERE `name` = ? AND `poolID` = ? LIMIT 1",

				// profile -> mobile is n->1, profile -> persist is 1->n
				's_profile_mobile'				=> "SELECT * FROM `profile_mobile` WHERE `profileID` = ?",
				's_profile_mobile_unique'		=> "SELECT * FROM `profile_mobile` WHERE `profileID` = ? AND `mobileID` = ?",
				's_profile_mobile_by_mobileID'	=> "SELECT * FROM `profile_mobile` WHERE `mobileID` = ? ORDER BY `profileID` DESC LIMIT 1", // the latest
				's_profile_persist'				=> "SELECT * FROM `profile_persist` WHERE `profileID` = ? ORDER BY `persistID` DESC LIMIT 1", // the latest
				's_profile_persist_by_persistID'=> "SELECT * FROM `profile_persist` WHERE `persistID` = ?",

				'i_profile_mobile'				=> "INSERT INTO `profile_mobile` (`profileID`,`mobileID`) VALUES (?,?)",
				'i_profile_persist'				=> "INSERT INTO `profile_persist` (`persistID`,`profileID`) VALUES (?,?)",
				'd_profile_by_profileID'		=> 'DELETE FROM `profile` WHERE `profileID` = ?',

				// Object image
				'l_images_by_profileID'			=> "SELECT i.* FROM `image` i WHERE `profileID` = ?",
				'l_profiles_images'				=> "SELECT i.* FROM `image` i
				 									LEFT JOIN `profile` p ON i.profileID = p.profileID
				 									WHERE p.poolID = ?",

				]];
		}


	public static function redis(){

		return redis::load_resource('app_bragi');
		}


	public static function get($req = []){

		// alternativ
		$alt = h::eX($req, [
			'profileID'	=> '~1,16777215/i',
			'name'		=> '~^.{3,30}$',
			'mobileID'	=> '~1,4294967295/i',
			'persistID'	=> '~1,18446744073709551615/i',
			'countryID'	=> '~0,255/i',
			'poolID'	=> '~0,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

  		// get redis instance
		$redis = self::redis();

		// on error
		if(!$redis or !$redis->isConnected()){
			return self::response(500, 'Connection to Redis could not be established: '.h::encode_php($redis));
			}

		// check unique constraint on name and poolID
		if(!empty($alt['name']) && isset($alt['poolID'])){

			// load profile
			$res = self::pdo('s_profile_by_name_poolID', [$alt['name'], $alt['poolID']]);

			// on error or not found
			if(!$res) return self::response($res === false ? 560 : 404);

			// return profile
			return self::response(200, $res);

			}

		// 1st param: profileID
		if(!empty($alt['profileID'])){

			// get profile from cache
			if($redis->exists("profile:id:".$alt['profileID'])){
				$profile = $redis->get('profile:id:'.$alt['profileID']);
				return self::response(200, $profile);
				}

			// load profile
			$res = self::pdo('s_profile_by_profileID', $alt['profileID']);

			// on error or null (not found)
			if(!$res) return self::response($res === false ? 560 : 404);

			// get profile its mobileID
			$user = self::pdo('s_profile_mobile', $alt['profileID']);

			// on error
			if($user === false) return self::response(560);

			// not empty array, mobileID is found, this profile is a customer profile (vs. fake profile)
			elseif($user){
				$res->isuser = true;
				$res->mobileID = $user[0]->mobileID;
				}

			// not found (empty array), try to find persistID
			else{

				// get profile its persistID (limit 1 profileID)
				$user = self::pdo('s_profile_persist', $alt['profileID']);

				// on error
				if($user === false) return self::response(560);

				// on not null (sql limit 1), mobileID is found, this profile is a customer profile (vs. fake profile)
				elseif($user){
					$res->isuser = true;
					$res->persistID = $user->persistID;
					}

				}

			// check if profileName contents a suffix (i.e. my_name_12345 transform to my_name)
			if($user){
				$name = substr($res->profileName, 0, strrpos( $res->profileName, '_') );

				// if yes, then replace the profile name
				if(!empty($name)){
					$res->profileName = $name;
					}

				}

			// add fake profile thumbnail by name...
			$thumb = image::get(['name' => 'thumb'.$alt['profileID'].'.jpg']);

			// ...if exists
			if($thumb->status == 200){
				$res->thumbName = $thumb->data->name;
				}

			// if not, add fake profile its first DB thumbnail
			else{
				//
				$result = image::get_list(['profileID' => $alt['profileID'], 'moderator' => 2]);

				// on error
				if($result->status != 200){
					e::logtrigger('thumbs could not be loaded');
					return self::response(570, $result);
					}

				// thumbs list not empty
				if(!empty($result->data)){

					$res->thumbName = $result->data[0]->imageName;

					}

				}

			// cache profile for 20 minutes
			$redis->set('profile:id:'.$res->profileID, $res);
			$redis->setTimeout("profile:id:".$res->profileID, 1200);

			// return profile
			return self::response(200, $res);
			}

		// 2nd param: name
		if(!empty($alt['name'])){

			// get profile from cache
			if($redis->exists("profile:name:".$alt['name'])){
				$profile = $redis->get('profile:name:'.$alt['name']);
				return self::response(200, $profile);
				}

			// load profile
			$res = self::pdo('s_profile_by_name', $alt['name']);

			// on error or null (not found)
			if(!$res) return self::response($res === false ? 560 : 404);

			// get profile its mobileID
			$user = self::pdo('s_profile_mobile', $res->profileID);

			// on error
			if ($user === false) return self::response(560);

			// not empty array, mobileID is found, this profile is a customer profile (vs. fake profile)
			elseif($user){
				$res->isuser = true;
				$res->mobileID = $user[0]->mobileID;
				}

			// not found (empty array), try to find persistID
			else{

				// get profile its persistID (limit 1 profileID)
				$user = self::pdo('s_profile_persist', $res->profileID);

				// on error
				if($user === false) return self::response(560);

				// on not null (sql limit 1), mobileID is found, this profile is a customer profile (vs. fake profile)
				elseif($user){
					$res->isuser = true;
					$res->persistID = $user->persistID;
					}

				}

			$thumb = image::get(['name' => 'thumb'.$res->profileID.'.jpg']);

			// ...if exists
			if($thumb->status == 200){
				$res->thumbName = $thumb->data->name;
				}

			// if not, add fake profile its first DB thumbnail
			else{
				//
				$result = image::get_list(['profileID' => $res->profileID, 'moderator' => 2]);

				// on error
				if($result->status != 200){
					e::logtrigger('thumbs could not be loaded');
					return self::response(570, $result);
					}

				// thumbs list not empty
				if(!empty($result->data)){

					$res->thumbName = $result->data[0]->imageName;

					}

				}

			$redis->set('profile:name:'.$res->profileName, $res);
			$redis->setTimeout("profile:name:".$res->profileName, 1200);

			return self::response(200, $res);
			}

		// 3th param: mobileID
		if(!empty($alt['mobileID'])){

			// load latest profile for mobileID (limit 1)
			$res = self::pdo('s_profile_mobile_by_mobileID', $alt['mobileID']);

			// on error
			if($res === false) return self::response(560);

			// if null (not found)
			if(!$res){

				// and no persistID given
				if(!isset($alt['persistID'])){

					// return 404 not found
					return self::response(404);
					}
				// else continue to next request param (persistID)
				}

			// not null (found)
			else{

				// return profile from redis if exists
				if($redis->exists("profile:id:".$res->profileID)){
					$profile = $redis->get('profile:id:'.$res->profileID);
					return self::response(200, $profile);
					}

				// load profile
				$res = self::pdo('s_profile_by_profileID', $res->profileID);
				if(!$res) return self::response($res === false ? 560 : 404);

				// add customer variables to profile
				$res->isuser = true;
				$res->mobileID = $alt['mobileID'];

				// check if profileName contents a suffix (i.e. my_name_12345 transform to my_name)
				$name = substr($res->profileName, 0, strrpos( $res->profileName, '_') );

				// if yes, then replace the profile name
				if(!empty($name)){
					$res->profileName = $name;
					}

				// cache profile
				$redis->set('profile:id:'.$res->profileID, $res);
				$redis->setTimeout("profile:id:".$res->profileID, 1200);

				// return profile
				return self::response(200, $res);
				}
			}

		// 4th param: persistID
		if(!empty($alt['persistID'])){

			// load profile for persistID (primary key)
			$res = self::pdo('s_profile_persist_by_persistID', $alt['persistID']);

			// on error or not found
			if(!$res) return self::response($res === false ? 560 : 404);

			// select first element of response array
			$res = $res[0];

			// load profile from redis if exists
			if($redis->exists("profile:id:".$res->profileID)){
				$profile = $redis->get('profile:id:'.$res->profileID);
				return self::response(200, $profile);
				}

			// load profile
			$res = self::pdo('s_profile_by_profileID', $res->profileID);

			// on error or not found
			if(!$res) return self::response($res === false ? 560 : 404);

			// add customer variables to profile
 			$res->isuser = true;
 			$res->persistID = $alt['persistID'];

 			// check if profileName contents a suffix (i.e. my_name_12345 transform to my_name)
			$name = substr($res->profileName, 0, strrpos( $res->profileName, '_') );

			// if yes, then replace the profile name
			if(!empty($name)){
				$res->profileName = $name;
				}

 			// cache profile
			$redis->set('profile:id:'.$res->profileID, $res);
			$redis->setTimeout("profile:id:".$res->profileID, 1200);

			return self::response(200, $res);
			}

		// no param: return latest profile
		if(empty($alt)){

			// load latest profileID
			$res = self::pdo('s_latest_profileID');

			// on error or not found
			if(!$res) return self::response($res === false ? 560 : 404);

			return self::response(200, $res);
			}

		// on Request params error
		return self::response(400, ['profileID|name|mobileID|persistID|no param']);
		}


	public static function create($req){

		// mandatory
		$mand = h::eX($req, [
			'name'			=> '~^.{3,30}$',
			], $error);

		// optionale
		$opt = h::eX($req, [
			'age'			=> '~^[1-9]{1}[0-9]{0,2}$',
			'plz'			=> '~^.{0,160}$',
			'weight'		=> '~1,255/f',
			'height'		=> '~1,255/f',
			'description'	=> '~0,500/s',
			'imageID'		=> '~1,16777215/i',
			'mobileID'		=> '~1,4294967295/i',
			'persistID'		=> '~1,18446744073709551615/i',
			'hidden'		=> '~^[0-1]{1}$',
			'countryID'		=> '~0,255/i',
			'poolID'		=> '~0,65535/i',
			'gender'		=> '~^[mMfF]{1}$',
			'orientation'	=> '~^[mMfFbB]{1}$',
			], $error, true);
		if($error) return self::response(400, $error);

		// unique profile for a mobile
		if(isset($opt['mobileID'])){

			// load latest profile for mobileID (limit 1)
			$res = self::pdo('s_profile_mobile_by_mobileID', $opt['mobileID']);

			// on error
			if($res === false) return self::response(560);

			// found
			elseif($res){

				// return 409 conflict
				return self::response(409, 'Profile allready exists for mobileID: '.h::encode_php($opt['mobileID']));
				}
			}

		// unique profile for a persistID
		if(isset($opt['persistID'])){

			// load latest profile for persistID (limit 1)
			$res = self::pdo('s_profile_persist_by_persistID', $opt['persistID']);

			// on error
			if($res === false) return self::response(560);

			// found
			elseif($res){

				// return 409 conflict
				return self::response(409, 'Profile allready exists for persistID: '.h::encode_php($opt['persistID']));
				}
			}



		// Customer profile (mobileID and persistID are intra-library validation trusted )
		if(isset($opt['mobileID']) or isset($opt['persistID'])){

			// Customer name should be unique
			$res = self::get(['name'=>$mand['name']]);

			// on error
			if(!in_array($res->status, [200, 404])){
				return self::response(500, 'Profile '.$mand['name'].' could not be loaded');
				}

			// profile name allready exists
			elseif($res->status == 200){
				//return self::response(409, 'ProfileName allready registered: '.h::encode_php($mand['name']));
				}
			}

		// Convert height in meter to centimeters
		if(isset($opt['height']) && $opt['height'] < 3) $opt['height'] = $opt['height']*100;

		// Remove emojis
		if(isset($opt['description'])){

			// Match Emoticons
	        $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
	        $opt['description'] = preg_replace($regexEmoticons, '', $opt['description']);

	        // Match Miscellaneous Symbols and Pictographs
	        $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
	        $opt['description'] = preg_replace($regexSymbols, '', $opt['description']);

	        // Match Transport And Map Symbols
	        $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';

       		}

		// create entry
		$profileID = self::pdo('i_profile', [
			$mand['name'],
			isset($opt['age']) 			? $opt['age'] 			: 0,
			!empty($opt['plz']) 		? $opt['plz'] 			: 0,
			isset($opt['height']) 		? $opt['height'] 		: 0,
			isset($opt['weight']) 		? $opt['weight'] 		: 0,
			!empty($opt['description']) ? $opt['description'] 	: 'no description',
			isset($opt['imageID']) 		? $opt['imageID'] 		: 0,
			isset($opt['hidden']) 		? $opt['hidden'] 		: 0,
			!empty($opt['countryID']) 	? $opt['countryID'] 	: 1,
			!empty($opt['poolID'])	 	? $opt['poolID']	 	: 0,
			isset($opt['gender']) 		? $opt['gender'] 		: 'M',
			isset($opt['orientation'])	? $opt['orientation']	: 'F',
			]);
		if($profileID === false) return self::response(560);

		// save profile/mobile association
		if(isset($opt['mobileID'])){
			$res = self::pdo('i_profile_mobile', [$profileID, $opt['mobileID']]);
			if($res === false) return self::response(560);
			}

		// save profile/persist association
		elseif(isset($opt['persistID'])){
			$res = self::pdo('i_profile_persist', [$opt['persistID'], $profileID]);
			if($res === false) return self::response(560);
			}


		return self::response(201, (object)['profileID'=>$profileID]);

		}


	public static function update($req){

		// mandatory
		$mand = h::eX($req, [
			'profileID'		=> '~1,16777215/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~^.{3,30}$',
			'age'			=> '~^[1-9]{1}[0-9]{0,2}$',
			'plz'			=> '~^.{0,160}$',
			'weight'		=> '~1,255/f',
			'height'		=> '~1,255/f',
			'description'	=> '~0,500/s',
			'imageID'		=> '~0,16777215/i', // need 0 to reset imageID after deleting front picture
			'hidden'		=> '~^[0-1]{1}$',
			'countryID'		=> '~1,255/i',
			'poolID'		=> '~1,65535/i',
			'gender'		=> '~^[mMfF]{1}$',
			'orientation'	=> '~^[mMfFbB]{1}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// Convert height in meter to height centimeters
		if(isset($opt['height']) && $opt['height'] < 3) $opt['height'] = $opt['height']*100;

		// Remove emojis
		if(isset($opt['description'])){

			// Match Emoticons
	        $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
	        $opt['description'] = preg_replace($regexEmoticons, '', $opt['description']);

	        // Match Miscellaneous Symbols and Pictographs
	        $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
	        $opt['description'] = preg_replace($regexSymbols, '', $opt['description']);

	        // Match Transport And Map Symbols
	        $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';

       		}

		// load profile
		$res = self::get(['profileID'=>$mand['profileID']]);

		// on error
		if(!in_array($res->status, [200, 404])){
			return self::response(500, 'Profile could not be loaded for profileID '.$mand['profileID']);
			}

		// not found
		elseif($res->status == 404){
			return self::response(406, 'Unknown profileID: '.h::encode_php($mand['profileID']));
			}

		// assign
		$profile = $res->data;

		// Customer profile
		if(!empty($profile->isuser)){

			// param name is set
			if(!empty($opt['name'])){

				// make username unique by concatenate it with mobileID or persistID
				//$opt['name'] .= '_'.($profile->mobileID ?? $profile->persistID);

				}

			}

		// update profile
		foreach($opt as $k=>$v){

			// update property value
			$res = self::pdo('u_profile_'.$k,[$v, $mand['profileID']]);

			// on error
			if($res === false) return self::response(560);

			}

		// refresh cached profile
		$redis = self::redis();
		if($redis){
			$redis->setTimeout("profile:id:".$mand['profileID'], 0);
			}

		return self::response(204);
		}


	public static function delete($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'profileID'	=> '~1,16777215/i',
			], $error);
		if($error) return self::response(400, $error);

		// check profile
		$res = self::pdo('s_profile_by_profileID', [$mand['profileID']]);
		if(!$res) return self::response($res === false ? 560 : 404);

		// load images
		$result = image::get_list(['profileID' => $mand['profileID']]);

		// on error
		if($result->status != 200) return $result;

		// images list is not empty
		if(!empty($result->data)){

			// loop images
			foreach ($result->data as $image) {

				// delete image data
				$del = image::delete(['imageID' => $image->imageID]);

				// on error
				if($del->status != 200){
    				e::logtrigger('image '.$image->imageID.' couldnt be deleted from database: '.h::encode_php($del));
					continue;
    				}

    			// delete image file
    			if(strtolower(pathinfo($image->imageName, PATHINFO_EXTENSION)) != "mp4"){

	    			if(file_exists($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$mand['profileID'].'/'.$image->imageName)){
						unlink($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$mand['profileID'].'/'.$image->imageName);
						}
					}

				// delete video file
    			if(strtolower(pathinfo($image->imageName, PATHINFO_EXTENSION)) == "mp4"){
	    			if(file_exists($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$mand['profileID'].'/videos/'.$image->imageName)){
						unlink($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$mand['profileID'].'/videos/'.$image->imageName);
						}
					}
				}
			}

		// assign variable
		$dir = $_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$mand['profileID'].'/videos/';

		// if videos folder exists
		if(is_dir($dir)){

			// and is empty
			if(count(scandir($dir)) == 2){

				// remove it
				if(!rmdir($dir)){
					e::logtrigger('videos directory for profile '.$mand['profileID'].' couldnt be deleted.');
					}
				}
			}

		// assign variable
		$dir = $_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$mand['profileID'];

		// if images folder exists
		if(is_dir($dir)){

			// and is empty
			if(count(scandir($dir)) == 2){

				// remove it
				if(!rmdir($dir)){
					e::logtrigger('images directory for profile '.$mand['profileID'].' couldnt be deleted.');
					}
				}
			}

		// delete profile from database
		$delete = self::pdo('d_profile_by_profileID', [$mand['profileID']]);
		if($delete === false) return self::response(560);

		return self::response(200);
		}


	public static function copy($req = []){

		// mandatory
		$mand = h::eX($req, [
			'profileID'	=> '~1,16777215/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'poolID'	=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// get profile
		$res = self::get(['profileID' => $mand['profileID']]);

		// on error or not found
		if($res->status != 200) return $res;

		// assign profile variable
		$profile = $res->data;
		$profileID = $profile->profileID;
		$profile_imageID = $profile->imageID;
		$wvga_copy = false; // make a light 480 px copy of each image

		// remove image ID
		$profile = (array)$profile;
		unset($profile['imageID']);

		// get images list of the profile
		$res = image::get_list(['profileID'=>$profileID]);

		// on error
		if($res->status != 200) return $res;

		$images = $res->data;

		// get pool
		$res = pool::get(['poolID'=>$opt['poolID']]);

		// on error
		if($res->status != 200) return $res;

		// set new pool ID
		$profile['poolID'] = $res->data->poolID;

		// set country from pool
		$profile['countryID'] = $res->data->countryID;

		// hide from active profiles list because this copy of profile has first to be customized by content admin
		$profile['hidden'] = 1;

		// rename "name" variable to database column name
		$profile['name'] = $profile['profileName'];

		// create profile copy
		$res = self::create($profile);

		/// on error
		if($res->status != 201) return $res;

		// get the new profile
		$result = self::get(['profileID' => $res->data->profileID]);

		// on error OR not found
		if($result->status != 200) return $res;

		// assign profile copy variable
		$copy_profile = $result->data;

		// Create new profile pictures directory
		$copy_profile_path = $_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$copy_profile->profileID;
		if(!is_dir($copy_profile_path)){
			if(!mkdir($copy_profile_path, 0755, true)) return self::response(560);
			}

		// Create new profile videos directory
		$copy_profile_video_path = $_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$copy_profile->profileID.'/videos';
		if(!is_dir($copy_profile_video_path)){
			if(!mkdir($copy_profile_video_path, 0755, true)) return self::response(560);
			}

		// copy all images
		foreach($images as $image){

			// set source profile image or video path
			if(strtolower(pathinfo($image->imageName, PATHINFO_EXTENSION)) == "mp4"){
				$src_image = $_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profileID.'/videos/'.$image->imageName;
				}
			else{
				$src_image = $_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profileID.'/'.$image->imageName;
				}

			// check if image or video exists
			if (is_file($src_image)) {

				// don't copy _wvga copies
				if (stripos($image->imageName, '_WVGA') == FALSE ) {

					// create new image
					$res = image::create();

					// on error
	            	if($res->status != 201) return $res;

	            	// assign variables
	            	$copy_imageID = $res->data->imageID;
	            	$copy_image_ext = strtolower(pathinfo($image->imageName, PATHINFO_EXTENSION));

	            	// copy thumb
	            	if (stripos($image->imageName, 'thumb') !== FALSE ) {
	            		$copy_image_name = 'thumb'.$copy_profile->profileID.'.'.$copy_image_ext;
	            		}
	            	// copy video
	            	elseif ($copy_image_ext == 'mp4') {
	            		$copy_image_name = 'v'.$copy_imageID.'.'.$copy_image_ext;
	            		}
	            	// copy picture
	            	else {
	            		$copy_image_name = $copy_imageID.'.'.$copy_image_ext;
	            		}

	            	// update image
	            	$res = image::update([
	            		'imageID' => $copy_imageID,
	            		'name' => $copy_image_name,
	            		'profileID' => $copy_profile->profileID,
	            		'mod' => !empty($image->moderator) ? $image->moderator : 0,
	            		'fsk' => $image->fsk
	            		]);

	            	// on error
	                if($res->status != 204) return $res;

					// set destination profile image/video path
					if($copy_image_ext == "mp4"){
						$dest_image = $_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$copy_profile->profileID.'/videos/'.$copy_image_name;
						}
					else{
						$dest_image = $_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$copy_profile->profileID.'/'.$copy_image_name;
						}

					// copy image or video
					if (!copy($src_image, $dest_image))  return self::response(560);


					/* Make a 480px copy (not for thumb/video) */
					if($wvga_copy){
						if($copy_image_ext != 'mp4'){
							if(stripos($copy_image_name, 'thumb') === false){
								$profile_path =$_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$copy_profile->profileID;
								$file = $profile_path.'/'.$copy_image_name;
								$resizedFile = $profile_path.'/'.$copy_imageID.'_WVGA.'.$copy_image_ext;

								list($width, $height) = getimagesize($file);
								if($width >= $height){ /* landscape */
									$new_width = 1000;
									$new_height = 480;
									}
								else{ 					/* portrait */
									$new_width = 480;
									$new_height = 1000;
									}

								$ratio_orig = $width/$height;

								if ($new_width/$new_height > $ratio_orig) {
								   	$new_width = $new_height*$ratio_orig;
									}
								else {
								   	$new_height = $new_width/$ratio_orig;
									}

								$image_p = imagecreatetruecolor($new_width, $new_height);
								if(in_array($copy_image_ext, ['jpg', 'jpeg'])){

									// increase temporarly php memory size if image need it
									$imageInfo = getimagesize($file);
									$memoryNeeded = round(($imageInfo[0] * $imageInfo[1] * $imageInfo['bits'] * $imageInfo['channels'] / 8 + Pow(2,16)) * 1.65);
									if (function_exists('memory_get_usage') && memory_get_usage() + $memoryNeeded > (integer) ini_get('memory_limit') *pow(1024, 2)) {
									 	ini_set('memory_limit', (integer) ini_get('memory_limit') + ceil(((memory_get_usage() + $memoryNeeded) - (integer) ini_get('memory_limit') * pow(1024, 2)) / pow(1024, 2)) . 'M');
										}
									$image_q = imagecreatefromjpeg($file);
									imagecopyresampled($image_p, $image_q, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
									imagejpeg($image_p, $resizedFile, 75); // compression from 0 (bad quality) to 100 (> 200Kb)
									}
								// png
								else{
									$image_q = imagecreatefrompng($file);
									imagecopyresampled($image_p, $image_q, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
									imagepng($image_p, $resizedFile, 7); // compression from 0 (bad quality) to 9 (> 200Kb)
									}

								$image_name = $copy_imageID.'_WVGA.'.$copy_image_ext;
								$res = image::create(['profileID'=>$copy_profile->profileID, 'name'=>$image_name]);
								if($res->status != 201) return $res;
								}
							}
						}

					// set profile front image
					if($image->imageID == $profile_imageID){
						$res = profile::update(['profileID'=>$copy_profile->profileID, 'imageID' => $copy_imageID]);
						if($res->status != 204) return $res;
						}

					}
				}
			}

		// return success
		return self::response(201, (object)['profileID' => $copy_profile->profileID]);
		}


	public static function get_list($req = []){

		// optional
		$opt = h::eX($req, [
			'countryID'		=> '~1,255/i',
			'poolID'		=> '~1,65335/i',
			'hidden'		=> '~^[0-1]{1}$',
			'since'			=> '~1,255/i',
			'gender'		=> '~^[mMfF]{1}$',
			'orientation'	=> '~^[mMfFbB]{1}$',
			'pools'			=> [],
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if no param, get all profiles
		if(empty($req)){

			// load profiles
			$res = self::pdo('l_profiles');

			// return result
			return ($res === false) ? self::response(560) : self::response(200, $res);

			}

		// 1st param
		if(isset($opt['countryID'])){

			// load profiles by countryID
			$res = self::pdo('l_profiles_by_countryID', [$opt['hidden'] ?? 0, $opt['countryID']]);

			// return result
			return ($res === false) ? self::response(560) : self::response(200, $res);

			}

		// 2nd params
		if(isset($opt['poolID']) and isset($opt['since'])){

			// load profiles by poolID and since
			$res = self::pdo('l_profiles_by_poolID_since', [$opt['hidden'] ?? 0, $opt['poolID'], $opt['since']]);

			// return result
			return ($res === false) ? self::response(560) : self::response(200, $res);

			}

		// 3th params
		if(isset($opt['since'])){

			// load profiles since
			$res = self::pdo('l_profiles_since', [$opt['hidden'] ?? 0, $opt['since']]);

			// return result
			return ($res === false) ? self::response(560) : self::response(200, $res);

			}

		// 4th param
		if(isset($opt['poolID'])){

			// load profiles by poolID
			$res = self::pdo('l_profiles_by_poolID', [$opt['hidden'] ?? 0, $opt['poolID']]);

			// return result
			return ($res === false) ? self::response(560) : self::response(200, $res);

			}

		// 5th param
		if(isset($opt['hidden'])){

			// load hidden profiles
			$res = self::pdo('l_hidden_profiles', [$opt['hidden']]);

			if($res === false) return self::response(560);

			foreach($res as $key => $profile){

				// get images
				$result = image::get_list(['profileID' => $profile->profileID]);

				$profile->images = $result->status == 200 ? $result->data : [];

				}

			// return result
			return self::response(200, $res);

			}

		// 6th param
		if(isset($opt['pools'])){

			$in = implode(",", $opt['pools']);

			// load profiles by poolID and since
			$res = self::pdo('l_profileIDs_by_pools', $in);

			// return result
			return ($res === false) ? self::response(560) : self::response(200, $res);
			}

		// invalid params
		return self::response(400, ['need countryID, poolID, hidden or no parameter']);

		}


	public static function get_users_list($req = []){

		// optional
		$opt = h::eX($req, [
			'countryID'	=> '~1,255/i',
			'poolID'	=> '~1,65535/i',
			'hidden'	=> '~^[0-1]{1}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if no param, get all profiles
		if(empty($req)){

			// load customer profiles having uploaded pics
			$res = self::pdo('l_users_with_pics');

			// return result
			return ($res === false) ? self::response(560) : self::response(200, $res);

			}

		// 1st param
		if(isset($opt['countryID'])){

			// load profiles by countryID
			$res = self::pdo('l_profiles_by_countryID', [$opt['hidden'] ?? 0, $opt['countryID']]);

			// return result
			return ($res === false) ? self::response(560) : self::response(200, $res);

			}

		// 2nd param
		if(isset($opt['poolID'])){

			// load profiles by poolID
			$res = self::pdo('l_profiles_by_poolID', [$opt['hidden'] ?? 0, $opt['poolID']]);

			// return result
			return ($res === false) ? self::response(560) : self::response(200, $res);

			}

		// 3th param
		if(isset($opt['hidden'])){

			// load hidden profiles
			$res = self::pdo('l_hidden_profiles', [$opt['hidden']]);

			// return result
			return ($res === false) ? self::response(560) : self::response(200, $res);

			}

		// invalid params
		return self::response(400, ['need countryID, poolID, hidden or no parameter']);

		}


	public static function get_profile($req = []){

		// altenativ
		$alt = h::eX($req, [
			'profileID'		=> '~1,16777215/i',
			'name'			=> '~^.{3,30}$',
			'mobileID'		=> '~1,4294967295/i',
			'persistID'		=> '~1,18446744073709551615/i',
			'poolID'		=> '~1,65335/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'gender'		=> '~^[MF]$',
			'orientation'	=> '~^[MFB]$',
			'fsk'			=> '~0,255/i',
			'since'			=> '~0,255/i',
			'hidden'		=> '~/b',
			], $error, true);


		// on error
		if($error) return self::response(400, $error);

		// get redis instance
		$redis = self::redis();

		// on error
		if(!$redis or !$redis->isConnected()){
			return self::response(500, 'Connection to Redis could not be established: '.h::encode_php($redis));
			}

		// check unique constraint on name and poolID
		if(!empty($alt['name']) && isset($alt['poolID'])){

			// load profile
			$res = self::pdo('s_profile_by_name_poolID', [$alt['name'], $alt['poolID']]);

			// on error or not found
			if(!$res) return self::response($res === false ? 560 : 404);

			// take profile
			$profile = $res->data;
			}

		// param: profileID
		if(isset($alt['profileID'])){

			// if entry exists
			if($redis->exists("profile:".$alt['profileID'].(isset($opt['fsk']) ? ':fsk'.$opt['fsk'] : ''))){

				// return entry
				return self::response(200, $redis->get("profile:".$alt['profileID'].(isset($opt['fsk']) ? ':fsk'.$opt['fsk'] : '')));
				}

			// load profile
			$res = self::pdo('s_profile_by_profileID', $alt['profileID']);

			// on error or null (not found)
			if(!$res) return self::response($res === false ? 560 : 404);

			// get profile its mobileID
			$user = self::pdo('s_profile_mobile', $alt['profileID']);

			// on error
			if($user === false) return self::response(560);

			// not empty array, mobileID is found, this profile is a customer profile (vs. fake profile)
			elseif($user){
				$res->isuser = true;
				$res->mobileID = $user[0]->mobileID;
				}

			// not found (empty array), try to find persistID
			else{

				// get profile its persistID (limit 1 profileID)
				$user = self::pdo('s_profile_persist', $alt['profileID']);

				// on error
				if($user === false) return self::response(560);

				// on not null (sql limit 1), mobileID is found, this profile is a customer profile (vs. fake profile)
				elseif($user){
					$res->isuser = true;
					$res->persistID = $user->persistID;
					}

				}

			// TEMP with unique key name_pool : check if user profileName contents a suffix (i.e. my_name_12345 transform to my_name)
			if($user){
				$name = substr($res->profileName, 0, strrpos( $res->profileName, '_') );

				// if yes, then replace the profile name
				if(!empty($name)){
					$res->profileName = $name;
					}
				}

			$profile = $res;
			}

		// param: mobileID
		if(isset($alt['mobileID'])){

			// if entry exists
			if($redis->exists("profileID:by_mobileID:".$alt['mobileID'])){

				// return entry
				return self::response(200, $redis->get("profileID:by_mobileID:".$alt['mobileID']));
				}

			// load profile for mobileID (limit 1)
			$res = self::pdo('s_profile_mobile_by_mobileID', $alt['mobileID']);

			// on error
			if($res === false) return self::response(560);

			// not found
			if(!$res){

				// and no fallback param persistID given
				if(!isset($alt['persistID'])){

					// return 404 not found
					return self::response(404);
					}
				// else continue to next request param (persistID)
				}

			// found
			else{

				// if entry exists (fsk filter is not relevant for user profile)
				if($redis->exists("profile:".$res->profileID)){

					// return entry
					return self::response(200, $redis->get("profile:".$res->profileID));
					}

				// load profile
				$res = self::pdo('s_profile_by_profileID', $res->profileID);
				if(!$res) return self::response($res === false ? 560 : 404);

				// add customer variables to profile
				$res->isuser = true;
				$res->mobileID = $alt['mobileID'];

				// TEMP: linked with unique key name_poolID_countryID,  check if profileName contents a suffix (i.e. my_name_12345 transform to my_name)
				$name = substr($res->profileName, 0, strrpos( $res->profileName, '_') );

				// if yes, then replace the profile name
				if(!empty($name)){
					$res->profileName = $name;
					}

				// take profile
				$profile = $res;
				}

			}

		// param: persistID
		if(isset($alt['persistID']) and !isset($profile)){

			// if entry exists
			if($redis->exists("profileID:by_persistID:".$alt['persistID'])){

				// return entry
				return self::response(200, $redis->get("profileID:by_persistID:".$alt['persistID']));
				}

			// load profile for persistID (primary key)
			$res = self::pdo('s_profile_persist_by_persistID', $alt['persistID']);

			// on error or not found
			if(!$res) return self::response($res === false ? 560 : 404);

			// select first element of response array
			$res = $res[0];

			// if entry exists (fsk filter is not relevant for user profile)
			if($redis->exists("profile:".$res->profileID)){

				// return entry
				return self::response(200, $redis->get("profile:".$res->profileID));
				}

			// load profile
			$res = self::pdo('s_profile_by_profileID', $res->profileID);

			// on error or not found
			if(!$res) return self::response($res === false ? 560 : 404);

			// add customer variables to profile
 			$res->isuser = true;
 			$res->persistID = $alt['persistID'];

 			// check if profileName contents a suffix (i.e. my_name_12345 transform to my_name)
			$name = substr($res->profileName, 0, strrpos( $res->profileName, '_') );

			// if yes, then replace the profile name
			if(!empty($name)){
				$res->profileName = $name;
				}

			$profile = $res;
			}

		// define function to add images to profiles
		$expand_profile = function($profile_list, $image_list) use ($opt){

			// convert single profile obj to list
			$single = is_object($profile_list);
			if(is_object($profile_list)) $profile_list = [$profile_list];

			// define associative list
			$assoc = [];

			// define
			foreach($profile_list as $key => $profile){
				if(isset($opt['since']) and $profile->createTime < $opt['since']) continue;
				if(isset($opt['gender']) and $profile->gender != $opt['gender']) continue;
				if(isset($opt['orientation']) and $profile->orientation != $opt['orientation']) continue;
				$assoc[$profile->profileID] = $profile;
				$profile->images = [];
				}

			foreach($image_list as $image){

				// skip images from unknown profiles
				if(!isset($assoc[$image->profileID])) continue;

				// if max fsk is defined, check if profile main image is fsk-invalid
				if(isset($opt['fsk']) and $opt['fsk'] < $image->fsk and isset($assoc[$image->profileID]->imageID) and $image->imageID == $assoc[$image->profileID]->imageID){

					// remove profile main image
					$assoc[$image->profileID]->imageID = 0;
					$assoc[$image->profileID]->imageName = "";

					// try to set a fsk-valid one
					if(isset($assoc[$image->profileID]->images[0])){

						$assoc[$image->profileID]->imageID = $assoc[$image->profileID]->images[0]->imageID;
						$assoc[$image->profileID]->imageName = $assoc[$image->profileID]->images[0]->name;
						}
					}

				// if max fsk is defined, skip if images fsk is higher
				if(isset($opt['fsk']) and $opt['fsk'] < $image->fsk) continue;

				// TEMP: fixing old thumb images to moderator 2
				if($image->moderator == 0 and strpos($image->name, 'thumb') !== false) $image->moderator = 2;

				// for basic images
				if($image->moderator == 0){

					// add image to corresponding profile
					$assoc[$image->profileID]->images[] = $image;

					// set profile image if not set
					if(empty($assoc[$image->profileID]->imageID)){

						$assoc[$image->profileID]->imageID = $image->imageID;
						$assoc[$image->profileID]->imageName = $image->name;
						}
					}

				// for thumbnails
				if($image->moderator == 2 and empty($assoc[$image->profileID]->image_preview)){

					// add thumbnail
					$assoc[$image->profileID]->image_preview = $image->name;
					}

				}

			// profile imageID is empty


			return $single ? current($assoc) : array_values($assoc);

			};

		// param: poolID for profiles list
		if(isset($alt['poolID'])){

			// if entry exists
			if($redis->exists('profile_list:'.$alt['poolID'].':filter:'.($opt['gender'] ?? '_').':'.($opt['orientation'] ?? '_').':'.($opt['fsk'] ?? '18'))){

				// return entry
				return self::response(200, $redis->get('profile_list:'.$alt['poolID'].':filter:'.($opt['gender'] ?? '_').':'.($opt['orientation'] ?? '_').':'.($opt['fsk'] ?? '18')));
				}

			// load pool profiles
			$res = self::pdo('l_profiles_by_poolID', [$opt['hidden'] ?? 0, $alt['poolID']]);

			// on error
			if($res === false) self::response(560);

			// take profiles list
			$profile_list = $res;

			// load pool images
			$res = self::pdo('l_profiles_images', $alt['poolID']);

			// on error
			if($res === false) self::response(560);

			// take images
			$image_list = $res;

			$profile_list = $expand_profile($profile_list, $image_list);

			$cache_key = 'profile_list:'.$alt['poolID'].':filter:'.($opt['gender'] ?? '_').':'.($opt['orientation'] ?? '_').':'.($opt['fsk'] ?? '18'); // profile list
			// profile_list:1:filter:_:_:18
			// profile_list:1:filter:M:F:16
			// profile_list:1:filter:M:F:_

			// cache entry
			$redis->set($cache_key, $profile_list, ['ex'=>1200, 'nx']); // 20 minutes

			// return profiles list
			return self::response(200, $profile_list);

			}

		// for specific profile
		if(isset($profile)){

			// load images
			$result = self::pdo('l_images_by_profileID', $profile->profileID);

			// on error
			if($result === false) self::response(560);

			// take image list
			$image_list = $result;

			$profile = $expand_profile($profile, $image_list);

			// define cache key
			if(isset($alt['profileID'])) 	 $cache_key = 'profile:'.$alt['profileID'].(isset($opt['fsk']) ? ':fsk'.$opt['fsk'] : ''); // profile:1 or profile:1:fsk16
			elseif(isset($alt['mobileID']))  $cache_key = 'profileID:by_mobileID:'.$alt['mobileID'];
			elseif(isset($alt['persistID'])) $cache_key = 'profileID:by_persistID:'.$alt['persistID'];

			// cache entry
			$redis->set($cache_key, $profile, ['ex'=>1200, 'nx']); // 20 minutes

			// return profile
			return self::response(200, $profile);
			}

		// other request param invalid
		return self::response(400, 'need profileID, mobileID, persistID, name or poolID');

		}

	}