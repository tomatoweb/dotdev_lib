<?php
/*****
 * Version 1.0.2019-02-07
**/
namespace dotdev\app\extension_trait;

use \tools\error as e;
use \tools\helper as h;
use \tools\event;
use \dotdev\nexus\base;
use \dotdev\nexus\domain;
use \dotdev\nexus\publisher;
use \dotdev\nexus\levelconfig;
use \dotdev\traffic\event as traffic_event;
use \dotdev\app\video;
use \xadmin\user as xadmin_user;

trait environment {

	protected $_levelconfig_cache = [];

	public function env_init($app_cfg = []){

		// define app config
		$cfg = $app_cfg + [
			'use_nexus_db'		=> true,
			];

		// define default env object
		$env = (object)[
			'fqdn'				=> $_SERVER['SERVER_NAME'],
			'pageID'			=> 0,
			'domainID'			=> 0,
			'domain'			=> $_SERVER['SERVER_NAME'],
			'hash' 				=> '',
			'appID'				=> 0,
			'firmID'			=> 0,
			'publisherID'		=> 0,
			'status' 			=> 'inherite',
			'domain_status' 	=> 'online',
			'url'				=> '/',
			'url_org'			=> '/',
			'is_first_request'	=> false,
			'is_click_request'	=> false,
			'is_xadmin_request'	=> false,
			];

		// $_SERVER['REQUEST_URI'] entspräche /url?params und dem Original Request ohne Änderungen seitens Nginx
		// $_SERVER['DOCUMENT_URI'] hingegen wäre /url ohne GET-Parameter und mit den Änderungen seitens Nginx
		$n = parse_url($_SERVER['DOCUMENT_URI']);
		if(isset($n['path'])){
			$env->url = $env->url_org = urldecode($n['path']);
			}

		// if the nexus shouldn't used
		if(!$cfg['use_nexus_db']){

			// save pageID in session
			$this->us_set('nexus:pageID', $env->pageID);

			// save $env
			foreach($env as $k => $v){
				$this->env_set('nexus:'.$k, $v, $env->pageID);
				}

			return true;
			}

		// define hash and click status
		$hash = '';
		$click_status = 0;

		// look for hash in url
		if(preg_match('/^\/([a-zA-Z0-9]{48})(\/.*|)$/', $env->url, $match)){

			// cut and save suburl after hash as url
			$hash = $match[1];
			$env->url = '/'.ltrim($match[2], '/');
			}

		// look for an pageID in Session
		$session_pageID = $this->us_get('nexus:pageID');

		// look for special internal request param
		if(h::cR('encmud', '~^[a-zA-Z0-9\+\/]*[\=]{0,2}$')){

			// save data to session
			$this->us_set('nexus:encmud', $_REQUEST['encmud']);

			// unset from request fields
			unset($_REQUEST['encmud']);
			unset($_GET['encmud']);
			unset($_POST['encmud']);
			}

		// if pageID in session exists
		if($session_pageID){

			// try to load adtarget with pageID
			$res = domain::get_adtarget([
				'pageID'	=> $session_pageID,
				]);

			// on success
			if($res->status == 200){

				// save adtarget
				$adtarget = $res->data;

				// if user now revisits us over an (other) adtarget (with hash) on domain
				if($hash and $hash !== $adtarget->hash){

					// we create a complete new session (incl. new usID !)
					$renewed = $this->us_renew();

					// if it fails, we directly abort here
					if(!$renewed){
						e::logtrigger('us_renew() failed');
						return false;
						}
					}

				// or if we have no hash
				elseif(!$hash){

					// then simply take it
					foreach($adtarget as $k => $v){
						$env->{$k} = $v;
						}
					}

				// else adtarget is correct, but since the hash exists we do not take it anyway and handle it like a new click
				}
			}

		// if no pageID is set and we have a adtarget hash
		if(!$env->pageID and $hash){

			// ********TEMP FIX FOR CCT SERVER MIGRATION ***********
			$search_domain = $env->fqdn;
			if($search_domain == 'bragiprofile2.cct4.net') $search_domain = 'bragiprofile.cct4.net';

			// try to load with fqdn and hash
			$res = domain::get_adtarget([
				'fqdn'	=> $search_domain ?? $env->fqdn,
				'hash'	=> $hash,
				]);

			// on success
			if($res->status == 200){

				// save adtarget
				$adtarget = $res->data;

				// check if adtarget status means to use mainpage instead
				if($adtarget->status == 'usemp'){

					// use click_status to block adtarget and log user
					$click_status = 6;
					}

				// load referer rule
				if($this->env_is('pub:click_rule', '~^{.*}$', $adtarget->pageID)){
					$click_rule = json_decode($this->env_get('pub:click_rule', $adtarget->pageID));
					}

				// set default when wrong configured
				if(!isset($click_rule)) $click_rule = (object)[];
				if(!isset($click_rule->referer)) $click_rule->referer = true;
				if(!isset($click_rule->pubdata)) $click_rule->pubdata = false;

				// convert to boolean, if referer rule is a timeset
				if(preg_match('/^((?:[0-1][0-9]|2[0-4]):(?:[0-6][0-9]))-((?:[0-1][0-9]|2[0-4]):(?:[0-6][0-9]))$/', $click_rule->referer, $m)){
					$click_rule->referer = h::is_in_daytime('now', $m[1], $m[2]);
					}


				// get referer and extract fqdn
				$referer = (string) h::gE('HTTP_REFERER');

				// if referer exists
				if($referer){

					// check if referer is xadmin tool
					if(strpos($referer, 'my.dotdev.de') !== false or strpos($referer, 'xadmin') !== false){

						// define it is a xadmin request
						$env->is_xadmin_request = true;
						}

					// check referer blacklist (2016-12-01 removed from blacklist: 'kruta.de', 'perfectgirls.net')
					/*
					foreach([] as $k){
						if(strpos($referer, $k) !== false){

							// set status: 1 "Referer Blacklist"
							$click_status = 1;
							break;
							}
						}
					*/
					}

				// if not blocked, check referer existance
				if(!$click_status and $click_rule->referer and !$referer){

					// set  status: 3 "No Referer found"
					$click_status = 3;
					}

				// take backup of $_GET
				$orig_GET = $_GET;

				// if click_status 0 "OK" or 3 "No Referer found"
				if(in_array($click_status, [0, 3]) and $adtarget->publisherID){

					// define value for found valid param
					$found = false;

					// load click_param rule
					if($this->env_is('pub:click_param', '~^\[.+\]$', $adtarget->pageID)){

						// extract publisher click param
						$click_param = json_decode($this->env_get('pub:click_param', $adtarget->pageID), true) ?: [];

						// for each defined param
						foreach($click_param as $key){

							// skip everything unreadable
							if(!$key or !is_string($key)) continue;

							// if we found one key
							if(isset($_GET[$key]) and !$found){

								// if click_status does not fail before
								if($click_status == 0){

									// set click-data for saving
									$this->env_set('nexus:new_click_request', $_GET, $adtarget->pageID);

									// also save it to session
									$this->us_set('nexus:click_data', $_GET);
									}

								// define something valid found
								$found = true;
								}

							// unset publisher key
							unset($_GET[$key]);
							}
						}

					// if click-rule says we need pubdata, but we didn't found something
					if($click_rule->pubdata and !$found){

						//  if status is 3 "No Referer found", convert it to 5 "No Referer and no ClickData found" .... or if not, set "4 No ClickData found"
						$click_status = ($click_status == 3) ? 5 : 4;
						}
					}

				// if click status not 0 "OK"
				if($click_status > 0){

					// save info to create block_click later
					$env->blocked_click = (object)[
						"pageID"		=> $adtarget->pageID,
						"publisherID"	=> $adtarget->publisherID,
						"status"		=> $click_status,
						"referer"		=> $referer,
						"pubdata"		=> $orig_GET,
						];

					// save info for debug
					$this->us_set('nexus:blocked_click', $env->blocked_click);
					}

				// else this is a matched click
				else{

					// define this is the first request
					$env->is_first_request = true;

					// define this is an click request
					$env->is_click_request = true;


					// overwrite url from adtarget settings (this allows to change the suburl after an adtarget-hash without giving the changed url to publisher)
					// - for PreLPs
					if($this->env_get('adtarget:prelp', $adtarget->pageID)){

						// set PreLP url
						$env->url = '/'.$this->env_get('adtarget:prelp', $adtarget->pageID);
						}

					// - for ckeys
					elseif($this->env_get('adtarget:ckey', $adtarget->pageID)){

						// set ckey url
						$env->url = '/'.$this->env_get('app:content_url_prefix', $adtarget->pageID).$this->env_get('adtarget:ckey', $adtarget->pageID);
						}

					// - for videos
					elseif($this->env_get('adtarget:videoID', $adtarget->pageID)){

						// load video from DB
						$res = video::get_poolvideo([
							'poolID'	=> $this->env_get('domain:video_poolID', $adtarget->pageID),
							'videoID'	=> $this->env_get('adtarget:videoID', $adtarget->pageID),
							]);

						// if found
						if($res->status == 200){

							// set video url
							$env->url = '/'.$this->env_get('app:content_url_prefix', $adtarget->pageID).$res->data->hash;
							}

						// if not, set index
						else{

							// set index as url
							$env->url = '/';
							}
						}

					// - for games
					elseif($this->env_get('adtarget:game', $adtarget->pageID)){

						// set game url
						$env->url = '/'.$this->env_get('app:content_url_prefix', $adtarget->pageID).$this->env_get('adtarget:game', $adtarget->pageID);
						}


					// if we have to check the uncover param
					if($this->env_is('pub:uncover_param', '~^\[.+\]$', $adtarget->pageID)){

						// extract publisher uncover param
						$uncover_param = json_decode($this->env_get('pub:uncover_param', $adtarget->pageID), true) ?: [];
						$uncover_name_param = json_decode($this->env_get('pub:uncover_name_param', $adtarget->pageID), true) ?: [];

						// define found
						$found = false;

						// for each defined param
						foreach($uncover_param as $key){

							// skip everything unreadable
							if(!$key or !is_string($key)) continue;

							// if we found one key
							if(h::cG($key, '~^[a-zA-Z0-9\-\_]{1,64}$') and !$found){

								// set pubdata
								$this->env_set('nexus:publisher_uncover_key', h::gG($key), $adtarget->pageID);

								// define found
								$found = true;
								}

							// unset affiliate key
							unset($_GET[$key]);
							}

						// define found
						$found = false;

						// for each defined param
						foreach($uncover_name_param as $key){

							// skip everything unreadable
							if(!$key or !is_string($key)) continue;

							// if we found one key
							if(h::cG($key, '~^[a-zA-Z0-9\-\_]{1,120}$') and !$found){

								// set pubdata
								$this->env_set('nexus:publisher_uncover_name', h::gG($key), $adtarget->pageID);

								// define found
								$found = true;
								}

							// unset affiliate key
							unset($_GET[$key]);
							}
						}

					// if we have to check the affiliate param
					if($this->env_is('pub:affiliate_param', '~^\[.+\]$', $adtarget->pageID)){

						// extract publisher affiliate param
						$affiliate_param = json_decode($this->env_get('pub:affiliate_param', $adtarget->pageID), true) ?: [];

						// define found
						$found = false;

						// for each defined param
						foreach($affiliate_param as $key){

							// skip everything unreadable
							if(!$key or !is_string($key)) continue;

							// if we found one key
							if(h::cG($key, '~^[a-zA-Z0-9\-\_\:\.]{1,255}$') and !$found){

								// set pubdata
								$this->env_set('nexus:publisher_affiliate_key', h::gG($key), $adtarget->pageID);

								// define found
								$found = true;
								}

							// unset affiliate key
							unset($_GET[$key]);
							}
						}

					// expand adtarget data to env
					foreach($adtarget as $k => $v){
						$env->{$k} = $v;
						}
					}
				}
			}

		// if even no pageID is found or acceptable
		if(!$env->pageID){

			// ********TEMP FIX FOR CCT SERVER MIGRATION ***********
			$search_domain = $env->fqdn;
			if($search_domain == 'bragiprofile2.cct4.net') $search_domain = 'bragiprofile.cct4.net';

			// use the primary adtarget for the domain
			$res = domain::get_adtarget([
				'fqdn'	=> $search_domain ?? $env->fqdn,
				'hash'	=> '',
				]);

			// on success
			if($res->status == 200){

				// save adtarget
				$adtarget = $res->data;

				// define this is the first request
				$env->is_first_request = true;

				// if we still have a hash and nothing was blocked or redirected, append hash to url (the router of the app controller should process this)
				if($hash and !$click_status) $env->url = $env->url_org;

				// expand adtarget data to env
				foreach($adtarget as $k => $v){
					$env->{$k} = $v;
					}
				}
			}

		// now we have no pageID, abort
		if(!$env->pageID) return false;

		// save pageID in session
		$this->us_set('nexus:pageID', $env->pageID);

		// get status if this page is online
		$online = in_array(($env->status == 'inherit') ? $env->domain_status : $env->status, ['online','dev']);

		// if online and we have a new hash or this is the actual cookie session
		if($online and ($hash or $this->us_is_cookie_session())){

			// set cookie
			$this->us_set_cookie();
			}

		// save $env
		foreach($env as $k => $v){
			$this->env_set('nexus:'.$k, $v, $env->pageID);
			}

		// if this is a xadmin request
		if($env->is_xadmin_request){

			// save it to session
			$this->us_set('is_xadmin_request', true);
			}

		// if there was a xadmin request before
		if($this->us_get('is_xadmin_request')){

			// set it to this request
			$this->env_set('nexus:is_xadmin_request', true, $env->pageID);
			}

		// check for possible redirect values
		foreach(['adtarget:redirect', 'domain:redirect'] as $key){

			// skip testing adtarget related options, if we have no hash (means this is no adtarget)
			if(!$hash and strpos($key, 'adtarget') !== false) continue;

			// try to take value

			$redirect = $this->env_get($key, $env->pageID);

			// if given
			if($redirect){

				// save it to environment
				$this->env_set('nexus:redirect', $redirect, $env->pageID);

				// and skip other keys
				break;
				}
			}

		// return if load of adtarget was successful
		return true;
		}

