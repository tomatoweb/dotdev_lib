<?php
/*****
 * Version 1.0.2018-02-20
**/
namespace bragiprofile;

use \tools\helper as h;
use \tools\error as e;
use \tools\event;
use \tools\postdata;
use \tools\redis;
use \dotdev\mobile;
use \dotdev\bragi\media;
use \dotdev\app\bragi\message;
use \dotdev\app\bragi\profile;
use \dotdev\app\bragi\pool;
use \dotdev\app\bragi\image;
use \dotdev\app\bragi\event as events;
use \dotdev\app\bragi\user;
use \dotdev\app\bragi\stats;
use \dotdev\nexus\base;

class controller {
	use \tools\router_trait,
		\dotdev\app\extension_trait\usersession,
		\dotdev\app\extension_trait\environment,
		\dotdev\app\extension_trait\builder,
		\dotdev\app\extension_trait\tracker;

	/* Konstrukt & Router */

	public function __construct(){

		//$redis = \tools\redis::load_resource('mt_nexus');
		//$redis = \tools\redis::load_resource('app_bragi');
		//$redis->flushDB();

		// temp fix during DNS domain configuraftion
        if(!isset($_SERVER['REQUEST_SCHEME'])) $_SERVER['REQUEST_SCHEME'] = 'http';

		// TODO: Maintenance/Offline by Domain-Config

		// Shutdown Events
		if(!$this->env_init_shutdown_events()) $this->env_exit_with_maintenance_site(null, 'env_init_shutdown_events() failed');

		// Session
		if(!$this->usersession_init(['with_persistID'=>false])) $this->env_exit_with_maintenance_site(null, 'usersession_init() failed');

		// Environment
		if(!$this->env_init()) $this->env_exit_with_maintenance_site(null, 'env_init() failed');

		// Preset
		if(!$this->env_init_preset()) $this->env_exit_with_maintenance_site(null, 'env_init_preset() failed');

		// Compiler
		if(!$this->builder_compile_preset()) $this->env_exit_with_maintenance_site(null, 'builder_compile_preset() failed');

		// if status is not online or dev, abort here
		$status = $this->env_is('nexus:status', 'inherit') ? $this->env_get('nexus:domain_status') : $this->env_get('nexus:status');
		if($status == 'ignore') $this->env_exit_with_httpcode_404();
		elseif($status == 'archive') $this->env_exit_with_offline_site();
		elseif(!in_array($status, ['online','dev'])) $this->env_exit_with_maintenance_site();

		// Router
		$this->router_dispatch($this->env_get('nexus:url'));

		}


	public function router_definition(){

		return [
			'*' => [

				'page_index'				=> '/',

				'page_edit'					=> '/edit_profile/',

				'ajax_get'					=> '/get',

				'page_profile'				=> '~^\/get_profile\/(mod_profile|cust_profile)\/(?:\+|00|)([1-9]{1}[0-9]{11,20})$',

				// base64 version
				'page_profile_with_hash'	=> '~^\/get_profile\/(mod_profile|cust_profile)\/([a-zA-Z0-9\+\/]{18,33}[\=]{0,2})$', // base64

				// OBSOLETE remove after call update in Chattool.net
				'page_profile_old'			=> '~^\/get_profile\/(mod_profile|cust_profile)\/([0-9]{3,14})\/([A-Z0-9]{1,32})\/(?:\+|00|)([1-9]{1}[0-9]{11,20})$',

				// OBSOLETE remove after call update in Chattool.net
				// base64 version
				'page_profile_with_hash_old'=> '~^\/get_profile\/(mod_profile|cust_profile)\/([0-9]{3,14})\/([A-Z0-9]{1,32})\/([a-zA-Z0-9\+\/]{18,33}[\=]{0,2})$', // base64

				'page_images'				=> '~^\/get_images\/([a-zA-Z0-9]{24})\/([1-9]{1}[0-9]{0,9})\/([a-z]{3})\/([a-zA-Z0-9]{2,24})$',

				'page_static'				=> '~^\/(impressum|contact|stat)$',

				'page_simulate'				=> '~^\/sim(?:\:([^\:]+)(?:\:([^\:]+)(?:\:([^\:]+)|)|)|)$',

				'page_test'					=> '/test',

				'env_page_404'				=> '~^\/(.+)$',
				]
			];
		}


	/* Page Funktionen */

	public function translate_page($page){

		// Prüfe, on Page nicht vorhanden
		if(!file_exists($this->env_get('preset:path_pages').'/'.$page.'.php')){
			e::logtrigger('Page /'.$this->env_get('preset:path_pages').'/'.$page.' does not exist');
			$page = 'error/500';
			}
		return $page;
		}


	public function load_page($page, $addto_trackstr = null, $postprocessing = null, $wrapper = true){

		$page = $this->translate_page($page);

		$include_page = $this->env_get('preset:path_pages').'/'.$page.'.php';

		return $this->response_ob(200, function() use ($include_page, $wrapper){
			if ($wrapper) include $this->env_get('preset:path_pages').'/wrapper.php';
			else include $include_page;
			}, $postprocessing);
		}


	/* Pages */

	public function  page_index(){

		//$redis = \tools\redis::load_resource('mt_nexus');
		//$redis = \tools\redis::load_resource('app_bragi');
		//$redis->flushDB();

		// authentication
		if(!$this->us_get('auth')){

			// Demo no-login flow
			$res = user::get(['user'=>'demo', 'auth'=>'89e495e7941cf9e40e6980d14a16bf023ccd4c91']);
			if($res->status == 200){
				$this->us_set(['auth'=>1]);
				if($res->data->admin) $this->us_set(['admin'=>1]);
				if($res->data->demo) $this->us_set(['demo'=>1]);
				return $this->page_index();
				}
			$this->us_set(['flash' => ['type' => 'danger', 'msg' => 404]]);

			// Default login flow
			return $this->page_login();
			}

		// page variable for ng-app
		$this->us_set(['page_edit'=>0]);


		return $this->load_page('static/index');
		}


	public function page_edit(){

		$mand = h::eX($_REQUEST, [
			'profileID'			=> '~0,4294967295/i'
			], $error);

		$opt = h::eX($_REQUEST, [
			'name'				=> '~^.{3,20}$',
			'age'				=> '~^[1-9]{1}[0-9]{0,2}$',
			'plz'				=> '~(*UTF8)^.{0,160}$',
			'weight'			=> '~^[1-9]{1}[0-9]{0,2}$',
			'height'			=> '~^[1-9]{1}[0-9]{0,2}$',
			'description'		=> '~(*UTF8)^.{0,500}$',
			'delete_image'		=> '~1,4294967295/i',
			'highlight_image'	=> '~1,4294967295/i',
			'move_image'		=> '~1,4294967295/i',
			'fsk_image'			=> '~1,4294967295/i',
			'fsk'				=> '~0,255/i',
			'copy'				=> '~0,255/i',
			'countryID'			=> '~0,255/i',
			'poolID'			=> '~0,255/i',
			'hidden'			=> '~^[0-1]{1}$',
			'gender'			=> '~^[mMfF]{1}$',
			'orientation'		=> '~^[mMfFbB]{1}$',
			'chat'				=> '~/s',
			'thumb'				=> '~/s',
			], $error, true);

		if($error){
			e::logtrigger('Missing or invalid parameter: '.h::encode_php($error));
			return $this->load_page('error/500');
			}

		if(isset($opt['plz'])) $opt['plz'] = str_replace(str_split('\\"\''), '-', $opt['plz']);

		if(isset($opt['description'])) $opt['description'] = str_replace(str_split('\\"<>'), '-', trim($opt['description']));

		// authentication
		if(!$this->us_get('auth')){
			return $this->page_login();
			}
		if(!$this->us_get('admin')){
			return $this->page_index();
			}

		// POST not empty: Form submission. Case of create or update a profile and its images
		if($_POST) {

			// checkbox 'hidden' is unchecked, unhide this profile
			if (!isset($opt['hidden'])) {
				$opt['hidden'] = 0;
				}

			// Create new profile
			if(!$mand['profileID']) {
				$res = profile::create($opt);
				if($res->status != 201){
					e::logtrigger('Fake-profile konnte nicht erstellt werden: '.h::encode_php($res));
					$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);
					return $this->load_page('edit');
					}
				$mand['profileID'] = $res->data->profileID;
				$this->us_set(['flash' => ['type' => 'success', 'msg' => 205]]);
				}

			// Update profile
			$res = profile::update($mand + $opt);

			if($res->status != 204){
				e::logtrigger('Fake-profile konnte nicht geändert werden: '.h::encode_php($res));
				$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);
				// redirect to new copy profile page
				return $this->response(302, $this->us_url('/edit_profile/?profileID='.$mand['profileID']));
				}

