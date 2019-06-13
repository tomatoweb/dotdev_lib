<?php
/*****
 * Version 1.0.2019-05-16
**/
namespace bragiportal;

use \tools\helper as h;
use \tools\error as e;
use \tools\postdata;
use \tools\redis;
use \dotdev\nexus\service;
use \dotdev\mobile\client;
use \dotdev\mobile;
use \dotdev\mobile\tan;
use \dotdev\app\bragi;
use \dotdev\bragi\media;
use \dotdev\app\bragi\profile;
use \dotdev\app\bragi\image;
use \dotdev\app\bragi\message;
use \dotdev\app\bragi\pool;
use \dotdev\app\bragi\event;
use \dotdev\nexus\base;

class controller {

	// Les traits permettent de faire de l'héritage multiple (en POO classique une Classe ne peut hériter que d'une seule Classe - extends)
	use \tools\router_trait,
		\dotdev\app\extension_trait\usersession,
		\dotdev\app\extension_trait\environment,
		\dotdev\app\extension_trait\builder,
		\dotdev\app\extension_trait\tracker;

	/* constructor */
	public function __construct(){

		// DEBUG100
		//e::logtrigger(h::encode_php($_REQUEST));

		//$redis = \tools\redis::load_resource('mt_nexus');
		//$redis = \tools\redis::load_resource('app_bragi');
		//$redis->flushDB();

		// temp fix during DNS domain configuraftion
        if(!isset($_SERVER['REQUEST_SCHEME'])) $_SERVER['REQUEST_SCHEME'] = 'http';

		// shutdown events
		if(!$this->env_init_shutdown_events()) $this->env_exit_with_maintenance_site(null, 'env_init_shutdown_events() failed');

		// init user session
		if(!$this->usersession_init()) $this->env_exit_with_maintenance_site(null, 'usersession_init() failed');

		// init environment
		if(!$this->env_init()) $this->env_exit_with_maintenance_site(null, 'env_init() failed');

		// extract preset name from presetID (cherry_de --> cherry)
		$this->us_set(['preset_name' => substr($this->env_get('domain:presetID'), 0, -3)]);

		// Language preference
		if(h::cR('lang', '~^(de|en|hu|cz)$')){

			// set lang preset and pages path, ...
			//$this->us_set('nexus:presetID', $this->us_get('scheme').'_'.h::gR('lang'));

			// set user session language variable
			$this->us_set(['lang' => h::gR('lang')]);
			}

		// Set browser lang as default lang
		if(!$this->us_get('lang')){
			if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
				$lang = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
				if(in_array($lang,['de','en','hu','cs'])){ // CZ is the domain
					//$this->us_set('nexus:presetID',$this->us_get('scheme').'_'.$lang);
					$this->us_set(['lang' => $lang]);
					}
				else{
					//$this->us_set('nexus:presetID',$this->us_get('scheme').'_de');
					$this->us_set(['lang' => 'de']);
					}
				}
			else{
				//$this->us_set('nexus:presetID',$this->us_get('scheme').'_de');
				$this->us_set(['lang' => 'de']);
				}
			}

		// Set unique preset for all domains (env_int cherche un presetID dans la session)
		$this->us_set('nexus:presetID', 'flirddy_de');

		// init preset
		if(!$this->env_init_preset()) $this->env_exit_with_maintenance_site(null, 'env_init_preset() failed');

		// init preset compiler
		if(!$this->builder_compile_preset()) $this->env_exit_with_maintenance_site(null, 'builder_compile_preset() failed');

		// if status is not online or dev, abort here
		$status = $this->env_is('nexus:status', 'inherit') ? $this->env_get('nexus:domain_status') : $this->env_get('nexus:status');
		if($status == 'ignore') $this->env_exit_with_httpcode_404();
		elseif($status == 'archive') $this->env_exit_with_offline_site();
		elseif(!in_array($status, ['online','dev'])) $this->env_exit_with_maintenance_site();


		// init tracking
		if(!$this->tracker_init()) $this->env_exit_with_maintenance_site(null, 'tracker_init() failed');

