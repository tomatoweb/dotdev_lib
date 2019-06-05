<?php
/*****
 * Version 1.0.2017-11-12
**/
namespace dotdev\app\extension_trait;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\session;
use \dotdev\persist;

trait usersession {

	public $us;
	public $_us_lvl1_cache = [];
	public $_us_lvl1_cache_keynames = [];

	public function usersession_init($app_cfg = []){

		$cfg = $app_cfg + [
			'with_persistID'=> true,
			];

		$this->us = (object)[
			'usID' 		=> 0,
			'redis' 	=> null,
			'error'		=> false,
			'isnew' 	=> false,
			];

		// try to take sessionID from GET/POST param
		if(h::cR('usID')){

			// try to open session
			$res = session::open(['sessionID'=>h::gR('usID'), 'reset'=>(bool) h::gR('sesreset')]);
			if($res->status == 200){
				$this->us->redis = $res->data->handle;
				$this->us->usID = $res->data->sessionID;
				}
			elseif($res->status == 500){
				$this->us->error = true;
				return false;
				}
			}

		// try take special string for url
		if(!$this->us->error and preg_match('/^\/S:([^\/]+)(\/.*|)/', $_SERVER['DOCUMENT_URI'], $match)){

			// remove this special string from url
			$_SERVER['DOCUMENT_URI'] = empty($match[2]) ? '/' : $match[2];

			// if we have no session (and error)
			if(!$this->us->error and !$this->us->usID){

				// try to open session
				$res = session::open(['sessionID'=>$match[1], 'no_check'=>true, 'reset'=>(bool) h::gR('sesreset')]);
				if($res->status == 200){
					$this->us->redis = $res->data->handle;
					$this->us->usID = $res->data->sessionID;
					}
				elseif($res->status == 500){
					$this->us->error = true;
					return false;
					}
				}
			}

		// if we have no session (and error), try to find a cookie
		if(!$this->us->error and !$this->us->usID and h::gC('usID')){

			// try to open session
			$res = session::open(['sessionID'=>h::gC('usID'), 'reset'=>(bool) h::gR('sesreset')]);
			if($res->status == 200){
				$this->us->redis = $res->data->handle;
				$this->us->usID = $res->data->sessionID;
				}
			elseif($res->status == 500){
				$this->us->error = true;
				return false;
				}
			}

		// if we did not have a session, create a new one
		if(!$this->us->error and !$this->us->usID){

			// try to create session
			$res = session::create([]);
			if($res->status == 200){
				$this->us->redis = $res->data->handle;
				$this->us->usID = $res->data->sessionID;
				$this->us->isnew = true;
				}
			elseif($res->status == 500){
				$this->us->error = true;
				return false;
				}
			}

		// if persistID is wanted and we did not have one
		if($cfg['with_persistID'] and !$this->us_get('persistID')){

			// create a persistID
			$res = persist::create();
			if($res->status !== 200){
				$this->us->error = true;
				return false;
				}

			// do not overwrite a faster concurrent process (and do not set lvl1 cache for the same reason)
			$this->us->redis->hSetNx($this->us->usID, 'persistID', $res->data->persistID);
			}

		// return boolean, if session exists
		return (!$this->us->error and $this->us->usID);
		}

	public function us_connected(){

		if(!$this->us->redis->isConnected()){
			e::logtrigger('Redis Session Verbindung verloren');
			return false;
			}
		return true;
		}

	public function us_set($set, $val = null, $nx = false){


		if(is_string($set)){
			$set = [$set=>$val];
			}

		if(!$this->us_connected()) return false;


		foreach($set as $key => $val){

			// nx set (= only if field does not yet exist)
			if($nx){
				$this->us->redis->hSetNx($this->us->usID, $key, $val);
				$this->_us_lvl1_cache[$key] = $this->us->redis->hGet($this->us->usID, $key);
				}

			// no nx set
			else{
				$this->us->redis->hSet($this->us->usID, $key, $val);
				$this->_us_lvl1_cache[$key] = $val;
				}

			$this->_us_lvl1_cache_keynames[$key] = true;
			}

		}

	public function us_get($key, $get_uncached = false){

		if(!$get_uncached and isset($this->_us_lvl1_cache_keynames[$key])){
			return isset($this->_us_lvl1_cache[$key]) ? $this->_us_lvl1_cache[$key] : null;
			}

		if($this->us_connected() and $this->us->redis->hExists($this->us->usID, $key)){
			$this->_us_lvl1_cache[$key] = $this->us->redis->hGet($this->us->usID, $key);
			$this->_us_lvl1_cache_keynames[$key] = true;
			return $this->_us_lvl1_cache[$key];
			}

		return null;
		}