	public function env_init_levelconfig_cache($pageID = null){

		// load actual pageID, if not given
		$pageID = $pageID ?: ($this->us_get('nexus:pageID') ?: 0);

		// check if levelconfig cache is loaded for this pageID
		if(!isset($this->_levelconfig_cache[$pageID])){

			// define levelconfig cache
			$this->_levelconfig_cache[$pageID] = [];

			// if pageID is given
			if($pageID){

				// load levelconfig values
				$res = levelconfig::get([
					'level'		=> 'user-inherited',
					'pageID'	=> $pageID,
					]);

				// on error
				if($res->status != 200){
					e::logtrigger('Error '.$res->status.' while loading user-inherited levelconfig for pageID '.$pageID);
					return false;
					}

				// copy values to cache
				foreach($res->data as $key => $val){
					$this->env_set($key, $val, $pageID);
					}
				}
			}

		// return success
		return true;
		}

	public function env_get($key = null, $pageID = null){

		// load actual pageID, if not given
		$pageID = $pageID ?: ($this->us_get('nexus:pageID') ?: 0);

		// init levelconfig cache or abort
		if(!self::env_init_levelconfig_cache($pageID)) return null;

		// return result
		return $key !== null ? h::gX($this->_levelconfig_cache[$pageID], $key) : $this->_levelconfig_cache[$pageID];
		}