			if(isset($_FILES)){

				if(isset($_FILES['file'])){

					// rename $_FILES["file"]
					$_FILES['images'] = $_FILES['file'];


					// Add new pictures to profile
					foreach ($_FILES['images']['name'] as $k => $v){

						// The last value of $_FILES['images']['name'] is allways empty, ignore it.
						if($v){
							$image_name = $v;
			                $image_tmp_name = $_FILES['images']['tmp_name'][$k];
			                $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

			                // Chat bilder = 1, thumbnail = 2, Profile bilder = others
			                $image_mod = 0;

			                // filter file extension
			                if (!in_array($image_ext, array('jpeg', 'jpg', 'png'))){
		                		$this->us_set(['flash' => ['type' => 'danger', 'msg' => 415]]);
		                		continue; // anyway with next pic
			                	}

			                // create a new image in DB
		                	$res = image::create();
		                	if($res->status != 201){
		                		e::logtrigger('image could not be created: '.h::encode_php($res));
		                		return $this->load_page('error/500');
		                		}

		                	$imageID = $res->data->imageID;

		            		// Check if it is a Chat-only image (used by moderators in chats, doesn't appear in profile images)
			                if($opt['chat'] == 'true'){

			                	$image_mod = 1;
			                	}

			                // Check if it is a thumbnail image
			                elseif($opt['thumb'] == 'true'){

			                	$image_mod = 2;
			                	}

			                // rename image to be unique: autoincremented id from DB + file extension
		            		$image_name = $imageID.'.'.$image_ext;

		                    $res = image::update([
		                    	'imageID'	=>$imageID,
		                    	'name'		=>$image_name,
		                    	'profileID'	=>$mand['profileID'],
		                    	'mod'		=> !empty($image_mod) ? $image_mod : 0
		                    	]);

		                    if($res->status != 204){
								e::logtrigger('Bilder konnte nicht geändert werden: '.h::encode_php($res));
								return $this->load_page('error/500');
								}

							// get profile
							$res = profile::get(['profileID'=>$mand['profileID']]);
							if($res->status != 200){
								e::logtrigger('Profile mit profileID '.$mand["profileID"].' konnte nicht geladen werden.');
								return $this->load_page('error/500');
								}

							// set as front picture not set
							if(!$res->data->imageID and empty($image_mod)){
								$res = profile::update(['profileID'=>$mand['profileID'], 'imageID'=>$imageID]);
								if($res->status != 204){
									e::logtrigger('Fake-profile konnte nicht geändert werden: '.h::encode_php($res));
									return $this->load_page('error/500');
									}
								}

							// Create new profile pictures directory
							$profile_path = $_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$mand['profileID'];
							if(!is_dir($profile_path)){
								if(!mkdir($profile_path, 0755, true)){
									e::logtrigger('Profile Verzeichnis konnte nicht erstellt werden.');
									return $this->load_page('error/500');
									}
								}

							// move picture to profile directory
							if(!move_uploaded_file($image_tmp_name, $profile_path.'/'.$image_name)){
								e::logtrigger('Failed to move uploaded picture.');
								return $this->load_page('error/500');
								}

							/* Make a 480px copy (optional historic)

							if($image_mod != 2){
								if(stripos($image_name, 'thumb') === false){
									$file = $profile_path.'/'.$image_name;
									$resizedFile = $profile_path.'/'.$imageID.'_WVGA.'.$image_ext;

									list($width, $height) = getimagesize($file);
									if($width >= $height){ // landscape
										$new_width = 1000;
										$new_height = 480;
										}
									else{ 					// portrait
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
									if(in_array($image_ext, ['jpg', 'jpeg'])){
										$image = imagecreatefromjpeg($file);
										imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
										imagejpeg($image_p, $resizedFile, 75); // compression from 0 (bad quality) to 100 (> 200Kb)
										}
									// png
									else{
										$image = imagecreatefrompng($file);
										imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
										imagepng($image_p, $resizedFile, 7); // compression from 0 (bad quality) to 9 (> 200Kb)
										}

									// !! no need to save copy in DB, if reactive code -> delete following
									$image_name = $imageID.'_WVGA.'.$image_ext;
									$res = image::create(['profileID'=>$mand['profileID'], 'name'=>$image_name]);
									if($res->status != 201){
				                		e::logtrigger('Bilder konnte in nicht erstellt werden: '.h::encode_php($res));
				                		return $this->load_page('error/500');
				                		}

									}
								} */

							}

						} // end foreach ($_FILES['images'])
					}

				// Check POST for video file(s)
				if(!empty($_FILES['videos']['name'][0])){

					foreach($_FILES['videos']['name'] as $k => $v){

						// The last value of $_FILES['videos']['name'] is allways empty, ignore it.
						if($v){

							// assign variables
							$video_name = $v;
							$video_tmp_name = $_FILES['videos']['tmp_name'][$k];
							$video_ext = strtolower(pathinfo($video_name, PATHINFO_EXTENSION));

							if (!in_array($video_ext, array('mp4', 'ts', 'mkv', '3gp', 'webm'))){
		                		$this->us_set(['flash' => ['type' => 'danger', 'msg' => 415]]);
		                		continue; // with next video
		                	}

		                	// get a new unique video ID
		                	$res = image::create();

		                	// on error
		                	if($res->status != 201){
		                		e::logtrigger('image could not be created: '.h::encode_php($res));
		                		return $this->load_page('error/500');
		                		}

		                	// assign variable
		                	$videoID = $res->data->imageID;

		                	// rename video file (to be unique in server data folder)
		                	$video_name = 'v'.$videoID.'.'.$video_ext;

		                	// save new unique name
		                	$res = image::update([
		                		'imageID'	=> $videoID,
		                		'name'		=> $video_name,
		                		'profileID'	=> $mand['profileID'],
		                		'mod'		=> 1 // videos are only for Chat context
		                		]);

		                	// on error
		                	if($res->status != 204){
								e::logtrigger('Video could not be updated: '.h::encode_php($res));
								return $this->load_page('error/500');
								}

							// Create new profile directory
							$profile_path = $_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$mand['profileID'];
							if(!is_dir($profile_path)){
								if(!mkdir($profile_path, 0755, true)){
									e::logtrigger('Cannot create profile server data folder.');
									return $this->load_page('error/500');
									}
								}

							// Create new profile directory
							$profile_videos_path = $_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$mand['profileID'].'/videos';
							if(!is_dir($profile_videos_path)){
								if(!mkdir($profile_videos_path, 0755, true)){
									e::logtrigger('Cannot create profile server data video folder.');
									return $this->load_page('error/500');
									}
								}

							// move video to profile videos directory
							if(!move_uploaded_file($video_tmp_name, $profile_videos_path.'/'.$video_name)){
								e::logtrigger('Failed to move tmp uploaded video to videos folder.');
								return $this->load_page('error/500');
								}
							}

						}

					}

				/* 'Update profile succeed' */
				if(!$this->us_get('flash')){
					$this->us_set(['flash' => ['type' => 'success', 'msg' => 204]]);
					}

				}

			} // End of if(POST)

		// ONLY GET: URL params. Case of hide profile, delete or highlight images, copy profile
		// copy profile to another Pool
		if(isset($opt['copy'])){

			// get profile
			$res = profile::copy(['profileID' => $mand['profileID'], 'poolID' => $opt['copy']]);

			// on error
			if($res->status != 201){
				e::logtrigger('Profile could be copied :'.h::encode_php($res));
				return $this->load_page('error/500');
				}

			// profile copied success flash message
			$this->us_set(['flash' => ['type' => 'success', 'msg' => 205]]);

			// redirect to new copy profile page
			return $this->response(302, $this->us_url('/edit_profile/?profileID='.$res->data->profileID));

			}

		// get profile and add it to controller instance
		if(!empty($mand['profileID'])){

			// get profile
			$res = $this->get_profile(['profileID' => $mand['profileID']]);

			// on error
			if($res->status != 200){

				// log error
				e::logtrigger('Profile with profileID '.$mand["profileID"].' could not be loaded: '.h::encode_php($res));

				// Set message to user 'Could not update or create profile'
				$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);

				// reload edit page
				$this->us_set(['page_edit'=>1]); // angular app variable
				return $this->load_page('edit');
				}
			}

		// Delete image and copies
		if(isset($opt['delete_image'])){

			// Delete image
			$res = media::delete_profile_media(['imageID' => $opt['delete_image']]);

			// error or not found
			if($res->status != 204){

				// log error
				e::logtrigger('image with imageID '.$opt["delete_image"].' could not be deleted: '.h::encode_php($res));

				// Set message to user 'Could not update or create profile'
				$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);

				// reload edit page
				$this->us_set(['page_edit'=>1]); // angular app variable
				return $this->load_page('edit');
				}

			// deleted image is defined as profile front image
			if($opt['delete_image'] == $this->profile->imageID){

				// define candidate for profile image found
				$candidate_found = false;

				foreach ($this->images as $img){

					//replace by first candidate
					if($img->moderator == 0 AND $img->imageID != $this->profile->imageID){

						// replace
						$res = profile::update([
							'profileID' => $this->profile->profileID,
							'imageID'	=> $img->imageID
							]);

						// on error
						if($res->status != 204){

							// log error
							e::logtrigger('could not update profile with new profile image '.$img->imageID.' : '.h::encode_php($res));

							// Set message to user 'Could not update or create profile'
							$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);

							// reload edit page
							$this->us_set(['page_edit'=>1]); // angular app variable
							return $this->load_page('edit');
							}

						$candidate_found = true;
						break;
						}
					}

				// candidate not found, set profile imageID to 0
				if(!$candidate_found){
					$res = profile::update([
						'profileID' => $this->profile->profileID,
						'imageID'	=> 0
						]);

					// on error
					if($res->status != 204){

						// log error
						e::logtrigger('could not update profile with new profile imageID = 0 : '.h::encode_php($res));

						// Set message to user 'Could not update or create profile'
						$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);

						// reload edit page
						$this->us_set(['page_edit'=>1]); // angular app variable
						return $this->load_page('edit');
						}
					}
				}

			if(!$this->us_get('flash')){
				$this->us_set(['flash' => ['type' => 'success', 'msg' => 204]]);
				}

			}

		// Set profile picture
		if(isset($opt['highlight_image']) && !empty($this->profile)){

			// Check if picture exist
			$res = media::get_profile_media(['imageID'=>$opt['highlight_image']]);
			if($res->status != 200){
				e::logtrigger('Bilder '.$opt['highlight_image'].' konnte nicht geladen werden.');
				if(!$this->us_get('flash')){
					$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);
					}
				}
			else{
				$res = profile::update(['profileID'=>$this->profile->profileID, 'imageID'=>$res->data->imageID]);
				if($res->status != 204){
					e::logtrigger('Profile '.$this->profile->profileID.' konnte nicht update werden mit bilder '.$res->data->imageID.'.');
					if(!$this->us_get('flash')){
						$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);
						}
					}
				}

			if(!$this->us_get('flash')){
				$this->us_set(['flash' => ['type' => 'success', 'msg' => 206]]);
				}

			}

		// Move picture from chat/profile to profile/chat
		if(isset($opt['move_image']) && !empty($this->profile)){

			// Check if picture exist
			$res = media::get_profile_media(['imageID'=>$opt['move_image']]);

			// on error
			if($res->status != 200){
				e::logtrigger('Bilder '.$opt['move_image'].' konnte nicht geladen werden. error '.$res->status);
				if(!$this->us_get('flash')){
					$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);
					}
				}


			else{

				// invert mod value
				$res->data->moderator = !empty($res->data->moderator) ? 0 : 1;

				$res = image::update([
					'imageID'	=> $opt['move_image'],
					'mod'		=> $res->data->moderator,
					'profileID'	=> $this->profile->profileID
					]);

				if($res->status != 204){
					e::logtrigger('Profile '.$this->profile->profileID.' konnte nicht update werden.');
					if(!$this->us_get('flash')){
						$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);
						}
					}
				}

			if(!$this->us_get('flash')){
				$this->us_set(['flash' => ['type' => 'success', 'msg' => 204]]);
				}

			}

		// Change FSK classification from image
		if(isset($opt['fsk_image']) && !empty($this->profile)){

			// Check if picture exist
			$res = media::get_profile_media(['imageID'=>$opt['fsk_image']]);

			// on error, log and continue
			if($res->status != 200){
				e::logtrigger('Bilder '.$opt['fsk_image'].' konnte nicht geladen werden. error '.$res->status);
				if(!$this->us_get('flash')){
					$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);
					}
				}

			// image found
			else{

				// FSK value change request
				if(isset($opt['fsk'])){

					// save changes
					$res = image::update([
						'imageID'=>$opt['fsk_image'],
						'fsk'=>$opt['fsk'],
						'profileID'	=> $this->profile->profileID
						]);

					// update did not succeed
					if($res->status != 204){

						e::logtrigger('Profile '.$this->profile->profileID.' konnte nicht update werden.');

						if(!$this->us_get('flash')){
							$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);
							}
						}
					}

				if(!$this->us_get('flash')){
					$this->us_set(['flash' => ['type' => 'success', 'msg' => 204]]);
					}

				}
			}

		// Reload profile in controller after update
		if(isset($mand['profileID'])){
			$res = $this->get_profile(['profileID'=>$mand['profileID']]);
			if($res->status != 200){
				e::logtrigger('Profile mit profileID '.$opt["profileID"].' konnte nicht geladen werden.');
				return $this->load_page('error/500');
				}
			}

		// page variable for ng-app
		$this->us_set(['page_edit'=>1]);

		return $this->load_page('edit');
		}


	public function page_login(){
		if(h::gR('username')){
			$res = user::get(['user'=>h::gR('username'), 'auth'=>h::gR('password')]);
			if($res->status == 200){
				$this->us_set(['auth'=>1]);
				if($res->data->admin) $this->us_set(['admin'=>1]);
				if($res->data->demo) $this->us_set(['demo'=>1]);
				return $this->page_index();
				}
			$this->us_set(['flash' => ['type' => 'danger', 'msg' => 404]]);
			}

		return $this->load_page('static/login');
		}


	public function page_static($page){

		return $this->load_page('static/'.$page,$addto_trackstr = null, $postprocessing = null, $wrapper = true);
		}


	public function page_profile($type, $data) {

		// Normal (short) MSISDN customer
		if (strlen(ltrim($data, '0')) <= 15) {

			// load no_app_user page
			return $this->load_page('no_app_profile',$addto_trackstr = null, $postprocessing = null, $wrapper = false);

			}

		// assign long msisdn
		$long_msisdn = $data;

		// extract profileID from long msisdn
		$profileID = ltrim(substr($long_msisdn, -6), '0');

		// get mod profile
		$res = $this->get_profile(['profileID'=>$profileID]);

		// on error
		if (!in_array($res->status, [200, 404])){

			// log error
			e::logtrigger('Profile with profileID '.$profileID.' could not be loaded. status '.h::encode_php($res->status));

			// load profile_error_page
			return $this->load_page('profile_not_found',$addto_trackstr = null, $postprocessing = null, $wrapper = false);
			}

		// profile not found
		if ($res->status == 404) {

			// load mod_profile_not_found_page
			return $this->load_page('mod_profile_not_found',$addto_trackstr = null, $postprocessing = null, $wrapper = false);
			}

		// get pool
		$res = pool::get(['poolID' => $this->profile->poolID]);

		// pool search error
		if(!in_array($res->status, [200, 404])){

			// log error
			e::logtrigger('pool '.$this->profile->poolID.' could not be loaded: error code '.h::encode_php($res->status));

			}

		// pool not found
		elseif ($res->status == 404) {

			// log
			e::logtrigger('pool '.$this->profile->poolID.' could not be found: error code '.h::encode_php($res->status));

			}

		// pool found
		else {

			$this->pool = $res->data;
			$this->us_set('scheme', $this->pool->scheme);

			}

		// save request type in controller
		$this->frame_request_type = $type;

		// Customer profile request
		if($this->frame_request_type == 'cust_profile'){

			// break down long MSISDN
			$msisdn = substr($long_msisdn, 0, -6);

			//get customer mobile
			$res = mobile::get(['msisdn' => $msisdn]);

			// customer mobile search error
			if(!in_array($res->status, [200, 404])){

				// log error
				e::logtrigger('Mobile with msisdn '.$msisdn.' could not be loaded: error code '.h::encode_php($res->status));

				// load error profile_not_found_frame page
				return $this->load_page('profile_not_found',$addto_trackstr = null, $postprocessing = null, $wrapper = false);
				}

			// customer not found
			elseif ($res->status == 404) {

				// log error
				e::logtrigger('Mobile with msisdn '.$msisdn.' not found. status '.h::encode_php($res->status));

				// load no app-user page
				return $this->load_page('no_app_profile',$addto_trackstr = null, $postprocessing = null, $wrapper = false);

				}

			// assign
			$mobileID = $res->data->mobileID;

			// get customer bragi profile
			$result = profile::get(['mobileID'=>$mobileID]);

			// customer profile search error
			if(!in_array($result->status, [200, 404])){

				// log error
				e::logtrigger('Profile with mobileID '.$mobileID.' could not be loaded: error code '.h::encode_php($result->status));

				// load error profile_not_found_frame page
				return $this->load_page('profile_not_found',$addto_trackstr = null, $postprocessing = null, $wrapper = false);
				}

			// customer profile not found
			if ($result->status == 404) {

				// load profile_not_found_frame page
				return $this->load_page('profile_not_created',$addto_trackstr = null, $postprocessing = null, $wrapper = false);

				}

			// get full customer profile (incl. images)
			$res = $this->get_profile(['profileID'=>$result->data->profileID]);

			// full profile not found
			if($res->status != 200){

				// log error
				e::logtrigger('Profile with profileID '.$result->data->profileID.' not found. status '.h::encode_php($res->status));

				// load profile_not_found_frame page
				return $this->load_page('profile_not_found',$addto_trackstr = null, $postprocessing = null, $wrapper = false);

				}

			}

		// Instantiate Redis
		$redis = $this->redis();

		// Redis connection error
		if(!$redis or !$redis->isConnected()){
			e::logtrigger('Connection to Redis Server failed: '.h::encode_php($redis));
			return (object)['status'=>500];
			}

		// Keep user sessionID 60 minutes in Redis instance
		$redis->set("bragiprofile:us_ID:".$this->us->usID, $this->us->usID);
		$redis->setTimeout("bragiprofile:us_ID:".$this->us->usID, 3600);

		// load frame_profile page
		return $this->load_page('frame_profile',$addto_trackstr = null, $postprocessing = null, $wrapper = false);

		}


	public function page_profile_with_hash($type, $hash){

		// this function only converts and call the other
		$longmsisdn = base64_decode($hash);
		return $this->page_profile($type, $longmsisdn);
		}


	// OBSOLETE remove after call update in Chattool.net
	public function page_profile_old($type, $number, $keyword, $data) {

		// Normal (short) MSISDN customer
		if (strlen(ltrim($data, '0')) <= 15) {

			// load no_app_user page
			return $this->load_page('no_app_profile',$addto_trackstr = null, $postprocessing = null, $wrapper = false);

			}

		// assign long msisdn
		$long_msisdn = $data;

		// extract profileID from long msisdn
		$profileID = ltrim(substr($long_msisdn, -6), '0');

		// get mod profile
		$res = $this->get_profile(['profileID'=>$profileID]);

		// on error
		if (!in_array($res->status, [200, 404])){

			// log error
			e::logtrigger('Profile with profileID '.$profileID.' could not be loaded. status '.h::encode_php($res->status));

			// load profile_error_page
			return $this->load_page('profile_not_found',$addto_trackstr = null, $postprocessing = null, $wrapper = false);
			}

		// profile not found
		if ($res->status == 404) {

			// load mod_profile_not_found_page
			return $this->load_page('mod_profile_not_found',$addto_trackstr = null, $postprocessing = null, $wrapper = false);
			}

		// get pool
		$res = pool::get(['poolID' => $this->profile->poolID]);

		// pool search error
		if(!in_array($res->status, [200, 404])){

			// log error
			e::logtrigger('pool '.$this->profile->poolID.' could not be loaded: error code '.h::encode_php($res->status));

			}

		// pool not found
		elseif ($res->status == 404) {

			// log
			e::logtrigger('pool '.$this->profile->poolID.' could not be found: error code '.h::encode_php($res->status));

			}

		// pool found
		else {

			$this->pool = $res->data;
			$this->us_set('scheme', $this->pool->scheme);

			}

		// save request type in controller
		$this->frame_request_type = $type;

		// Customer profile request
		if($this->frame_request_type == 'cust_profile'){

			// break down long MSISDN
			$msisdn = substr($long_msisdn, 0, -6);

			//get customer mobile
			$res = mobile::get(['msisdn' => $msisdn]);

			// customer mobile search error
			if(!in_array($res->status, [200, 404])){

				// log error
				e::logtrigger('Mobile with msisdn '.$msisdn.' could not be loaded: error code '.h::encode_php($res->status));

				// load error profile_not_found_frame page
				return $this->load_page('profile_not_found',$addto_trackstr = null, $postprocessing = null, $wrapper = false);
				}

			// customer not found
			elseif ($res->status == 404) {

				// log error
				e::logtrigger('Mobile with msisdn '.$msisdn.' not found. status '.h::encode_php($res->status));

				// load no app-user page
				return $this->load_page('no_app_profile',$addto_trackstr = null, $postprocessing = null, $wrapper = false);

				}

			// assign
			$mobileID = $res->data->mobileID;

			// get customer bragi profile
			$result = profile::get(['mobileID'=>$mobileID]);

			// customer profile search error
			if(!in_array($result->status, [200, 404])){

				// log error
				e::logtrigger('Profile with mobileID '.$mobileID.' could not be loaded: error code '.h::encode_php($result->status));

				// load error profile_not_found_frame page
				return $this->load_page('profile_not_found',$addto_trackstr = null, $postprocessing = null, $wrapper = false);
				}

			// customer profile not found
			if ($result->status == 404) {

				// load profile_not_found_frame page
				return $this->load_page('profile_not_created',$addto_trackstr = null, $postprocessing = null, $wrapper = false);

				}

			// get full customer profile (incl. images)
			$res = $this->get_profile(['profileID'=>$result->data->profileID]);

			// full profile not found
			if($res->status != 200){

				// log error
				e::logtrigger('Profile with profileID '.$result->data->profileID.' not found. status '.h::encode_php($res->status));

				// load profile_not_found_frame page
				return $this->load_page('profile_not_found',$addto_trackstr = null, $postprocessing = null, $wrapper = false);

				}

			}

		// Instantiate Redis
		$redis = $this->redis();

		// Redis connection error
		if(!$redis or !$redis->isConnected()){
			e::logtrigger('Connection to Redis Server failed: '.h::encode_php($redis));
			return (object)['status'=>500];
			}

		// Keep user sessionID 60 minutes in Redis instance
		$redis->set("bragiprofile:us_ID:".$this->us->usID, $this->us->usID);
		$redis->setTimeout("bragiprofile:us_ID:".$this->us->usID, 3600);

		// load frame_profile page
		return $this->load_page('frame_profile',$addto_trackstr = null, $postprocessing = null, $wrapper = false);

		}


	// OBSOLETE remove after call update in Chattool.net
	public function page_profile_with_hash_old($type, $number, $keyword, $hash){

		// this function only converts and call the other
		$longmsisdn = base64_decode($hash);
		return $this->page_profile_old($type, $number, $keyword, $longmsisdn);
		}


	public function page_images($usID, $profileID, $view, $scheme){

		// set default pool
		$this->pool = (object)[
			'scheme'	=>	$scheme,
			];

		// Instantiate Redis
		$redis = $this->redis();

		// Redis connection error
		if(!$redis or !$redis->isConnected()){
			e::logtrigger('Connection to Redis Server failed: '.h::encode_php($redis));
			return (object)['status'=>500];
			}

		// Only accept request from registered user sessions
		if(!$redis->exists("bragiprofile:us_ID:".$usID)){
			e::logtrigger('user sessionID not found in Redis instance: '.h::encode_php($redis));
			return (object)['status'=>500];
		}

		$this->profileID = $profileID;

		// save view choice (images or videos)
		$this->us_set(['view'=>$view]);

		// load profile page
		return $this->load_page('page_profile',$addto_trackstr = null, $postprocessing = null, $wrapper = false);

		} // End page function


	public function page_test(){

		// authentication
		if(!$this->us_get('auth')){
			return $this->page_login();
			}

		// page variable for ng-app
		$this->us_set(['page_edit'=>1]);

		return $this->load_page('test');
		}


	/* Functions */

	protected function redis(){

		return redis::load_resource('app_bragi');
		}


	// get profile and images, and save it in Cnotroller
	protected function get_profile($req){

		$mand = h::eX($req, [
			'profileID'	=> '~1,4294967295/i',
			], $error);
		if($error){
			e::logtrigger('Missing or invalid parameter: '.h::encode_php($error));
			// return error code 404
			return (object)['status'=>400];
			}

		// get profile
		$res = profile::get(['profileID' => $mand['profileID']]);

		// on error
		if(!in_array($res->status, [200, 404])){

			// log error
			e::logtrigger('Profile with profileID '.$mand["profileID"].' could not be loaded.');

			// return error code 500
			return (object)['status'=>500];
			}

		// profile not found
		if ($res->status == 404) {

			// return error code 404
			return (object)['status'=>404];
			}

		// save profile in Controller as array and as json
		$this->profile = $res->data;

		$this->json_profile = str_replace(["\\n", "\\r"],'<br>',json_encode($this->profile));

		$res = media::get_profile_media(['profileID' => $mand['profileID']]);
		if($res->status != 200){
			e::logtrigger('Bilder konnte nicht geladen werden.');
			return (object)['status'=>404];
			}

		// save profile images in Controller, as array and as json
		$this->images = $res->data;
		$this->json_images = json_encode($this->images);

		return (object)['status'=>200];

		}


	protected function get_stat(){

		$res = message::get_stat();
		if($res->status != 200){
			e::logtrigger('Stats konnte nicht geladen werden.');
			return $this->load_page('error/500');
			}

		return (object)['status'=>200, 'data'=>$res->data];
		}


	protected function ajax_get(){

		// POST only
		if(postdata::get()){

			$opt = h::eX(postdata::get(), [

				'profiles'				=> '~^[0-1]{1}$',
				'allProfiles'			=> '~^[0-1]{1}$',
				'hidden'				=> '~^[0-1]{1}$',
				'users'					=> '~^[0-1]{1}$',
				'profileID'				=> '~1,4294967295/i',
				'name'					=> '~^[a-zA-Z0-9_-]{3,20}$',
				'fakeProfileID'			=> '~1,4294967295/i',
				'customerName'			=> '~^[a-zA-Z0-9_-]{3,20}$',
				'msisdn'				=> '~^$|^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
				'imsi'					=> '~^$|^[1-9]{1}[0-9]{5,15}$',
				'mobileID'				=> '~^$|^[1-9]{1}[0-9]{0,9}$',
				'msisdn_long'			=> '~^$|^(?:\+|00|)([1-9]{1}[0-9]{11,20})$',
				'project'				=> '~^$|^[a-zA-Z0-9 _-]{3,30}$',
				'event'					=> '~^$|^[a-zA-Z0-9 _-]{3,30}$',
				'active'				=> '~^[0-1]{1}$',
				'period'				=> '~^[0-9]{1,3}$',
				'since'					=> '~^[0-9]{1,3}$',
				'amount'				=> [],
				'lifetime'				=> '~^.*$',
				'top50users'			=> '~^[0-2]{1}$',
				'profiles_stats'		=> [],
				'MOs_stats'				=> '~^[0-1]{1}$',
				'heavy_users'			=> [],
				'profile_is_unique'		=> [],
				'updatePool'			=> [],
				'createPool'			=> [],
				'copyPool'				=> [],
				'imagesList'			=> [],
				'updateFsk'				=> [],
				'deleteImage'			=> [],
				'deleteProfile'			=> [],
				'clustering'			=> [],
				'profiles_list'			=> [],
				'countries'				=> '~^[0-1]{1}$',
				'pools'					=> '~^[0-1]{1}$',
				'MOsByCountries'		=> [],
				'poolID'				=> '~1,65335/i',
				'from'					=> '~Y-m-d H:i:s/d',
				'to'					=> '~Y-m-d H:i:s/d',

				], $error, true);

			// on error
			if($error){
				return $this->load_page('error/500');
				}

			// Normalize params
			if(empty($opt['msisdn'])) unset($opt['msisdn']);
			else $opt['msisdn'] = $opt['msisdn'][0];

			if(empty($opt['msisdn_long'])) unset($opt['msisdn_long']);
			else $opt['msisdn_long'] = $opt['msisdn_long'][0];

			if(empty($opt['imsi'])) unset($opt['imsi']);
			if(empty($opt['mobileID'])) unset($opt['mobileID']);
			if(empty($opt['project'])) unset($opt['project']);
			if(empty($opt['event'])) unset($opt['event']);

			// get all profiles
			if( !empty($opt['allProfiles'])) {

				// load profiles
				$res = profile::get_list();

				// on error
				if($res->status != 200){
					e::logtrigger('Profiles list could not be loaded');
					return $this->load_page('error/500');
					}

				// assign variable
				$profiles = $res->data;

				return $this->response(200, json_encode($profiles));

				}

			// get fake profiles
			if( !empty($opt['profiles'])) {

				// set default values
				$opt += [
					'poolID' 	=> 1,
					'hidden' 	=> 0,
					'since' 	=> 1
					];

				// load profiles
				$res = profile::get_list([
					'poolID' 	=> $opt['poolID'],
					'hidden' 	=> $opt['hidden'],
					'since' 	=> $opt['since']
					]);

				// on error
				if($res->status != 200){
					e::logtrigger('Profiles list could not be loaded');
					return $this->load_page('error/500');
					}

				// assign variable
				$profiles = $res->data;

				// load countries
				$res = base::get_country();

				// on error
				if($res->status != 200){
					e::logtrigger('Countries list could not be loaded');
					return $this->load_page('error/500');
					}

				// complete profiles parameters
				foreach ($profiles as $profile) {

					// assign country name each profile
					foreach ($res->data as $country) {
						if($profile->countryID == $country->countryID){
							$profile->country = $country->name;
							}
						}

					// check profile image on server
					if(!is_file($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID.'/'.$profile->imageName)){
						unset($profile->imageName);
						}

					// add profile url
					$profile->edit_url = $this->us_url('/edit_profile/?profileID='.$profile->profileID);

					// get images
					$result = media::get_profile_media(['profileID'=>$profile->profileID]);

					// on error
					if($result->status != 200){
						e::logtrigger('Profile images list could not be loaded');
						$profile->images = [];
						}

					// append images to profile
					else{
						$profile->images = $result->data;
						}

					}

				$json_profiles = str_replace(["\\n", "\\r"],'<br>',json_encode($profiles));

				return $this->response(200, $json_profiles);

				}

			// get customers
			if( !empty($opt['users'])) {

				// load profiles
				$res = profile::get_users_list();

				// on error
				if($res->status != 200){
					e::logtrigger('Users list could not be loaded');
					return $this->load_page('error/500');
					}

				// assign variable
				$users = array_reverse($res->data);

				$json_users = str_replace(["\\n", "\\r"],'<br>',json_encode($users));

				return $this->response(200, $json_users);

				}

			// get a fake profile
			if( !empty($opt['profileID'])) {

				// get profile
				$res = profile::get(['profileID' => $opt['profileID']]);

				// on error
				if($res->status != 200){
					e::logtrigger('Profile for profileID '.$opt['profileID'].' could not be loaded');
					return $this->load_page('error/500');
					}

				$profile = $res->data;

				// get images
				$res = media::get_profile_media(['profileID'=>$profile->profileID]);

				// on error
				if($res->status != 200){
					e::logtrigger('images could not be loaded for profileID '.$opt['profileID']);
					return $this->load_page('error/500');
					}

				// declare variables
				$images_mod = [];
				$images_non_mod = [];
				$thumbs = [];
				$videos = [];

				// loop images
				foreach ($res->data as $key => $image) {

					// Its a video file
					if(substr($image->name, 0, 1) == 'v'){

						// if file exists, copy it in the videos array
						if (is_file($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID.'/videos/'.$image->name)) {
							array_push($videos, $image);
							}
						}

					// Its an image
					else {

						// check if file exists
						if (is_file($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID.'/'.$image->name)) {

							// copy moderator images to mod images array
							if ($image->moderator == 1) {
								array_push($images_mod, $image);
								}
							// copy moderator images to mod images array
							elseif ($image->moderator == 2 || (stripos($image->name, 'thumb') !== FALSE) ) {
								array_push($thumbs, $image);
								}
							else {
								array_push($images_non_mod, $image);
								}

							}

						}

					}

				// add gallery images to profile
				$profile->images = array_reverse($images_non_mod);

				/* move profile image to first place */
				// find key of profile image
				$found_key = null;
				foreach($profile->images as $key => $struct) {
				    if ($profile->imageName == $struct->name) {
				        $found_key = $key;
				        break;
				    }
				}

				if($found_key){
					// cache value
					$temp = array($key => $profile->images[$key]);

					// unset value
				    unset($profile->images[$key]);

				    // add value to array
				    $profile->images = $temp + $profile->images;

				    // re-index array
				    $profile->images = array_values($profile->images);
					}

				/*
				// other method: array_unshift
				if(!empty($profile->imageID) and $image->imageID == $profile->imageID){

					// push image at first position in list
					array_unshift($profile->images, $struct);
					}
				*/

				// add chat images to profile
				$profile->images_mod = array_reverse($images_mod);

				// add thumbs to profile
				$profile->thumbs = array_reverse($thumbs);

				// add videos to profile
				$profile->videos = array_reverse($videos);

				// add thumbnail URL to profile
				if(isset($profile->thumbName) AND is_file($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID.'/'.$profile->thumbName)) {
					$profile->url_thumb = $this->env_get('domain:resource_url').'/p'.$profile->profileID.'/'.$profile->thumbName;
					}
				elseif(isset($profile->imageName) AND is_file($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID.'/'.$profile->imageName)){
					$profile->url_thumb = $this->env_get('domain:resource_url').'/p'.$profile->profileID.'/'.$profile->imageName;
					}
				else $profile->url_thumb = isset($profile->isuser) ? $this->builder_url('/img/avatar_male.png') : $this->builder_url('/img/default_pic.png');

				// add profile edit URL
				$profile->edit_url = $this->us_url('/edit_profile/?profileID='.$profile->profileID);

				// load country
				$res = base::get_country(['countryID' => $profile->countryID]);

				// on error
				if($res->status != 200){
					return $this->response(404, 'country could not be loaded'.h::encode_php($res));
					}

				// add country name to profile
				$profile->country = $res->data->name;
				$profile->countryCode = $res->data->code;

				return $this->response(200, json_encode($profile));

				}

			// get a customer by name
			if( !empty($opt['customerName'])) {

				// get profile
				$res = profile::get(['name' => $opt['customerName']]);

				// on error
				if($res->status != 200){
					return $this->response(404, 'customer could not be loaded or found, please see log.');
					}

				$profile = $res->data;

				$mob = mobile::get(['mobileID' => $profile->mobileID]);

				if($mob->status == 200) $profile->msisdn = $mob->data->msisdn;


				// get images
				$res = media::get_profile_media(['profileID'=>$profile->profileID]);

				// on error
				if($res->status != 200){
					e::logtrigger('images could not be loaded for profileID '.$opt['customerName']);
					return $this->load_page('error/500');
					}

				// declare variables
				$images_mod = [];
				$images_non_mod = [];
				$videos = [];

				// loop images
				foreach ($res->data as $key => $image) {

					// Its a video file
					if(substr($image->name, 0, 1) == 'v'){

						// if file exists, copy it in the videos array
						if (is_file($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID.'/videos/'.$image->name)) {
							array_push($videos, $image);
							}
						}

					// Its an image
					else {

						// check if file exists
						if (is_file($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID.'/'.$image->name)) {

							// copy moderator images to mod images array
							if ($image->moderator == 1) {
								array_push($images_mod, $image);
								}
							else {
								array_push($images_non_mod, $image);
								}

							}

						}

					}

				// add gallery images to profile
				$profile->images = array_reverse($images_non_mod);

				// add chat images to profile
				$profile->images_mod = array_reverse($images_mod);

				// add videos to profile
				$profile->videos = array_reverse($videos);

				// add thumbnail URL to profile
				if(isset($profile->thumbName) AND is_file($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID.'/'.$profile->thumbName)) {
					$profile->url_thumb = $this->env_get('domain:resource_url').'/p'.$profile->profileID.'/'.$profile->thumbName;
					}
				elseif(isset($profile->imageName) AND is_file($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID.'/'.$profile->imageName)){
					$profile->url_thumb = $this->env_get('domain:resource_url').'/p'.$profile->profileID.'/'.$profile->imageName;
					}
				else $profile->url_thumb = isset($profile->isuser) ? $this->builder_url('/img/avatar_male.png') : $this->builder_url('/img/default_pic.png');

				// add profile edit URL
				$profile->edit_url = $this->us_url('/edit_profile/?profileID='.$profile->profileID);

				// load country
				$res = base::get_country(['countryID' => $profile->countryID]);

				// on error
				if($res->status != 200){
					return $this->response(404, 'country could not be loaded'.h::encode_php($res));
					}

				// add country name to profile
				$profile->country = $res->data->name;

				return $this->response(200, json_encode($profile));

				}

			// Get a single Chat (one profile and one customer)
			if( !empty($opt['msisdn_long']) || (!empty($opt['fakeProfileID']) && (!empty($opt['msisdn']) || !empty($opt['imsi']) || !empty($opt['mobileID'])) ) ){

				$res = $this->get_chat($opt);
				if($res->status != 200) return $this->response(404, $res->data);
				return $this->response(200, json_encode($res));
				}

			// get all msisdn's for one fake profile
			else if(isset($opt['fakeProfileID'])){

				$res = message::get_senders(['profileID' => $opt['fakeProfileID']]);

				// on error
				if($res->status != 200){
					return $this->response(404, 'MSISDN liste konnte nicht geladen werden. status: '.h::encode_php($res->status));
					}
				}

			// get all fake-profiles for one customer
			else if(isset($opt['msisdn']) || isset($opt['imsi']) || isset($opt['mobileID'])){

				// param #1 MSISDN
				if(isset($opt['msisdn'])){

					//get customer mobile
					$res = mobile::get(['msisdn' => $opt['msisdn']]);

					// on error
					if($res->status != 200){

						return $this->response(404);
					}

					// assign
					$mobile = $res->data;
				}

				// param #2 IMSI
				elseif(isset($opt['imsi'])){

					//get customer mobile
					$res = mobile::get(['imsi' => $opt['imsi']]);

					// on error
					if($res->status != 200){
					return $this->response(404);
					}

					// assign
					$mobile = $res->data;
				}

				// param #3 mobileID
				else{

					//get customer mobile
					$res = mobile::get(['mobileID' => $opt['mobileID']]);

					// on error
					if($res->status != 200){
					return $this->response(404);
					}

					// assign
					$mobile = $res->data;
					}

				// get customer profile
				$res = profile::get(['mobileID' => $mobile->mobileID]);

				// Customer has not set a profile
				if($res->status != 200){
					$profile = [];
					}

				// Customer has set a profile
				else{
					// assign
					$profile = $res->data;

					// get customer images
					$res = media::get_profile_media(['profileID'=>$profile->profileID]);

					// on error
					if($res->status != 200){
						return $this->response(404);
						}

					// split images into categories
					$images_mod = [];
					$images_non_mod = [];
					$videos = [];

					// loop images
					foreach ($res->data as $key => $image) {

						// Its a video file
						if(substr($image->name, 0, 1) == 'v'){

							// if file exists, copy it in the videos array
							if (is_file($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID.'/videos/'.$image->name)) {
								array_push($videos, $image);
								}
							}

						// Its an image
						else {

							// check if file exists
							if (is_file($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID.'/'.$image->name)) {

								// copy moderator images to mod images array
								if ($image->moderator == 1) {
									array_push($images_mod, $image);
									}
								else {
									array_push($images_non_mod, $image);
									}

								}

							}

						}

					// add gallery images to profile
					$profile->images = array_reverse($images_non_mod);

					// add chat images to profile
					$profile->images_mod = array_reverse($images_mod);

					// add videos to profile
					$profile->videos = array_reverse($videos);

					// add thumbnail URL to profile
					if(isset($profile->thumbName) AND is_file($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID.'/'.$profile->thumbName)) {
						$profile->url_thumb = $this->env_get('domain:resource_url').'/p'.$profile->profileID.'/'.$profile->thumbName;
						}
					elseif(isset($profile->imageName) AND is_file($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID.'/'.$profile->imageName)){
						$profile->url_thumb = $this->env_get('domain:resource_url').'/p'.$profile->profileID.'/'.$profile->imageName;
						}
					else $profile->url_thumb = isset($profile->isuser) ? $this->builder_url('/img/avatar_male.png') : $this->builder_url('/img/default_pic.png');

					// add profile edit URL
					$profile->edit_url = $this->us_url('/edit_profile/?profileID='.$profile->profileID);

					// add country name
					if(!empty($profile->countryID)){
						// load country
						$res = base::get_country(['countryID' => $profile->countryID]);

						// on error
						if($res->status != 200){
							return $this->response(404, 'country could not be loaded'.h::encode_php($res));
							}

						// add country name to profile
						$profile->country = $res->data->name;
						}

					}

				// get customer's chats profiles
				$res = message::get_profiles($opt);

				// on error
				if($res->status != 200){
					return $this->response(404, 'Chat Profiles liste konnte nicht geladen werden.'.h::encode_php($res->status).' '.h::encode_php($res->data));
					}

				// add customer profile to profiles list
				array_push($res->data, $profile);

				// add customer msisdn to profiles list
				array_push($res->data, $mobile->msisdn);

				}

			if(!empty($opt['project']) || !empty($opt['event'])){
				$res = events::get(['project' => !empty($opt['project']) ? $opt['project'] : null, 'event' => !empty($opt['event']) ? $opt['event'] : null]);
				if($res->status != 200) return $this->response(404, h::encode_php($res));
				foreach($res->data as $event){
					if(isset($event->data)){
						$event->data = json_decode($event->data);
						}
					}

				// Add project name and event name to response if exist
				if(isset($opt['project'])) $res->data += ['project'=>$opt['project']];
				else $res->data += ['project'=>''];
				if(isset($opt['event'])) $res->data += ['event'=>$opt['event']];
				else $res->data += ['event'=>''];
				}

			if(isset($opt['active'])){

				// set default value
				$opt['period'] ?? "48";

				// prepare for query
				if(isset($opt['amount'])){
					if(count($opt['amount']) == 1) array_push($opt['amount'], "1");
					}
				// set default value
				else{
					$opt['amount'] = ["2", "10"];
					}

				// create missing time range points
				if(!isset($opt['from'])) $opt['from'] 	= h::dtstr('now -30 days');
				if(!isset($opt['to'])) $opt['to'] 		= h::dtstr('now');


				$res = message::get_users_stats([
					'active' 	=> $opt['active'],
					'period' 	=> $opt['period'] ,
					'range'		=> $opt['amount'],
					'from'  	=> $opt['from'],
					'to'		=> $opt['to']
				]);

				if($res->status != 200){
					return $this->response(404, 'Nutzern stats konnte nicht geladen werden.'.h::encode_php($res));
					}

				}

			if(isset($opt['lifetime'])){
				$res = message::get_users_lifetime([
					'lifetime'=>$opt['lifetime'],
					'from'=>$opt['from'],
					'to'=>$opt['to']
					]);
				if($res->status != 200){
					return $this->response(404, 'Nutzern Aktivitätsdauer konnte nicht geladen werden.'.h::encode_php($res));
					}
				}

			if(!empty($opt['top50users'])){

				// 1 = all users; 2 = active 48H users
				$active = ($opt['top50users'] == 2 ? "active" : "");

				$res = message::get_top50users([
					"active" => $active,
					'from'=>$opt['from'],
					'to'=>$opt['to']
					]);

				if($res->status != 200){
					return $this->response(404, 'top50 Nutzern konnte nicht geladen werden.'.h::encode_php($res));
					}
				}

			if(!empty($opt['profiles_stats'])){
				$res = stats::get_stats_messages_by_profiles([
					'from'	=> $opt['profiles_stats']->from,
					'to'	=> $opt['profiles_stats']->to
					]);
				if($res->status != 200){
					return $this->response(404, 'profiles stats konnte nicht geladen werden.'.h::encode_php($res));
					}
				}

			if(!empty($opt['MOs_stats'])){
				$res = message::get_MOs_stats();
				if($res->status != 200){
					return $this->response(404, 'MOs stats konnte nicht geladen werden.'.h::encode_php($res));
					}
				}

			if(!empty($opt['heavy_users'])){

				$res = message::get_heavy_users_MOs([
					'from'	=> $opt['heavy_users']->from,
					'to'	=> $opt['heavy_users']->to
					]);
				if($res->status != 200){
					return $this->response(404, 'list heavy users could not be loaded'.h::encode_php($res));
					}
				}

			if(!empty($opt['clustering'])){
				$res = message::get_clustering(['clusters'=>$opt['clustering']]);
				if($res->status != 200){
					return $this->response(404, 'Clusterung konnte nicht geladen werden.'.h::encode_php($res));
					}
				}

			if(!empty($opt['profile_is_unique'])){

				// get profile
				$res = profile::get([
					'name' => $opt['profile_is_unique']->name,
					'poolID' => $opt['profile_is_unique']->poolID,
					]);

				// on error
				if(!in_array($res->status, [200, 404])){
					return self::response(500, 'profile could not be loaded '.$res->status);
					}

				// another profile with the unique key on name-poolID was not found, not an error
				elseif ($res->status == 404) {
					return self::response(204, 'profile not found '.$res->status);
					}

				// another profile with the unique key on name/countryID/poolID allready exists
				// return its profileID
				return $this->response(200, json_encode($res->data->profileID));
				}

			if(!empty($opt['countries'])){
				$res = base::get_country();

				if($res->status != 200){
					return $this->response(404, 'countries could not be loaded'.h::encode_php($res));
					}
				}

			if(!empty($opt['pools'])){
				$res = pool::get();
				if($res->status != 200){
					return $this->response(404, 'pools could not be loaded'.h::encode_php($res));
					}
				}

			if(!empty($opt['MOsByCountries'])){

				$res = message::get_by_countries([
					'from'	=> $opt['MOsByCountries']->from,
					'to'	=> $opt['MOsByCountries']->to
					]);

				if($res->status != 200){
					return $this->response(404, 'msg by countries could not be loaded'.h::encode_php($res));
					}
				}

			if(!empty($opt['updatePool'])){
				$res = pool::update([
					'poolID'=>$opt['updatePool']->poolID,
					'name'=>$opt['updatePool']->name,
					'portal_domain'=>$opt['updatePool']->portal_domain]);
				if($res->status != 204){
					return $this->response(404, 'pool could not be updated'.h::encode_php($res));
					}
				}

			if(!empty($opt['createPool'])){
				$res = pool::create($opt['createPool']);
				if($res->status != 201){
					return $this->response(404, 'pool could not be updated '.h::encode_php($res));
					}
				}

			if(!empty($opt['copyPool'])){
				$res = pool::copy($opt['copyPool']);
				if($res->status != 201){
					return $this->response(404, 'pool could not be copied '.h::encode_php($res));
					}
				}

			if(!empty($opt['imagesList'])){

				$res = image::get_active_list($opt);

				if($res->status != 200){
					return $this->response(404, 'pool could not be updated'.h::encode_php($res));
					}
				}

			if(!empty($opt['updateFsk'])){
				$res = image::update(['imageID'=>$opt['updateFsk']->imageID, 'fsk'=>$opt['updateFsk']->fsk]);
				if($res->status != 204){
					return $this->response(404, 'pool could not be updated'.h::encode_php($res));
					}
				}

			if(!empty($opt['deleteProfile'])){
				$res = profile::delete(['profileID'=>$opt['deleteProfile']->profileID]);
				if($res->status != 200){
					return $this->response(404, 'Profile could not be deleted '.h::encode_php($res));
					}
				}

			if(!empty($opt['deleteImage'])){

				// delete image
				$res = media::delete_profile_media(['imageID' => $opt['deleteImage']->imageID]);

				// error or not found
				if($res->status != 204){
					e::logtrigger('image with imageID '.$opt['deleteImage']->imageID.' could not be deleted: '.h::encode_php($res));
					return $this->response(404, 'Could not delete image '.h::encode_php($res));
					}

				// get profile
				$res = profile::get(['profileID' => $opt['deleteImage']->profileID]);

				// on error
				if(!in_array($res->status, [200, 404])){
					return self::response(409, 'profile could not be loaded '.$res->status);
					}

				// not found
				elseif ($res->status == 404) {
					return self::response(409, 'profile not found '.$res->status);
					}

				// assign
				$profile = $res->data;

				// deleted image is defined as profile front image
				if($opt['deleteImage']->imageID == $profile->imageID){

					// Get profile images
					$res = media::get_profile_media([
						'profileID' => $opt['deleteImage']->profileID,
						'moderator' => 0
						]);

					// on error
					if($res->status != 200){
						return $this->response(404, 'Could not load images '.h::encode_php($res));
						}

					// assign
					$images = $res->data;

					// replace profile image by first candidate if exists else by 0
					$res = profile::update([
						'profileID' => $profile->profileID,
						'imageID'	=> !empty($images) ? $images[0]->imageID : 0
						]);

					// on error
					if($res->status != 204){
						return self::response(409, 'profile front image could not be replaced after delete '.$res->status);
						}
					}
				}

			if(!empty($opt['profiles_list'])){

				// load profiles
				$res = profile::get_list(['poolID'=>$opt['profiles_list']->poolID]);

				// on error
				if($res->status != 200){
					return $this->response(404, 'pool could not be updated'.h::encode_php($res));
					}
				}

			// response
			return $this->response(200, json_encode($res->data ?? []));
			//return (object)['status'=>200, 'data'=>json_encode($res->data)];
			}

		// no POST
		return $this->page_index();
		}


	public function get_chat($req){

		$opt = h::eX($req, [
			// ^$| accept empty string
			'msisdn_long'			=> '~^$|^(?:\+|00|)([1-9]{1}[0-9]{11,20})$',
			'name'					=> '~^$|^[a-zA-Z0-9_-]{3,20}$',
			'msisdn'				=> '~^$|^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'imsi'					=> '~^$|^[1-9]{1}[0-9]{5,15}$',
			'mobileID'				=> '~^$|^[1-9]{1}[0-9]{0,9}$',
			'fakeProfileID'			=> '~^$|^[1-9]{1}[0-9]{0,9}$',
			], $error, true);
		if($error){
			e::logtrigger('Missing or invalid parameter: '.h::encode_php($error));
			return $this->load_page('error/500');
			}

		if(!empty($opt['msisdn_long'])){

			$msisdn = substr($opt['msisdn_long'][0], 0, -6);
			$profileID = ltrim(substr($opt['msisdn_long'][0], -6), '0');

			// check profile
			$res = profile::get(['profileID'=>$profileID]);
			if($res->status != 200){
				return (object)['status'=>404, "data" => "Profile mit profileID".' '.$profileID.' konnte nicht geladen werden.'];
				//return $this->response(404, 'Profile not found. '.h::encode_php($res->status));
				}
			$profile = $res->data;

			}

		else {

			// get profile by name
			if(!empty($opt['name'])){

				$res = profile::get(['name'=>$opt['name']]);

				if($res->status != 200){
					return $this->response(404, 'Profile not found. '.h::encode_php($res->status).' '.h::encode_php($res->data));
					}
				$profile = $res->data;
				}

			// get profile by ID
			elseif(!empty($opt['fakeProfileID'])){

				$res = profile::get(['profileID'=>$opt['fakeProfileID']]);

				if($res->status != 200){
					return $this->response(404, 'Profile not found. '.h::encode_php($res->status).' '.h::encode_php($res->data));
					}
				$profile = $res->data;
				}

			// get mobile by IMSI
			if(!empty($opt['imsi'])){

				$res = mobile::get(['imsi' => $opt['imsi']]);

				if($res->status == 200) $mobile = $res->data;
				}

			// not found, search by mobileID
			if(!empty($opt['mobileID']) && empty($mobile)){

				$res = mobile::get(['mobileID' => $opt['mobileID']]);

				if($res->status == 200) $mobile = $res->data;
				}

			}

		//  get mobile by msisdn from long_msisdn or from $opt[]
		if((!empty($opt['msisdn']) || !empty($msisdn)) && empty($mobile)){
			$res = mobile::get(['msisdn' => !empty($opt['msisdn']) ? $opt['msisdn'][0] : $msisdn]);
			if($res->status != 200){
				return (object)['status'=>404, "data" => "Mobile konnte nicht geladen werden."];
				}
			$mobile = $res->data;
			}

		// get Chat
		$res = message::get([
			'profileID'=>$profile->profileID,
			'mobileID'=>$mobile->mobileID
			]);

		// on error
		if($res->status != 200){
			return $this->response(404, 'server error '.h::encode_php($res->status));
			}

		// empty chats
		if(empty($res->data)){
			$res->data = [ (object) [ "messageID" => 0, "mobileID" => $mobile->mobileID, "profileID" => $profile->profileID, "from" => 2, "text" => "No message."]];
			}

		// normalize long msisdn
		$msisdn_long = !empty($opt['msisdn_long']) ? $opt['msisdn_long'][0] : $mobile->msisdn.str_pad($profile->profileID, 6, '0', STR_PAD_LEFT);

		//get user name
		$result = profile::get(['mobileID'=>$mobile->mobileID]);
		if($result->status == 200) $user = $result->data;
		else $user = [];

		$res->data[] = [$msisdn_long, $profile->profileName, $mobile->msisdn, $user];

		return $res;
		}

	// HTTP Header check if an image or any file exists on server, without loading it's content.
	public function checkRemoteFile($url){

	    $ch = curl_init();

	    curl_setopt($ch, CURLOPT_URL,$url);

	    // don't download content
	    curl_setopt($ch, CURLOPT_NOBODY, 1);
	    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	    if (curl_exec($ch) !== FALSE) {
	        return true;
	    	}
	    else {
	        return false;
	    	}

		}


	}
