<?php
/*****
 * Version 1.0.2016-08-30
**/
namespace dotdev\app\aurora;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class base {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	//entry definitions
	protected static function pdo_config() {
		return ['app_aurora:aurora', [

			//queries: Menu
			'l_menu'					=> 'SELECT * FROM `menu` ORDER BY `menu_uri` DESC',
			's_menu'					=> 'SELECT * FROM `menu` WHERE `menuID`=? ORDER BY `menu_uri` DESC LIMIT 1',
			'i_menu'					=> 'INSERT INTO `menu` (`menu_name`, `menu_uri`) VALUES (?, ?)',
			'd_menu'					=> 'DELETE FROM `menu` WHERE `menuID` = ?',
			'u_menu'					=> 'UPDATE `menu` SET `menu_name` = ?, `menu_uri` = ? WHERE `menuID` = ?',
			'count_menu'				=> 'SELECT COUNT(*) AS entries FROM `menu`',

			//queries: Content
			'l_content'					=> 'SELECT * FROM `content` ORDER BY `content_uri` ASC',
			's_content'					=> 'SELECT * FROM `content` WHERE `contentID`= ? ORDER BY `content_uri` ASC LIMIT 1',
			's2_content'				=> 'SELECT * FROM `content` WHERE `content_uri` = ? ORDER BY `created_at` DESC',
			's_only_uri'				=> 'SELECT DISTINCT `content_uri` FROM `content` ORDER BY `content_uri` ASC',
			'i_content'					=> 'INSERT INTO `content` (`content_text`, `content_header`, `content_uri`) VALUES (?, ?, ?)',
			'd_content'					=> 'DELETE FROM `content` WHERE `contentID` = ?',
			'u_content'					=> 'UPDATE `content` SET `content_header` = ?, `content_text` = ?, `content_uri` = ? WHERE `contentID` = ?',
			'count_content'				=> 'SELECT COUNT(*) AS entries FROM `content`',

			//queries: User
			'l_user'					=> 'SELECT * FROM `user` ORDER BY `created_at` DESC',
			's_user'					=> 'SELECT * FROM `user` WHERE `userID` = ? ORDER BY `created_at` DESC LIMIT 1',
			'login_user'				=> 'SELECT `username`, `auth` FROM `user` WHERE BINARY `username` = ? LIMIT 1',
			'i_user'					=> 'INSERT INTO `user` (`username`, `auth`) VALUES (?, ?)',
			'd_user'					=> 'DELETE FROM `user` WHERE `userID` = ?',
			'u_user'					=> 'UPDATE `user` SET `username` = ?, `auth` = ? WHERE `userID` = ?',
			'count_user'				=> 'SELECT COUNT(*) AS entries FROM `user`',

			]];
		}

	// Redis
	public static function redis(){

		return redis::load_resource('app_aurora');
		}