	public function env_is($key, $check = true, $pageID = null){

		// load actual pageID, if not given
		$pageID = $pageID ?: ($this->us_get('nexus:pageID') ?: 0);

		// init levelconfig cache or abort
		if(!self::env_init_levelconfig_cache($pageID)) return null;

		// return result
		return h::cX($this->_levelconfig_cache[$pageID], $key, $check);
		}

	public function env_set($klist, $val = null, $pageID = null){

		// convert param to array version, if needed
		if(is_string($klist)) $klist = [$klist => $val];

		// check array version of param
		if(!is_array($klist)){
			e::logtrigger('Wrong call of env_set('.h::encode_php($klist).', '.h::encode_php($val).')');
			return false;
			}

		// load actual pageID, if not given
		$pageID = $pageID ?: ($this->us_get('nexus:pageID') ?: 0);

		// init levelconfig cache or abort
		if(!self::env_init_levelconfig_cache($pageID)) return null;

		// for each param
		foreach($klist as $key => $val){

			// reference link of key
			$link = &$this->_levelconfig_cache[$pageID];

			// create array hierachy from flat key
			foreach(explode(':', $key) as $k){
				if(is_array($link)){
					if(!isset($link[$k])) $link[$k] = [];
					$link = &$link[$k];
					continue;
					}
				return false;
				}

			// save value to key
			$link = $val;
			}

		// return success
		return true;
		}

