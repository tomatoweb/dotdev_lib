<?php
/*****
 * Version 1.4.2019-01-08
**/
namespace dotdev\app\extension_trait;

use \tools\error as e;
use \tools\helper as h;
use \tools\event as amboss_event;
use \dotdev\cronjob;
use \dotdev\traffic\session as traffic_session;

trait tracker {

	public function tracker_init($reset = false){

		// if tracker event is not defined
		if(!$this->env_is('tracker:event')){

			// register event
			amboss_event::on('close', function(){
				$this->tracker_store();
				});
			}

		// if tracker event is not defined or reset is given
		if(!$this->env_is('tracker:event') or $reset){

			// reset tracker data
			$this->env_reset_data('tracker', [
				'event'		=> true,
				'callinfo'	=> [],
				]);
			}

		// check for redirect
		$this->env_redirect();

		// return success
		return true;
		}

	public function tracker_abort(){

		// set false to abort tracker
		$this->env_set('tracker:event', false);
		}

	public function tracker_store(){

		// abort if tracker status is not true
		if(!$this->env_get('tracker:event')) return;

		// get exact request time
		$request_time =	round($_SERVER['REQUEST_TIME_FLOAT'], 4);

		// set a value (if not already set from a concurrent process) to block concurrent processes
		$unique = uniqid(rand(), true);
		$this->us_set('tracker:session_created', $unique, true);


		// check if unique key matched uncached session value
		if($this->us_is('tracker:session_created', $unique, true)){

			// if already session update data is found
			$session_data = $this->env_get('tracker:session_data') ?: [];

			// add redisjob to add session
			$res = traffic_session::delayed_create_session([
				// base param
				'persistID' 				=> $this->us_get('persistID'),
				'createTime'				=> $_SERVER['REQUEST_TIME'],
				'domainID'					=> $this->env_get('nexus:domainID'),
				'pageID'					=> $this->env_get('nexus:pageID'),
				'publisherID'				=> $this->env_get('nexus:publisherID'),

				// special param
				'publisher_uncover_key'		=> $this->env_get('nexus:publisher_uncover_key'),
				'publisher_uncover_name'	=> $this->env_get('nexus:publisher_uncover_name'),
				'publisher_affiliate_key'	=> $this->env_get('nexus:publisher_affiliate_key'),
				'usID'						=> $this->us->usID,
				'ipv4'						=> (strpos($_SERVER['REMOTE_ADDR'], ':') === false) ? $_SERVER['REMOTE_ADDR'] : null,
				'ipv6'						=> (strpos($_SERVER['REMOTE_ADDR'], ':') !== false) ? $_SERVER['REMOTE_ADDR'] : null,
				'useragent'					=> !empty($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
				'referer'					=> $_SERVER['HTTP_REFERER'] ?? null,

				// options
				'ipv4_range_detection'		=> $this->env_get('domain:ipv4range_detection') ? true : false,
				'delayed_parsing'			=> true,
				] + $session_data);

			// on error
			if($res->status != 204){

				// log error
				e::logtrigger('Failed to create RedisJob for create_session: '.$res->status);

				// and abort
				return;
				}
			}

		// else if session update data is ready
		elseif($this->env_get('tracker:session_data')){

			// add redisjob to update session
			$res = traffic_session::delayed_update_session([
				'persistID' 	=> $this->us_get('persistID'),
				] + $this->env_get('tracker:session_data')
				);

			// on error
			if($res->status != 204){

				// log error
				e::logtrigger('Failed to create RedisJob for update_session: '.$res->status);

				// and abort
				return;
				}
			}

		// if this is a new click
		if($this->env_is('nexus:new_click_request')){

			// get click request data
			$request_data = $this->env_get('nexus:new_click_request');

			// save in session
			$this->us_set('tracker:last_click_request', $request_data);

			// add redisjob to add click to session
			$res = traffic_session::delayed_create_click([
				// base param
				'persistID'		=> $this->us_get('persistID'),
				'createTime'	=> $_SERVER['REQUEST_TIME'],

				// special param
				'referer'		=> $_SERVER['HTTP_REFERER'] ?? null,
				'request'		=> $request_data,
				]);

			// on error
			if($res->status != 204){

				// log error
				e::logtrigger('Failed to create RedisJob for create_click: '.$res->status);

				// and abort
				return;
				}
			}

		// if we have a new blocked click
        if($this->env_is('nexus:blocked_click')){

            // get data
            $blocked_click = $this->env_get('nexus:blocked_click');

            // add redisjob to add click to session
			$res = traffic_session::delayed_create_blocked_click([
				// base param
				'persistID'		=> $this->us_get('persistID'),
				'createTime'	=> $_SERVER['REQUEST_TIME'],
				'pageID'		=> $blocked_click->pageID,
				'publisherID'	=> $blocked_click->publisherID,
				'status'		=> $blocked_click->status,

				// special param
				'referer'		=> $blocked_click->referer ?: null,
				'request'		=> $blocked_click->pubdata ?: null,
				]);

			// on error
			if($res->status != 204){

				// log error
				e::logtrigger('Failed to create RedisJob for create_blocked_click: '.$res->status);

				// and abort
				return;
				}
            }


		// create pageview_data
		$pageview_data = $this->env_get('tracker:callinfo');
		if(!is_array($pageview_data)) $pageview_data = [];
		$pageview_data['url'] = $this->env_get('nexus:url');

		// add redisjob to add session_pageview
		$res = traffic_session::delayed_create_session_pageview([
			// base param
			'persistID'		=> $this->us_get('persistID'),
			'createTime'	=> $_SERVER['REQUEST_TIME'],
			'data'			=> $pageview_data,
			]);

		// on error
		if($res->status != 204){

			// log error
			e::logtrigger('Failed to create RedisJob for session_pageview: '.$res->status);

			// and abort
			return;
			}
		}

	public function tracker_add_session_data($req){

		// convert
		if(is_object($req)) $req = (array) $req;

		// abort if not array
		if(!is_array($req)){

			// log error
			e::logtrigger('DEBUG: Invalid session data for tracker found: '.h::encode_php($req));

			// and abort
			return false;
			}

		// if data is not empty
		if(!empty($req)){

			// load already given session data
			$session_data = $this->env_get('tracker:session_data') ?: [];

			// add new session data for tracker
			$this->env_set('tracker:session_update', $req + $session_data);
			}

		// return success
		return true;
		}

	}
