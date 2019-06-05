<?php
/*****
 * Version 2.0.2016-06-08
**/
namespace dotdev\app;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class video {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['app_video', [

			// Video Queries
			'l_video'					=> "SELECT v.ID AS `videoID`, v.*, t.ext, t.mimetype, s.ID AS `posterID`
											FROM `video` v
											INNER JOIN `video_type` t ON t.ID = v.typeID
											LEFT JOIN `screenshot` s ON s.videoID = v.ID AND s.poster = 1
											WHERE 1
											",
			's_video_by_videoID'		=> ['l_video', ['WHERE 1' => 'WHERE v.ID = ? LIMIT 1']],

			'l_video_screenshot'		=> "SELECT s.ID AS `screenshotID`, s.videoID, s.sort
											FROM `screenshot` s
											WHERE s.videoID = ?
											ORDER BY s.videoID, s.sort ASC
											",
			's_videotype_by_ext'		=> "SELECT * FROM `video_type` WHERE `ext` = ? LIMIT 1",

			'i_video'					=> "INSERT INTO `video` (`createTime`,`typeID`,`height`,`width`,`duration`,`title`,`fsk`,`views`,`voting`,`votes`) VALUES (?,?,?,?,?,?,?,?,?,?)",
			'i_screenshot'				=> "INSERT INTO `screenshot` (`videoID`,`poster`) VALUES (?,?)",

			'u_video'					=> "UPDATE `video` SET WHERE `ID` = ?",
			'u_screenshot_poster'		=> "UPDATE `screenshot` SET `poster` = IF(`ID` = ?, 1, 0) WHERE `videoID` = ?",

			'd_video'					=> "DELETE FROM `video` WHERE `ID` = ?",
			'd_screenshot'				=> "DELETE FROM `screenshot` WHERE `ID` = ?",



			// Pool Queries
			'l_pool'					=> "SELECT * FROM `pool`",
			's_pool'					=> "SELECT * FROM `pool` WHERE `ID` = ? LIMIT 1",

			'i_pool'					=> "INSERT INTO `pool` (`name`) VALUES (?)",

			'u_pool'					=> "UPDATE `pool` SET `name` = ? WHERE `ID` = ?",



			// Poolvideo Queries
			'l_poolvideo'				=> "SELECT v.ID AS `videoID`, v.*, t.ext, t.mimetype, r.hash, r.poolID, r.desc, r.removed, s.ID AS `posterID`
											FROM `relation` r
											INNER JOIN `video` v ON v.ID = r.videoID
											INNER JOIN `video_type` t ON t.ID = v.typeID
											LEFT JOIN `screenshot` s ON s.videoID = v.ID AND s.poster = 1
											WHERE r.poolID = ? AND 1
											",
			's_poolvideo_by_videoID'	=> ['l_poolvideo', ['AND 1' => 'AND r.videoID = ? LIMIT 1']],
			's_poolvideo_by_hash'		=> ['l_poolvideo', ['r.poolID = ? AND 1' => 'r.hash = ? LIMIT 1']],
			'l_poolvideo_tag'			=> "SELECT * FROM `relation_tag` r WHERE r.poolID = ? AND r.videoID = ? ORDER BY r.videoID, r.name ASC",

			'i_poolvideo'				=> "INSERT INTO `relation` (`poolID`,`videoID`,`hash`,`desc`) VALUES (?,?,?,?)",
			'i_poolvideo_tag'			=> "INSERT INTO `relation_tag` (`poolID`,`videoID`,`tag`) VALUES (?,?,?)",

			'u_poolvideo'				=> "UPDATE `relation` SET WHERE `poolID` = ? AND `videoID` = ?",

			'd_poolvideo_tags'			=> "DELETE FROM `relation_tag` WHERE `poolID` = ? AND `videoID` = ?",



			// App Queries
			'u_video_add_vote'			=> "UPDATE `video` SET `voting` = ((`voting` * `votes`) + ?) / (`votes` + 1), `votes` = `votes` + 1  WHERE `ID` = ?",
			'u_video_incby_views'		=> "UPDATE `video` SET `views` = `views` + ? WHERE `ID` = ?",

			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_nexus');
		}


	/* Static values */
	public static $source_path = '/video/src';
	public static $screenshot_path = '/video/screenshot';
	public static $upload_path = '/video/upload';
	public static $videohash_chars = 'abcdefghijkmnopqrstuvwxyz0123456789';


	/* Helper Functions */
	protected static function _extend_video($data){

		// best practise for a list of videos
		if(is_array($data)){

			// make assoc list and append empty screenshot (and tag) list to videos
			$assoc_list = [];
			foreach($data as $video){
				$poolID = !empty($video->poolID) ? $video->poolID : 0;
				$assoc_list[$poolID][$video->videoID] = $video;
				$video->screenshots = [];
				if($poolID) $video->tags = [];
				}

			// run through assoc list for each pool
			foreach($assoc_list as $poolID => $pool_list){

				// create mergeable list of videoIDs
				$videoID_list = array_keys($pool_list);

				// make special query
				$res = self::pdo_query([
					'query'		=> self::pdo_extract('l_video_screenshot', ['s.videoID = ?' => 's.videoID IN ('.implode(',', $videoID_list).')']),
					'no_cache'	=> true
					]);

				// get screenshot list
				$screenshot_list = ($res->status == 200) ? $res->data : [];

				// append screenshots to video list
				foreach($screenshot_list as $entry){
					$pool_list[$entry->videoID]->screenshots[] = $entry->screenshotID;
					}

				// for poolvideos
				if($poolID){

					// make special query
					$res = self::pdo_query([
						'query'		=> self::pdo_extract('l_poolvideo_tag', ['r.videoID = ?' => 'r.videoID IN ('.implode(',', $videoID_list).')']),
						'param'		=> [$poolID],
						'no_cache'	=> true
						]);

					// get screenshot list
					$tag_list = ($res->status == 200) ? $res->data : [];

					// append screenshots to video list
					foreach($tag_list as $entry){
						$pool_list[$entry->videoID]->tags[] = $entry->name;
						}
					}
				}

			// return list
			return $data;
			}


		// or best practise for a single entry
		// search screenshot
		$screenshot_list = self::pdo('l_video_screenshot', [$data->videoID]);
		if($screenshot_list === false){
			self::response(560); // log error only
			$screenshot_list = [];
			}

		// and append
		$data->screenshots = [];
		foreach($screenshot_list as $entry){
			$data->screenshots[] = $entry->screenshotID;
			}

		// for poolvideo
		if(!empty($data->poolID)){

			// search tags
			$tag_list = self::pdo('l_poolvideo_tag', [$data->poolID, $data->videoID]);
			if($tag_list === false){
				self::response(560); // log error only
				$tag_list = [];
				}

			// and append
			$data->tags = [];
			foreach($tag_list as $entry){
				$data->tags[] = $entry->name;
				}
			}

		// return entry
		return $data;
		}

	public static function get_upload($req = []){

		$list = [];

		foreach(scandir($_SERVER['DATA_PATH'].self::$upload_path) as $entry){

			// skip hidden and every non file entry
			if($entry[0] === '.' or !is_file($_SERVER['DATA_PATH'].self::$upload_path.'/'.$entry)) continue;

			// also skip each file with unsupported extension
			if(!preg_match('/^(.{1,255})\.(jpg|mp4)$/', $entry, $match)){
				continue;
				}

			// extract info
			list($file, $name, $ext) = $match;

			// check existance of entry
			if(!isset($list[$name])){
				$list[$name] = (object)[
					'filekey'	=> $name,
					'src'		=> null,
					'screenshot'=> null,
					];
				}

			// add video
			if($ext == 'mp4') $list[$name]->src = $file;
			if($ext == 'jpg') $list[$name]->screenshot = $file;
			}

		return self::response(200, $list);
		}



	/* Video Functions */
	public static function get_video($req = []){

		// alternative
		$alt = h::eX($req, [
			'videoID'	=> '~1,16777215/i',
			], $error, true);
		if($error) return self::response(400, $error);

		// param order 1: videoID
		if(isset($alt['videoID'])){

			// search entry in DB
			$entry = self::pdo('s_video_by_videoID', [$alt['videoID']]);
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// extend entry
			$entry = self::_extend_video($entry);

			return self::response(200, $entry);
			}

		// param order 2: no param
		if(empty($req)){

			// search in DB
			$list = self::pdo('l_video');
			if($list === false) return self::response(560);

			// extend entry
			$list = self::_extend_video($list);

			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need videoID or no parameter');
		}

	public static function create_video($req){

		// mandatory
		$mand = h::eX($req, [
			'filekey'	=> '~^.{1,255}$',
			'height'	=> '~80,2560/i',
			'width'		=> '~80,2560/i',
			'duration'	=> '~1,65535/i',
			], $error);

		// optional
		$opt =  h::eX($req, [
			'createTime'=> '~Y-m-d H:i:s/d',
			'title'		=> '~^.{0,255}$',
			'fsk'		=> '~0,21/i',
			'views'		=> '~0,16777215/i',
			'voting'	=> '~0,5/f',
			'votes'		=> '~0,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define files
		$src_file = $_SERVER['DATA_PATH'].self::$upload_path.'/'.$mand['filekey'].'.mp4';
		$screenshot_file = $_SERVER['DATA_PATH'].self::$upload_path.'/'.$mand['filekey'].'.jpg';

		// check existance of files
		if(!file_exists($src_file) or !file_exists($screenshot_file)){
			return self::response(400, ['filekey']);
			}

		// get typeID
		$type = self::pdo('s_videotype_by_ext', 'mp4');
		if(!$type) return $type === false ? self::response(560) : self::response(400, ['link']);

		// define defaults
		$opt += [
			'createTime'=> date('Y-m-d H:i:s'),
			'title' 	=> $mand['filekey'],
			'fsk'		=> 0,
			'views'		=> 0,
			'voting'	=> 0,
			'votes'		=> 0,
			];

		// create videoID
		$videoID = self::pdo('i_video', [
			$opt['createTime'],
			$type->ID,
			$mand['height'],
			$mand['width'],
			$mand['duration'],
			$opt['title'],
			$opt['fsk'],
			$opt['views'],
			$opt['voting'],
			$opt['votes'],
			]);
		if(!$videoID) return self::response(560);

		// create screenshotID
		$screenshotID = self::pdo('i_screenshot', [$videoID, 1]);
		if(!$screenshotID){

			// first log error
			self::response(560);

			// revert changes
			$unset = self::pdo('d_video', $videoID);
			if($unset === false) self::response(560);

			return self::response(500);
			}

		// move screenshot
		if(!rename($screenshot_file, $_SERVER['DATA_PATH'].self::$screenshot_path.'/'.$screenshotID.'.jpg')){

			// revert changes
			$unset = self::pdo('d_screenshot', $screenshotID);
			if($unset === false) self::response(560);

			$unset = self::pdo('d_video', $videoID);
			if($unset === false) self::response(560);

			return self::response(500, 'Cannot move screenshot file');
			}

		// move file
		if(!rename($src_file, $_SERVER['DATA_PATH'].self::$source_path.'/'.$videoID.'.mp4')){

			// revert changes
			$unset = self::pdo('d_video', $videoID);
			if($unset === false) self::response(560);

			return self::response(500, 'Cannot move video file');
			}

		return self::response(201, (object)['videoID'=>$videoID, 'screenshotID'=>$screenshotID]);
		}

	public static function update_video($req){

		// mandatory
		$mand = h::eX($req, [
			'videoID'		=> '~1,16777215/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'height'	=> '~80,2560/i',
			'width'		=> '~80,2560/i',
			'duration'	=> '~1,65535/i',
			'title'		=> '~^.{0,255}$',
			'fsk'		=> '~0,21/i',
			'views'		=> '~0,16777215/i',
			'voting'	=> '~0,5/f',
			'votes'		=> '~0,4294967295/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$entry = self::pdo('s_video_by_videoID', $mand['videoID']);
		if(!$entry) return self::response($entry === false ? 560 : 404);

		// if there is something to update
		if($opt){

			// param set for query
			$param = array_values($opt);
			array_push($param, $entry->videoID);

			// make special build query for only updating wanted keys
			$res = self::pdo_query([
				'query'		=> self::pdo_extract('u_video', ['SET' => 'SET `'.implode('` = ?, `', array_keys($opt)).'` = ?']),
				'param'		=> $param,
				'no_cache'	=> true
				]);
			if($res->status != 200) return $res;
			}

		// if redis accessable, expire entry
		$res = self::redis_unset([
			'search'	=> 'videopool:*:video:'.$entry->videoID,
			]);

		// if redis accessable, expire all videopool lists (not perfect, but suitable)
		$res = self::redis_unset([
			'search'	=> 'videopool:*:list',
			]);

		// return success
		return self::response(204);
		}



	/* Pool Functions */
	public static function get_pool($req = []){

		// alternativ
		$alt = h::eX($req, [
			'poolID'	=> '~1,65535/i',
			], $error, true);
		if($error) return self::response(400, $error);

		// param order 1: poolID
		if(isset($alt['poolID'])){

			// search entry in DB
			$entry = self::pdo('s_pool', [$alt['poolID']]);
			if(!$entry) return self::response($entry === false ? 560 : 404);

			return self::response(200, $entry);
			}

		// param order 2: no param
		if(empty($req)){

			// search in DB
			$list = self::pdo('l_pool');
			if($list === false) return self::response(560);

			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need poolID no parameter');
		}

	public static function create_pool($req){

		// mandatory
		$mand = h::eX($req, [
			'name'	=> '~^.{1,60}$',
			], $error);
		if($error) return self::response(400, $error);

		// create entry
		$poolID = self::pdo('i_pool', $mand['name']);
		if(!$poolID) return self::response(560);

		// return success
		return self::response(201, (object)['poolID'=>$poolID]);
		}

	public static function update_pool($req){

		// mandatory
		$mand = h::eX($req, [
			'poolID'	=> '~1,65535/i',
			'name'		=> '~^.{1,60}$',
			], $error);
		if($error) return self::response(400, $error);

		// create entry
		$poolID = self::pdo('u_pool', [$mand['name'], $mand['poolID']]);
		if(!$poolID) return self::response(560);

		// return success
		return self::response(201, (object)['poolID'=>$poolID]);
		}



	/* Poolvideo Functions */
	public static function get_poolvideo($req = []){

		// alternative
		$alt = h::eX($req, [
			'poolID'	=> '~1,65535/i',
			'videoID'	=> '~1,16777215/i',
			'hash'		=> '~^['.self::$videohash_chars.']{16}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: poolID + videoID
		if(isset($alt['poolID']) and isset($alt['videoID'])){

			// init redis
			$redis = self::redis();
			$ckey_poolvideo = 'videopool:'.$alt['poolID'].':video:'.$alt['videoID'];

			// if redis accessable, search for entry in redis
			if($redis and $redis->exists($ckey_poolvideo)){

				// and return it directly
				return self::response(200, $redis->get($ckey_poolvideo));
				}

			// search entry
			$entry = self::pdo('s_poolvideo_by_videoID', [$alt['poolID'], $alt['videoID']]);
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// extend entry
			$entry = self::_extend_video($entry);

			// if redis accessable
			if($redis){

				// cache video data
				$redis->set($ckey_poolvideo, $entry, ['ex'=>28800, 'nx']); // 8 hours

				// and cache hash assoc
				$redis->set('videopool:'.$entry->poolID.':videoID_of_hash:'.$entry->hash, $entry->videoID, ['ex'=>28800, 'nx']); // 8 hours
				}

			return self::response(200, $entry);
			}

		// param order 2: poolID + hash
		if(isset($alt['poolID']) and isset($alt['hash'])){

			// init redis
			$redis = self::redis();
			$ckey_poolvideo_videoID = 'videopool:'.$alt['poolID'].':videoID_of_hash:'.$alt['hash'];

			// if redis accessable, search for entry in redis
			if($redis and $redis->exists($ckey_poolvideo_videoID)){

				// define redis key with videoID
				$ckey_poolvideo = 'videopool:'.$alt['poolID'].':video:'.$redis->get($ckey_poolvideo_videoID);

				//  search for entry in redis
				if($redis->exists($ckey_poolvideo)){

					// and return it directly
					return self::response(200, $redis->get($ckey_poolvideo));
					}
				}

			// search entry in DB
			$entry = self::pdo('s_poolvideo_by_hash', [$alt['hash']]);
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// extend entry
			$entry = self::_extend_video($entry);

			// if redis accessable
			if($redis){

				// cache hash assoc
				$redis->set($ckey_poolvideo_videoID, $entry->videoID, ['ex'=>28800, 'nx']); // 8 hours

				// and cache video data
				$redis->set('videopool:'.$entry->poolID.':video:'.$entry->videoID, $entry, ['ex'=>28800, 'nx']); // 8 hours
				}

			return self::response(200, $entry);
			}

		// param order 3: only poolID
		if(isset($alt['poolID'])){

			// init redis
			$redis = self::redis();
			$ckey_poolvideolist = 'videopool:'.$alt['poolID'].':list';

			// if redis accessable, search for list in redis
			if($redis and $redis->exists($ckey_poolvideolist)){

				// get list
				$list = $redis->hGetAll($ckey_poolvideolist);

				// if is array, return it
				if(is_array($list)) return self::response(200, array_values($list));
				}

			// search in DB
			$list = self::pdo('l_poolvideo', [$alt['poolID']]);
			if($list === false) return self::response(560);

			// extend entries
			$list = self::_extend_video($list);

			// if redis accessable
			if($redis){

				// create hashlist
				$hashlist = [];
				foreach($list as $entry){
					$hashlist[$entry->videoID] = $entry;
					}

				// cache videolist
				$redis->hMSet($ckey_poolvideolist, $hashlist);
				$redis->setTimeout($ckey_poolvideolist, 28800); // 8 hours
				}

			return self::response(200, $list);
			}


		// other request param invalid
		return self::response(400, 'need poolID (+ videoID or hash)');
		}

	public static function create_poolvideo($req){

		// mandatory
		$mand = h::eX($req, [
			'videoID'	=> '~1,16777215/i',
			'poolID'	=> '~1,65535/i',
			], $error);

		 // optional
		$opt = h::eX($req, [
			'desc'		=> '~^.{0,255}$',
			'tagnames'	=> '~/a',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if tagnames list is not empty
		if(!empty($opt['tagnames'])){

			// check each tagname
			foreach($opt['tagnames'] as $tagname){
				if(!h::is($tagname, '~^[a-z0-9\_]{1,32}$')) return self::response(400, ['tagnames']);
				}
			}

		// check existance of entry
		$chk = self::pdo('s_poolvideo_by_videoID', [$mand['poolID'], $mand['videoID']]);
		if($chk === false) return self::response(560);
		elseif($chk) return self::response(409);

		// create unique hash
		$charrange = strlen(self::$videohash_chars)-1;
		$x = 0;
		do{
			$x++;
			$hash = '';
			for($i=1;$i<=16;$i++){
				$hash .= self::$videohash_chars[mt_rand(0, $charrange)];
				}

			$chk = self::pdo('s_poolvideo_by_hash', [$hash]);
			if($chk === false) return self::response(560);
			elseif($chk and $x >= 16) return self::response(500, 'Timeout creating hash for video relation');
			} while($chk);

		// add poolvideo
		$ins = self::pdo('i_poolvideo', [$mand['poolID'], $mand['videoID'], $hash, isset($opt['desc']) ? $opt['desc'] : '']);
		if($ins === false) return self::response(560); // $ins has no primary key value, because videoID+poolID

		// if tagname list given and not empty
		if(!empty($opt['tagnames'])){

			// define query param
			$param = [];
			foreach($opt['tagnames'] as $tagname){
				array_push($param, $entry->poolID, $entry->videoID, $tagname);
				}

			// and make special query inserting all given tags
			$res = self::pdo_query([
				'query'		=> self::pdo_extract('i_poolvideo_tag', ['(?,?,?)' => '(?,?,?)'.(count($opt['tagnames']) > 1 ? str_repeat(',(?,?,?)', count($opt['tagnames'])-1) : '')]),
				'param'		=> $param,
				'no_cache'	=> true
				]);
			if($res->status != 200) return $res;
			}

		// if redis accessable, expire videopool list
		$res = self::redis_unset([
			'search'	=> 'videopool:'.$mand['poolID'].':list',
			]);

		return self::response(204, (object)['videoID'=>$mand['videoID'], 'poolID'=>$mand['poolID'], 'hash'=>$hash]);
		}

	public static function update_poolvideo($req){

		// mandatory
		$mand = h::eX($req, [
			'poolID'	=> '~1,65535/i',
			'videoID'	=> '~1,16777215/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'desc'		=> '~^.{0,255}$',
			'removed'	=> '~/b',
			], $error, true);

		// optional 2
		$opt2 = h::eX($req, [
			'tagnames'	=> '~sequential/a',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if tagnames list is not empty
		if(!empty($opt2['tagnames'])){

			// check each tagname
			foreach($opt2['tagnames'] as $tagname){
				if(!h::is($tagname, '~^[a-z0-9\_]{1,32}$')) return self::response(400, ['tagnames']);
				}
			}

		// load entry
		$entry = self::pdo('s_poolvideo_by_videoID', [$mand['poolID'], $mand['videoID']]);
		if(!$entry) return self::response($entry === false ? 560 : 404);

		// if there is something to update
		if($opt){

			// define query param
			if(isset($opt['removed'])) $opt['removed'] = $opt['removed'] ? 1 : 0;
			$param = array_values($opt);
			array_push($param, $entry->poolID, $entry->videoID);

			// and make special query updating only wanted keys
			$res = self::pdo_query([
				'query'		=> self::pdo_extract('u_poolvideo', ['SET' => 'SET `'.implode('` = ?, `', array_keys($opt)).'` = ?']),
				'param'		=> $param,
				'no_cache'	=> true
				]);
			if($res->status != 200) return $res;
			}

		// if tagname list given
		if(isset($opt2)){

			// first delete all tags
			$del = self::pdo('d_poolvideo_tags', [$entry->poolID, $entry->videoID]);
			if($del === false) return self::response(560);

			// if tagname list is not empty
			if(!empty($opt2)){

				// define query param
				$param = [];
				foreach($opt2 as $tagname){
					array_push($param, $entry->poolID, $entry->videoID, $tagname);
					}

				// and make special query inserting all given tags
				$res = self::pdo_query([
					'query'		=> self::pdo_extract('i_poolvideo_tag', ['(?,?,?)' => '(?,?,?)'.(count($opt2) > 1 ? str_repeat(',(?,?,?)', count($opt2)-1) : '')]),
					'param'		=> $param,
					'no_cache'	=> true
					]);
				if($res->status != 200) return $res;
				}
			}

		// if redis accessable, expire entry
		$res = self::redis_unset([
			'search'	=> 'videopool:'.$entry->poolID.':video:'.$entry->videoID,
			]);

		// if redis accessable, expire videopool list
		$res = self::redis_unset([
			'search'	=> 'videopool:'.$entry->poolID.':list',
			]);

		// return success
		return self::response(204);
		}



	/* App Functions */
	public static function get_poolvideo_randomized($req){

		// mandatory
		$mand = h::eX($req, [
			'poolID'	=> '~1,65535/i',
			'max'		=> '~1,128/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'filterTag'	=> '~^[a-z0-9\_]{1,32}$',
			'skip'		=> '~/a',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default
		$opt += ['filterTag'=>null, 'skip'=>[]];

		// check skip entries
		if(isset($opt['skip'])){
			foreach($opt['skip'] as $videoID){
				if(!h::is($videoID, '~1,16777215/i')) return self::response(400, ['skip']);
				}
			}

		// get poolvideo list
		$res = self::get_poolvideo(['poolID'=>$mand['poolID']]);
		if($res->status != 200) return $res;
		$list = $res->data;

		// check if list is empty
		if(empty($list)) return self::response(200, []);

		// filter videos
		foreach($list as $k => $entry){

			// filter removed
			if($entry->removed){
				unset($list[$k]);
				continue;
				}

			// filter tags
			if($opt['filterTag']){
				if(!in_array($opt['filterTag'], $entry->tags)){
					unset($list[$k]);
					}
				}
			}

		// shuffle list
		shuffle($list);

		// result list
		$result_list = [];
		$i = 0;

		// take videos until finished
		foreach($list as $entry){

			// skip unwanted
			if($opt['skip'] and in_array($entry->videoID, $opt['skip'])) continue;

			// refer entry in result list
			$result_list[] = $entry;
			$i++;

			// stop if max is reached
			if($i >= $mand['max']) break;
			}

		return self::response(200, $result_list);
		}

	public static function get_poolvideo_paged($req){

		// mandatory
		$mand = h::eX($req, [
			'poolID'	=> '~1,65535/i',
			'max'		=> '~1,256/i',
			'page'		=> '~1,65536/i',
			], $error);
		// optional
		$opt = h::eX($req, [
			'filterTag'	=> '~^[a-z0-9\_]{1,32}$',
			'orderBy'	=> '~^(?:videoID|ID|createTime|voting|votes|views)$',
			'order'		=> '~^DESC$|^ASC$',
			], $error, true);
		if($error) return self::response(400, $error);

		// default
		$opt += ['filterTag'=>null, 'orderBy'=>'ID', 'order'=>'DESC'];

		// get poolvideo list
		$res = self::get_poolvideo(['poolID'=>$mand['poolID']]);
		if($res->status != 200) return $res;
		$list = $res->data;

		// result obj
		$result = (object)[
			'page'		=> $mand['page'],
			'pages'		=> 1,
			'max'		=> $mand['max'],
			'number'	=> 0,
			'orderBy'	=> $opt['orderBy'],
			'order'		=> $opt['order'],
			'filterTag'	=> $opt['filterTag'],
			'list'		=> [],
			];

		// check if list is empty
		if(empty($list)) return self::response(200, $result);

		// filter videos
		foreach($list as $k => $entry){

			// filter removed
			if($entry->removed){
				unset($list[$k]);
				continue;
				}

			// filter tags
			if($opt['filterTag']){
				if(!in_array($opt['filterTag'], $entry->tags)){
					unset($list[$k]);
					}
				}
			}

		// define count of filtered list
		$result->number = count($list);

		// order
		usort($list, function($a, $b) use ($opt){

			// special date sorting
			if($opt['orderBy'] == 'createTime'){
				if($opt['order'] == 'ASC') return h::date($a->createTime) > h::date($b->createTime);
				return h::date($a->createTime) < h::date($b->createTime);
				}

			// default sorting
			if($opt['order'] == 'ASC') return $a->{$opt['orderBy']} > $b->{$opt['orderBy']};
			return $a->{$opt['orderBy']} < $b->{$opt['orderBy']};
			});

		// for pagination
		$i = 0;
		$skip = ($mand['page']-1) * $mand['max'];

		// take videos for this page
		foreach($list as $entry){

			// check skip
			if($skip > 0){
				$skip--;
				continue;
				}

			// refer entry in result list
			$result->list[] = $entry;
			$i++;

			// stop if max is reached
			if($i >= $mand['max']) break;
			}

		// define pagination
		if($result->number > $result->max){
			$pagemodulo = $result->number % $result->max;
			$result->pages = ($result->number - $pagemodulo) / $result->max;
			if($pagemodulo > 0) $result->pages++;
			}

		return self::response(200, $result);
		}

	public static function increment_video_views($req){

		// mandatory
		$mand = h::eX($req, [
			'poolID'	=> '~1,65535/i',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'videoID'	=> '~1,16777215/i',
			'hash'		=> '~^['.self::$videohash_chars.']{16}$',
			], $error, true);

		// optional
		$opt = h::eX($req, [
			'by'		=> '~1,1000/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(count($alt) !== 1) return self::response(400, 'need videoID or hash together with poolID');

		// append alt
		$mand += $alt;

		// Load Video
		$res = self::get_poolvideo($mand);
		if($res->status != 200) return $res;
		$entry = $res->data;

		// define optional
		$opt += ['by'=>1];

		// update DB
		$upd = self::pdo('u_video_incby_views', [$opt['by'], $entry->videoID]);
		if($upd === false) return self::response(560);

		// init redis
		$redis = self::redis();
		$ckey_poolvideo = 'videopool:'.$entry->poolID.':video:'.$entry->videoID;
		$ckey_poolvideolist = 'videopool:'.$entry->poolID.':list';

		// if redis accessable
		if($redis){

			// set incremented views
			$entry->views += $opt['by'];

			// if video is already cached
			if($redis->exists($ckey_poolvideo)){

				// and update video data
				$redis->set($ckey_poolvideo, $entry, ['ex'=>28800]); // 8 hours
				}

			// if video is already cached in videolist
			if($redis->hExists($ckey_poolvideolist, $entry->videoID)){

				// and update video data
				$redis->hSet($ckey_poolvideolist, $entry->videoID, $entry);
				}
			}

		// success
		return self::response(204);
		}

	public static function add_video_voting($req){

		// mandatory
		$mand = h::eX($req, [
			'poolID'	=> '~1,65535/i',
			'voting'	=> '~1,5/f',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'videoID'	=> '~1,16777215/i',
			'hash'		=> '~^['.self::$videohash_chars.']{16}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(count($alt) !== 1) return self::response(400, 'need videoID or hash together with poolID');

		// append alt
		$mand += $alt;

		// Load Video
		$res = self::get_poolvideo($mand);
		if($res->status != 200) return $res;
		$entry = $res->data;

		// update DB
		$upd = self::pdo('u_video_add_vote', [$mand['voting'], $entry->videoID]);
		if($upd === false) return self::response(560);

		// init redis
		$redis = self::redis();
		$ckey_poolvideo = 'videopool:'.$entry->poolID.':video:'.$entry->videoID;
		$ckey_poolvideolist = 'videopool:'.$entry->poolID.':list';

		// if redis accessable
		if($redis){

			// set voting and votes
			$entry->voting = (($entry->voting * $entry->votes) + $mand['voting']) / ($entry->votes + 1);
			$entry->votes++;

			// if video is already cached
			if($redis->exists($ckey_poolvideo)){

				// and update video data
				$redis->set($ckey_poolvideo, $entry, ['ex'=>28800]); // 8 hours
				}

			// if video is already cached in videolist
			if($redis->hExists($ckey_poolvideolist, $entry->videoID)){

				// and update video data
				$redis->hSet($ckey_poolvideolist, $entry->videoID, $entry);
				}
			}

		// success
		return self::response(204);
		}

	}