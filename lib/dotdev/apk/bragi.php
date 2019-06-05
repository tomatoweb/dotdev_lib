<?php
/*****
 * Version 1.0.2018-09-03
**/
namespace dotdev\apk;

use \tools\error as e;
use \tools\helper as h;
use \tools\http;
use \dotdev\apk\share as apk_share;
use \dotdev\bragi\media as bragi_media;

// DEPRECATED
use \dotdev\app\bragi\profile as old_bragi_profile;
use \dotdev\app\bragi\message as old_bragi_message;


class bragi {
	use \tools\libcom_trait,
		\tools\redis_trait;

	/* Redis */
	public static function redis(){

		return redis::load_resource('app_bragi');
		}


	/* helper */
	protected static function _patch_request_data($req = []){

		// convert request data to array
		$req = (array) $req;

		// check for invalid IMSI
		if(isset($req['imsi']) and !h::is($req['imsi'], '~^[1-7]{1}[0-9]{5,15}$')){

			// unset IMSI
			unset($req['imsi']);
			}

		// return patched request data
		return $req;
		}


	/* fake profile */
	public static function get_profile($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($patched_req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'persistID'		=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($patched_req, [
			'imsi'			=> '~^[1-7]{1}[0-9]{5,15}$',
			'countryID'		=> '~1,255/i',
			'profileID'		=> '~1,16777215/i',
			'gender'		=> '~^[MF]$',
			'orientation'	=> '~^[MFB]$',
			'max_fsk'		=> '~0,255/i',
			'date_format'	=> '~1,100/s',
			'skip_imageless'=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'gender'		=> 'M',
			'orientation'	=> 'F',
			'max_fsk'		=> 18,
			'date_format'	=> 'Y-m-d H:i:s',
			'skip_imageless'=> false,
			];


		// load mobile data
		$res = apk_share::get_mobile_info([
			'persistID'			=> $mand['persistID'],
			'imsi'				=> $opt['imsi'] ?? null,
			'countryID'			=> $opt['countryID'] ?? null,
			'load_imsi_mobile'	=> true,
			]);

		// on error
		if($res->status != 200) return $res;

		// take mobile info
		$mobile_info = $res->data;


		// load apk server config for countryID
		$res = apk_share::get_config_server([
			'project'	=> $mand['project'],
			'countryID'	=> $mobile_info->countryID,
			'persistID'	=> $mand['persistID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take related server config
		$server_config = $res->data;


		// define poolID and server url
		$poolID = $server_config->profile_poolID ?? null;
		$bragi_server = $server_config->bragi_server ?? null;

		// TEMP: special 'male pool for women' fix
		if($opt['gender'] == 'F' and $opt['orientation'] == 'M' and isset($server_config->profile_men_poolID)){

			// take poolID
			$poolID = $server_config->profile_men_poolID;
			}

		// if poolID is not defined, return failed dependency
		if(!$poolID) return self::response(424);


		// define function to expand/convert profiles
		$expand_profile = function($profile_list) use ($mand, $opt, $poolID, $bragi_server){

			// define if single entry
			$single = !is_array($profile_list);

			// convert single entry to list
			if($single) $profile_list = [$profile_list];

			// for each profile
			foreach($profile_list as $key => $profile){

				// rewrite object
				$fake_profile = (object)[
					'profileID'		=> $profile->profileID,
					'name'			=> $profile->profileName,
					'age'			=> $profile->age,
					'plz'			=> $profile->plz,
					'height'		=> $profile->height,
					'weight'		=> $profile->weight,
					'description'	=> $profile->description,
					'createTime'	=> ($opt['date_format'] != 'Y-m-d H:i:s') ? h::dtstr($profile->createTime, $opt['date_format']) : $profile->createTime,
					'gender'		=> $profile->gender,
					'orientation'	=> $profile->orientation,
					'image_preview'	=> null,
					'image_list'	=> [],
					'video_list'	=> [],
					];

				// if bragi server is defined
				if($bragi_server){

					// load files
					$res = bragi_media::get_profile_media([
						'profileID'		=> $profile->profileID,
						]);

					// on error
					if($res->status != 200) return self::response(570, $res);

					// take file list
					$file_list = $res->data;


					// for each file
					foreach($file_list as $file){

						// if max fsk is defined, skip if file fsk is higher
						if(isset($opt['max_fsk']) and $opt['max_fsk'] < $file->fsk) continue;

						// define file url
						$file_url = 'http://'.$bragi_server.'/p'.$profile->profileID.'/'.$file->name;

						// define ext
						$file_ext = strtolower(substr($file->name, strrpos($file->name, '.') + 1));

						// for viewable videos
						if($file->moderator == 0 and $file_ext == 'mp4'){

							// add video
							$fake_profile->video_list[] = $file_url;
							}

						// skip every non image file
						if(!in_array($file_ext, ['jpg','jpeg','png'])) continue;

						// for basic images
						if($file->moderator == 0){

							// check if profile has this image as first image defined
							if(!empty($profile->imageID) and $file->imageID == $profile->imageID){

								// push image at first position in list
								array_unshift($fake_profile->image_list, $file_url);
								}

							// else
							else {

								// add image
								$fake_profile->image_list[] = $file_url;
								}
							}

						// for thumb images
						if($file->moderator == 2 and !$fake_profile->image_preview){

							// add image
							$fake_profile->image_preview = $file_url;
							}
						}

					// if no preview image found
					if(empty($fake_profile->image_preview) and !empty($fake_profile->image_list)){

						// take first image as preview image
						$fake_profile->image_preview = reset($fake_profile->image_list);
						}
					}

				// if profile without image should be skipped
				if($opt['skip_imageless'] and !$fake_profile->image_list){

					// remove/skip entry it this case
					unset($profile_list[$key]);
					continue;
					}

				// add profile to result
				$profile_list[$key] = $fake_profile;
				}

			// return result as single entry or list
			return $single ? reset($profile_list) : ($opt['skip_imageless'] ? array_values($profile_list) : $profile_list);
			};


		// param order 1: profileID
		if(isset($opt['profileID'])){

			// load profile
			$res = old_bragi_profile::get([
				'profileID'		=> $opt['profileID'],
				]);

			// on unexpected error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if not found
			if($res->status == 404) return $res;

			// take profile
			$profile = $res->data;

			// if poolID not matches, return not found
			if($profile->poolID != $poolID) return self::response(404);

			// expand profile
			$profile = $expand_profile($profile);

			// return result
			return self::response(200, $profile);
			}


		// param order 2: no profileID
		// load profile list
		$res = old_bragi_profile::get_list([
			'poolID'	=> $poolID,
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take profile list
		$profile_list = $expand_profile($res->data);

		// return result
		return self::response(200, $profile_list);
		}


	/* user profile */
	public static function get_user_profile($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($patched_req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'persistID'		=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($patched_req, [
			'imsi'			=> '~^[1-7]{1}[0-9]{5,15}$',
			'date_format'	=> '~1,100/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'date_format'	=> 'Y-m-d H:i:s',
			];


		// load mobile data
		$res = apk_share::get_mobile([
			'persistID'		=> $mand['persistID'],
			'imsi'			=> $opt['imsi'] ?? null,
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return $res;

		// define mobile
		$mobile = ($res->status == 200) ? $res->data : null;


		// load profile
		$res = old_bragi_profile::get([
			'mobileID'		=> $mobile ? $mobile->mobileID : null,
			'persistID'		=> $mand['persistID'],
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if not found
		if($res->status == 404) return $res;

		// take profile
		$profile = $res->data;


		// load apk server config for countryID
		$res = apk_share::get_config_server([
			'project'	=> $mand['project'],
			'countryID'	=> $profile->countryID,
			'persistID'	=> $mand['persistID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take related server config
		$server_config = $res->data;

		// define server url
		$bragi_server = $server_config->bragi_server ?? null;


		// rewrite object
		$user_profile = (object)[
			'profileID'		=> $profile->profileID,
			'name'			=> $profile->profileName,
			'age'			=> $profile->age,
			'plz'			=> $profile->plz,
			'height'		=> $profile->height,
			'weight'		=> $profile->weight,
			'description'	=> $profile->description,
			'createTime'	=> ($opt['date_format'] != 'Y-m-d H:i:s') ? h::dtstr($profile->createTime, $opt['date_format']) : $profile->createTime,
			'gender'		=> $profile->gender,
			'orientation'	=> $profile->orientation,
			'image_preview'	=> null,
			'image_list'	=> [],
			'video_list'	=> [],
			];


		// if bragi server is defined
		if($bragi_server){

			// load media files
			$res = bragi_media::get_profile_media([
				'profileID'		=> $profile->profileID,
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// take file list
			$file_list = $res->data;

			// for each file
			foreach($file_list as $file){

				// define file_url
				$file_url = 'http://'.$bragi_server.'/p'.$profile->profileID.'/'.$file->name;

				// define ext
				$file_ext = strtolower(substr($file->name, strrpos($file->name, '.') + 1));

				// for viewable videos
				if($file->moderator == 0 and $file_ext == 'mp4'){

					// add video
					$user_profile->video_list[] = $file_url;
					}

				// skip every non image file
				if(!in_array($file_ext, ['jpg','jpeg','png'])) continue;

				// for basic files
				if($file->moderator == 0){

					// check if profile has this image as first image defined
					if(!empty($profile->imageID) and $file->imageID == $profile->imageID){

						// push image at first position in list
						array_unshift($user_profile->image_list, $file_url);
						}

					// else
					else {

						// add image
						$user_profile->image_list[] = $file_url;
						}
					}

				// for thumb files
				if($file->moderator == 2 and !$user_profile->image_preview){

					// add file
					$user_profile->image_preview = $file_url;
					}
				}

			// if no preview image found
			if(empty($user_profile->image_preview) and !empty($user_profile->image_list)){

				// take first image as preview image
				$user_profile->image_preview = reset($user_profile->image_list);
				}
			}

		// return result
		return self::response(200, $user_profile);
		}

	public static function create_user_profile($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($patched_req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'persistID'		=> '~1,18446744073709551615/i',
			'name'			=> '~^[a-zA-Z0-9_\-]{3,30}$',
			], $error);

		// optional
		$opt = h::eX($patched_req, [
			'imsi'			=> '~^[1-7]{1}[0-9]{5,15}$',
			'countryID'		=> '~1,255/i',
			'age'			=> '~18,255/i',
			'plz'			=> '~0,160/s',
			'weight'		=> '~1,255/i',
			'height'		=> '~1,255/i',
			'description'	=> '~0,500/s',
			'gender'		=> '~^[MF]$',
			'orientation'	=> '~^[MFB]$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'mobileID'		=> null,
			'age'			=> 18,
			'plz'			=> null,
			'weight'		=> null,
			'height'		=> null,
			'description'	=> null,
			'gender'		=> 'M',
			'orientation'	=> 'F',
			];

		// load mobile data
		$res = apk_share::get_mobile_info([
			'persistID'		=> $mand['persistID'],
			'imsi'			=> $opt['imsi'] ?? null,
			'countryID'		=> $opt['countryID'] ?? null,
			]);

		// on error
		if($res->status != 200) return $res;

		// take mobile info
		$mobile_info = $res->data;

		// create profile
		$res = old_bragi_profile::create([
			'persistID'		=> $mand['persistID'],
			'mobileID'		=> $mobile_info->mobileID, // could be null
			'countryID'		=> $mobile_info->countryID,
			'name'			=> $mand['name'],
			'age'			=> $opt['age'],
			'plz'			=> $opt['plz'],
			'weight'		=> $opt['weight'],
			'height'		=> $opt['height'],
			'description'	=> $opt['description'],
			'gender'		=> $opt['gender'],
			'orientation'	=> $opt['orientation'],
			]);

		// if profile already exists, return conflict
		if($res->status == 409) return self::response(409);

		// on unexpected error
		if($res->status != 201) return self::response(570, $res);

		// return success
		return self::response(201, (object)['persistID' => $mand['persistID']]);
		}

	public static function update_user_profile($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($patched_req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'persistID'		=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($patched_req, [
			'imsi'			=> '~^[1-7]{1}[0-9]{5,15}$',
			'name'			=> '~^[a-zA-Z0-9_\-]{3,30}$',
			'age'			=> '~18,255/i',
			'plz'			=> '~0,160/s',
			'weight'		=> '~1,255/i',
			'height'		=> '~1,255/i',
			'description'	=> '~0,500/s',
			'gender'		=> '~^[MF]$',
			'orientation'	=> '~^[MFB]$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load mobile data
		$res = apk_share::get_mobile([
			'persistID'		=> $mand['persistID'],
			'imsi'			=> $opt['imsi'] ?? null,
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return $res;

		// define mobile
		$mobile = ($res->status == 200) ? $res->data : null;


		// load profile
		$res = old_bragi_profile::get([
			'mobileID'		=> $mobile ? $mobile->mobileID : null,
			'persistID'		=> $mand['persistID'],
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if not found
		if($res->status == 404) return $res;

		// take profile
		$profile = $res->data;

		// define uupdateable keys and data
		$updateable_keys = ['name','age','plz','weight','height','description','gender','orientation'];
		$update_data = [];

		// for each updateable key
		foreach($updateable_keys as $key){

			// define compare key
			$compare_key = ($key == 'name') ? 'profileName' : $key;

			// skip if key is defined as update or has already the same value
			if(!isset($opt[$key]) or $opt[$key] == $profile->{$compare_key}) continue;

			// take value
			$update_data[$key] = $opt[$key];
			}


		// if there is something to update
		if($update_data){

			// update profile
			$res = old_bragi_profile::update([
				'profileID'		=> $profile->profileID,
				] + $update_data);

			// if profile already exists, return conflict
			if($res->status == 409) return self::response(409);

			// on unexpected error
			if($res->status != 204) return self::response(570, $res);
			}

		// return success
		return self::response(204);
		}

	public static function delete_user_profile_media($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($patched_req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'persistID'		=> '~1,18446744073709551615/i',
			'file_url'		=> '~^http(?:s|):\/\/(?:[a-z0-9\-\.]{1,174}\.|)[a-z0-9\-]{3,74}\.[a-z0-9]{2,5}\/p[1-9]{1}[0-9]{0,7}\/[^\/]{1,32}\.(?:jpg|jpeg|png)$',
			], $error);

		// optional
		$opt = h::eX($patched_req, [
			'imsi'			=> '~^[1-7]{1}[0-9]{5,15}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load mobile data
		$res = apk_share::get_mobile([
			'persistID'		=> $mand['persistID'],
			'imsi'			=> $opt['imsi'] ?? null,
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return $res;

		// define mobile
		$mobile = ($res->status == 200) ? $res->data : null;


		// load profile
		$res = old_bragi_profile::get([
			'mobileID'		=> $mobile ? $mobile->mobileID : null,
			'persistID'		=> $mand['persistID'],
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if not found
		if($res->status == 404) return $res;

		// take user profile
		$user_profile = $res->data;


		// load apk server config for countryID
		$res = apk_share::get_config_server([
			'project'	=> $mand['project'],
			'countryID'	=> $user_profile->countryID,
			'persistID'	=> $mand['persistID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take related server config
		$server_config = $res->data;


		// if bragiresource server is not defined, return failed dependency
		if(empty($server_config->bragi_server)) return self::response(424);


		// load media files
		$res = bragi_media::get_profile_media([
			'profileID'		=> $user_profile->profileID,
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take media list
		$file_list = $res->data;

		// define affected file
		$affected_file = null;


		// for each file
		foreach($file_list as $file){

			// skip unreachable moderator types
			if($file->moderator != 0 and $file->moderator != 2) continue;

			// if media url matches given
			if($mand['file_url'] == 'http://'.$server_config->bragi_server.'/p'.$user_profile->profileID.'/'.$file->name){

				// take media file
				$affected_file = $file;

				// abort further searching
				break;
				}
			}

		// if no file found
		if(!$affected_file) return self::response(404);


		// execute job on remote server
		$curl_obj = http::curl_obj([
			'url'		=> 'http://'.$server_config->bragi_server.'/com/nsexec.json',
			'ipv4only'	=> true,
			'method'	=> 'POST',
			'jsonencode'=> true,
			'post'		=> [
				'ns'	=> '\\dotdev\\bragi\\media::delete_profile_media',
				'data'	=> [
					'imageID'	=> $affected_file->imageID,
					],
				],
			]);

		// if request was somehow not okay
		if($curl_obj->httpcode != 200){

			// return error
			return self::response(500, 'Remote deletion of imageID '.$affected_file->imageID.' for profileID '.$user_profile->profileID.' failed with httpcode: '.$curl_obj->httpcode);
			}

		// if content is not json
		if($curl_obj->contenttype != "text/json; charset=utf-8"){

			// return error
			return self::response(500, 'Remote deletion of imageID '.$affected_file->imageID.' for profileID '.$user_profile->profileID.' failed with invalid contenttype: '.$curl_obj->contenttype);
			}

		// take decoded response
		$res = json_decode($curl_obj->content);

		// if response seems invalid or unexpected
		if(!isset($res->status) or $res->status != 204){

			// return error
			return self::response(500, 'Remote deletion of imageID '.$affected_file->imageID.' for profileID '.$user_profile->profileID.' failed with unexpected response: '.h::encode_php($res));
			}

		// return success
		return self::response(204);
		}


	/* media upload */
	public static function prepare_media_upload($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($patched_req, [
			'project'	=> '~^[a-z0-9_]{1,32}$',
			'persistID'	=> '~1,18446744073709551615/i',
			'type'		=> '~^(?:create_user_profile_media|create_chat_media)$',
			], $error);

		// optional
		$opt = h::eX($patched_req, [
			'countryID'	=> '~1,255/i',
			'imsi'		=> '~^[1-7]{1}[0-9]{5,15}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// load mobile data
		$res = apk_share::get_mobile([
			'persistID'	=> $mand['persistID'],
			'imsi'		=> $opt['imsi'] ?? null,
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return $res;

		// define mobile
		$mobile = ($res->status == 200) ? $res->data : null;


		// load user profile
		$res = old_bragi_profile::get([
			'mobileID'	=> $mobile ? $mobile->mobileID : null,
			'persistID'	=> $mand['persistID'],
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// take user profile
		$user_profile = ($res->status == 200) ? $res->data : null;

		// if profile does not exist for create_user_profile_media, return not found
		if($mand['type'] == 'create_user_profile_media' and !$user_profile) return self::response(404);

		// if profile does not exist for create_user_profile_media, return failed dependency
		if($mand['type'] == 'create_chat_media' and (!$user_profile and !isset($opt['countryID']))) return self::response(424);


		// load apk server config for countryID
		$res = apk_share::get_config_server([
			'project'	=> $mand['project'],
			'countryID'	=> $user_profile ? $user_profile->countryID : $opt['countryID'],
			'persistID'	=> $mand['persistID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take related server config
		$server_config = $res->data;


		// if upload server is not defined, return forbidden
		if(empty($server_config->media_upload)) return self::response(403);

		// if bragiresource server is not defined, return failed dependency
		if(empty($server_config->bragi_server)) return self::response(424);


		// define preparation param
		$param = [
			'persistID'		=> $mand['persistID'],
			'upload_fn'		=> null,
			'append'		=> [
				'project'		=> $mand['project'],
				'countryID'		=> $user_profile ? $user_profile->countryID : $opt['countryID'],
				],
			];

		// for user profile upload
		if($mand['type'] == 'create_user_profile_media'){

			// define param
			$param['upload_fn'] = 'create_profile_media';
			$param['profileID'] = $user_profile->profileID;
			$param['moderator'] = 0;
			$param['fsk'] = 18;
			}

		// for user profile upload
		if($mand['type'] == 'create_chat_media'){

			// define param
			$param['upload_fn'] = 'create_chat_media';
			$param['profileID'] = $user_profile ? $user_profile->profileID : null;
			}

		// if upload function is not defined
		if(empty($param['upload_fn']) or !is_callable('\\dotdev\\bragi\\media::'.$param['upload_fn'])) return self::response(403);

		// create upload preparation
		$res = bragi_media::create_upload_preparation($param);

		// on unexpected error
		if(!in_array($res->status, [201, 409])) return self::response(570, $res);

		// take upload preparation
		$prepared = $res->data;
		$preparation_already_existed = ($res->status == 409);

		// upload url
		$upload_url = 'http://'.$server_config->bragi_server.'/user_upload/'.$prepared->upload_key.'.json';

		// return result
		return self::response($preparation_already_existed ? 409 : 307, (object)['url' => $upload_url]);
		}

	public static function service_media_upload($req = []){

		// mandatory
		$mand = h::eX($req, [
			'upload_key'	=> '~^[a-z0-9]{40}$',
			], $error);

		// on error
		if($error) return self::response(400, $error);


		// load upload preparation
		$res = bragi_media::get_upload_preparation([
			'upload_key'	=> $mand['upload_key'],
			]);

		// if resource was not found, return not found
		if($res->status == 404) return self::response(404);

		// on other errors
		if($res->status != 200){

			// log error
			e::logtrigger('Loading upload preparation failed: '.$res->status);

			// return internal server error
			return self::response(200, (object)['status'=>500, 'error'=>'Internal server error']);
			}

		// take upload_preparation
		$prepared = $res->data;

		// check mandatory param
		$mand += h::eX($prepared, [
			'persistID'		=> '~1,18446744073709551615/i',
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'countryID'		=> '~1,255/i',
			'upload_fn'		=> '~^[a-zA-Z\_]{1,120}$',
			'upload_param'	=> '~/c',
			], $error);

		// on error or if upload function is not defined
		if($error or !is_callable('\\dotdev\\bragi\\media::'.$mand['upload_fn'])){

			// remove upload preparation
			$res = bragi_media::update_upload_preparation([
				'upload_key'	=> $mand['upload_key'],
				'done'			=> true,
				]);

			// log error
			e::logtrigger('Prepared upload package is invalid: '.($error ? h::encode_php($error) : 'upload_fn').' (prepared = '.h::encode_php($prepared).')');

			// return internal server error
			return self::response(200, (object)['status'=>500, 'error'=>'Internal server error']);
			}


		// load apk server config for countryID
		$res = apk_share::get_config_server([
			'project'	=> $mand['project'],
			'countryID'	=> $mand['countryID'],
			'persistID'	=> $mand['persistID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take related server config
		$server_config = $res->data;


		// if upload server is not defined, return forbidden
		if(empty($server_config->media_upload)) return self::response(200, (object)['status'=>403]);

		// if bragiresource server is not defined, return failed dependency
		if(empty($server_config->bragi_server)) return self::response(200, (object)['status'=>424]);


		// take first $_FILES entry
		$file = (!empty($_FILES) and is_array($_FILES)) ? reset($_FILES) : null;

		// basic check
		if(!$file or !isset($file['tmp_name']) or !isset($file['error'])){

			// return error
			return self::response(200, (object)['status'=>400, 'error'=>'File upload error: missing or invalid post struktur']);
			}

		// if this is a multi upload
		if(is_array($file['error'])){

			// take first entry only
			$file['tmp_name'] = $file['tmp_name'][0];
			$file['name'] = $file['name'][0];
			$file['error'] = $file['error'][0];
			}


		// if file upload failed
		if($file['error'] != UPLOAD_ERR_OK){

			// define error
			if($file['error'] == UPLOAD_ERR_INI_SIZE or $file['error'] == UPLOAD_ERR_FORM_SIZE) $error = 'too big';
			elseif($file['error'] == UPLOAD_ERR_PARTIAL) $error = 'only partial upload';
			elseif($file['error'] == UPLOAD_ERR_NO_FILE) $error = 'no file uploaded';
			else $error = 'other upload error ('.$file['error'].')';

			// log error
			e::logtrigger('DEBUG: Error while uploading file: '.$error);

			// return error
			return self::response(200, (object)['status'=>400, 'error'=>'File upload error: '.($file['error'] ?? 'missing')]);
			}

		// check again if file is really the uploaded file
		if(!is_uploaded_file($file['tmp_name'])){

			// log error
			e::logtrigger('DEBUG: Marked uploaded file seems to be manipulated: '.h::encode_php($file));

			// return error
			return self::response(200, (object)['status'=>403]);
			}


		// first set upload preparation to done
		$res = bragi_media::update_upload_preparation([
			'upload_key'	=> $mand['upload_key'],
			'done'			=> true,
			]);

		// if resource was not found, return not found
		if($res->status == 404) return self::response(404);

		// on other errors, return internal server error
		if($res->status != 204){

			// log error
			e::logtrigger('Cannot update upload preparation: '.$res->status);

			// return internal server error
			return self::response(200, (object)['status'=>500, 'error'=>'Internal server error']);
			}

		// append file to upload_param
		$mand['upload_param']['file'] = $file['tmp_name'];
		$mand['upload_param']['upload_file'] = $file['name'];

		// take extension
		$ext = strrpos($mand['upload_param']['upload_file'], '.') ? strtolower(substr($mand['upload_param']['upload_file'], strrpos($mand['upload_param']['upload_file'], '.') + 1)) : '';

		// if extension seems basically invalid
		if(!h::is($ext, '~^[a-z0-9]{3,4}$')){

			// return failed dependency
			return self::response(200, (object)['status'=>403]);
			}

		// remove unwanted chars from file name (remove extension, replace unwanted chars with _, trim left/right _, reduce multiple _, append extension)
		$mand['upload_param']['upload_file'] = substr($mand['upload_param']['upload_file'], 0, (strlen($ext) + 1) * -1);
		$mand['upload_param']['upload_file'] = preg_replace('/[^a-zA-Z0-9]/', '_', $mand['upload_param']['upload_file']);
		$mand['upload_param']['upload_file'] = trim($mand['upload_param']['upload_file'], '_');
		$mand['upload_param']['upload_file'] = preg_replace('/__+/', '_', $mand['upload_param']['upload_file']);
		$mand['upload_param']['upload_file'] .= '.'.($ext == 'jpeg' ? 'jpg' : $ext);

		// call upload function
		$res = call_user_func('\\dotdev\\bragi\\media::'.$mand['upload_fn'], $mand['upload_param']);

		// on error
		if($res->status == 500) return self::response(200, (object)['status'=>500, 'error'=>'Internal server error']);

		// for profile media
		if($mand['upload_fn'] == 'create_profile_media' and $res->status == 201){

			// define file_url
			$file_url = 'http://'.$server_config->bragi_server.'/p'.$mand['upload_param']['profileID'].'/'.$res->data->name;

			// return response
			return self::response(200, (object)['status'=>201, 'data'=>(object)['file_url'=>$file_url]]);
			}

		// for profile media
		if($mand['upload_fn'] == 'create_chat_media' and $res->status == 201){

			// define file_url
			$file_url = 'http://'.$server_config->bragi_server.'/c'.$res->data->file;

			// return response
			return self::response(200, (object)['status'=>201, 'data'=>(object)['file_url'=>$file_url]]);
			}

		// return response
		return self::response(200, $res);
		}


	/* user messages */
	public static function get_message($req = []){

		// patch request data
		$patched_req = self::_patch_request_data($req);

		// mandatory
		$mand = h::eX($patched_req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'persistID'		=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($patched_req, [
			'imsi'			=> '~^[1-7]{1}[0-9]{5,15}$',
			'from'			=> '~Y-m-d H:i:s/d',
			'date_format'	=> '~1,100/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'date_format'	=> 'Y-m-d H:i:s',
			];


		// load mobile data
		$res = apk_share::get_mobile([
			'persistID'		=> $mand['persistID'],
			'imsi'			=> $opt['imsi'] ?? null,
			]);

		// on error
		if($res->status != 200) return $res;

		// take mobile
		$mobile = $res->data;


		// load apk server config for IMSI
		$res = apk_share::get_config_server([
			'project'		=> $mand['project'],
			'countryID'		=> $mobile->countryID,
			'persistID'		=> $mand['persistID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take related server config
		$server_config = $res->data;

		// define poolID and server url
		$poolID = $server_config->profile_poolID ?? null;
		$bragi_server = $server_config->bragi_server ?? null;


		// load messages
		$res = old_bragi_message::get([
			'mobileID'		=> $mobile->mobileID,
			'poolID'		=> $poolID,
			'startTime'		=> $opt['from'] ?? null,
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take message list
		$message_list = $res->data;

		// for each message
		foreach($message_list as $key => $message){

			// skip unwanted message types
			if($message->from != 1 and $message->from != 2) continue;

			// rewrite object
			$message_list[$key] = (object)[
				'messageID'		=> $message->messageID,
				'profileID'		=> $message->profileID,
				'type'			=> $message->from,
				'createTime'	=> ($opt['date_format'] != 'Y-m-d H:i:s') ? h::dtstr($message->createTime, $opt['date_format']) : $message->createTime,
				'read'			=> $message->read ? true : false,
				'text'			=> $message->text,
				];
			}

		// return result
		return self::response(200, $message_list);
		}

	}
