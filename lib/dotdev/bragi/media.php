<?php
/*****
 * Version 1.0.2019-01-16
**/
namespace dotdev\bragi;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

// DEPRECATED
use \dotdev\app\bragi\profile as old_bragi_profile;


class media {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['app_bragi', [

			// queries: image
			's_image'					=> 'SELECT * FROM `image` WHERE `imageID` = ? LIMIT 1',
			'l_image_by_profileID'		=> 'SELECT * FROM `image` WHERE `profileID` = ?',

			'i_image'					=> 'INSERT INTO `image` (`name`,`profileID`,`moderator`,`fsk`) VALUES (?,?,?,?)',
			'u_image'					=> 'UPDATE `image` SET `moderator` = ?, `fsk` = ? WHERE `imageID` = ?',
			'd_image'					=> 'DELETE FROM `image` WHERE `imageID` = ?',

			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('app_bragi');
		}


	/* Static values */
	public static $profile_path = '/bragiprofile/profile';
	public static $chatmedia_path = '/bragiprofile/chatmedia';


	/* object: profile media */
	public static function get_profile_media($req = []){

		// alternative
		$alt = h::eX($req, [
			'imageID'		=> '~1,16777215/i',
			'profileID'		=> '~1,16777215/i',
			], $error, true);

		// optional
		$filter = h::eX($req, [
			'moderator'		=> '~0,255/i',
			'moderator_list'=> '~!empty/a',
			'fsk'			=> '~0,255/i',
			'max_fsk'		=> '~0,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: imageID
		if(isset($alt['imageID'])){

			// load list from DB
			$entry = self::pdo('s_image', $alt['imageID']);

			// on error or not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// return list
			return self::response(200, $entry);
			}

		// param order 2: profileID
		if(isset($alt['profileID'])){

			// init redis
			$redis = self::redis();

			// define cache key
			$cache_key = 'profile_image_list:'.$alt['profileID'];

			// define list
			$list = null;

			// if redis accessable and list exists
			if($redis and $redis->exists($cache_key)){

				// take list
				$list = $redis->get($cache_key);
				}

			// if no list is loaded
			if(!is_array($list)){

				// load list from DB
				$list = self::pdo('l_image_by_profileID', $alt['profileID']);

				// on error
				if($list === false) return self::response(560);

				// if redis is accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $list, ['ex'=>7200, 'nx']); // 2 hours
					}
				}

			// if there are filter
			if($filter){

				// for each image
				foreach($list as $key => $image){

					// if moderator is set, remove not matching
					if(isset($filter['moderator']) and $image->moderator != $filter['moderator']) unset($list[$key]);

					// if moderator_list is set, remove not matching
					if(isset($filter['moderator_list']) and !in_array($image->moderator, $filter['moderator_list'])) unset($list[$key]);

					// if fsk is set, remove not matching
					if(isset($filter['fsk']) and $image->fsk != $filter['fsk']) unset($list[$key]);

					// if max_fsk is set, remove any above max_fsk
					if(isset($filter['max_fsk']) and $image->fsk > $filter['max_fsk']) unset($list[$key]);
					}

				// convert back to sequential array
				$list = array_values($list);
				}

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need persistID or profileID (+moderator, +fsk) parameter');
		}

	public static function create_profile_media($req = []){

		// mandatory
		$mand = h::eX($req, [
			'profileID'		=> '~1,16777215/i',
			], $error);

		// alternativ
		$alt = h::eX($req, [
			'file'			=> '~1,1000/s',
			'imageID'		=> '~1,16777215/i',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'moderator'		=> '~0,255/i',
			'fsk'			=> '~0,255/i',
			'upload_file'	=> '~^[^\/]{1,100}\.[a-z0-9]{3,4}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(count($alt) != 1) return self::response(400, 'Need file or imageID');

		// define defaults
		$opt += [
			'moderator'		=> 0,
			'fsk'			=> 0,
			];

		// define source file
		$file = (object)[
			'source'		=> null,
			'name'			=> null,
			'ext'			=> null,
			'target'		=> '',
			'target_path'	=> $_SERVER['DATA_PATH'].self::$profile_path.'/ProfileID_'.$mand['profileID'].'',
			'method'		=> 'copy',
			];


		// load profile
		$res = old_bragi_profile::get([
			'profileID'		=> $mand['profileID'],
			]);

		// if profile does not exist, return failed dependency
		if($res->status == 404) return self::response(424);

		// on unexpected error
		if($res->status != 200) return self::response(570, $res);

		// take profile
		$profile = $res->data;


		// param order 1: file
		if(isset($alt['file'])){

			// define source file, name and copy mechanism
			$file->source = $alt['file'];
			$file->name = $opt['upload_file'] ?? substr($file->source, (strrpos($file->source, '/') ?: -1) + 1);
			if(isset($opt['upload_file'])) $file->method = 'move_uploaded_file';
			}

		// param order 3: imageID
		if(isset($alt['imageID'])){

			// load media
			$res = self::get_profile_media([
				'imageID' => $alt['imageID'],
				]);

			// on unexpected error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if not found, return failed dependency
			if($res->status == 404) return self::response(424);

			// define source file, name
			$file->source = $_SERVER['DATA_PATH'].self::$profile_path.'/ProfileID_'.$res->data->profileID.'/'.$res->data->name;
			$file->name = $res->data->name;
			}


		// if source file does not exists
		if(!$file->source or !file_exists($file->source)){

			// return internal server error or failed dependency
			return isset($alt['imageID']) ? self::response(500, 'File of imageID '.$alt['imageID'].' does not exist') : self::response(424);
			}

		// define source extension
		if(strrpos($file->name, '.')) $file->ext = substr($file->name, strrpos($file->name, '.') + 1);


		// define allowed extension
		$allowed_ext = ['jpg','jpeg','png'];

		// for fake profiles, add mp4
		if($profile->poolID) $allowed_ext[] = 'mp4';

		// if target file already exists, return failed dependency
		if(!in_array($file->ext, $allowed_ext)) return self::response(403);

		// define target file
		$file->target = $file->name;

		// if target file already exists, return conflict
		if(file_exists($file->target_path.'/'.$file->target)){

			// save org target name
			$org_name = substr($file->target, 0, (strlen($file->ext) + 1) * -1);
			$i = 1;

			// try to find a name
			do{
				$file->target = $org_name.'_'.$i.'.'.$file->ext;
				$i++;
				} while(file_exists($file->target_path.'/'.$file->target) or $i > 1000);

			// if iteration exeeded, return error
			if($i > 1000) return self::response(500, 'Cannot find filename to copy '.h::encode_php($file->source).' to '.h::encode_php($org_name.'.'.$file->ext));
			}

		// if profile path not exists
		if(!is_dir($file->target_path)){

			// create profile path
			$cmd_result = @mkdir($file->target_path, 0755, true);

			// on error
			if(!$cmd_result) return self::response(500, 'DEBUG: Failed to create profile path '.h::encode_php($file->target_path));
			}

		// move/copy file
		$cmd = ($file->method == 'move_uploaded_file') ? @move_uploaded_file($file->source, $file->target_path.'/'.$file->target) : @copy($file->source, $file->target_path.'/'.$file->target);

		// on error
		if(!$cmd) return self::response(500, 'Cannot '.$file->method.' file '.h::encode_php($file->source).' to '.h::encode_php($file->target_path.'/'.$file->target));


		// create entry
		$imageID = self::pdo('i_image', [$file->target, $mand['profileID'], $opt['moderator'], $opt['fsk']]);

		// on error
		if($imageID === false) return self::response(560);


		// define cache key
		$cache_key = 'profile_image_list:'.$mand['profileID'];

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire entry
			$redis->setTimeout($cache_key, 0);
			}


		// return success
		return self::response(201, (object)['imageID' => $imageID, 'name' => $file->target]);
		}

	public static function update_profile_media($req = []){

		// mandatory
		$mand = h::eX($req, [
			'imageID'	=> '~1,16777215/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'moderator'	=> '~0,255/i',
			'fsk'		=> '~0,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_profile_media([
			'imageID'	=> $mand['imageID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// define updateable
		$updateable = false;

		// replace params
		foreach($opt as $k => $v){

			// skip if value is the same
			if($entry->{$k} === $v) continue;

			// take value and define updateable
			$entry->{$k} = $v;
			$updateable = true;
			}

		// if there is something to update
		if($updateable){

			// update
			$upd = self::pdo('u_image', [$entry->moderator, $entry->fsk, $entry->imageID]);

			// on error
			if($upd === false) return self::response(560);

			// define cache key
			$cache_key = 'profile_image_list:'.$entry->profileID;

			// init redis
			$redis = self::redis();

			// if redis is accessable
			if($redis){

				// expire entry
				$redis->setTimeout($cache_key, 0);
				}
			}

		// return success
		return self::response(204);
		}

	public static function delete_profile_media($req = []){

		// mandatory
		$mand = h::eX($req, [
			'imageID'	=> '~1,16777215/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_profile_media([
			'imageID'	=> $mand['imageID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// update
		$upd = self::pdo('d_image', [$entry->imageID]);

		// on error
		if($upd === false) return self::response(560);

		// define cache key
		$cache_key = 'profile_image_list:'.$entry->profileID;

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// expire entry
			$redis->setTimeout($cache_key, 0);
			}

		// define file
		$file = $_SERVER['DATA_PATH'].self::$profile_path.'/ProfileID_'.$entry->profileID.'/'.$entry->name;

		// delete file
		$cmd_result = shell_exec('rm '.$file);

		// return success
		return self::response(204);
		}


	/* object: chat media */
	public static function create_chat_media($req = []){

		// mandatory
		$mand = h::eX($req, [
			'file'			=> '~1,1000/s',
			], $error);

		// optional
		$opt = h::eX($req, [
			'upload_file'	=> '~^[^\/]{1,100}\.[a-z0-9]{3,4}$',
			'profileID'		=> '~1,16777215/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define shortcut for Year-Month, like (2018-03 == 8c)
		$month_shortcut = ['01'=>'a', '02'=>'b', '03'=>'c', '04'=>'d', '05'=>'e', '06'=>'f', '07'=>'g', '08'=>'h', '09'=>'i', '10'=>'j', '11'=>'k', '12'=>'l'];
		$ym_dir = h::dtstr('today', 'Y')[3].$month_shortcut[h::dtstr('today', 'm')];

		// define source file
		$file = (object)[
			'source'		=> null,
			'name'			=> null,
			'ext'			=> null,
			'target'		=> '',
			'target_path'	=> $_SERVER['DATA_PATH'].self::$chatmedia_path.'/'.$ym_dir,
			'method'		=> 'copy',
			];


		// if profileID is defined
		if(isset($opt['profileID'])){

			// load profile
			$res = old_bragi_profile::get([
				'profileID'		=> $opt['profileID'],
				]);

			// if profile does not exist, return failed dependency
			if($res->status == 404) return self::response(424);

			// on unexpected error
			if($res->status != 200) return self::response(570, $res);
			}


		// define source file, name and copy mechanism
		$file->source = $mand['file'];
		$file->name = $opt['upload_file'] ?? substr($file->source, (strrpos($file->source, '/') ?: -1) + 1);
		if(isset($opt['upload_file'])) $file->method = 'move_uploaded_file';


		// if source file does not exists
		if(!$file->source or !file_exists($file->source)){

			// return failed dependency
			return self::response(424);
			}

		// define source extension
		if(strrpos($file->name, '.')) $file->ext = substr($file->name, strrpos($file->name, '.') + 1);


		// define allowed extension
		$allowed_ext = ['jpg','jpeg','png','mp4'];

		// if target file already exists, return failed dependency
		if(!in_array($file->ext, $allowed_ext)) return self::response(403);


		// define increment counter
		$i = 1;

		// try to find a name
		do{
			$file->target = h::rand_str(8, '', 'abcdefghijklmnopqrstuwxyz0123456789').'.'.$file->ext;
			$i++;
			} while(file_exists($file->target_path.'/'.$file->target) or $i > 1000);

		// if iteration exeeded, return error
		if($i > 1000) return self::response(500, 'Cannot find filename to copy '.h::encode_php($file->source).' to '.h::encode_php($org_name.'.'.$file->ext));


		// if profile path not exists
		if(!is_dir($file->target_path)){

			// create profile path
			$cmd_result = @mkdir($file->target_path, 0755, true);

			// on error
			if(!$cmd_result) return self::response(500, 'DEBUG: Failed to create chatmedia subpath '.h::encode_php($file->target_path));
			}

		// move/copy file
		$cmd = ($file->method == 'move_uploaded_file') ? @move_uploaded_file($file->source, $file->target_path.'/'.$file->target) : @copy($file->source, $file->target_path.'/'.$file->target);

		// on error
		if(!$cmd) return self::response(500, 'Cannot '.$file->method.' file '.h::encode_php($file->source).' to '.h::encode_php($file->target_path.'/'.$file->target));


		// return success
		return self::response(201, (object)['file' => $ym_dir.'/'.$file->target]);
		}


	/* object: upload_preparation */
	public static function get_upload_preparation($req = []){

		// mandatory
		$mand = h::eX($req, [
			'upload_key'	=> '~^[a-z0-9]{40}$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// define cache key
		$cache_key = 'user_upload:'.$mand['upload_key'];

		// init redis
		$redis = self::redis();

		// if redis is not accessable
		if(!$redis) return self::response(500, 'Redis is not accessable for get_upload_preparation ('.h::encode_php($mand).')');

		// if entry not exists
		if(!$redis->exists($cache_key)){

			// return not found
			return self::response(404);
			}

		// load entry
		$entry = $redis->get($cache_key);

		// return result
		return self::response(200, $entry);
		}

	public static function create_upload_preparation($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'		=> '~1,18446744073709551615/i',
			'upload_fn'		=> '~^(?:create_profile_media|create_chat_media)$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'lifetime'		=> '~1,86400/i',
			'append'		=> '~/c',
			], $error, true);

		// upload_param
		$upload_param = h::eX($req, [
			'persistID'		=> '~1,18446744073709551615/i',
			'profileID'		=> '~1,16777215/i',
			'moderator'		=> '~0,255/i',
			'fsk'			=> '~0,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'lifetime'		=> 600, // 10 min
			];

		// define new entry
		$entry = (object)([
			'persistID'		=> $mand['persistID'],
			'upload_fn'		=> $mand['upload_fn'],
			'upload_param'	=> $upload_param,
			'lifetime'		=> h::dtstr(time() + $opt['lifetime']),
			'check_key'		=> 'user_upload_check:'.$mand['persistID'].':'.$mand['upload_fn'],
			] + (isset($opt['append']) ? (array) $opt['append'] : []));

		// define download key as a random hash
		$upload_key = sha1($mand['persistID'].'_'.$mand['upload_fn'].'_'.h::rand_str(10));

		// define cache/check keys
		$cache_key = 'user_upload:'.$upload_key;

		// for media created by/for profiles
		if(isset($opt['profileID'])){

			// use profileID differentation check
			$entry->check_key = 'user_upload_check:'.$mand['persistID'].':'.$mand['upload_fn'].':'.$opt['profileID'];
			}

		// init redis
		$redis = self::redis();

		// if redis is not accessable
		if(!$redis) return self::response(500, 'Redis is not accessable for create_upload_preparation ('.h::encode_php($mand).')');

		// if entry already exists
		if($redis->exists($entry->check_key)){

			// return conflict (already prepared)
			return self::response(409, (object)['upload_key' => $redis->get($entry->check_key)]);
			}

		// set cache entry
		$success = $redis->set($cache_key, $entry, ['ex'=>$opt['lifetime'], 'nx']);

		// on success
		if($success){

			// set check entry
			$redis->set($entry->check_key, $upload_key, ['ex'=>$opt['lifetime'], 'nx']);
			}

		// else if caching failed (concurrent process already does that)
		else {

			// take created upload key
			$upload_key = $redis->get($entry->check_key);

			// if not found
			if(!$upload_key){

				// return error
				return self::response(500, 'create_upload_preparation failed somehow with concurrent process ('.h::encode_php($mand).')');
				}
			}

		// return success/conflict
		return self::response($success ? 201 : 409, (object)['upload_key' => $upload_key]);
		}

	public static function update_upload_preparation($req = []){

		// mandatory
		$mand = h::eX($req, [
			'upload_key'	=> '~^[a-z0-9]{40}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'done'			=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define cache key
		$cache_key = 'user_upload:'.$mand['upload_key'];

		// init redis
		$redis = self::redis();

		// if redis is not accessable
		if(!$redis) return self::response(500, 'Redis is not accessable for update_upload_preparation ('.h::encode_php($mand).')');

		// if entry not exists
		if(!$redis->exists($cache_key)){

			// return success
			return self::response(204);
			}

		// load entry
		$entry = $redis->get($cache_key);

		// if upload is done
		if(!empty($opt['done'])){

			// expire cached entries
			$redis->expire($cache_key, 0);
			$redis->expire($entry->check_key, 0);

			// return success
			return self::response(204);
			}

		/*
		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}
		*/

		// take ttl
		$ttl = $redis->ttl($cache_key);

		// update entry
		$redis->set($cache_key, $entry, ['ex'=>$ttl]);
		$redis->set($cache_key, $entry->check_key, ['ex'=>$ttl]);

		// return success
		return self::response(204);
		}

	}