	public function us_is($key, $check = true, $get_uncached = false){

		if(!$get_uncached and isset($this->_us_lvl1_cache_keynames[$key])){
			return h::is(isset($this->_us_lvl1_cache[$key]) ? $this->_us_lvl1_cache[$key] : null, $check);
			}

		if(!$this->us_connected()) return null;

		return ($this->us->redis->hExists($this->us->usID, $key) and h::is($this->us_get($key), $check));
		}

	public function us_delete($set){

		if(is_string($set)){
			$set = [$set];
			}

		if(!$this->us_connected()) return false;

		foreach($set as $key){
			$this->us->redis->hDel($this->us->usID, $key); // Wenn das hier fehlschlägt, gibt es auch den Key nicht
			unset($this->_us_lvl1_cache[$key]);
			unset($this->_us_lvl1_cache_keynames[$key]);
			}
		}

	public function us_url($url, $use_url_usid = false){

		if(!$url or $url[0] != '/') $url = '/'.$url;

		$prefix = (strpos($url, '://') !== false) ? '' : $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'];

		if($use_url_usid) return $prefix.(strpos($url, '/S:') === false ? '/S:'.$this->us->usID : '').$url;
		else return $prefix.$url.(strpos($url, '?') === false ? '?' : '&').(strpos($url, 'usID=') === false ? 'usID='.$this->us->usID.'&' : '').substr($_SERVER['REQUEST_TIME'], -3);
		}

	public function us_same_url($append_url = '', $use_url_usid = false){

		return $this->us_url($this->env_get('nexus:url').$append_url, $use_url_usid);
		}

	public function us_get_like($str = null){

		if(!$this->us_connected()) return false;

		if(!$str){
			return $this->us->redis->hGetAll($this->us->usID);
			}

		$keys = $this->us->redis->hKeys($this->us->usID);
		if(!is_array($keys)) return false;

		$found = [];
		$strlen = strlen($str);
		foreach($keys as $key){
			if(substr($key, 0, $strlen) === $str) $found[$key] = $this->us_get($key);
			}

		return $found;
		}

	public function us_reset(){

		if(!$this->us_connected()) return false;

		// check if persistID was used
		$with_persistID = $this->us_get('persistID') ? true : false;
		$session_time = $this->us_get('session_createtime');

		// expire complete hashKey
		$this->us->redis->setTimeout($this->us->usID, 0);
		$this->_us_lvl1_cache = [];
		$this->_us_lvl1_cache_keynames = [];

		// add old session time
		$this->us_set('session_createtime', $session_time);

		// if persistID was used and we did not have one
		if($with_persistID and !$this->us_get('persistID')){

			// create a persistID
			$res = persist::create();
			if($res->status !== 200){
				$this->us->error = true;
				return false;
				}

			// do not overwrite a faster concurrent process (and do not set lvl1 cache for the same reason)
			$this->us->redis->hSetNx($this->us->usID, 'persistID', $res->data->persistID);
			}

		return true;
		}

	public function us_renew(){

		// check if persistID was used
		$with_persistID = $this->us_get('persistID') ? true : false;

		$this->us = (object)[
			'usID' 		=> 0,
			'redis' 	=> null,
			'error'		=> false,
			'isnew' 	=> false,
			];

		// try to create session
		$res = session::create([]);
		if($res->status != 200){
			$this->us->error = true;
			return false;
			}

		$this->us->redis = $res->data->handle;
		$this->us->usID = $res->data->sessionID;
		$this->us->isnew = true;

		// if persistID was used and we did not have one
		if($with_persistID){

			// create a persistID
			$res = persist::create();
			if($res->status !== 200){
				$this->us->error = true;
				return false;
				}

			// set persistID
			$this->us_set('persistID', $res->data->persistID);
			}

		return true;
		}

	public function us_set_cookie(){

		static $processed = false;
		if($processed) return true;

		// set or updates cookie
		setcookie("usID", $this->us->usID, $_SERVER['REQUEST_TIME']+1200, '/');

		$processed = true;
		return true;
		}

	public function us_is_cookie_session(){

		// check if no cookie exists, or the cookie holds the same usID
		return (!h::cC('usID') or h::cC('usID', $this->us->usID));
		}

	}
