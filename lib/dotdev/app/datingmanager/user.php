<?php
/*****
 * Version 1.0.2019-04-10
**/
namespace dotdev\app\datingmanager;

use \tools\error as e;
use \tools\helper as h;

class user {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* PDO/Redis Config */
	protected static function pdo_config(){
		return ['app_datingmgm', [
			'l_user'			=> 'SELECT `email`, sha1(`email`) AS `shaemail` FROM `user`',
			's_user'			=> 'SELECT *, sha1(`email`) AS `shaemail` FROM `user` WHERE `email` = ? LIMIT 1',
			's_user_by_shaemail'=> 'SELECT *, sha1(`email`) AS `shaemail` FROM `user` WHERE sha1(`email`) = ? LIMIT 1',
			'i_user'			=> 'INSERT INTO `user` (`email`,`auth`) VALUES (?,?)',
			'u_user'			=> 'UPDATE `user` SET `auth` = ? WHERE `email` = ?',
			'd_user'			=> 'DELETE FROM `user` WHERE `email` = ?',

			'l_user_right' 		=> 'SELECT `key` FROM `user_right` WHERE `email` = ? ORDER BY `key` ASC',
			'i_user_right'		=> 'INSERT INTO `user_right` (`email`,`key`) VALUES (?,?)',
			'd_user_right'		=> 'DELETE FROM `user_right` WHERE `email` = ?',

			'd_right'			=> 'DELETE FROM `user_right` WHERE `key` = ?',
			'd_right_nouser'	=> 'DELETE r FROM `user_right` r LEFT JOIN `user` u ON u.email = r.email WHERE u.email IS NULL',
			]];
		}

	public static function redis_config(){

		return 'app_datingmgm';
		}