	public function env_reset_data($in, $arr, $pageID = null){

		// load actual pageID, if not given
		$pageID = $pageID ?: ($this->us_get('nexus:pageID') ?: 0);

		// init levelconfig cache or abort
		if(!self::env_init_levelconfig_cache($pageID)) return null;

		// convert object to array
		if(is_object($arr)) $arr = (array) $arr;

		// check if reset is impossible
		if(!is_array($arr) or empty($in) or !is_string($in)){
			e::logtrigger('cannot env_set_data('.h::encode_php($in).', '.h::encode_php($arr).')');
			return false;
			}

		// reset data hierachy
		$this->_levelconfig_cache[$pageID][$in] = $arr;
		}

	public function env_whitelist_check(){
		static $result;

		if($result === null){
			$result = false;

			$public_ip = ['87.138.211.134','217.91.162.102','217.110.40.178','217.110.40.179','217.110.40.180','217.110.40.181'];

			// check special ip
			if(in_array($_SERVER['REMOTE_ADDR'], $public_ip)){
				$result = true;
				}

			// check local ip
			elseif(substr($_SERVER['REMOTE_ADDR'], 0, 8) === '192.168.' or substr($_SERVER['REMOTE_ADDR'], 0, 5) === '10.0.'){
				$result = true;
				}

			// check xaauth in param
			elseif(h::cG('xaauth', '~^[a-z0-9]{40}$')){

				$res = xadmin_user::get_user(['shaemail'=>h::gG('xaauth')]);
				if($res->status == 200){
					$result = true;

					// save in session
					$this->us_set('xaauth:email', $res->data->email);
					}
				}

			// check xaauih in session
			elseif($this->us_get('xaauth:email')){
				$res = xadmin_user::get_user(['email'=>$this->us_get('xaauth:email')]);
				if($res->status == 200) $result = true;
				}

			}


		return $result;
		}