		// start router
		$this->router_dispatch($this->env_get('nexus:url'));
		}


	/* route definition */
	public function router_definition(){

		// define preset routes
		$preset_routes = [];

		// append routes from preset
		foreach($this->env_get('preset:routes') as $url => $conf){

			// escape special chars and also "/"
			$preset_routes[] = preg_quote($url, '/');
			}

		// return route definition
		return [
			'*' => [
				'page_by_preset'			=> '~^('.implode('|', $preset_routes).')$',
				'page_newtan'				=> '/mymobile/newtan',
				'page_mymobile'				=> '~^\/mymobile\/(evn|abo|)$',
				'ajax_get'					=> '/get',
				'page_edit'					=> '/edit_profile/',
				'page_static'				=> '~^\/static\/([a-z]{2,3})\/([a-z ]{1,30})\/$',
				'env_page_debug'			=> '/debug',
				'page_simulate'				=> '~^\/sim(?:\:([^\:]+)(?:\:([^\:]+)(?:\:([^\:]+)|)|)|)$',
				'env_page_404'				=> '~^\/(.+)$',
				]
			];
		}

	public function redis(){

		return redis::load_resource('app_bragi');
		}

	/* page translater and loader */
	public function translate_page($page){

		// check if page does not exist
		if(!file_exists($this->env_get('preset:path_pages').'/'.$page.'.php')){

			// else log error
			e::logtrigger('Page '.h::encode_php($page).' does not exist. ('.h::encode_php($this->env_get('preset:path_pages').'/'.$page.'.php').')');

			// and set error page
			$page = 'error/500';
			}

		// return page
		return $page;
		}


	public function load_page($page, $addto_trackstr = null, $postprocessing = null, $use_wrapper = true){

		// translate page
		$page = $this->translate_page($page);

		// set nexus page
		$this->env_set('nexus:page', $page);

		// set tracking page info
		$this->env_set('tracker:callinfo:page', $page.($addto_trackstr ? '/'.$addto_trackstr : ''));

		// define page inclusion
		$include_page = $this->env_get('preset:path_pages').'/'.$page.'.php';

		// load page (incl. wrapper)
		return $this->response_ob(200, function() use ($include_page, $use_wrapper){
			include $use_wrapper ? $this->env_get('preset:path_pages').'/wrapper.php' : $include_page;
			}, $postprocessing);
		}


	/* DEPRECATED */
	public function cmd_generate_tan(){

		// validate MSISDN
		if(!h::cR('msisdn', '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$')){
			return (object)['status'=>400, 'type'=>'danger'];
			}

		// get mobile
		$res = client::get_mobile(['msisdn' => h::gR('msisdn')]);

		// on error
		if(!in_array($res->status, [200, 404])){
			return (object)['status'=>500, 'type'=>'danger'];
			}

		// Mobile not found
		elseif($res->status == 404){
			return (object)['status'=>406, 'type'=>'danger'];
			}

		 // Mobile found
		else{

			// assign mobile
			$mobile = $res->data;

			// Set Mobile in user session
			$this->us_set(['msisdn' => $mobile->msisdn, 'mobileID' => $mobile->mobileID]);

			// get messages
			$res = message::get(['mobileID'=>$mobile->mobileID]);

			// on error
			if($res->status != 200){
				return (object)['status'=>500, 'type'=>'danger'];
				}

			// existiert bisher kein Chat zur eingegebenen msisdn
			elseif(empty($res->data)){
				return (object)['status'=>406, 'type'=>'danger'];
				}

			// Chat zur eingegebenen msisdn existiert
			else{

				// instantiate Redis
				$redis = redis::load_resource('app_bragi');

		 		// Redis connection error
				if(!$redis or !$redis->isConnected()){
					e::logtrigger('redis connection failed: '.h::encode_php($redis));
					return (object)['status'=>500, 'type'=>'danger'];
					}

				// user allready asked for a TAN in the last 20 minutes
				if(empty($_SERVER['DEV_MODE'])){ // is not proceeded in dev mode
					if($redis->exists("bragiportal:tan_login:".$_SERVER['REMOTE_ADDR'])){
						return (object)['status'=>429, 'type'=>'danger'];
						}
					}

				// create a new TAN
				$res = tan::create_tan([
					'mobileID'	=> $mobile->mobileID,
					'persistID'	=> $this->us_get('persistID')
					]);

				// on error
				if($res->status != 201){
					return (object)['status'=>500,'type'=>'danger'];
					}

				// assign new tan
				$new_tan = $res->data->tan;

				// keep user IP Adresse as key and TAN as value 20 minuten in Redis instanz
				$redis->set("bragiportal:tan_login:".$_SERVER['REMOTE_ADDR'], $new_tan);
				$redis->setTimeout("bragiportal:tan_login:".$_SERVER['REMOTE_ADDR'], 1200);

				// Get Gate
				$res = service::get_smsgate([
					'smsgateID' => $this->env_get('domain:smsgateID')
					]);

				// on error
				if(!in_array($res->status, [200, 404])){
					return (object)['status'=>500, 'type'=>'danger'];
					}

				// not found
				elseif($res->status == 404){
					e::logtrigger('smsgate '.h::encode_php($this->env_get('domain:smsgateID')).' could not be found: '.h::encode_php($res));
					return (object)['status'=>500, 'type'=>'danger'];
					}

				// assign gate
				$gate = $res->data;
				$this->us_set(['smsgateID' => $gate->smsgateID]);

				if(empty($_SERVER['DEV_MODE'])){ // is not proceeded in dev mode
					// send SMS
					$res = client::send_sms([
						'mobileID'		=> $mobile->mobileID,
						'serviceID'		=> $gate->serviceID,
						'smsgateID'		=> $gate->smsgateID,
						'text'			=> h::replace_in_str($this->env_get('preset:tan_sms_str'), ['{tan}'=>$new_tan, '{title}'=>$this->env_get('preset:title:'.$this->us_get('preset_name'))]),
						'persistID'		=> $this->us_get('persistID'),
						]);

					// on error
					if($res->status != 201){
						e::logtrigger('SMS could not be sended : '.h::encode_php($res));
						return (object)['status'=>403, 'type'=>'danger'];
						}
					}


				// Set Mobile and Tan in user session
				$this->us_set(['msisdn' => $mobile->msisdn, 'mobileID' => $mobile->mobileID, 'tan' => $new_tan]);
				}
			}

		return (object)['status'=>201, 'type'=>'success'];
		}

	/* DEPRECATED */
	public function cmd_validate_tan(){

		// Unidentified user
		if(!$this->us_get('mobileID')){
			return (object)['status'=>401, 'type'=>'danger'];
		}

		// validate TAN
		if(h::cR('inputTan', '~^[a-zA-Z0-9]{6}$')== ''){
			return (object)['status'=>405, 'type'=>'danger'];
			}

			// get mobile's TAN list
			$res = tan::get_tan(['mobileID' => $this->us_get('mobileID')]);

			// on error
			if($res->status != 200){
				return (object)['status'=>500, 'type'=>'danger'];
				}

			// no TAN found for this mobile
			if(empty($res->data)){
				return (object)['status'=>401, 'type'=>'danger'];
				}

			// Search a matching Tan in the list and check if it is a valid one ( < 20 min)
			foreach (array_reverse($res->data) as $data){

				// matching Tan found
				if(h::cR('inputTan', $data->tan)){

					// createTime < 20 min
					if((time() - strtotime($data->createTime)) < 1200){

						// set authentication variables to TRUE and return
						$this->us_set(['auth' => true]);
						return (object)['status' => 201, 'type'=>'success'];
						}

					// TAN found is not valid anymore ( > 20 min)
					else{
						return (object)['status'=>403, 'type'=>'danger'];
						}
					}
				}
			// no matching TAN found in the TAN list
			return (object)['status'=>404, 'type'=>'danger'];


		}


	public function cmd_generate_tan_new(){

		// get mobile
		$res = client::get_mobile(['msisdn' => $this->us_get('submitMsisdn')]);

		// on error
		if(!in_array($res->status, [200, 404])){
			return (object)['status'=>500, 'data'=>'Could not load MSISDN, server error: '.$res->status];
			}

		// Mobile not found
		elseif($res->status == 404){

			// create mobile entry
			$create = mobile::create([
				'msisdn' => $this->us_get('submitMsisdn'),
				'operatorID' => 0
				]);

			// on error
			if($create->status != 201){
				e::logtrigger('MSISDN konnte nicht erstellt werden: '.h::encode_php($create));
				return (object)['status'=>500, 'data'=>'server error, could not create mobile.'];
				}

			// get created mobile
			$res = client::get_mobile(['mobileID' => $create->data->mobileID]);

			// on error
			if($res->status != 200){
				return (object)['status'=>500, 'data'=>'server error, could not load created mobile.'];
				}
			}

		// assign mobile
		$mobile = $res->data;

		// Set Mobile in user session
		$this->us_set([
			'msisdn' => $mobile->msisdn,
			'mobileID' => $mobile->mobileID
			]);

		/* get messages
		$res = message::get(['mobileID'=>$mobile->mobileID]);

		// on error
		if($res->status != 200){
			return (object)['status'=>500, 'data'=>'Could not load messages, server error: '.$res->status];
			}

		// existiert bisher kein Chat zur eingegebenen msisdn
		elseif(empty($res->data)){
			return (object)['status'=>406, 'data'=>'existiert bisher kein Chat zur eingegebenen msisdn'];
			}
		*/

		// instantiate Redis
		$redis = redis::load_resource('app_bragi');

 		// Redis connection error
		if(!$redis or !$redis->isConnected()){
			e::logtrigger('redis connection failed: '.h::encode_php($redis));
			return (object)['status'=>500, 'data'=>'redis connection failed'];
			}

		// IP allready asked for a TAN in the last 20 minutes
		if(empty($_SERVER['DEV_MODE'])){ // is not proceed in dev mode
			if($redis->exists("bragiportal:tan_login:".$_SERVER['REMOTE_ADDR'])){

				// prepare return datas
				$data = (object) [
								"tan" => $redis->get("bragiportal:tan_login:".$_SERVER['REMOTE_ADDR']),
								"mobileID" => $this->us_get('mobileID'),
								"msisdn" => $this->us_get('msisdn'),
								];
				return (object)['status'=>429, 'data' => $data];
				}
			}

		// create a new TAN
		$res = tan::create_tan([
			'mobileID'	=> $mobile->mobileID,
			'persistID'	=> $this->us_get('persistID')
			]);

		// on error
		if($res->status != 201){
			return (object)['status'=>500,'data'=>'could not generate new TAN.'];
			}

		// assign new tan
		$new_tan = $res->data->tan;
		$this->us_set(['tan' => $new_tan]);

		// keep user IP Adresse as key and TAN as value 20 minuten in Redis instanz
		$redis->set("bragiportal:tan_login:".$_SERVER['REMOTE_ADDR'], $new_tan);
		$redis->setTimeout("bragiportal:tan_login:".$_SERVER['REMOTE_ADDR'], 1200);

		// DEBUG: log tan_sms_str output
		//e::logtrigger(h::replace_in_str($this->env_get('preset:tan_sms_str'), ['{tan}'=>$new_tan, '{title}'=>$this->env_get('preset:title:'.$this->us_get('preset_name'))]));

		if(empty($_SERVER['DEV_MODE'])){ // is not proceed in dev mode

			// send SMS
			$res = client::send_sms([
				'mobileID'		=> $mobile->mobileID,
				'serviceID'		=> $this->us_get("serviceID"),
				'smsgateID'		=> $this->us_get("smsgateID"),
				'text'			=> h::replace_in_str($this->env_get('preset:tan_sms_str'), ['{tan}'=>$new_tan, '{title}'=>$this->env_get('preset:title:'.$this->us_get('preset_name'))]),
				//'text'			=> h::replace_in_str($this->env_get('preset:tan_sms_str'), ['{tan}'=>$new_tan]),
				'persistID'		=> $this->us_get('persistID'),
				]);

			// on error
			if($res->status != 201){
				e::logtrigger('SMS could not be sended : '.h::encode_php($res));
				return (object)['status'=>500, 'data'=>'server error, SMS could not be sent.'];
				}
			}

		// prepare return datas
		$data = (object) [
						"tan" => $new_tan,
						"mobileID" => $this->us_get('mobileID'),
						"msisdn" => $this->us_get('msisdn'),
						];

		return (object)['status'=>201, 'data'=>$data];
		}


	public function cmd_validate_tan_new(){

		// Unidentified user
		if(!$this->us_get('mobileID')){
			return (object)['status'=>401, 'data'=>'Bitte geben sie eine gültige Mobilnummer ein.'];
			}

		// get mobile's TAN list
		$res = tan::get_tan(['mobileID' => $this->us_get('mobileID')]);

		// on error
		if($res->status != 200){
			return (object)['status'=>500, 'data'=>'server error, could not load TAN list.'];
			}

		// no TAN found for this mobile
		if(empty($res->data)){
			return (object)['status'=>402, 'data'=>'no Tan found for this mobile.'];
			}

			// Search a matching Tan in the list and check if it is a valid one ( < 20 min)
			foreach (array_reverse($res->data) as $data){

				// matching Tan found
				if($this->us_get('submitTan') == $data->tan){

					// createTime < 20 min
					if((time() - strtotime($data->createTime)) < 1200){

						// get mobile
						$rslt = client::get_mobile(['mobileID' => $data->mobileID]);

						// on error
						if(!in_array($rslt->status, [200, 404])){
							return (object)['status'=>500, 'data'=>'Could not load MSISDN, server error: '.$rslt->status];
							}

						// Mobile not found
						elseif($rslt->status == 404){
							return (object)['status'=>404, 'data'=>'mobile not found.'];
							}

						// assign mobile
						$mobile = $rslt->data;

						// Set Mobile in user session
						$this->us_set([
							'msisdn' 	=> $mobile->msisdn,
							'mobileID' 	=> $mobile->mobileID,
							'auth' 		=> true
							]);

						// prepare return datas
						$data = (object) [
										"tan" 		=> $data->tan,
										"mobileID" 	=> $this->us_get('mobileID'),
										"msisdn" 	=> $this->us_get('msisdn'),
										];

						// try to get browser language
						$lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2)) : "null";

						// prepare data as json for event log
						$json_data = json_encode(array(
						    'lang' 			=> $lang,
						    'scheme'		=> $this->env_get('preset:scheme:'.$this->us_get('preset_name')),
						    'smsversion'	=> $this->us_get('sms') ?? null,
						    'mobileID'		=> $this->us_get('mobileID'),
						    'IP'			=> $_SERVER['REMOTE_ADDR']
						));

						// log event "tan checked"
						$create = event::create([
							"event"		=>	"tanchecked",
							"project"	=>	"bragiportal",
							"data"		=>	$json_data,
							]);

						if($create->status != 201) e::logtrigger('Event could not be created: '.h::encode_php($create));

						// return
						return (object)['status' => 201, 'data'=>$data];
						}

					// TAN found is not valid anymore ( > 20 min)
					else{
						return (object)['status'=>403, 'data'=>'TAN found is not valid anymore ( > 20 min)'];
						}
					}
				}
			// no matching TAN found in the TAN list
			return (object)['status'=>405, 'data'=>'no matching TAN found in the TAN list'];

		}


	/* page routes */

	// home page
	public function page_by_preset($suburl){

		//echo '<pre>'.h::encode_php($this->us);
		//echo '<pre>'.h::encode_php($this->us_get('nexus:presetID'));
		//echo '<pre>'.h::encode_php($this->env_get());die;

		// check if preset route is undefined
		if(!$this->env_is('preset:routes:'.$suburl)){

			// log error
			e::logtrigger('DEBUG: Cannot load preset route '.h::encode_php($suburl));

			// return error page
			return $this->load_page('error/500');
			}

		// take page from route
		$page = $this->env_get('preset:routes:'.$suburl);

		// try to get browser language
		$lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2)) : "null";

		// prepare data as json for event log
		$json_data = json_encode(array(
		    'lang' 			=> $lang,
		    'scheme'		=> $this->env_get('preset:scheme:'.$this->us_get('preset_name')),
		    'page'			=> $suburl,
		    'IP'			=> $_SERVER['REMOTE_ADDR']
		));

		// log event "tan checked"
		$create = event::create([
			"event"		=>	"visit",
			"project"	=>	"bragiportal",
			"data"		=>	$json_data,
			]);

		if($create->status != 201) e::logtrigger('Event could not be created: '.h::encode_php($create));

		// temp variable for beta payment version
		//$this->sms = ($suburl == '/sms') ? true : false;
		$this->sms = true;
		$this->us_set(['sms' =>  ($suburl == '/sms') ? true : false]);

		// get profiles list
		//$this->get_profiles();

		// Get Gate
		$res = service::get_smsgate([
			'smsgateID' => $this->env_get('domain:smsgateID')
			]);

		// on error
		if(!in_array($res->status, [200, 404])){
			return (object)['status'=>500, 'data'=>'server error, could not load gate.'];
			}

		// not found
		elseif($res->status == 404){
			e::logtrigger('smsgate '.h::encode_php($this->env_get('domain:smsgateID')).' could not be found: '.h::encode_php($res));
			return (object)['status'=>500, 'data'=>'server erro, gate not found.'];
			}

		// assign gate
		$gate = $res->data;
		$this->us_set(['smsgateID' => $gate->smsgateID]);
		$this->us_set(['serviceID' => $gate->serviceID]);

		// load page
		return $this->load_page(h::gX($page, 'page'), null, null, (h::gX($page, 'use_wrapper') === false ? false : true));
		}


	/* DEPRECATED */
	public function page_newtan(){

		// user is allready logged, redirect to chat page
		if($this->us_get('auth')){

			// load chat page
			return $this->load_page('mobile/chat');
			}

		// request contents an MSISDN
		if(h::gR('msisdn')) {

			// new TAN generation process
			$res = $this->cmd_generate_tan();

			// clear flash message box
			$this->us_delete('flash');

			// Set current flash message
			$this->us_set(['flash' => ['type' => $res->type, 'msg' => $res->status]]);

			// TAN generation did not succeeded
			if($res->status != 201)	{

				// stay on newtan page
				return $this->load_page('mobile/newtan');
				}

			// Tan generation succeeded, redirect to TAN validation page
			return $this->load_page('mobile/login');
			}

		// empty MSISDN field in the form request, stay on newtan page
		return $this->load_page('mobile/newtan');
		}

	/* DEPRECATED */
	public function page_upload(){

		// POST
		if(postdata::get()){

			$request = (object)(postdata::get());

			if(isset($request->hash)){

				$hash = $request->hash;

				// instantiate Redis
				$redis = redis::load_resource('app_bragi');

		 		// Redis connection error
				if(!$redis or !$redis->isConnected()){
					e::logtrigger('redis connection failed: '.h::encode_php($redis));
					return $this->response(500);
					}

				// user authentication
				if(!$redis->exists("prepareupload:by_profilelID:".$hash)){
					e::logtrigger('Redis key not found for hash or timeout temp key '.h::encode_php($hash));
					return $this->response(404, 'Redis key not found for hash or timeout temp key');
					}

				// Get request data mapped with hash in Redis
				$req = $redis->get("prepareupload:by_profilelID:".$hash);

				// get profile
				$res = profile::get(['profileID'=>$req['profileID']]);

				// on error
				if($res->status != 200){
					e::logtrigger('could not get profile: '.h::encode_php($res));
					return $this->response(404, 'profile not found');
					}

				// assign
				$profile = $res->data;

				// first param order: highlight existing image
				if(isset($req['highlight'])){

					// highlight an existing image request (imageID > 1)
					if($req['highlight'] > 1){

						// Check if picture exists
						$res = media::get_profile_media(['imageID' => $req['highlight']]);

						// image not found
						if($res->status != 200){
							return $this->response(404, 'Image could not be loaded '.h::encode_php($res));
							}

						// image do not belongs to profile
						if($res->data->profileID != $profile->profileID){

							return $this->response(404, 'Image to highlight not found');
							}

						// set image as profile image
						$res = profile::update(['profileID' => $profile->profileID, 'imageID' => $req['highlight']]);

						// on error
				        if($res->status != 204){
							return $this->response(500);
							}

						// refresh cached profile
						$redis->setTimeout("profile:id:".$profile->profileID, 0);


						// return update ok
						return $this->response(200, json_encode((object)["text" => "image highlighted."]));
						}

					}

				// delete or replace image
				if(isset($req['delete']) || isset($req['replace'])){

					// assign
					$imageID = isset($req['delete']) ? $req['delete'] : $req['replace'];

					// get image
					$res = media::get_profile_media(['imageID' => $imageID]);

					// image not found
					if($res->status != 200){
						return $this->response(404, 'Image could not be loaded '.h::encode_php($res));
						}

					// assign
					$image = $res->data;

					// delete from server directory
					if(file_exists($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID.'/'.$image->name)){
						unlink($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID.'/'.$image->name);
						}
					else{
						e::logtrigger('image '.$image->imageID.' could not be deleted from profile directory');
						return $this->response(409, 'Image could not be deleted ');
						}

					// and delete from database
					$res = image::delete(['imageID' => $image->imageID]);
					if($res->status != 200){
						e::logtrigger('image '.$image->imageID.' could not be deleted from DB');
						return $this->response(409, 'Image could not be deleted ');
						}

					// if this image was the profile front image, also update profile->imageID = 0
					if(!empty($profile->imageID)){
						if($image->imageID == $profile->imageID){

							// set profile imageID to 0
							$res = profile::update(['profileID'=>$profile->profileID, 'imageID'=>0]);

							// on error
							if($res->status != 204){
								e::logtrigger('profile front imageID could not be set to 0 after deleting front image '.h::encode_php($res));
								return $this->response(409, 'profile front imageID could not be set to 0 after deleting front image '.$res->status);
								}
							}
						}

					// return if it is a delete request, else (replace) continue with upload
					if(isset($req['delete'])){
						return $this->response(200, 'image deleted.');
						}
					}

				// get images from customer
				$res = media::get_profile_media(['profileID' => $profile->profileID]);

				// on error
				if($res->status != 200){
					e::logtrigger('images could not be loaded for profileID '.$profile->profileID);
					return $this->response(500);
					}

				// max 5 images upload
				if(count($res->data) > 4){

					return $this->response(409, 'upload limit reached');
					}

				// Post contents an image
				if(isset($_FILES['image'])){

					// assign variable
		            $image_name = $_FILES['image']['name'];
		            $image_tmp_name = $_FILES['image']['tmp_name'];
		            $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

		            // filter file extension
		            if (!in_array(strtolower($image_ext), array('jpeg', 'jpg', 'png'))){
		        		return $this->response(409, 'file extension not allowed');
		            	}

		            // create new image in DB
		        	$res = image::create();

		        	// on error
		        	if($res->status != 201){
		        		return $this->response(500);
		        		}

		        	// assign
		        	$imageID = $res->data->imageID;

		        	// rename image to be unique by name
		        	$image_name = $imageID.'.'.$image_ext;

		        	// update image in DB
		            $res = image::update([
		            	'imageID'	=> $imageID,
		            	'name'		=> $image_name,
		            	'profileID'	=> $req['profileID'],
		            	'mod'		=> 0
		            	]);

		            // on error
		            if($res->status != 204){
						return $this->response(500);
						}

					// reload profile
					$res = profile::get(['profileID'=>$req['profileID']]);

					// on error
					if($res->status != 200){
						e::logtrigger('could not get profile: '.h::encode_php($req['profileID']));
						return $this->response(500);
						}

					// assign
					$profile = $res->data;

					// Profile front image not set
					if(!$profile->imageID){
						$res = profile::update(['profileID'=>$profile->profileID, 'imageID'=>$imageID]);
						if($res->status != 204){
							e::logtrigger('could not update profile: '.h::encode_php($res));
							return $this->response(500);
							}
						}

					// Get profile pictures directory
					$profile_path = $_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$profile->profileID;

					// Or create it
					if(!is_dir($profile_path)){
						if(!mkdir($profile_path, 0755, true)){
							e::logtrigger('Profile Verzeichnis konnte nicht erstellt werden.');
							return $this->response(500);
							}
						}

					// move uploaded picture to profile directory
					if(!move_uploaded_file($image_tmp_name, $profile_path.'/'.$image_name)){
						e::logtrigger('Failed to move uploaded picture.');
						return $this->response(500);
						}

					// highlight the uploaded image
					if(isset($req['highlight'])){

						// highlight request
						if($req['highlight'] == 1){

							// set image as profile image
							$res = profile::update(['profileID' => $profile->profileID, 'imageID' => $imageID]);

							// on error
					        if($res->status != 204){
								return $this->response(500);
								}
							}
						}

					return $this->response(200, json_encode((object)["text" => "image uploaded.","imageID" => $imageID]));
					}

				// no image found in POST
				return $this->response(200, json_encode((object)["text" => "no file uploaded."]));
				}

			e::logtrigger('missing hash: '.h::encode_php($request));
			return $this->response(404, 'missing hash');

			}

		e::logtrigger('POST data not found');
		return $this->response(404, 'missing POST data');

		}

	/* DEPRECATED */
	public function page_mymobile($subpage = null){

		// the request contents a TAN
		if(h::gR('inputTan')){

			// TAN validation process
			$res = $this->cmd_validate_tan();

			// clear flash message box
			$this->us_delete('flash');

			// Set new flash message
			$this->us_set(['flash' => ['type' => $res->type, 'msg' => $res->status]]);
			}

		// TAN validation succeeded or user is authenticated
		if($this->us_get('auth')){

			// set default page
			if(!$subpage) $subpage = 'chat';

			// load page
			return $this->load_page('mobile/'.$subpage);
			}

		// TAN validation failed or request is empty (case of first landing or not authenticated)
		return $this->load_page('mobile/login');
		}


	public function page_edit(){

		$opt = h::eX($_REQUEST, [
			'profileID'			=> '~0,4294967295/i',
			'mobileID'			=> '~1,4294967295/i',
			'name'				=> '~^[a-zA-Z0-9_-]{3,20}$',
			'age'				=> '~^[1-9]{1}[0-9]{0,2}$',
			'plz'				=> '~(*UTF8)^.{0,160}$',
			'weight'			=> '~^[0-9]{1}[0-9]{0,2}$',
			'height'			=> '~^[0-9]{1}[0-9]{0,2}$',
			'description'		=> '~(*UTF8)^.{0,500}$',
			'delete_image'		=> '~1,4294967295/i',
			'highlight_image'	=> '~1,4294967295/i',
			'mod'				=> [],
			'copy'				=> '~0,255/i',
			'countryID'			=> '~0,255/i',
			'poolID'			=> '~0,255/i',
			], $error, true);

		if($error){
			e::logtrigger('Missing or invalid parameter: '.h::encode_php($error));
			return $this->load_page('error/500');
			}

		// in PLZ: Replace backslash, double quotes and simple quote by dash (hyphen)
		if(isset($opt['plz'])) $opt['plz'] = str_replace(str_split('\\"\''), '-', $opt['plz']);

		// in Description: Replace backslash, double quotes, simple quote and tags by dash (hyphen), and removes leading and trailing whitespaces.
		if(isset($opt['description'])) $opt['description'] = str_replace(str_split('\\"\'<>'), '-', trim($opt['description']));

		// authentication
		if(!$this->us_get('auth') && empty($opt['custUpload'])){
			return $this->load_page('mobile/newtan');;
			}

		// if POST then it is a profile Form submission. Create or update a profile (incl. images)
		if($_POST) {

			// Create new profile
			if(!$opt['profileID']) {

				$res = profile::create($opt);
				if($res->status != 201){
					e::logtrigger('Fake-profile konnte nicht erstellt werden: '.h::encode_php($res));
					$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);
					return $this->load_page('edit');
					}
				$opt['profileID'] = $res->data->profileID;
				$this->us_set(['flash' => ['type' => 'success', 'msg' => 205]]);
				}

			// Update profile
			else {

				$res = profile::update($opt);

				// on error
				if($res->status != 204){

					e::logtrigger('profile konnte nicht geändert werden: '.h::encode_php($res));

					$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);

					// redirect to home page
					return $this->response(302, $this->us_url('/mymobile/'));
					}
				}

			// Add new pictures to profile
			foreach ($_FILES['images']['name'] as $k => $v){

				// The last value of $_FILES['images']['name'] is allways empty, ignore it.
				if($v){
					$image_name = $v;
	                // Change image name to be unique in profile folder, i.e: image_id.ext (autoincremented id from DB + extension)
	                $image_tmp_name = $_FILES['images']['tmp_name'][$k];
	                $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

	                // Profile pic (Chat pic: 1, thumbnail: 2)
	                $image_mod = 0;

	                // filter file extension
	                if (!in_array(strtolower($image_ext), array('jpeg', 'jpg', 'png'))){
                		$this->us_set(['flash' => ['type' => 'danger', 'msg' => 415]]);
                		continue; // anyway with next pic
	                	}

	                // create new image in DB
                	$res = image::create();
                	if($res->status != 201){
                		e::logtrigger('image could not be created: '.h::encode_php($res));
                		return $this->load_page('error/500');
                		}
                	$imageID = $res->data->imageID;

                	// assign new unique name to uploaded file
                	$image_name = $imageID.'.'.$image_ext;

                	// update image with new name
                    $res = image::update([
                    	'imageID'	=>$imageID,
                    	'name'		=>$image_name,
                    	'profileID'	=>$opt['profileID'],
                    	'mod'		=> !empty($image_mod) ? $image_mod : 0
                    	]);

                    // on error
                    if($res->status != 204){
						e::logtrigger('Bilder konnte nicht geändert werden: '.h::encode_php($res));
						return $this->load_page('error/500');
						}

					// Get profile
					$res = profile::get(['profileID'=>$opt['profileID']]);
					if($res->status != 200){
						e::logtrigger('Profile mit profileID '.$opt["profileID"].' konnte nicht geladen werden.');
						return $this->load_page('error/500');
						}

					// set as front picture if not set
					if(!$res->data->imageID and empty($image_mod)){
						$res = profile::update(['profileID'=>$opt['profileID'], 'imageID'=>$imageID]);
						if($res->status != 204){
							e::logtrigger('Fake-profile konnte nicht geändert werden: '.h::encode_php($res));
							return $this->load_page('error/500');
							}
						}

					// Create new profile pictures directory
					$profile_path = $_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$opt['profileID'];
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
						$res = image::create(['profileID'=>$opt['profileID'], 'name'=>$image_name]);
						if($res->status != 201){
	                		e::logtrigger('Bilder konnte in nicht erstellt werden: '.h::encode_php($res));
	                		return $this->load_page('error/500');
	                		}

						} */

					}

				} // end foreach ($_FILES['images'])

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
	                		'profileID'	=> $opt['profileID'],
	                		'mod'		=> 1 // videos are only for Chat context
	                		]);

	                	// on error
	                	if($res->status != 204){
							e::logtrigger('Video could not be updated: '.h::encode_php($res));
							return $this->load_page('error/500');
							}

						// Create new profile directory
						$profile_path = $_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$opt['profileID'];
						if(!is_dir($profile_path)){
							if(!mkdir($profile_path, 0755, true)){
								e::logtrigger('Cannot create profile server data folder.');
								return $this->load_page('error/500');
								}
							}

						// Create new profile directory
						$profile_videos_path = $_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$opt['profileID'].'/videos';
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

			} // End of if(POST)

		// Delete image and copies
		if(isset($opt['delete_image'])){

			// load image
			$res = media::get_profile_media(['imageID'=>$opt['delete_image']]);

			// not found
			if($res->status != 200){
				e::logtrigger('image with imageID '.$opt["delete_image"].' could not be found: '.h::encode_php($res));
				return (object)['status'=>500];
				}

			// assign image
			$image = $res->data;

				// Its a video file
				if(substr($image->name, 0, 1) == 'v'){

					// delete from server directory
					if(file_exists($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$opt['profileID'].'/videos/'.$image->name)){
						unlink($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$opt['profileID'].'/videos/'.$image->name);
						}
					else{
						e::logtrigger('Image '.$opt["delete_image"].' could not be deleted from profile directory');
						$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);
						}
					}

				// Its an image file
				else{

					// delete from server directory
					if(file_exists($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$opt['profileID'].'/'.$image->name)){
						unlink($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$opt['profileID'].'/'.$image->name);
						}
					else{
						e::logtrigger('Image '.$opt["delete_image"].' could not be deleted from profile directory');
						$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);
						}
					}

				// and delete from database
				$res = image::delete(['imageID'=>$image->imageID]);
				if($res->status != 200){
					e::logtrigger('image '.$opt["delete_image"].' could be deleted from database');
					if(!$this->us_get('flash')){
						$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);
						}
					}


			// if this image was the profile front image, also update profile->imageID = 0
			if(!empty($this->profile->imageID)){
				if($opt['delete_image'] == $this->profile->imageID){
					$res = profile::update(['profileID'=>$this->profile->profileID, 'imageID'=>0]);

					// on error
					if($res->status != 204){
						e::logtrigger('profile front imageID could not be set to 0 after deleting front image '.h::encode_php($res));
						return $this->load_page('error/500');
						}
					}
				}

			if(!$this->us_get('flash')){
				$this->us_set(['flash' => ['type' => 'success', 'msg' => 204]]);
				}

			}

		// Set profile picture
		if(isset($opt['highlight_image']) && isset($opt['profileID'])){

			// Check if picture exist
			$res = media::get_profile_media(['imageID'=>$opt['highlight_image']]);

			if($res->status != 200){
				e::logtrigger('Bilder '.$opt['highlight_image'].' konnte nicht geladen werden.');
				if(!$this->us_get('flash')){
					$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);
					}
				}
			else{
				// check profile
				$res = profile::get(['profileID'=>$opt['profileID']]);
				if($res->status != 200){
					return (object)['status'=>404, "data" => "Profile mit profileID".' '.$opt['profileID'].' konnte nicht geladen werden.'];
					//return $this->response(404, 'Profile not found. '.h::encode_php($res->status));
					}

				$res = profile::update(['profileID'=>$opt['profileID'], 'imageID'=>$opt['highlight_image']]);

				// also change profile->imageName

				if($res->status != 204){
					e::logtrigger('Profile '.$opt['profileID'].' konnte nicht update werden mit bilder '.$opt['highlight_image'].'.');
					if(!$this->us_get('flash')){
						$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);
						}
					}
				}

			if(!$this->us_get('flash')){
				$this->us_set(['flash' => ['type' => 'success', 'msg' => 206]]);
				}

			}

		return $this->load_page('mobile/chat');

		}


	public function page_static($lang = null, $page = null){

		return $this->load_page('static/'.$lang.'/'.$page);
		}


	/* ajax request */
	public function ajax_get(){

		// get POST
		$post = postdata::get();

		if($post){

			// optional params
			$opt = h::eX($post, [
				'msisdn'			=> '~^$|^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
				'submitMsisdn'		=> '~^$|^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
				'submitTan'			=> '~4,12/s',
				'getChat'			=> [],
				'mobileID'			=> '~1,4294967295/i',
				'images'			=> '~^$|^[1-9]{1}[0-9]{0,9}$',
				'profiles'			=> '~^[0-1]{1}$',
				'countries'			=> '~^[0-1]{1}$',
				'profile_is_unique'	=> [],
				'proceed_message'	=> [],
				'updateUserProfile'	=> [],
				'delete_image'		=> [],
				'highlight_image'	=> [],
				'tan_users'			=> [],
				'visits'			=> [],
				], $error, true);

			// on error
			if($error or empty($opt)) return $this->response(400);

			// get chats profiles
			if(!empty($opt['msisdn'])){

				// get mobile
				$res = client::get_mobile(['msisdn' => $opt['msisdn'][0]]);

				// on error
				if(!in_array($res->status, [200, 404])){
					return $this->response(200, 'Could not load mobile, status code: '.h::encode_php($res->status));
					}

				// Mobile not found
				elseif($res->status == 404){
					return $this->response(200, 'mobile not found for msisdn: '.h::encode_php($opt['msisdn']));
					}

				 // Mobile found
				else{

					// assign mobile
					$mobile = $res->data;

					// Set Mobile in user session
					$this->us_set([
						'msisdn' 	=> $mobile->msisdn,
						'mobileID' 	=> $mobile->mobileID
						]);
					}

				// get the list of chats profiles
				$res = message::get_profiles(['msisdn'=>$opt['msisdn'][0]]);

				// on error
				if($res->status != 200){
					return $this->response(200, 'Chat Profiles liste konnte nicht geladen werden.'.h::encode_php($res->status));
					}

				// check each profile for existing thumbnail and last MT
				foreach($res->data as $profile) {

					// load thumbs
					$list = image::get_list([
										'profileID' => $profile->profileID,
										'moderator' => 2
										]);

					// on error
					if($list->status != 200){
						return self::response(500, 'thumbs list could not be loaded '.$list->status);
						}

					// no thumb found, set default pic
					elseif (!$list->data){
						//$profile->thumb_url = $this->builder_url('/img/ico_woman.svg');
						}

					// set first thumbName as profile thumbName
					else {
						$profile->thumbName = $list->data[0]->imageName;
						}

					if(!empty($profile->imageID)){

						// load profile image
						$result = media::get_profile_media(['imageID'=>$profile->imageID]);

						// on error or not found
						if($result->status != 200){

							// assign empty image name
							$profile->imageName = '';
							}

						else{

							// assign image name
							$profile->imageName = $result->data->name;
							}
						}


					// load last MT
					$r = message::get_last_MT([
											'mobileID'	=> $this->us_get('mobileID'),
											'profileID'	=> $profile->profileID,
											]);

					// assign
					if($r->status == 200){

						// add last MT text
						$profile->lastMT = $r->data->text;

						// add last MT date
						 $profile->lastMTCreateTime = $r->data->createTime;
						}

					}// end foreach

				// re-index array
				//$res->data = array_values($res->data);
				}

			// Submit MSISDN and request a TAN
			elseif(!empty($opt['submitMsisdn'])){

				// save submitted MSISDN in controller instance
				$this->us_set(['submitMsisdn' => $opt['submitMsisdn'][0]]);

				// generate new TAN
				$result = $this->cmd_generate_tan_new();

				// forward result
				$res = (object)[
							'data' => $result
							];
				}

			// Submited TAN requesting a TAN
			elseif(!empty($opt['submitTan'])){

				// save submitted MSISDN in controller
				$this->us_set(['submitTan' => $opt['submitTan']]);

				// validate TAN
				$result = $this->cmd_validate_tan_new();

				// return
				$res = (object)[
							'data' => $result
							];

				return $this->response(200, json_encode($res->data));
				}

			// get chat
			elseif(!empty($opt['getChat']) ){

				// get the chat for the given profile
				$res = message::get([
					'profileID' => $opt['getChat']->profileID,
					'mobileID'  => $opt['getChat']->mobileID
					]);

				// on error
				if($res->status != 200){
					return $this->response(404, 'Chat not found. '.h::encode_php($res));
					}


				}

			// get profile images
			elseif(isset($opt['images'])){

				//
				$res = media::get_profile_media(['profileID'=>$opt['images']]);

				// on error
				if($res->status != 200){
					return $this->response(404, 'profile images not found. '.h::encode_php($res));
					}
				}

			// get user profile
			if(!empty($opt['mobileID'])) {

				// get profile
				$res = profile::get(['mobileID' => $opt['mobileID']]);

				// on error
				if(!in_array($res->status, [200, 404])){
					return self::response(500, 'profile could not be loaded '.$res->status);
					}

				// Profile not found
				elseif ($res->status == 404) {
					return self::response(404, 'profile not found');
					}

				$profile = $res->data;

				// get images
				$res = media::get_profile_media(['profileID'=>$profile->profileID]);

				// on error
				if($res->status != 200){
					return self::response(500, 'user profile images could not be loaded. status '.$res->status);
					}

				// add gallery images to profile
				$profile->images = array_reverse($res->data);

				// check for a countryID
				if(!empty($profile->countryID)){

					// load country
					$res = base::get_country(['countryID' => $profile->countryID]);

					// on error
					if(!in_array($res->status, [200, 404])){
						return self::response(500, 'country could not be loaded '.$res->status);
						}

					// not found
					elseif ($res->status == 404) {
						return self::response(404, 'country not found');
						}

					// add country name to profile
					$profile->country = $res->data->name;
					}

				return $this->response(200, json_encode($profile));
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

				// name-poolID is unique
				elseif ($res->status == 404) {
					return self::response(204, 'profile not found '.$res->status);
					}

				// name-poolID is NOT unique, return existing profileID from existing name-poolID
				return $this->response(200, json_encode($res->data->profileID));
				}

			if(!empty($opt['profiles'])){

				$this->get_profiles();

				$res = (object) ['data' => $this->profiles];

				}

			if(!empty($opt['proceed_message'])){

				// proceed message
				$result = $this->proceed_msg([
					'profileID'	=> $opt['proceed_message']->profileID,
					'text'		=> $opt['proceed_message']->text,
					]);

				// forward result
				$res = (object)[
							'data' => $result
							];
				}

			if(!empty($opt['updateUserProfile'])){

				// no profileID
				if(empty($opt['updateUserProfile']->profileID)) {

					// create new profile
					$result = profile::create($opt['updateUserProfile']);

					// on error
					if($result->status != 201){
						e::logtrigger('Profile konnte nicht erstellt werden: '.h::encode_php($result));
						return $this->response($result->status, json_encode($result));
						}

					// assign new profileID
					$opt['updateUserProfile']->profileID = $result->data->profileID;

					}

				// Update profile
				$result = profile::update($opt['updateUserProfile']);

				// forward result
				$res = (object)[
							'data' => $result
							];
				}

			if(!empty($opt['delete_image'])){

				// load image
				$result = media::get_profile_media(['imageID'=>$opt['delete_image']->imageID]);

				// on error
				if($result->status != 200){
					e::logtrigger('image could not be loaded: '.h::encode_php($result));
					return $this->response($result->status, json_encode($result));
					}

				// assign image
				$image = $result->data;

				// Its a video file
				if(substr($image->name, 0, 1) == 'v'){

					//  Is not proceed in media::delete_profile_media : delete video from server directory
					if(file_exists($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$opt['profileID'].'/videos/'.$image->name)){
						unlink($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$opt['profileID'].'/videos/'.$image->name);
						}
					else{
						e::logtrigger('Image '.$opt["delete_image"].' could not be deleted from profile directory');
						$this->us_set(['flash' => ['type' => 'danger', 'msg' => 410]]);
						}
					}

				// Its an image file
				else{

					// Is also proceed in media::delete_profile_media : delete from server directory
					if(file_exists($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$opt['delete_image']->profileID.'/'.$image->name)){
						unlink($_SERVER['DATA_PATH'].'/bragiprofile/profile/ProfileID_'.$opt['delete_image']->profileID.'/'.$image->name);
						}
					else{
						e::logtrigger('Image '.$opt["delete_image"]->imageID.' could not be deleted from profile directory');
						}
					}

				// and delete from database
				$result = media::delete_profile_media(['imageID'=>$image->imageID]);
				if($result->status != 204){
					e::logtrigger('image '.$opt["delete_image"]->imageID.' could be deleted from database');
					}

				// get profile
				$result = profile::get([
					'profileID' => $opt['delete_image']->profileID,
					]);

				// on error
				if(!in_array($result->status, [200, 404])){
					return self::response(500, 'profile could not be loaded '.$result->status);
					}

				// not found
				elseif ($result->status == 404) {
					return self::response(204, 'profile not found '.$result->status);
					}

				$profile = $result->data;

				// if deleted image was the profile front image, also update profile->imageID = 0
				if(!empty($profile->imageID)){
					if($opt['delete_image']->imageID == $profile->imageID){
						$resultat = profile::update(['profileID'=>$profile->profileID, 'imageID'=>0]);

						// on error
						if($resultat->status != 204){
							e::logtrigger('profile front imageID could not be set to 0 after deleting front image '.h::encode_php($resultat));
							return self::response(500, 'profile could not be updated '.$resultat->status);
							}
						}
					}

				// get updated profile
				$result = profile::get([
					'profileID' => $opt['delete_image']->profileID,
					]);

				// on error
				if(!in_array($result->status, [200, 404])){
					return self::response(500, 'profile could not be loaded '.$result->status);
					}

				// not found
				elseif ($result->status == 404) {
					return self::response(204, 'profile not found '.$result->status);
					}

				$profile = $result->data;

				// forward result
				$res = (object)[
							'data' => $result
							];
				}

			if(!empty($opt['highlight_image'])){

				// Check if picture exist
				$res = media::get_profile_media(['imageID'=>$opt['highlight_image']->imageID]);

				if($res->status != 200){
					e::logtrigger('image could not be loaded: '.h::encode_php($res));
					return $this->response($res->status, json_encode($res));
					}

				// assign image
				$image = $res->data;

				// get profile
				$res = profile::get(['profileID' => $opt['highlight_image']->profileID]);

				// assign profile
				$profile = $res->data;

				// on error
				if($res->status != 200){
					e::logtrigger('Profile could not be loaded: '.h::encode_php($res));
					return $this->response($result->status, json_encode($result));
					}

				// update profile image
				$res = profile::update([
					'profileID' => $profile->profileID,
					'imageID'	=> $image->imageID
					]);

				// on error
				if($res->status != 204){
					e::logtrigger('Profile could not be updated for imageID '.$image->imageID);
					return $this->response($res->status, json_encode($res));
						}
				}

			if(!empty($opt['tan_users'])){

				$res = event::get_stats_users([
					'event'	=> 'tanchecked',
					'from'	=> $opt['tan_users']->from,
					'to'	=> $opt['tan_users']->to
					]);
				if($res->status != 200){
					return $this->response(404, 'list TAN users could not be loaded'.h::encode_php($res));
					}
				}

			if(!empty($opt['visits'])){

				$res = event::get_stats_users([
					'event'	=> 'visit',
					'from'	=> $opt['visits']->from,
					'to'	=> $opt['visits']->to
					]);
				if($res->status != 200){
					return $this->response(404, 'list visits users could not be loaded'.h::encode_php($res));
					}
				}

			if(!empty($opt['countries'])){
				$res = base::get_country();
				if($res->status != 200){
					return $this->response(404, 'countries could not be loaded'.h::encode_php($res));
					}
				}

			return $this->response(200, json_encode($res->data ?? []));
			}
		}


	/* debug routes */
	public function page_simulate($page = null, $var1 = null, $var2 = null){

		// abort tracker
		$this->tracker_abort();

		// if whitelist check fails
		if(!$this->env_whitelist_check()){

			// log error
			e::logtrigger('whitelist check failed');

			// return 404
			return $this->response(404);
			}

		// define wrapper use and post processing function
		$use_wrapper = true;
		$postFn = null;

		// if page is given
		if($page){

			// translate page
			$page = $this->translate_page($page);

			// call environment class page
			return $this->env_page_simulate($page, $var1, $var2, $use_wrapper, $postFn);
			}


		$include_page = $this->env_get('preset:path_pages').'/'.$page.'.php';
		return $this->response_ob(200, function() use ($include_page, $use_wrapper){
			include $use_wrapper ? $this->env_get('preset:path_pages').'/wrapper.php' : $include_page;
			}, $postFn);
		}


	/* Funktionen */

	public function get_profiles(){

		// get pools
		$res = pool::get();

		// on error
		if($res->status != 200){
			return $this->response(404, 'pools could not be loaded'.h::encode_php($res));
			}

		// assign
		$pools = $res->data;
		$country_pools = [];

		// extend DE lang to AT and CH, ENG to IE and ZA
		if($this->us_get('lang') == 'de'){
			$country = ['de', 'at', 'ch'];
			}
		elseif($this->us_get('lang') == 'en'){
			$country = ['uk', 'ie', 'za'];
			}
		else{
			$country = [$this->us_get('lang')];
			}

		// find pools for selected language
		foreach ($pools as $pool) {

			foreach ($country as $token) {

			    if(stristr($pool->name, ' '.$token) != false and stristr($pool->name, substr($this->env_get('domain:presetID'), 0, -3)) != false) {

			        array_push($country_pools, $pool->poolID);
			    	}
				}
			}

		// default pools
		if(empty($country_pools)) $country_pools = [1, 2];

		// save pools list in user session
		$this->us_set(['pools' => $country_pools]);

		// load profiles
		$res = profile::get_list([
			'pools' 	=> $country_pools
			]);

		// on error
		if($res->status != 200){
			e::logtrigger('Profiles list could not be loaded');
			return $this->load_page('error/500');
			}

		// assign
		$this->profiles = $res->data;

		// shuffle profiles
		shuffle($this->profiles);

		// get each profile its first thumb
		foreach ($this->profiles as $profile) {

			// load profile thumbs
			$result = image::get_list([
									'profileID'=>$profile->profileID,
									'moderator' => 2
									]);

			// on error
			if($result->status != 200){
				e::logtrigger('thumbs list could not be loaded');
				return $this->load_page('error/500');
				}

			// set thumbName (or 0 if no thumb found)
			if(!empty($result->data)){
				$profile->thumbName = $result->data[0]->imageName;
				}
			else{
				$profile->thumbName = 0;
				}
			}
		}


	// proceed user text message to chattool
	public function proceed_msg($req = []){

			// mandatory
			$mand = h::eX($req, [
				'profileID'	=> '~1,4294967295/i',
				'text'		=> '~1,150/s',
				], $error);

			// optional
			$opt = h::eX($req, [
				'receiveTime'	=> '~Y-m-d H:i:s/d',
				], $error, true);

			// on error
			if($error or empty($mand)) return $this->response(400);

			// store message
			$res = message::create([
				'mobileID'		=> $this->us_get("mobileID"),
				'profileID'		=> $mand['profileID'],
				'text'			=> $mand['text'],
				'from'			=> 1,
				'smsgateID'		=> $this->us_get("smsgateID"),
				'persistID'		=> $this->us_get('persistID'),
				'receiveTime'	=> $opt['receiveTime'] ?? h::dtstr('now'),
				]);

			// on error
			if($res->status != 201){
				return (object)[
							'status' => 500,
							'data'=>'Could not create message, server error: '.$res->status
							];
				}

			// prepare return datas
			$data =  (object) [
							"messageID" => $res->data,
							];

			// return
			return (object)[
							'status'=>201,
							'data'=>$data
							];
		}


	// Check if an image or any file exists on server, without loading it's content.
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