	/* Object: user */
	public static function get_user($req = []){

		// alternativ
		$alt = h::eX($req, [
			'email'		=> '~^[a-z0-9\-\.]+@[a-z0-9\-]+\.[a-z]{2,5}$',
			'shaemail'	=> '~^[a-z0-9]{40}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: email
		if(isset($alt['email'])){

			// init redis
			$redis = self::redis();

			// define cache key
			$cache_key = 'xadmin:user:'.$alt['email'];
			$cache_key_sha = 'xadmin:user:'.sha1($alt['email']);

			// define entry
			$entry = null;

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// take entry
				$entry = $redis->get($cache_key);

				// TEMP: unset old entry
				if(!is_object($entry) or !isset($entry->right)) $entry = null;
				}

			// if no entry loaded
			if(!$entry){

				// load from DB
				$entry = self::pdo('s_user', $alt['email']);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// define user right
				$entry->right = [];

				// load user rights
				$right_list = self::pdo('l_user_right', $entry->email);

				// for each entry
				foreach($right_list as $right){

					// add key
					$entry->right[] = $right->key;
					}

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					$redis->set($cache_key_sha, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// return entry
			return self::response(200, $entry);
			}

		// param order 2: shaemail
		if(isset($alt['shaemail'])){

			// init redis
			$redis = self::redis();

			// define cache key
			$cache_key_sha = 'xadmin:user:'.$alt['shaemail'];

			// define entry
			$entry = null;

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key_sha)){

				// take entry
				$entry = $redis->get($cache_key_sha);

				// TEMP: fixed old cache type
				if(!is_object($entry) or !isset($entry->right)) $entry = null;
				}

			// if no entry loaded
			if(!$entry){

				// load entry from DB
				$entry = self::pdo('s_user_by_shaemail', $alt['shaemail']);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// define user right
				$entry->right = [];

				// load user rights
				$right_list = self::pdo('l_user_right', $entry->email);

				// for each entry
				foreach($right_list as $right){

					// add key
					$entry->right[] = $right->key;
					}

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set('xadmin:user:'.$entry->email, $entry, ['ex'=>21600, 'nx']); // 6 hours
					$redis->set($cache_key_sha, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// return entry
			return self::response(200, $entry);
			}

		// param order 3: no param
		if(empty($req)){

			// load list
			$list = self::pdo('l_user');

			// on error
			if($list === false) return self::response(560);

			// return list
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need email, shaemail or no parameter');
		}

	public static function create_user($req = []){

		// mandatory
		$mand = h::eX($req, [
			'email'		=> '~^[a-z0-9\-\.]+@[a-z0-9\-]+\.[a-z]{2,5}$',
			'auth'		=> '~^[a-z0-9]{40}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'right'		=> '~sequential/a',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'right'=> [],
			];

		// for each key
		foreach($opt['right'] as $pos => $key){

			// if invalid, return error
			if(!h::is($key, '~^[a-zA-Z0-9\_\:]{1,120}$')) return self::response(400, 'Invalid key at pos '.$pos.' of param right');
			}

		// load user
		$res = self::get_user([
			'email'	=> $mand['email'],
			]);

		// on unexpected error
		if(!in_array($res->status, [200,404])) return $res;

		// if found, return conflict
		if($res->status == 200) return self::response(409);

		// insert entry
		$ins = self::pdo('i_user', [$mand['email'], $mand['auth']]);

		// on error
		if($ins === false) return self::response(560);

		// for each key
		foreach($opt['right'] as $key){

			// insert user right
			$insert = self::pdo('i_user_right', [$mand['email'], $key]);

			// on error
			if($insert === false) return self::response(560);
			}

		// return success
		return self::response(201, (object)['email'=>$mand['email'], 'shaemail'=>sha1($mand['email'])]);
		}

	public static function update_user($req = []){

		// mandatory
		$mand = h::eX($req, [
			'email'		=> '~^[a-z0-9\-\.]+@[a-z0-9\-]+\.[a-z]{2,5}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'auth'		=> '~^[a-z0-9]{40}$',
			'right'		=> '~sequential/a',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if rights list is given
		if(isset($opt['right'])){

			// for each key
			foreach($opt['right'] as $pos => $key){

				// if invalid, return error
				if(!h::is($key, '~^[a-zA-Z0-9\_\:]{1,120}$')) return self::response(400, 'Invalid key at pos '.$pos.' of param right');
				}
			}


		// on error
		if($error) return self::response(400, $error);

		// load user
		$res = self::get_user([
			'email'	=> $mand['email'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take user
		$entry = $res->data;

		// define update
		$base_update = false;

		// if there are updateable keys for base entry
		if(isset($opt['auth'])){

			// replace params
			foreach(['auth'] as $key){

				// skip if key should not be updated
				if(!isset($opt[$key])) continue;

				// take new value
				$entry->{$key} = $opt[$key];

				// define update
				$base_update = true;
				}
			}

		// if base entry should be updated
		if($base_update){

			// update
			$upd = self::pdo('u_user', [$entry->auth, $entry->email]);

			// on error
			if($upd === false) return self::response(560);
			}

		// if rights list is given
		if(isset($opt['right'])){

			// delete user rights
			$delete = self::pdo('d_user_right', $entry->email);

			// on error
			if($delete === false) return self::response(560);

			// for each key
			foreach($opt['right'] as $key){

				// insert user right
				$insert = self::pdo('i_user_right', [$entry->email, $key]);

				// on error
				if($insert === false) return self::response(560);
				}
			}

		// init redis
		$redis = self::redis();

		// if redis accessable
		if($redis){

			// expire entry
			$redis->setTimeout('xadmin:user:'.$entry->email, 0);
			$redis->setTimeout('xadmin:user:'.sha1($entry->email), 0);
			}

		// return success
		return self::response(204);
		}

	public static function delete_user($req = []){

		// mandatory
		$mand = h::eX($req, [
			'email'		=> '~^[a-z0-9\-\.]+@[a-z0-9\-]+\.[a-z]{2,5}$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load user
		$res = self::get_user([
			'email'		=> $mand['email'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take user
		$entry = $res->data;

		// delete user
		$delete = self::pdo('d_user', $entry->email);

		// on error
		if($delete === false) return self::response(560);

		// delete user rights
		$delete = self::pdo('d_user_right', $entry->email);

		// on error
		if($delete === false) return self::response(560);

		// init redis
		$redis = self::redis();

		// if redis accessable
		if($redis){

			// expire entry
			$redis->setTimeout('xadmin:user:'.$entry->email, 0);
			$redis->setTimeout('xadmin:user:'.sha1($entry->email), 0);
			}

		// return success
		return self::response(204);
		}


	/* Object: right */
	public static function cleanup_right($req = []){

		// mandatory
		$mand = h::eX($req, [
			'valid_right'	=> '~!empty/a',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// for each key
		foreach($mand['valid_right'] as $pos => $key){

			// if invalid, return error
			if(!h::is($key, '~^[a-zA-Z0-9\_\:]{1,120}$')) return self::response(400, 'Invalid key at pos '.$pos.' of param keys');
			}

		// define notin list
		$notin_list = [];

		// for each key
		foreach($mand['valid_right'] as $key){

			// add key to query
			$notin_list[] = self::pdo_quote($key);
			}

		// if there are keys in list
		if($notin_list){

			// extract query and insert notin list
			$query = self::pdo_extract('d_right', ['`key` = ?' => '`key` NOT IN ('.implode(',', $notin_list).')']);

			// delete entries (don't cache query)
			$delete = self::pdo($query, null, ['no_cache'=>true]);

			// on error
			if(!$delete) return self::response(560);
			}

		// delete rights from already deleted user
		$delete = self::pdo('d_right_nouser');

		// on error
		if($delete === false) return self::response(560);

		// unset redis cache
		self::redis_unset();

		// return success
		return self::response(204);
		}

	}