	public function env_init_preset($app_cfg = []){

		$save_in_session = true;
		$presetID = null;

		// get presetID
		if($this->env_whitelist_check() and h::cG('force_presetID')){
			$presetID = h::gG('force_presetID');
			}
		elseif($this->us_is('nexus:presetID')){
			$presetID = $this->us_get('nexus:presetID');
			$save_in_session = false;
			}
		elseif($this->env_get('domain:presetID')){
			$presetID = $this->env_get('domain:presetID');
			}
		elseif(!empty($app_cfg['default_presetID'])){
			$presetID = $app_cfg['default_presetID'];
			}

		// check if not found
		if(!$presetID){
			e::logtrigger('Es konnte keine presetID für pageID '.$this->env_get('nexus:pageID').' geladen werden. ('.h::encode_php($this->env_get('domain')).')');
			return false;
			}
		if(!is_file($_SERVER['DOCUMENT_ROOT'].'/preset/'.$presetID.'.php')){
			e::logtrigger('Preset "'.$_SERVER['DOCUMENT_ROOT'].'/preset/'.$presetID.'.php'.'" nicht gefunden');
			return false;
			}

		// load preset
		$preset = include($_SERVER['DOCUMENT_ROOT'].'/preset/'.$presetID.'.php');

		// save presetID in session
		if($save_in_session) $this->us_set('nexus:presetID', $presetID);

		// save $preset
		foreach($preset as $k => $v){
			$this->env_set('preset:'.$k, $v);
			}

		return true;
		}

