<?php
/*****
 * Version	 	1.0.2018-06-28
**/
namespace dotdev\app;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\app\bragi\profile;
use \dotdev\app\bragi\message;
use \dotdev\app\bragi\image;
use \dotdev\app\bragi\pool;
use \dotdev\mobile;
use \dotdev\nexus\base;
use \tools\redis;
use \dimoco\paysms;
use \dotdev\nexus\service;

class bragi {
	use \tools\libcom_trait;

	public static function redis(){

		return redis::load_resource('app_bragi');
		}


	public static function get_chat($req){

		// TEMP DENNIS: Fix for startTime Bug in Flirrdy (remove when apk is fixed)
		$req = (array) $req;
		if(isset($req['startTime']) and !h::is($req['startTime'], '~Y-m-d H:i:s/d')) unset($req['startTime']);

		// Alternativ
		$alt = h::eX($req, [
			'mobileID'	=> '~1,4294967295/i',
			'persistID'	=> '~1,18446744073709551615/i',
			'profileID'	=> '~1,16777215/i',
			'msisdn'	=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'imsi'			=> '~^[1-9]{1}[0-9]{5,15}$',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'startTime' => '~Y-m-d H:i:s/d',
			'unread'	=> '~[0-1]{1}$',
			'messageID'	=> '~1,4294967295/i',
			'poolID'	=> '~0,255/i',
			'fsk'		=> '~0,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// get Chat by mobile ID and profile ID
		if(!empty($alt['mobileID']) && !empty($alt['profileID'])) {

			// Get mobile
			$res = mobile::get(['mobileID' => $alt['mobileID']]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// mobile not found
			elseif($res->status == 404){
				$err_text = 'MobileID '.h::encode_php($alt['mobileID']).' not found';
				e::logtrigger($err_text);
				return self::response(406, $err_text);
				}

			// get consistent migrated mobileID
			$alt['mobileID'] = $res->data->mobileID;

			// Get profile
			$res = profile::get(['profileID' => $alt['profileID']]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// profile not found
			elseif($res->status == 404){
				$err_text = 'profileID '.h::encode_php($alt['profileID']).' not found';
				e::logtrigger($err_text);
				return self::response(406, $err_text);
				}

			// get chat
			$res = message::get([
				'mobileID'	=>$alt['mobileID'],
				'profileID'	=>$alt['profileID']
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// only unread messages
			if(!empty($opt['unread'])){
				foreach($res->data as $key => $message) {
					if($message->read) unset($res->data[$key]);
					}
				}

			// return chat
			return $res;
			}


		// param: IMSI
		if(!empty($alt['imsi'])){

			// get mobile
			$res = mobile::get(["imsi" => $alt['imsi']]);

			// on error
			if(!in_array($res->status, [200, 404])){
				return self::response(500, h::encode_php('Mobile could not be loaded: '.$res->status));
				}

			// not found
			elseif($res->status == 404){
				return self::response(404, 'Unknown Mobile imsi: '.h::encode_php($alt['imsi']));
				}

			// assign
			$alt['mobileID'] = $res->data->mobileID;
			}

		// customer params
		if(!empty($alt['mobileID']) or !empty($alt['persistID'])){

			// group params
			$alt = $alt + $opt;

			// get chat
			$res = message::get($alt);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// select only unread messages
			if(!empty($opt['unread'])){

				foreach($res->data as $key => $message){
					if($message->read)	unset($res->data[$key]);
					}
				}

			// try to get smsgateID from last message if exists
			if(!empty($res->data)){

				$smsgateID = $res->data[0]->smsgateID;

				if(isset($alt['mobileID'])){

					// get unlocked status (monthly MO amount = 200 + 50-tuple and one of the last MO's text is a confirming text)
					$result = message::month_MOs_unlocked(['mobileID' => $alt['mobileID'], 'smsgateID' => $smsgateID]);
					}
				else{

					// get unlocked status (monthly MO amount = 200 + 50-tuple and one of the last MO's text is a confirming text)
					$result = message::month_MOs_unlocked(['persistID' => $alt['persistID'], 'smsgateID' => $smsgateID]);
					}

				// on success, assign value
				if($result->status == 200){
					$unlocked = $result->data;
					}

				// on error unlock user anyway
				else {
					$unlocked = true;
					}
				}

			// chat is empty, unlock user anyway
			else {
				$unlocked = true;
				}

			// declare variables
			$profiles = [];

			// prepare messages for return
			foreach ($res->data as $key => $message) {

				// if current message contents a profileID
				if(!isset($profiles['profileID'.$message->profileID])){

					// get profile
					$res = self::get_profile(['profileID' => $message->profileID, 'fsk' => $opt['fsk'] ?? null]);

					// on error
					if(!in_array($res->status, [200, 404])) return self::response(570, $res);

					// profile not found
					elseif($res->status == 404){

						continue; // anyway with other profiles/chats
						}

					// initialize a new index in profiles list
					$profiles['profileID'.$message->profileID] = [];

					// insert the related profile into profiles list
					if (is_array($res->data) || is_object($res->data)){
						foreach ($res->data as $key => $value) {
							$profiles['profileID'.$message->profileID][$key] = $value;
							}
						}

					}

				// initialize a new index in profile array
				if(!isset($profiles['profileID'.$message->profileID]['msgs'])) $profiles['profileID'.$message->profileID]['msgs'] = [];

				// insert messages in profile array
				array_push($profiles['profileID'.$message->profileID]['msgs'], $message);
				}

			// param poolID
			if(isset($opt['poolID'])){

				// loop chats
				foreach($profiles as $key => $chat) {

					if(isset($chat['poolID'])){

						// remove non-pool chats
						if($chat['poolID'] != $opt['poolID']) unset($profiles[$key]);

						}
					else{

						// remove non-pool chats
						unset($profiles[$key]);
						}

					}
				}

			// add monthly limit status to messages list
			$profiles += ['locked'=> !$unlocked];

			$res->data = $profiles;

			// return chat
			return $res;
			}

		if(!empty($alt['profileID'])){

			// Get profile
			$res = profile::get(['profileID' => $alt['profileID']]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// profile not found
			elseif($res->status == 404){
				$err_text = 'profileID '.h::encode_php($alt['profileID']).' not found';
				e::logtrigger($err_text);
				return self::response(406, $err_text);
				}

			// get chat
			$res = message::get([
				'profileID'	=>$alt['profileID']
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// only select unread messages
			if(!empty($opt['unread'])){
				foreach($res->data as $key => $message) {
					if($message->read) unset($res->data[$key]);
					}
				}

			// return chat
			return $res;
			}

		if(!empty($alt['msisdn'])){

			// get mobile
			$res = mobile::get(['msisdn'=>$alt['msisdn'][0]]);

			if(!in_array($res->status, [200, 404])){
				return self::response(570, $res);
				}

			// mobile not found
			elseif($res->status == 404){
				$err_text = 'mobile '.h::encode_php($alt['msisdn'][0]).' not found';
				e::logtrigger($err_text);
				return self::response(406, $err_text);
				}

			// assign mobile ID
			$mobileID = $res->data->mobileID;

			// get chat
			$res = message::get(['mobileID'=>$mobileID]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// only select unread messages
			if(!empty($opt['unread'])){
				foreach($res->data as $key => $message) {
					if($message->read)	unset($res->data[$key]);
					}
				}

			// return chat
			return $res;
			}

		return self::response(400, ['mobileID|profileID|msisdn']);

		}


	public static function delete_chat($req){

		// mandatory
		$mand = h::eX($req, [
			'mobileID'	=> '~1,4294967295/i',
			'profileID'	=> '~1,16777215/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// Get mobile
		$res = mobile::get(['mobileID' => $mand['mobileID']]);

		// on error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// mobile not found
		elseif($res->status == 404){
			$err_text = 'MobileID '.h::encode_php($mand['mobileID']).' not found';
			e::logtrigger($err_text);
			return self::response(406, $err_text);
			}

		// Get profile
		$res = profile::get(['profileID' => $mand['profileID']]);

		// on error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// profile not found
		elseif($res->status == 404){
			$err_text = 'profileID '.h::encode_php($mand['profileID']).' not found';
			e::logtrigger($err_text);
			return self::response(406, $err_text);
			}

		// set chat's 'deleted' field to 1
		$res = message::archive(['mobileID'=>$mand['mobileID'], 'profileID'=>$mand['profileID']]);

		// on success return status 204 else 500
		return $res->status == 204 ? self::response(204) : self::response(570, $res);

		}


	public static function delete_message($req){

		// mendatory
		$mand = h::eX($req, [
			'messageID'	=> '~0,4294967295/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// set message's 'deleted' field to 1 if messageID exists
		$res = message::archive(['messageID'=>$mand['messageID']]);

		// on success return status 204 else 500
		return $res->status = 204 ? self::response(204) : self::response(570, $res);

		}


	public static function get_profile($req){

		// alternativ
		$alt = h::eX($req, [
			'profileID'	=> '~1,16777215/i',
			'name'		=> '~^[a-zA-Z0-9_-]{3,30}$',
			'mobileID'	=> '~1,4294967295/i',
			'persistID'	=> '~1,18446744073709551615/i',
			'imsi'		=> '~^[1-9]{1}[0-9]{5,15}$',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'fsk'		=> '~0,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param 1: profileID
		if(!empty($alt['profileID'])){

			// Get profile by profileID
			$res = profile::get(['profileID' => $alt['profileID']]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// profile not found
			elseif($res->status == 404){
				$err_text = 'profileID '.h::encode_php($alt['profileID']).' not found';
				e::logtrigger($err_text);
				return self::response(404, $err_text);
				}

			// age limitation on profile image
			if(isset($opt['fsk'])){

				// profile is not empty
				if(!empty($res->data)){

					// profile imageID is not null
					if($res->data->imageID != 0){

						// get image
						$result = image::get(['imageID' => $res->data->imageID]);

						// on error
						if(!in_array($result->status, [200, 404])) return self::response(570, $result);

						// not found
						elseif($result->status == 404){
							$err_text = 'image with imageID '.h::encode_php($res->data->imageID).' not found';
							e::logtrigger($err_text);
							return self::response(406, $err_text);
							}

						// profile image FSK exceed requested age limitation
						if($result->data->fsk > $opt['fsk']){

							// get profile images with an FSK <= requested FSK
							$result = image::get_list(['profileID' => $alt['profileID'], 'fsk' => $opt['fsk']]);

							// on error
							if($result->status != 200){
								e::logtrigger('images could not be loaded');
								return self::response(570, $result);
								}

							// images list not empty
							if(!empty($result->data)){

								// replace profile image
								foreach($result->data as $img){

									// exclude default historic thumbnail
									if(strpos($img->imageName, 'thumb') === false){

										// set image as profile image
										$res->data->imageID = $img->imageID;
										$res->data->imageName = $img->imageName;

										// exit foreach
										break;
										}
									}
								}

							// fsk limited images list is empty, set image to empty
							else{
								$res->data->imageID = 0;
								$res->data->imageName = '';
								}
							}
						}

					// profile thumbnail is set
					if(!empty($res->data->thumbName)){

						// get image
						$thumb = image::get(['name' => $res->data->thumbName]);

						// on error
						if(!in_array($thumb->status, [200, 404])) return self::response(570, $thumb);

						// not found
						elseif($thumb->status == 404){
							$err_text = 'thumbnail '.h::encode_php($res->data->thumbName).' not found';
							e::logtrigger($err_text);
							return self::response(406, $err_text);
							}

						// fsk NOK
						if($thumb->data->fsk > $opt['fsk']){

							// get profile thumbs
							$result = image::get_list(['profileID' => $alt['profileID'], 'moderator' => 2]);

							// on error
							if($result->status != 200){
								e::logtrigger('images could not be loaded');
								return self::response(570, $result);
								}

							// thumbs list not empty
							if(!empty($result->data)){

								// replace thumb
								foreach($result->data as $img){

									// exclude default historic thumbnail
									if($img->fsk <= $opt['fsk']){
										$res->data->thumbName = $img->imageName;
										break;
										}
									// unset thumbName
									else{
										$res->data->thumbName = null;
										}
									}
								}

							// unset thumbName
							else{
								$res->data->thumbName = null;
								}

							}

						}

					}

				}

			// return
			return $res;
			}

		// param 2: name
		if(!empty($alt['name'])){

			// Get profile by name
			$res = profile::get(['name' => $alt['name']]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// profile not found
			elseif($res->status == 404){
				$err_text = 'profile '.h::encode_php($alt['name']).' not found';
				e::logtrigger($err_text);
				return self::response(406, $err_text);
				}

			return $res;
			}

		// param: IMSI
		if(!empty($alt['imsi'])){

			// get mobile
			$res = mobile::get(["imsi" => $alt['imsi']]);

			// on error
			if(!in_array($res->status, [200, 404])){
				return self::response(500, h::encode_php('Mobile could not be loaded: '.$res->status));
				}

			// not found
			elseif($res->status == 404){
				return self::response(404, 'Unknown Mobile imsi: '.h::encode_php($alt['imsi']));
				}

			// assign
			$alt['mobileID'] = $res->data->mobileID;
			}

		// customer params
		if(!empty($alt['mobileID']) or !empty($alt['persistID'])){

			// Get profile by mobileID or persistID (prio: mobileID)
			$res = profile::get($alt);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// profile not found
			elseif($res->status == 404){
				$err_text = 'profile for '.h::encode_php($alt).' not found';
				return self::response(404, $err_text);
				}

			return $res;
			}

		// on Request params error
		return self::response(400, ['profileID|name|mobileID|persistID|imsi']);

		}


	public static function create_profile($req){

		// mandatory
		$mand = h::eX($req, [
			'name'			=> '~^[a-zA-Z0-9_-]{3,30}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'age'			=> '~^[1-9]{1}[0-9]{0,2}$',
			'plz'			=> '~^.{0,160}$', // default value "aus deiner Umgebung"
			'weight'		=> '~1,255/f',
			'height'		=> '~1,255/f',
			'description'	=> '~^.{0,500}$',
			'imageID'		=> '~1,16777215/i',
			'countryID'		=> '~0,255/i',
			'poolID'		=> '~0,255/i',
			'mobileID'		=> '~1,4294967295/i',
			'persistID'		=> '~1,18446744073709551615/i',
			'imsi'			=> '~^[1-9]{1}[0-9]{5,15}$',
			'gender'		=> '~^[mMfF]{1}$',
			'orientation'	=> '~^[mMfFbB]{1}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if there is no customer identification param
		if(!isset($opt['mobileID']) and !isset($opt['persistID']) and !isset($opt['imsi'])) return self::response(400, 'Need at least mobileID, imsi or persistID');

		// param: IMSI
		if(!empty($opt['imsi'])){

			// get mobile
			$res = mobile::get(["imsi" => $opt['imsi']]);

			// on error
			if(!in_array($res->status, [200, 404])){
				return self::response(500, h::encode_php('Mobile could not be loaded: '.$res->status));
				}

			// not found
			elseif($res->status == 404){
				return self::response(404, 'Unknown Mobile imsi: '.h::encode_php($opt['imsi']));
				}

			// assign
			$opt['mobileID'] = $res->data->mobileID;
			}

		// make username unique by concatenate it with mobileID or persistID
		$mand['name'] .= '_'.($opt['mobileID'] ?? $opt['persistID']);

		// group params
		$mand = $mand + $opt;

		// create profile
		$res = profile::create($mand);

		// a profile allready exists
		if($res->status == 409) return self::response(409, $res);

		// on error
		if($res->status != 201) return self::response(500, $res);

		return $res;

		}


	public static function update_profile($req){

		// alternativ
		$alt = h::eX($req, [
			'profileID'		=> '~1,16777215/i',
			'mobileID'		=> '~1,4294967295/i',
			'persistID'		=> '~1,18446744073709551615/i',
			'imsi'			=> '~^[1-9]{1}[0-9]{5,15}$',
			], $error, true);

		// Optional
		$opt = h::eX($req, [
			'name'			=> '~^[a-zA-Z0-9_-]{3,30}$',
			'age'			=> '~^[1-9]{1}[0-9]{0,2}$',
			'plz'			=> '~^.{0,160}$',
			'weight'		=> '~1,255/f',
			'height'		=> '~1,255/f',
			'description'	=> '~^.{0,500}$',
			'imageID'		=> '~1,16777215/i',
			'hidden'		=> '~^[0-9]{1}$',
			'countryID'		=> '~1,255/i',
			'poolID'		=> '~1,255/i',
			'gender'		=> '~^[mMfF]{1}$',
			'orientation'	=> '~^[mMfFbB]{1}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param: IMSI
		if(!empty($alt['imsi'])){

			// get mobile
			$res = mobile::get(["imsi" => $alt['imsi']]);

			// on error
			if(!in_array($res->status, [200, 404])){
				return self::response(500, h::encode_php('Mobile could not be loaded: '.$res->status));
				}

			// not found
			elseif($res->status == 404){
				return self::response(404, 'Unknown Mobile imsi: '.h::encode_php($alt['imsi']));
				}

			// assign
			$alt['mobileID'] = $res->data->mobileID;
			}

		// customer params
		if(!empty($alt['mobileID']) or !empty($alt['persistID'])){

			// Get profile by mobileID or persistID (prio: mobileID)
			$res = profile::get($alt);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// profile not found
			elseif($res->status == 404){
				$err_text = 'profile for '.h::encode_php($alt).' not found';
				return self::response(406, $err_text);
				}

			$alt['profileID'] = $res->data->profileID;
			}

		// alternativ mobileID, persistID or profileID
		elseif(!isset($alt['profileID'])){

			// on Request params error
			return self::response(400, ['need profileID|mobileID|persistID|imsi']);
			}

		// group params
		$alt = $alt + $opt;

		// update profile
		$res = profile::update($alt);

		// on conflict
		if($res->status == 409) return self::response(409, $res);

		// on error
		if($res->status != 204) return self::response(570, $res);

		// return update success
		return self::response(204);

		}


	public static function profiles_list($req = []){

		// alternativ
		$opt = h::eX($req, [
			'countryID'		=> '~1,255/i',
			'mcc'			=> '~200,799/i',
			'poolID'		=> '~1,255/i',
			'fsk'			=> '~0,255/i',
			'gender'		=> '~^[mMfF]{1}$',
			'orientation'	=> '~^[mMfFbB]{1}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// no param : set default country to DE (Obsolete, only for old APK client versions support)
		if(empty($req)){

			// get country
			$res = base::get_country(['code'=>'DE']);

			// on error
			if(!in_array($res->status, [200, 404])){
				return self::response(500, 'countryID for DE could not be loaded: error code '.$res->status);
				}

			// not found
			elseif($res->status == 404){
				$err_text = 'countryID for DE could not be found';
				e::logtrigger($err_text);
				return self::response(406, $err_text);
				}

			// assign countryID
			$opt['countryID'] = $res->data->countryID;
			}

		// param order #1: poolID
		elseif(isset($opt['poolID'])){

			$profiles = [];

			// get profiles
			$res = profile::get_list(['poolID' => $opt['poolID']]);

			// on error
			if($res->status != 200) return self::response(500, 'Profiles List could not be loaded');

			foreach ($res->data as $profile) {

				// filter profile image on requested FSK limit
				$result = self::get_profile(['profileID' => $profile->profileID, 'fsk' => $opt['fsk'] ?? null]);

				// on error
				if(!in_array($result->status, [200, 404])){
					return self::response(500, 'profile '.h::encode_php($profile->profileID).' could not be loaded: error code '.$result->status);
					}

				// not found
				elseif($result->status == 404){
					$err_text = 'profile '.h::encode_php($profile->profileID).' could not be found';
					e::logtrigger($err_text);
					return self::response(406, $err_text);
					}

				array_push($profiles, $result->data);

				}

			// return profiles list
			return self::response(200, $profiles);

			}

		// param order #2: MCC
		elseif(isset($opt['mcc'])){

			// load country
			$res = base::get_country(['mcc'=>$opt['mcc']]);

			// on error
			if(!in_array($res->status, [200, 404])){
				return self::response(500, 'country for mcc '.h::encode_php($opt['mcc']).' could not be loaded: error code '.$res->status);
				}

			// not found
			elseif($res->status == 404){
				$err_text = 'country for mcc '.h::encode_php($opt['mcc']).' could not be found';
				e::logtrigger($err_text);
				return self::response(406, $err_text);
				}

			// assign countryID
			$opt['countryID'] = $res->data->countryID;

			}

		// other request param invalid
		elseif(!isset($opt['countryID'])){

			return self::response(400, ['need countryID, mcc or no parameter']);

			}

		// get profiles
		$res = profile::get_list(['countryID' => $opt['countryID']]);

		// on error
		if($res->status != 200) return self::response(500, 'Profiles List could not be loaded');

		// add each profile its thumbnail name
		foreach ($res->data as $profile) {
			$thumb = image::get(['name'=>'thumb'.$profile->profileID.'.jpg']);
			if ($thumb->status == 200) $profile->thumbName = $thumb->data->name;
			}

		// return profiles list
		return $res;

		}


	public static function get_images($req){

		// mandatory
		$mand = h::eX($req, [
			'profileID'	=> '~1,16777215/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'fsk'		=> '~0,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// Get profile
		$res = profile::get(['profileID' => $mand['profileID']]);

		// on error
		if(!in_array($res->status, [200, 404])){
			return self::response(500, 'Profile could not be loaded for profileID '.$mand['profileID'].'.');
			}

		// not found
		elseif($res->status == 404){
			e::logtrigger('ProfileID '.h::encode_php($mand['profileID']).' could not be found');
			return self::response(406, 'Unknown profileID: '.h::encode_php($mand['profileID']));
			}

		$res = image::get_list([
							'profileID'	=> $mand['profileID'],
							'fsk'		=> ($opt['fsk'] ?? 18)
								]);

		// on error
		if($res->status != 200) return self::response(500, 'images could not be loaded for profileID '.$mand['profileID'].'.');

		// Remove thumbnail
		if(!empty($res->data)){
			foreach($res->data as $key => $image){
				if(strpos($image->imageName, 'thumb') !== false){
					unset($res->data[$key]);
					}
				}
			}

		// re-index array
		$res->data = array_values($res->data);

		return $res;
		}


	public static function prepare_upload($req = []){

		// mandatory
		$mand = h::eX($req, [
			'profileID'	=> '~1,4294967295/i',
			'poolID'	=> '~0,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'delete'	=> '~1,16777215/i',
			'replace'	=> '~1,16777215/i',
			'highlight'	=> '~1,16777215/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// get pool
		$res = pool::get(['poolID' => $mand['poolID']]);

		// pool search error
		if(!in_array($res->status, [200, 404])){

			// return error
			return self::response(500, 'Pool '.h::encode_php($mand['poolID']).' could not be loaded, error status: '.$res->status);
			}

		// pool not found
		elseif ($res->status == 404) {

			// return not found
			return self::response(404, 'Pool '.h::encode_php($mand['poolID']).' could not be found, error status: '.$res->status);
			}

		// pool found
		else {

			$pool = $res->data;
			}

		// get profile
		$res = profile::get(['profileID' => $mand['profileID']]);

		// profile search error
		if(!in_array($res->status, [200, 404])){

			// return error
			return self::response(500, 'Profile '.h::encode_php($mand['profileID']).' could not be loaded, error status: '.$res->status);
			}

		// profile not found
		elseif ($res->status == 404) {

			// return not found
			return self::response(404, 'Profile '.h::encode_php($mand['profileID']).' could not be found, error status: '.$res->status);
			}

		// profile found
		else {

			$profile = $res->data;
			}

		// hash
		$hash = sha1(h::dtstr('now'));

		// init redis
		$redis = self::redis();

		// define cache key
		$cache_key = 'prepareupload:by_profilelID:'.$hash;

		// group params
		$mand = $mand + $opt;

		// if redis accessable, cache entry
		if($redis) $redis->set($cache_key, $mand, ['ex'=>600, 'nx']); // 10 minutes

		$url = 'http://'.$pool->portal_domain.'/userupload/'.$hash;

		// return response
		return self::response(200, (object)['url' => $url]);
		}


	public static function proceed_mo($req){

		// mandatory
		$mand = h::eX($req, [
			'message' 		=> '~^(?:([a-zA-Z0-9]{1,10}) |)(?:#(?:l([1-9]{1}[0-9]{0,19})|)(?:i([1-9]{1}[0-9]{5,15})|)(?:p([1-9]{1}[0-9]{0,5}))\:|)(.*)$',
			'msisdn' 		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'smsgateID'		=> '~1,65535/i',
			'operatorID'	=> '~1,65535/i'
			], $error);

		// optional
		$opt = h::eX($req, [
			'persistID'		=> '~0,18446744073709551615/i',
			'receiveTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// convert msisdn
		$mand['msisdn'] = $mand['msisdn'][0];

		// assign variables
		list($keyword, , , $profileID, $text) = $mand['message'];

		// get mobile
		$res = mobile::get(['msisdn' => $mand['msisdn']]);

		// on error
		if(!in_array($res->status, [200, 404])){
			return self::response(500, 'MSISDN '.h::encode_php($mand['msisdn']).' could not be loaded: '.$res->status);
			}

		// not found
		elseif($res->status == 404){
			e::logtrigger('MSISDN '.h::encode_php($mand['msisdn']).' could not be found');
			return self::response(406, 'Unknown MSISDN: '.h::encode_php($mand['msisdn']));
			}

		// assign mobile ID
		$mobileID = $res->data->mobileID;

		// get gate
		$res = service::get_smsgate(['smsgateID'=>$mand['smsgateID']]);

		// on error
		if(!in_array($res->status, [200, 404])){
			return self::response(500, 'Gate '.h::encode_php($mand['smsgateID']).' could not be loaded: '.$res->status);
			}

		// not found
		elseif($res->status == 404){
			e::logtrigger('Gate '.h::encode_php($mand['smsgateID']).' could not be found');
			return self::response(406, 'Unknown GateId: '.h::encode_php($mand['smsgateID']));
			}

		// assign gate
		$gate = $res->data;

		// assign default variable
		$unlocked = true;

		// A profile ID in MO
		if(!empty($profileID)){

			// get profile
			$res = profile::get(['profileID' => $profileID]);

			// on error
			if(!in_array($res->status, [200, 404])){
				return self::response(500, 'Profile could not be loaded for profileID '.$profileID.'.');
				}

			// not found
			elseif($res->status == 404){
				e::logtrigger('ProfileID '.h::encode_php($profileID).' could not be found');
				return self::response(406, 'Unknown profileID: '.h::encode_php($profileID));
				}

			// assign profile variables
			$profileID 	= $res->data->profileID;
			$profileID_long = str_pad($profileID, 6, '0', STR_PAD_LEFT);
			$profileName = $res->data->profileName;

			// store MO
			$res = message::create([
				'mobileID'		=> $mobileID,
				'profileID'		=> $profileID,
				'text'			=> $text,
				'from'			=> 1,
				'smsgateID'		=> $mand['smsgateID'],
				'persistID'		=> $opt['persistID'] ?? 0,
				'receiveTime'	=> $opt['receiveTime'] ?? h::dtstr('now'),
				]);

			// on error
			if($res->status != 201){
				return self::response(500, 'Message could not be stored: '.h::encode_php($res));
				}

			// assign message ID
			$messageID = $res->data;

			// prepare return values
			$mand['msisdn'] = $mand['msisdn'].$profileID_long;
			$mand['message'] = ($keyword ? $keyword.' ' : '').'At '.$profileName.': '.$text;

			// try to get a Redis instance
			$redis = self::redis();

			// if no Redis instance get MOs counter from DB
			if(!$redis or !$redis->isConnected()){
				$res = message::count(['mobileID'=>$mobileID]);

				// on error
				if($res->status != 200){
					return self::response(500, 'Messages count could not be loaded: '.h::encode_php($res));
					}

				// assign counter
				$count = $res->data;
				}

			// Redis is instanciated
			else{

				// if counter didn't exist create it with value from DB
				if(!$redis->exists("bragi:msgcount:".$mobileID)){
					$res = message::count(['mobileID'=>$mobileID]);
					if($res->status != 200){
						return self::response(500, 'Messages count could not be loaded: '.h::encode_php($res));
						}
					$count = $res->data;
					$redis->incr("bragi:msgcount:".$mobileID, $count); // redis incr is atomic
					}

				// else increment counter
				else{
					$count = $redis->incr("bragi:msgcount:".$mobileID); // redis incr is atomic
					}
				}

			// try to get reminder value from gate
			if(!empty($gate->param['reminder_sms'])){

				// assign variables
				$start = $gate->param['reminder_sms']['start'];
				$interval = $gate->param['reminder_sms']['interval'];

				// check if counter is multiple of reminder value
				if( $count == $start  or ($count - $start ) % $interval === 0 ){

					// Send SMS
					$res = \dotdev\mobile\client::send_sms([
						'mobileID'		=> $mobileID,
						'serviceID'		=> $gate->serviceID,
						'smsgateID'		=> $gate->smsgateID,
						'text'			=> $gate->param['reminder_sms']['mt_text'],
						'persistID'		=> $opt['persistID'] ?? null,
						]);

					// on error
					if($res->status != 201){
						e::logtrigger('SMS could not be sended : '.h::encode_php($res));
						}
					}
				}

			// Try to get autostop value from gate
			if(!empty($gate->param['autostop'])){

				// get monthly MO counter
				$res = message::month_MOs_count(['mobileID'=>$mobileID]);

				// on error
				if($res->status != 200){
					return self::response(500, 'Messages Monthly count could not be loaded: '.h::encode_php($res));
					}

				// assign counter
				$mo_count = $res->data;
				$start = $gate->param['autostop']['start'];
				$interval = $gate->param['autostop']['interval'];

				if($mo_count >= $start) {

					// load user month MO limit status
					$res = message::month_MOs_unlocked(['mobileID'=>$mobileID, 'smsgateID'=>$mand['smsgateID']]);

					// on error
					if($res->status != 200){
						return self::response(500, 'Monthly MOs count unlocked could not be loaded: '.h::encode_php($res));
						}

					// assign variable
					$unlocked = $res->data;

					// if user is locked
					if( ! $unlocked ){

						// set SMS with monthly MOs count value
						$sms_text = str_replace("{mo_count}", $mo_count, $gate->param['autostop']['mt_text']);

						// send sms
						$res = \dotdev\mobile\client::send_sms([
							'mobileID'		=> $mobileID,
							'serviceID'		=> $gate->serviceID,
							'smsgateID'		=> $gate->smsgateID,
							'text'			=> $sms_text,
							'persistID'		=> $opt['persistID'] ?? null,
							]);

						// on error log error and continue
						if($res->status != 201){
							e::logtrigger('SMS could not be sent : '.h::encode_php($res));
							}

						}
					}
				}


			// return result
			return self::response(200, (object)[
				'overwrite' => [
					'msisdn'	=> $mand['msisdn'],
					'message'	=> $mand['message'],
					]
				]);
			}


		// all other MO's without profileID starts here

		// check for the gate's autostop value
		if (!empty($gate->param['autostop'])) {

			// MO text matches gate's unlock word
			if (in_array(strtolower(trim($text)), $gate->param['autostop']['unlock_words'])) {

				// count mobile's MOs
				$res = message::month_MOs_count(['mobileID'=>$mobileID]);

				// on error
				if ($res->status != 200) {
					return self::response(500, 'Messages Monthly count could not be loaded: '.h::encode_php($res));
					}

				// assign variables
				$mo_count = $res->data;
				$start = $gate->param['autostop']['start'];
				//$interval = $gate->param['autostop']['interval'];

				if ($mo_count >= $start) {

					// check if user if locked
					$res = message::month_MOs_unlocked(['mobileID'=>$mobileID, 'smsgateID'=>$mand['smsgateID']]);

					// on error
					if($res->status != 200){
						return self::response(500, 'Monthly MOs count unlocked could not be loaded: '.h::encode_php($res));
						}

					$unlocked = $res->data;

					// mobile has reached month MOs limit
					//if( $mo_count >= $start and ($mo_count - $start) % $interval === 0){
					if( ! $unlocked){

						// store MO
						$res = message::create([
							'mobileID'		=> $mobileID,
							'profileID'		=> 0,
							'text'			=> $text,
							'from'			=> 3,
							'smsgateID'		=> $mand['smsgateID'],
							'persistID'		=> $opt['persistID'] ?? 0,
							'receiveTime'	=> $opt['receiveTime'] ?? h::dtstr('now'),
							]);

						// on error
						if($res->status != 201){
							return self::response(500, 'Message could not be stored: '.h::encode_php($res));
							}

						// return to caller
						return self::response(204);
						}
					}
				}
			}

		// return success (with no alteration)
		return self::response(204);
		}


	public static function proceed_chattool_mt($req){

		// mandatory
		$mand = h::eX($req, [
			'smsgateID'		=> '~1,65535/i',
			'persistID'		=> '~0,4294967295/i',
			'mobileID'		=> '~1,4294967295/i',
			'operatorID'	=> '~1,65535/i',
			'recipient'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,20})$',
			'message'		=> '~0,65535/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'receiveTime'	=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// check if recipient is an extended msisdn
		if(strlen($mand['recipient'][0]) > 15){

			// extract msisdn and profileID from recipient
			$msisdn = substr($mand['recipient'][0], 0, -6);
			$profileID_str = substr($mand['recipient'][0], -6);

			// get profile
			$res = profile::get([
				'profileID' => $profileID_str,
				]);

			// on error
			if($res->status != 200) return self::response(500, 'Could not load profileID_str '.h::encode_php($profileID_str).': '.$res->status);

			// assign profileID
			$profileID = $res->data->profileID;

			// store message
			$res = message::create([
				'mobileID'		=> $mand['mobileID'],
				'profileID'		=> $profileID,
				'text'			=> $mand['message'],
				'from'			=> 2,
				'smsgateID'		=> $mand['smsgateID'],
				'persistID'		=> $mand['persistID'],
				'receiveTime'	=> $opt['receiveTime'] ?? h::dtstr('now'),
				]);

			// on error
			if($res->status != 201) return self::response(500, 'Could not create message (from = 2) for mobileID '.$mand['mobileID'].': '.$res->status);
			}

		// return to caller
		return self::response(204);
		}


	}