	//Object Menu
	public static function get_menu($req = []){

		//alternative
		$alt = h::eX($req, [
			'menuID'	=> '~1,255/i',
			], $error, true);

		//Error
		if($error) return self::response(400, $error);

		//execute if menuID was set
		if(isset($alt['menuID'])) {

			// init redis
			$redis = self::redis();
			$cache_key = 'menu:by_menuID:'.$alt['menuID'];

			// if redis accessable, search for entry
			if($redis and $redis->exists($cache_key)){

				$entry = $redis->get($cache_key);
				}

			else{

				// create entry
				$entry = self::pdo('s_menu', [$alt['menuID']]);

				//return error when menuID was not found
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable, cache entry
				if($redis){

					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			return self::response(200, $entry);
			}

		//execute if no menuID was set -> load complete list
		if(empty($req)){

			// init redis
			$redis = self::redis();
			$cache_key = 'menus';

			if($redis and $redis->exists($cache_key)) {
				$list = $redis->get($cache_key);
				}

			else {
				// load list from DB
				$list = self::pdo('l_menu');
				if($list === false) return self::response(560);

				//e::logtrigger('DEBUG: '.h::encode_php($list));

				if($redis) {
						$redis->set($cache_key, $list);
					}
				}

			// return list
			return self::response(200, $list);
			}

		// other request param invalID
		return self::response(400, ['need menuID or no parameter']);

		}

	public static function add_menu($req = []){

		// mandatory
		$mand = h::eX($req, [
			'menu_name'			=> '~^.{1,30}$',
			'menu_uri'			=> '~^[a-zA-Z0-9\_]{1,60}$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// create entry
		$menuID = self::pdo('i_menu', [$mand['menu_name'], $mand['menu_uri']]);
		if($menuID === false) return self::response(560);

		$redis = self::redis();
		if($redis){
			$redis->setTimeout('menus', 0);
			}

		return self::response(201, (object)['menuID' => $menuID]);
		}

	public static function delete_menu($req = []){

		//mandatory
		$mand = h::eX($req, [
			'menuID'	=> '~1,255/i',
			], $error, true);

		//Error
		if($error) return self::response(400, $error);

		//execute if menuID was set
		if(isset($mand['menuID'])) {
			$entry = self::pdo('d_menu', [$mand['menuID']]);

			//return error when menuID was not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// if redis accessable, delete its key
			$redis = self::redis();
			if($redis){
				$redis->del('menu:by_menuID:'.$mand['menuID']);
				$redis->setTimeout('menus', 0);
				}

			return self::response(200, $entry);
			}

		//on empty
		if(empty($req)) {
			return self::response(400, ['need menuID']);
			}
		}

	public static function update_menu($req = []){

		// mandatory
		$mand = h::eX($req, [
			'menuID'			=> '~1,255/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'menu_name'			=> '~^.{1,30}$',
			'menu_uri'			=> '~^[a-zA-Z0-9\_]{1,60}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		//load entry
		$res = self::get_menu(['menuID' => $mand['menuID']]);
		//if status of $res (=return of add_menu) than output the status of add_menu (560 or 404)
		if($res->status != 200) return $res;
		//otherwise safe the data's as entry
		$entry = $res->data;

		//for each entry in $opt, set the given value to the key
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_menu', [$entry->menu_name, $entry->menu_uri, $entry->menuID]);
		if($upd === false) return self::response(560);

		// if redis accessable, expire entry
		$redis = self::redis();
		if($redis){
			$redis->setTimeout('menu:by_menuID:'.$entry->menuID, 0);
			$redis->setTimeout('menus', 0);
			}

		return self::response(204, 'Gespeichert.');
		}

	public static function count_menu_entries ($req = []){

		//execute if no menuID was set -> load complete list
		if(empty($req)){

			// load list from DB
			$list = self::pdo('count_menu');
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalID
		return self::response(400, ['need menuID or no parameter']);
		}

	//Object Content
	public static function get_content($req = []){

		//alternative
		$alt = h::eX($req, [
			'contentID'	=> '~1,255/i',
			], $error, true);

		//Error
		if($error) return self::response(400, $error);

		//execute if contentID was set
		if(isset($alt['contentID'])) {

			// init redis
			$redis = self::redis();
			$cache_key = 'content:by_contentID:'.$alt['contentID'];

			// if redis accessable, search for entry
			if($redis and $redis->exists($cache_key)){

				$entry = $redis->get($cache_key);
				}

			else{

				//create entry
				$entry = self::pdo('s_content', [$alt['contentID']]);

				//return error when contentID was not found
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable, cache entry
				if($redis){

					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			return self::response(200, $entry);
			}

		//execute if no contentID was set -> load complete list
		if(empty($req)){

			// init redis
			$redis = self::redis();
			$cache_key = 'contents';

			if($redis and $redis->exists($cache_key)) {
				$list = $redis->get($cache_key);
				}

			else {
				// load list from DB
				$list = self::pdo('l_content');
				if($list === false) return self::response(560);

				if($redis) {
					$redis->set($cache_key, $list);
					}
				}

			// return list
			return self::response(200, $list);
			}

		// other request param invalID
		return self::response(400, ['need contentID or no parameter']);
		}

	public static function get_content2($req = []){

		//alternative
		$alt = h::eX($req, [
			'content_uri'	=> '~^[a-zA-Z0-9\_]{0,60}$',
			], $error, true);

		//Error
		if($error) return self::response(400, $error);

		//execute if content_uri was set
		if(isset($req['content_uri'])) {

			// init redis
			$redis = self::redis();
			$cache_key = 'content:by_content_uri:'.$alt['content_uri'];

			// if redis accessable, search for entry
			if($redis and $redis->exists($cache_key)){

				$entry = $redis->get($cache_key);
				}

			else{

				//create entry
				$entry = self::pdo('s2_content', [$alt['content_uri']]);

				//return error when content_uri was not found
				if(!$entry) return self::response($entry === false ? 560 : 404);

				if($redis){

					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			return self::response(200, $entry);
			}

		//execute if no content_uri was set -> response 404
		if(empty($req)){
			return self::response(404, ['URI not found']);
			}

		// other request param invalID
		return self::response(400, ['need content_uri']);
		}

	public static function get_only_uri($req = []){

		//execute if no contentID was set -> load complete list
		if(empty($req)){

			// init redis
			$redis = self::redis();
			$cache_key = 'contents_uri';

			if($redis and $redis->exists($cache_key)) {
				$list = $redis->get($cache_key);
				}

			else {

				// load list from DB
				$list = self::pdo('s_only_uri');
				if($list === false) return self::response(560);

				if($redis) {
					$redis->set($cache_key, $list, ['ex'=>21600, 'nx']);
					}
				}

			// return list
			return self::response(200, $list);
			}

		// other request param invalID
		return self::response(400, ['need contentID or no parameter']);
		}

	public static function count_content_entries ($req = []){

		//execute if no menuID was set -> load complete list
		if(empty($req)){

			// load list from DB
			$list = self::pdo('count_content');
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalID
		return self::response(400, ['need menuID or no parameter']);

		}

	public static function add_content($req = []){

		// mandatory
		$mand = h::eX($req, [
			'content_text'		=> '~^.{1,5000}$',
			'content_header'	=> '~^.{1,100}$',
			'content_uri'		=> '~^[a-zA-Z0-9\_]{0,60}$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// create entry
		$contentID = self::pdo('i_content', [$mand['content_text'], $mand['content_header'], $mand['content_uri']]);
		if($contentID === false) return self::response(560);

		$redis = self::redis();
		if($redis){
			$redis->setTimeout('content:by_content_uri:'.$mand['content_uri'], 0);
			$redis->setTimeout('contents', 0);
			$redis->setTimeout('contents_uri', 0);
			}

		return self::response(201, (object)['contentID' => $contentID]);
		}

	public static function delete_content($req = []){

		//mandatory
		$mand = h::eX($req, [
			'contentID'			=> '~1,255/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'content_uri'		=> '~^[a-zA-Z0-9\_]{0,60}$',
			], $error, true);

		//Error
		if($error) return self::response(400, $error);

		//execute if contentID was set
		if(isset($mand['contentID'])) {
			$entry = self::pdo('d_content', [$mand['contentID']]);

			//return error when contentID was not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// if redis accessable, delete its key
			$redis = self::redis();
			if($redis){
				$redis->del('content:by_content_uri:'.$opt['content_uri']);
				$redis->del('content:by_contentID:'.$mand['contentID']);
				$redis->setTimeout('contents', 0);
				$redis->setTimeout('contents_uri', 0);
				}

			return self::response(200, $entry);
			}

		if(empty($req)) {
			return self::response(400, ['need contentID']);
			}
		}

	public static function update_content($req = []){

		// mandatory
		$mand = h::eX($req, [
			'contentID'			=> '~1,255/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'content_text'		=> '~^.{1,5000}$',
			'content_header'	=> '~^.{1,100}$',
			'content_uri'		=> '~^[a-zA-Z0-9\_]{0,60}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		//load entry
		$res = self::get_content(['contentID' => $mand['contentID']]);
		//if status of $res (=return of add_menu) than output the status of add_menu (560 or 404)
		if($res->status != 200) return $res;
		//otherwise safe the datas as entry
		$entry = $res->data;

		//for each entry in $opt, set the given value to the key
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_content', [$entry->content_header, $entry->content_text, $entry->content_uri, $entry->contentID]);
		if($upd === false) return self::response(560);

		// if redis accessable, expire entry
		$redis = self::redis();
		if($redis){
			$redis->setTimeout('content:by_contentID:'.$entry->contentID, 0);
			$redis->setTimeout('content:by_content_uri:'.$entry->content_uri, 0);
			$redis->setTimeout('contents', 0);
			$redis->setTimeout('contents_uri', 0);
			}

		return self::response(204);
		}

	//Object User
	public static function get_user($req = []){

		//alternative
		$alt = h::eX($req, [
			'userID'	=> '~1,255/i',
			], $error, true);

		//Error
		if($error) return self::response(400, $error);

		//execute if userID was set
		if(isset($alt['userID'])) {

			// init redis
			$redis = self::redis();
			$cache_key = 'user:by_userID:'.$alt['userID'];

			// if redis accessable, search for entry
			if($redis and $redis->exists($cache_key)){
				$entry = $redis->get($cache_key);
				}

			else{

				//create entry
				$entry = self::pdo('s_user', [$alt['userID']]);

				//return error when userID was not found
				if(!$entry) return self::response($entry === false ? 560 : 404);

					// if redis accessable, cache entry
				if($redis){
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			return self::response(200, $entry);
			}

		//execute if no userID was set -> load complete list
		if(empty($req)){

			// init redis
			$redis = self::redis();
			$cache_key = 'users';

			if($redis and $redis->exists($cache_key)) {
				$list = $redis->get($cache_key);
				}

			else {
				// load list from DB
				$list = self::pdo('l_user');
				if($list === false) return self::response(560);

				if($redis) {
					$redis->set($cache_key, $list);
					}
				}

			// return list
			return self::response(200, $list);
			}

		// other request param invalID
		return self::response(400, ['need userID or no parameter']);
		}

	public static function add_user($req = []){

		// mandatory
		$mand = h::eX($req, [
			'username'			=> '~^[a-zA-Z0-9\S]{1,40}$',
			'auth'				=> '~^[a-z0-9]{40}$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// create entry
		$userID = self::pdo('i_user', [$mand['username'], $mand['auth']]);
		if($userID === false) return self::response(560);

		$redis = self::redis();
		if($redis){
			$redis->setTimeout('users', 0);
			}

		return self::response(201, (object)['userID' => $userID]);
		}

	public static function delete_user($req = []){

		//mandatory
		$mand = h::eX($req, [
			'userID'		=> '~1,255/i',
			], $error, true);

		//Error
		if($error) return self::response(400, $error);

		//execute if userID was set
		if(isset($mand['userID'])) {
			$entry = self::pdo('d_user', [$mand['userID']]);

			//return error when userID was not found
			if(!$entry) return self::response($entry === false ? 560 : 404);

			// if redis accessable, delete its key
			$redis = self::redis();
			if($redis){
				$redis->del('user:by_userID:'.$mand['userID']);
				$redis->setTimeout('users', 0);
				}

			return self::response(200, $entry);
			}

		if(empty($req)) {
			return self::response(400, ['need userID']);
			}
		}

	public static function update_user($req = []){

		// mandatory
		$mand = h::eX($req, [
			'userID'			=> '~1,255/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'username'			=> '~^[a-zA-Z0-9\S]{1,40}$',
			'auth'				=> '~^[a-z0-9]{40}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		//load entry
		$res = self::get_user(['userID' => $mand['userID']]);
		//if status of $res (=return of add_menu) than output the status of add_menu (560 or 404)
		if($res->status != 200) return $res;
		//otherwise safe the datas as entry
		$entry = $res->data;

		//for each entry in $opt, set the given value to the key
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// update
		$upd = self::pdo('u_user', [$entry->username, $entry->auth, $entry->userID]);
		if($upd === false) return self::response(560);

		// if redis accessable, expire entry
		$redis = self::redis();
		if($redis){
			$redis->setTimeout('user:by_userID:'.$entry->userID, 0);
			$redis->setTimeout('users', 0);
			}

		return self::response(204);
		}

	public static function login_user ($req = []){

		//mandatory
		$mand = h::eX($req, [
			'username'			=> '~^[a-zA-Z0-9\S]{1,40}$',
			'auth'				=> '~^[a-z0-9]{40}$',
			], $error, true);

		//Error
		if($error) return self::response(400, $error);

		//create entry
		$entry = self::pdo('login_user', [$mand['username']]);
		if(!$entry) return self::response($entry === false ? 560 : 404);

		if($entry->auth == $mand['auth']){
			return self::response(200, $entry);
			}

		//return error when username was not found
		return self::response(400, ['wrong username or auth']);
		}

	public static function count_user_entries ($req = []){

		//execute if no menuID was set -> load complete list
		if(empty($req)){

			// load list from DB
			$list = self::pdo('count_user');
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalID
		return self::response(400, ['need menuID or no parameter']);

		}

	}