	public function env_redirect(){

		// load redirect
		$redirect = $this->env_get('nexus:redirect');

		// skip entry, if nothing is defined
		if(!$redirect) return;

		// define title
		$title = $this->env_get('nexus:redirect_title'); //, explode(':', $key)[0].'/external', $env->pageID);

		// if redirect targets another adtarget
		if(h::is($redirect, '~1,65535/i')){

			// load adtarget
			$res = domain::get_adtarget([
				'pageID'	=> $redirect,
				]);

			// if found
			if($res->status == 200){

				// take adtarget
				$adtarget = $res->data;

				// define title
				if(!$title) $title = $adtarget->pageID;

				// build url
				$redirect = '{REQUEST_SCHEME}://'.$adtarget->fqdn.($adtarget->hash ? '/'.$adtarget->hash : '').'?{CLICK_DATA}';
				}
			}

		// define click data
		$click_data = '';

		// if click data found in session
		if($this->us_get('nexus:click_data')){

			// for each param
			foreach($this->us_get('nexus:click_data') as $k => $v){

				// add param
				$click_data .= ($click_data ? '&' : '').$k.'='.urlencode($v);
				}
			}

		// define redirect url
		$url = h::replace_in_str($redirect, [
			'{REQUEST_SCHEME}'	=> $_SERVER['REQUEST_SCHEME'] ?? '',
			'{REQUEST_URI}'		=> $_SERVER['REQUEST_URI'] ?? '',
			'{DOCUMENT_URI}'	=> $_SERVER['DOCUMENT_URI'] ?? '',
			'{QUERY_STRING}'	=> $_SERVER['QUERY_STRING'] ?? '',
			'{HTTP_REFERER}'	=> $_SERVER['HTTP_REFERER'] ?? '',
			'{CLICK_DATA}'		=> $click_data,
			'?&'				=> '?',
			]);

		// remove trailing ? or &
		$url = rtrim($url, '?&');

		// set tracker page info
		$this->env_set('tracker:callinfo:page', 'redirect'.($title ? '/'.$title : '/custom'));

		// redirect and exit
		header('HTTP/1.1 302 Found');
		header('Location: '.$url);
		exit;
		}

	public function env_page_simulate($page = null, $var1 = null, $var2 = null, $use_wrapper = true, $postFn = null){

		// abort tracker
		if(method_exists($this, 'tracker_abort')){
			$this->tracker_abort();
			}

		// check if access is allowed
		if(!$this->env_whitelist_check()){
			e::logtrigger('whitelist check failed');
			return $this->response(404);
			}

		// define simulate page, if not set
		if(!$page){

			// check if special simulate page is given
			if(file_exists($this->env_get('preset:path_pages').'/simulate.php')){

				// define simulate page
				$page = 'simulate';
				}

			// return simulate page
			return $this->response_ob(200, function() use ($page){

				// echo header
				echo '<!doctype html>'."\n"
					.'<html xmlns:ng="http://angularjs.org" lang="de">'."\n"
					.'<head>'."\n"
					.'<meta charset="utf-8"/>'."\n"
					.'<meta name="viewport" content="width=device-width, initial-scale=1"/>'."\n"
					.'<meta name="robots" content="noindex,nofollow"/>'."\n"
					.'<base href="http://'.$_SERVER['SERVER_NAME'].'/" />'."\n"
					.'<title>'.$_SERVER['SERVER_NAME'].'</title>'."\n"
					.'<link href="http://cdn.dotdev.de/bootstrap/3.3.4/css/bootstrap.min.css" rel="stylesheet"/>'."\n"
					.'</head>'."\n"
					.'<body>'."\n"
					.($this->env_get('preset:payment:logo') ? '<img src="'.$this->builder_url($this->env_get('preset:payment:logo')).'" style="width:100%;" />' : '<h3 style="text-align:center">'.$this->env_get('nexus:fqdn').'</h3>')."\n"
					.'<table class="table table-condensed">'."\n"
					.'<tr class="success"><td style="width:80px">Preset</td><td>'.$this->env_get('preset:name').' ('.$this->env_get('preset:ID').')</td></tr>'."\n"
					.'<tr><td></td><td></td></tr>'."\n"
					.'<tr class="info"><td>persistID</td><td>'.$this->us_get('persistID').'</td></tr>'."\n"
					.'<tr class="info"><td>usID</td><td>'.$this->us->usID.'</td></tr>'."\n"
					.'</table>'."\n";

				// include special simulate page parts
				if($page) include($this->env_get('preset:path_pages').'/'.$page.'.php');

				// echo footer
				echo '</body>'."\n"
					.'</html>';
				});
			}

		// define page inclusion
		$include_page = $this->env_get('preset:path_pages').'/'.$page.'.php';

		// check wrapper existance
		if($use_wrapper and !file_exists($this->env_get('preset:path_pages').'/wrapper.php')){

			// deaktivate wrapper
			$use_wrapper = false;
			}

		// include simulated page
		return $this->response_ob(200, function() use ($include_page, $use_wrapper){
			include $use_wrapper ? $this->env_get('preset:path_pages').'/wrapper.php' : $include_page;
			}, $postFn);
		}

