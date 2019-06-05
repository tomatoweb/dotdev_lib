<?php
/*****
 * Version 1.0.2017-11-08
**/
namespace dotdev\app\extension_trait;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\mail;

trait mailer {

	// route generator
	public function mailer_regex_routes(){

		// define contact form routes
		static $cf_routes = null;

		// if routes not defined yet
		if($cf_routes === null){

			// define
			$cf_routes = [];
			$cf_set = $this->env_get('preset:contactform_routes') ?: [];

			// append routes from preset
			foreach($cf_set as $url => $conf){
				$cf_routes[] = preg_quote($url, '/');
				}
			}

		// return regex routes (or empty string, witch does not match any url)
		return $cf_routes ? '~^('.implode('|', $cf_routes).')$' : '';
		}

	// page route function
	public function mailer_page_contactform($suburl){

		// check if contact form route is undefined
		if(!$this->env_is('preset:contactform_routes:'.$suburl)){

			// log error
			e::logtrigger('DEBUG: Cannot load preset contactform_route '.h::encode_php($suburl));

			// return error page
			return $this->load_page('error/500');
			}

		// take page from route
		$page = $this->env_get('preset:contactform_routes:'.$suburl);

		// check if (pre)identify is set and possible
		if(in_array(h::gX($page, 'identify'), ['pre','full']) and in_array($this->env_get('payment:type'), ['abo','otp'])){

			// identify
			$page_handle = $this->mp_hook_identify([
				'preidentify'		=> h::cX($page, 'identify', 'pre'),
				'restart_identify'	=> h::cG('reidentify'),
				]);
			if($page_handle !== null) return $page_handle;
			}

		// if page needs a verified age
		if(h::gX($page, 'verified_age')){

			// verify age (if also configured in nexus)
			$page_handle = $this->hook_verify_age();
			if($page_handle !== null) return $page_handle;
			}

		// if status means form was sent
		if($this->us_get('cf_status') == 204){

			// unset it
			$this->us_delete('cf_status');

			// return contact from success page
			return $this->load_page(h::gX($page, 'page_success'));
			}

		// return contact form page
		return $this->load_page(h::gX($page, 'page_form'));
		}

	// post route function
	public function mailer_post_contactform($postdata, $suburl){

		// abort tracker
		$this->tracker_abort();

		// take page from route
		$page = $this->env_get('preset:contactform_routes:'.$suburl) ?: [];

		// define account
		$account = [];

		// mandatory configuration
		$mand = h::eX($page, [
			'page_form'		=> '~1,100/s',
			'page_success'	=> '~1,100/s',
			'from'			=> '~1,100/s',
			'to' 			=> '~1,100/s',
			'subject' 		=> '~1,200/s',
			'mailbody'		=> '~1,100/s',
			], $cfg_error);

		// optional configuration
		$opt = h::eX($page, [
			'fromname'		=> '~1,100/s',
			'mand_fields'	=> '~associative/a',
			'opt_fields'	=> '~associative/a',
			], $cfg_error);

		// check account
		if(isset($mand['from'])){

			// define path to file
			$account_file = $_SERVER['ENV_PATH'].'/config/email/'.$mand['from'].'.php';

			// check if file is missing
			if(!is_file($account_file)){

				// append error
				$cfg_error[] = 'from';
				}

			// else
			else{

				// define mail settings
				$account = include($account_file);
				}
			}

		// check mailbody
		if(isset($mand['mailbody'])){

			// define path to file
			$mand['mailbody'] = $this->env_get('preset:path_pages').'/'.$mand['mailbody'].'.php';

			// check if file is missing
			if(!is_file($mand['mailbody'])){

				// append error
				$cfg_error[] = 'mailbody';
				}
			}

		// check if contact form route is undefined
		if($cfg_error){

			// log error
			e::logtrigger('DEBUG: Cannot load/use preset contactform_route '.h::encode_php($suburl).' ($cfg_error = '.h::encode_php($cfg_error).')');

			// and redirect to contact form url
			return $this->response(302, $this->us_url('/error'));
			}



		// mandatory param
		$cf_mand = !empty($opt['mand_fields']) ? h::eP($opt['mand_fields'], $cf_error) : [];

		// optional param
		$cf_opt = !empty($opt['opt_fields']) ? h::eP($opt['opt_fields'], $cf_error, true) : [];

		// save actual field data and errorfields
		$this->us_set(['cf_errorfields' => $cf_error] + $cf_mand + $cf_opt);

		// if params have errors
		if($cf_error){

			// save error and fieldnames with errors
			$this->us_set('cf_status', 400);

			// and redirect to contact form url
			return $this->response(302, $this->us_url($suburl));
			}


		// prepare saving output
		ob_start();

		// include mailbody
		include $mand['mailbody'];

		// send mail using saved output and settings
		$res = mail::smtp_send($account + [
			'from'		=> $mand['from'],
			'to' 		=> $mand['to'],
			'subject' 	=> $mand['subject'],
			'fromname'	=> $opt['fromname'] ?? $mand['from'],
			'htmlbody'	=> ob_get_clean(),
			]);

		// save status
		$this->us_set('cf_status', $res->status);

		// on success
		if($res->status == 204){

			// define
			$set = (!empty($opt['mand_fields']) ? $opt['mand_fields'] : []) + (!empty($opt['opt_fields']) ? $opt['opt_fields'] : []);

			// delete saved field data
			if($set) $this->us_delete(array_keys($set));
			}

		// on error
		else{

			// log error
			e::logtrigger('DEBUG: contact form fails with: '.$res->status);
			}

		// and redirect to contact form page
		return $this->response(302, $this->us_url($suburl));
		}

	}