	public function env_page_debug(){

		// abort tracker
		if(method_exists($this, 'tracker_abort')) $this->tracker_abort();

		// if access is not allowed
		if(!$this->env_whitelist_check()){

			// log error
			e::logtrigger('Debug page access failed for whitelist check');

			// return not found
			return $this->response(404);
			}

		// if special trigger is defined
		if(h::cG('trigger_event', 'payment:success')){

			// trigger test callback
			$res = traffic_event::test_callback([
				'type'		=> $this->env_get('payment:type') ?: 'base',
				'persistID' => $this->us_get('persistID'),
				]);

			// save result
			$this->us_set('debug:callback_test', $res);
			}

		// get some values from session
		$cbtest = $this->us_get('debug:callback_test');
		$blocked_click = $this->us_get('nexus:blocked_click');
		if($blocked_click){
			$blocked_click->info = 'Unknown';
			if($blocked_click->status == 1) $blocked_click->info = 'Referer Blacklist';
			if($blocked_click->status == 2) $blocked_click->info = 'IP Blacklist';
			if($blocked_click->status == 3) $blocked_click->info = 'No Referer found for click rule';
			if($blocked_click->status == 4) $blocked_click->info = 'No ClickData found for click rule';
			if($blocked_click->status == 5) $blocked_click->info = 'No Referer and no ClickData found for click rule';
			if($blocked_click->status == 6) $blocked_click->info = 'Adtarget status defines using main page';
			}

		// define default cdn url
		$cdn_url = 'https://cdn.dotdev.de';

		// define config file
		$config_file = $_SERVER['ENV_PATH'].'/config/service/mtcdn/server.php';

		// if config file is given, load content to cache
		if(is_file($config_file)) $cdn_url = include($config_file);

		// return page
		return $this->response(200, '<!doctype html>'
			.'<html xmlns:ng="http://angularjs.org" lang="de">'
			.'<head>'
			.'<meta charset="utf-8"/>'
			.'<meta name="viewport" content="width=device-width, initial-scale=1"/>'
			.'<meta name="robots" content="noindex,nofollow"/>'
			.'<base href="'.$_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].'/" />'
			.'<title>'.$_SERVER['SERVER_NAME'].'</title>'
			.'<link href="'.$cdn_url.'/bootstrap/3.3.4/css/bootstrap.min.css" rel="stylesheet"/>'
			.'</head>'
			.'<body>'
			.($this->env_get('preset:payment:logo') ? '<img src="'.$this->builder_url($this->env_get('preset:payment:logo')).'" style="width:100%;" />' : '<h3 style="text-align:center">'.$this->env_get('nexus:fqdn').'</h3>')
			.'<table class="table table-condensed">'
			.'<tr class="'.($blocked_click ? '' : 'success').'"><td style="width:80px">pageID</td><td>'.$this->env_get('nexus:pageID').'</td></tr>'
			.($blocked_click ? '<tr class="danger"><td></td><td>Blocked Click for pageID '.$blocked_click->pageID.' because &quot;'.$blocked_click->info.'&quot;</td></tr>' : '')
			.($blocked_click ? '<tr class="danger"><td></td><td>Referer: '.h::encode_php($blocked_click->referer).'</td></tr>' : '')
			.'<tr><td>Preset</td><td>'.$this->env_get('preset:name').' ('.$this->env_get('preset:ID').')</td></tr>'
			.'<tr><td></td><td></td></tr>'
			.'<tr class="info"><td>persistID</td><td>'.$this->us_get('persistID').'</td></tr>'
			.'<tr><td>usID</td><td>'.$this->us->usID.'</td></tr>'
			.'<tr><td></td><td></td></tr>'
			.'<tr class="'.($this->us_get('tracker:last_click_request') ? 'success' : 'danger').'"><td>Publisher</td><td>'.($this->env_get('nexus:publisherID') ? $this->env_get('nexus:publisher').' ('.$this->env_get('nexus:publisherID').')' : '-').'</td></tr>'
			.(!$this->us_get('tracker:last_click_request') ? '<tr class="danger"><td></td><td>Click-Request not identified</td></tr>' : '')
			.($this->us_get('tracker:last_click_request') ? '<tr class="success"><td>PubData</td><td>'.json_encode($this->us_get('tracker:last_click_request')).'</td></tr>' : '')
			.'<tr><td></td><td></td></tr>'
			.($this->us_get('tracker:last_click_request') ? '<tr class="active"><td></td><td>'.($this->env_get('nexus:publisherID') ? '<a href="/debug?xaauth='.h::gG('xaauth').'&trigger_event=payment:success">'.(!h::cX($cbtest, 'status') ? 'Start' : 'Restart').' Payment/Callback Test</a>' : '').'</td></tr>' : '')
			.(h::cX($cbtest, 'status', '~200,204/i') ? '<tr><td>Callback</td><td>'.h::gX($cbtest, 'data:request').'</td></tr>' : '')
			.(h::cX($cbtest, 'status', '~200,204/i') ? '<tr class="'.(h::cX($cbtest, 'data:httpcode', '~200,204/i') ? 'warning' : 'danger').'"><td>Response</td><td>'.h::gX($cbtest, 'data:httpcode').' '.h::gX($cbtest, 'data:response').'</td></tr>' : '')
			.(h::cX($cbtest, 'status', '~400,600/i') ? '<tr class="danger"><td>Error</td><td>Callback not sent with internal error '.h::gX($cbtest, 'status').'</td></tr>' : '')
			.'</table>'
			.'<br/><br/>'
			.'<table class="table table-striped table-condensed">'
			.'<tr><td style="width:80px">Export</td><td><textarea style="width:100%; height:200px; overflow:auto;">{"usID":"'.$this->us->usID.'","session":'.json_encode($this->us_get_like()).',"env":'.json_encode($this->env_get()).(method_exists($this, 'mp_get_product_param') ? ',"product_param":'.json_encode($this->mp_get_product_param(true)) : '').'}</textarea></td></tr>'
			.'</table>'
			.'</body>'
			.'</html>');
		}

	public function env_page_404(){

		// abort tracker
		if(method_exists($this, 'tracker_abort')) $this->tracker_abort();

		// return not found
		return $this->response(404);
		}

	public function env_init_shutdown_events(){
		/* Für PHP-FPM: nach einem exit wird der Output gesendet und erst dann Shutdown-Funktionen aufgerufen */
		register_shutdown_function(function(){
			fastcgi_finish_request();
			event::trigger('obSend');
			register_shutdown_function(function(){
				event::trigger('close');
				});
			});

		return true;
		}

	public function env_exit_with_maintenance_site($phpfile = null, $logmsg = null){
		if($logmsg) e::logtrigger($logmsg);
		header('HTTP/1.1 200 OK');
		if($phpfile) include($phpfile);
		else echo '<html><head><title>'.$_SERVER['SERVER_NAME'].' is under maintenance</title><meta name="robots" content="noindex"/></head><body style="text-align:center;"><h3 style="padding-top:30px;">Maintenance</h3><p>The website '.$_SERVER['SERVER_NAME'].' is <b>currently</b> under maintenance.</p></body></html>';
		exit;
		}

	public function env_exit_with_offline_site($phpfile = null, $logmsg = null){
		if($logmsg) e::logtrigger($logmsg);
		header('HTTP/1.1 200 OK');
		if($phpfile) include($phpfile);
		else echo '<html><head><title>'.$_SERVER['SERVER_NAME'].' is offline</title><meta name="robots" content="noindex"/></head><body style="text-align:center;"><h3 style="padding-top:30px;">Offline</h3><p>The website '.$_SERVER['SERVER_NAME'].' is offline.</p></body></html>';
		exit;
		}

	public function env_exit_with_httpcode_404($logmsg = null){
		if($logmsg) e::logtrigger($logmsg);
		header('HTTP/1.1 404 Not Found');
		exit;
		}

	public function env_no_iframe_script(){

		// return script
		return $this->env_get('nexus:is_xadmin_request') ? '' : '<script>if(top != self) top.location=location;</script>';
		}
	}
