<?php
/*****
 * Version 1.9.2019-01-30
**/
namespace dotdev\app\extension_trait;

use \tools\helper as h;
use \tools\error as e;
use \tools\crypt;
use \dotdev\reflector;
use \dotdev\nexus\base as nexus_base;
use \dotdev\nexus\service;
use \dotdev\nexus\domain;
use \dotdev\mobile\client;
use \dotdev\mobile\login as mobile_login;
use \dotdev\livestat;
use \tim\emp;

trait mobilepayment {

	/* 16 byte key for mobile user data encryption */
	protected $encmud_key = 'mud_3rDS-RaB9G9u';


	/* Session */
	public function mp_init(){

		// define static value
		static $done;

		// if already done, return here
		if($done) return true;

		// save force_payment param, if given and allowed
		if($this->env_whitelist_check() and h::cG('force_payment')){
			$this->us_set('force_payment', h::gR('force_payment'));
			}

		// if payment is forced to be overwritten
		if($this->us_get('force_payment')){
			$this->env_set('payment:type', $this->us_get('force_payment'));
			}

		// define payment type
		$type = $this->env_get('payment:type');

		// define session parts to initialize
		$init_names = [];

		// init session data for abo|otp
		if(in_array($type, ['abo','otp'])){

			// add ibr session data
			$init_names[] = 'ibr';

			// add abo|otp session data
			$init_names[] = $type;
			}

		// initialize session parts
		if($init_names) $this->mp_init_session($init_names);

		// for these payment types
		if(in_array($type, ['abo','smsabo','otp','smspay'])){

			// load mobile payment environment
			return $done = $this->mp_load_env();
			}

		// set and return true
		return $done = true;
		}

	public function mp_init_session($names, $reset = false){

		// convert to array, if string
		if(is_string($names)) $names = [$names];

		// if names is no array
		if(!is_array($names)){

			// log error
			e::logtrigger('DEBUG: mp_session names are invalid: '.h::encode_php($name));

			// return false;
			return false;
			}

		// for IBR and IBI
		if((in_array('ibr', $names) or in_array('ibi', $names)) and ($reset or !$this->us_is('mp:identify:status'))){

			// define set
			$this->us_set([
				'mp:identify:status'		=> 0,
				'mp:identify:mobileID'		=> null,
				'mp:identify:confirmed'		=> false,
				]);
			}

		// for IBR
		if(in_array('ibr', $names) and ($reset or !$this->us_is('mp:ibr:status'))){

			// define set
			$this->us_set([
				'mp:ibr:status'				=> 0,
				'mp:ibr:reflectorID'		=> null,
				'mp:ibr:startStackID'		=> null,
				'mp:ibr:returnStackID'		=> null,
				'mp:ibr:lastcall'			=> 0,
				'mp:ibr:finished'			=> false,
				'mp:ibr:resetable'			=> false,
				]);
			}

		// for IBI
		if(in_array('ibi', $names) and ($reset or !$this->us_is('mp:ibi:status'))){

			// define set
			$this->us_set([
				'mp:ibi:status'				=> 0,
				'mp:ibi:tan_status'			=> 0,
				]);
			}

		// for Abo
		if(in_array('abo', $names) and ($reset or !$this->us_is('mp:abo:status'))){

			// define set
			$this->us_set([
				'mp:abo:status'				=> 0,
				'mp:abo:aboID' 				=> null,
				'mp:abo:confirmationURL'	=> '',
				'mp:abo:reflectorID'		=> null,
				'mp:abo:startStackID'		=> null,
				'mp:abo:returnStackID'		=> null,
				'mp:abo:finished'			=> false,
				'mp:abo:resetable'			=> false,
				'mp:abo:lockstatus'			=> 0,
				'mp:abo:unlocktime'			=> 0,
				]);
			}

		// for OTP
		if(in_array('otp', $names) and ($reset or !$this->us_is('mp:otp:status'))){

			// define set
			$this->us_set([
				'mp:otp:status'				=> 0,
				'mp:otp:otpID' 				=> null,
				'mp:otp:confirmationURL'	=> '',
				'mp:otp:reflectorID'		=> null,
				'mp:otp:startStackID'		=> null,
				'mp:otp:returnStackID'		=> null,
				'mp:otp:finished'			=> false,
				'mp:otp:resetable'			=> false,
				]);
			}

		// return success
		return true;
		}


	/* Shared */
	public function mp_load_env($forcereload = false){

		static $loaded_productID;

		// if mobile must be loaded
		if(($forcereload or !$this->env_get('mp_mobile')) and $this->us_get('mp:identify:mobileID')){

			// load mobile
			$res = client::get_mobile([
				'mobileID'	=> $this->us_get('mp:identify:mobileID')
				] + $this->mp_get_product_param(true));

			// on success
			if($res->status == 200){

				// set mobile in environment
				$this->env_reset_data('mp_mobile', $res->data);

				// check if mobileID has changed (through dummy migration)
				if($this->us_get('mp:identify:mobileID') != $this->env_get('mp_mobile:mobileID')){

					// overwrite with new mobileID
					$this->us_set('mp:identify:mobileID', $this->env_get('mp_mobile:mobileID'));
					}
				}

			// on error
			else{
				$this->us_set('mp:ibr:status', 500);
				}
			}

		// get type
		$type = $this->env_get('payment:type');

		// convert payment type to product type
		if($type == 'smsabo') $type = 'abo';
		if($type == 'smspay') $type = 'otp';

		// only continue here with abo|otp type
		if(!in_array($type, ['abo','otp'])) return true;

		// get product param
		$product_param = $this->mp_get_product_param();

		// if productID is not loaded or different
		if($loaded_productID != $product_param['productID']){

			// reset
			$loaded_productID = null;

			// load product
			$res = service::get_product($product_param);

			// on success
			if($res->status == 200){

				// save to static
				$loaded_productID = $product_param['productID'];

				// reset data in env
				$this->env_reset_data('mp_product', $res->data);
				}

			// on error
			else{
				e::logtrigger('Could not load product with '.h::encode_php($product_param).': '.$res->status);
				}
			}

		// return
		return (bool) $loaded_productID;
		}

	public function mp_get_product_param($with_productID_list = false){

		static $collected = false;
		static $type;
		static $param_type;
		static $productID_list = [];
		static $operator_productID = [];

		// if static values are not collected before
		if(!$collected){

			// set it from config
			$type = $this->env_get('payment:type');

			// set param_type
			if($type == 'smsabo') $param_type = 'abo';
			elseif($type == 'smspay') $param_type = 'otp';
			else $param_type = $type;



			// load new configuration (and convert it to a associative array)
			$cfg = $this->env_get('payment:product');
			if($cfg) $cfg = json_decode($cfg, true);

			// if configuration is given
			if($cfg){

				// define active productIDs
				$operator_productID = h::gX($cfg, 'default');
				if(!is_array($operator_productID)) $operator_productID = [];

				// define additional accessable productIDs
				$additional = h::gX($cfg, 'additional');
				if(!is_array($additional)) $additional = [];

				// create productID_list from both
				$productID_list = array_merge(array_values($operator_productID), array_values($additional));
				}


			// check if at least a default productID is given
			if(empty($operator_productID[0])){

				// if not, log it
				e::logtrigger('DEBUG: Domain payment:product config error, no default productID could be loaded: '.h::encode_php($operator_productID).'(payment:product = '.h::encode_php($this->env_get('payment:product')).')');
				}

			// set collected to true
			$collected = true;
			}

		// define active productID
		$active_operatorID = $this->env_get('mp_mobile:operatorID') ?: 0;

		// return product param
		return [
			'type'				=> $param_type,
			'productID' 		=> $operator_productID[$active_operatorID] ?? $operator_productID[0] ?? 0,
			] + ($with_productID_list ? [
			'productID_list'	=> $productID_list,
			] : []);
		}

	public function mp_change_contingent($req){

		// if req is no array, return false
		if(!is_array($req)) return false;

		// change contingent
		$res = client::change_contingent([
			'mobileID' => $this->env_get('mp_mobile:mobileID'),
			] + $this->mp_get_product_param(true) + $req);

		// on error
		if($res->status != 204) return false;

		// reload environment
		$this->mp_load_env();

		// return success
		return true;
		}

	public function mp_has_page($type, $fallback_operatorID = 0, $skip = null){

		// static cache
		static $result = [];

		// check if supported type
		if(!in_array($type, ['own_offer', 'mdk_offer', 'own_confirmation', 'mdk_confirmation', 'configurable_confirmation', 'own_tan', 'operator_tan', 'own_sms_pending', 'own_success'])){

			// log error
			e::logtrigger('DEBUG: mp_has_page triggered unknown type: '.h::encode_php($type));

			// return false
			return false;
			}

		// define key for option array
		$key = 'mp_product:param:has_'.$type.'_page';

		// define operator
		$operatorID = $this->env_get('mp_mobile:operatorID') ?: $fallback_operatorID;

		// if static result is not calculated yet
		if(!isset($result[$key][$operatorID])){

			// get option array, if given
			$has_array = $this->env_get($key);

			// calc result
			$result[$key][$operatorID] = (is_array($has_array) and in_array($operatorID, $has_array));

			// special code for offer pages
			if(in_array($type, ['own_offer', 'mdk_offer'])){

				// get skip status
				$skip = $this->env_get('adtarget:offer_pp_skip');

				// if skip status should be calculated
				if($result[$key][$operatorID] and $skip){

					// convert to boolean, if rule is a timeset
					if(preg_match('/^((?:[0-1][0-9]|2[0-4]):(?:[0-6][0-9]))-((?:[0-1][0-9]|2[0-4]):(?:[0-6][0-9]))$/', $skip, $m)){
						$skip = h::is_in_daytime('now', $m[1], $m[2]);
						}

					// overwrite result
					$result[$key][$operatorID] = !$skip;
					}
				}
			}

		// return result
		return $result[$key][$operatorID];
		}


	/* Client Functions */
	public function mp_client_ibr($ajaxcall = false, $restart = false){

		// if process is restartable and restart is wanted
		if($this->us_get('mp:ibr:finished') and $this->us_get('mp:ibr:resetable') and $restart){

			// reinit session
			$this->mp_init_session('ibr', true);
			}

		// else overwrite restart value
		else $restart = false;

		// if process is not finished and calltime of this client request younger than the last
		if(!$this->us_get('mp:ibr:finished') and $this->us_get('mp:ibr:lastcall') < $_SERVER['REQUEST_TIME_FLOAT']){

			// proceed process
			$res = client::ibr_mobile([
				'persistID'		=> $this->us_get('persistID'),
				'restart'		=> $restart,
				'domainID'		=> $this->env_get('nexus:domainID'),
				'pageID'		=> $this->env_get('nexus:pageID'),
				] + $this->mp_get_product_param());

			// take status and set lastcall
			$this->us_set('mp:ibr:status', $res->status);
			$this->us_set('mp:ibr:lastcall', $_SERVER['REQUEST_TIME_FLOAT']);

			// if there is data
			if(isset($res->data)){

				// if mobileID is given
				if(isset($res->data->mobileID)){

					// take mobileID and set identification to confirmed
					$this->us_set('mp:identify:mobileID', $res->data->mobileID);
					$this->us_set('mp:identify:confirmed', true);
					}

				// if reflector values are given, take them
				if(isset($res->data->reflectorID)) $this->us_set('mp:ibr:reflectorID', $res->data->reflectorID);
				if(isset($res->data->stackID)) $this->us_set('mp:ibr:startStackID', $res->data->stackID);
				}
			}

		// if process is not in pending mode, no redirect is needed and it's not finished
		if(!in_array($this->us_get('mp:ibr:status'), [102, 307]) and !$this->us_get('mp:ibr:finished')){

			// set it to finished
			$this->us_set('mp:ibr:finished', true);

			// for these stati
			if(in_array($this->us_get('mp:ibr:status'), [403,503])){

				// define process is restartable
				$this->us_set('mp:ibr:resetable', true);
				}
			}

		// if a redirection is needed
		if($this->us_get('mp:ibr:status') == 307){

			// if no reflectorID exists
			if(!$this->us_get('mp:ibr:reflectorID')){

				// log error
				e::logtrigger('Did not have a reflectorID for identify redirect '.h::encode_php($this->us_get('mp:identify')));

				// set process to error and abort
				$this->us_set('mp:ibr:status', 500);
				return;
				}

			// stack the return url to app
			if(!$this->us_get('mp:ibr:returnStackID')){

				// add a stack
				$res = reflector::stack([
					'reflectorID' 	=> $this->us_get('mp:ibr:reflectorID'),
					'url'			=> $this->us_same_url(),
					]);

				// on error
				if($res->status != 201){

					// set process to error and abort
					$this->us_set('mp:ibr:status', 500);
					return;
					}

				// save stackID to session
				$this->us_set('mp:ibr:returnStackID', $res->data->stackID);
				}

			// if this is not a ajax call
			if(!$ajaxcall){

				// get actual stack
				$res = reflector::run_stack([
					'reflectorID'	=> $this->us_get('mp:ibr:reflectorID'),
					'stackID'		=> $this->us_get('mp:ibr:startStackID'),
					]);

				// on error
				if($res->status != 200){

					// log error
					e::logtrigger('run_stack returned status '.$res->status.' for identify redirect');

					// set process to error and abort
					$this->us_get('mp:ibr:status', 500);
					return;
					}

				// take stack
				$stack = $res->data;

				// make redirect
				header('HTTP/1.1 302 Found');
				header('Location:'.$stack->url);

				// track page (TODO: theoretisch kann hier auch die stackID angehangen werden: 'ibr/redirect/'.$stack->stackID)
				$this->env_set('tracker:callinfo:page', 'ibr/redirect');

				// and exit here
				exit;
				}
			}

		// reload environment and return
		return $this->mp_load_env();
		}

	public function mp_client_ibi($restart = false){

		// if restart is wanted
		if($restart){

			// reinit session
			$this->mp_init_session('ibi', true);
			}

		// if process needs the MSISDN input from user
		if(in_array($this->us_get('mp:ibi:status'), [0, 400]) and h::cR('ibi-msisdn')){

			// if MSISDN format is valid
			if(preg_match('/^(\+|00|0)([1-9]{1}[0-9]{5,14})$/', h::gR('ibi-msisdn'), $match)){

				// define msisdn
				$msisdn = $match[2];

				// load country with products countryID
				$res = nexus_base::get_country([
					'countryID' => $this->env_get('mp_product:countryID'),
					]);

				// on error
				if($res->status !== 200){

					// set process status to error and return
					return $this->us_set('mp:ibi:status', 500);
					}

				// take country
				$country = $res->data;

				// if input has one leading 0
				if($match[1] === '0'){

					// replace with international prefix
					$msisdn = $country->prefix_int.$match[2];
					}

				// if MSISDN now has no leading or the wrong international prefix
				if(substr($msisdn, 0, strlen((string) $country->prefix_int)) != $country->prefix_int or strlen($msisdn) > 15){

					// set status to "invalid data"
					$this->us_set('mp:ibi:status', 400);
					}

				// else
				else {

					// proceed process
					$res = client::ibi_mobile([
						'msisdn'	=> $msisdn,
						'persistID'	=> $this->us_get('persistID'),
						'domainID'	=> $this->env_get('nexus:domainID'),
						'pageID'	=> $this->env_get('nexus:pageID'),
						] + $this->mp_get_product_param());

					// take status
					$this->us_set('mp:ibi:status', $res->status);

					// on success
					if($res->status == 200){

						// take mobileID, but identification is still not confirmed
						$this->us_set('mp:identify:mobileID', $res->data->mobileID);
						$this->us_set('mp:identify:confirmed', false);
						}
					}
				}

			// else not
			else{

				// set status to "invalid data"
				$this->us_set('mp:ibi:status', 400);
				}
			}

		// reload environment and return
		return $this->mp_load_env();
		}

	public function mp_client_ibi_tan_confirm($restart = false){

		// if process means to start or reenter input
		if(in_array($this->us_get('mp:ibi:tan_status'), [0, 100, 400, 401])){

			// if no serviceID given
			if(!$this->env_get('mp_product:param:identify:tan_serviceID')){

				// set status to error
				$this->us_set('mp:ibi:tan_status', 500);
				}

			// define default param
			$param = [
				'serviceID'				=> $this->env_get('mp_product:param:identify:tan_serviceID'),
				'mobileID'				=> $this->us_get('mp:identify:mobileID'),
				'persistID'				=> $this->us_get('persistID'),
				'expire'				=> '1 day',
				'tan_retry'				=> 3,
				'retry_expires'			=> true,
				'match_expires'			=> true,
				'allow_recreation'		=> true,
				'recreation_lock'		=> '20 min',
				'sms_senderString'		=> $this->env_get('mp_product:param:identify:tan_senderString'),
				'sms_text'				=> $this->env_get('mp_product:param:identify:tan_text') ?: '{tan}',
				];

			// if restart is forced
			if($restart){

				// define param to immediately restart
				$param['allow_recreation'] = true;
				unset($param['recreation_lock']);
				}

			// if tan is given
			if(h::cR('tan', '~4,12/s')){

				// set check_tan
				$param['check_tan'] = h::gR('tan');
				}

			// call client tan confirmation process
			$res = client::ibi_tan_confirm($param);

			// take status
			$this->us_set('mp:ibi:tan_status', $res->status);

			// on success
			if($res->status == 204){

				// set identification to confirmed
				$this->us_set('mp:identify:confirmed', true);
				}
			}

		// return
		return true;
		}

	public function mp_client_ibi_with_mud($req){

		// if msisdn is given
		if(h::gX($req, 'msisdn')){

			// proceed process
			$res = client::ibi_mobile([
				'persistID'		=> $this->us_get('persistID'),
				'msisdn'		=> h::gX($req, 'msisdn'),
				'operatorID'	=> h::gX($req, 'operatorID'),
				'confirmTime'	=> h::gX($req, 'confirmTime'),
				'domainID'		=> $this->env_get('nexus:domainID'),
				'pageID'		=> $this->env_get('nexus:pageID'),
				] + $this->mp_get_product_param());

			// take status
			$this->us_set('mp:ibi:status', $res->status);

			// on success
			if($res->status == 200){

				// take mobileID, but identification is still not confirmed
				$this->us_set('mp:identify:mobileID', $res->data->mobileID);

				// set identification as not confirmed, which forces the need for a tan confirmation to validate user
				$this->us_set('mp:identify:confirmed', false);

				// load this user, if not already happend
				$this->mp_load_env();
				}
			}

		// if imsi is given
		if(h::gX($req, 'imsi')){

			// load mobile
			$res = client::get_mobile([
				'imsi'				=> h::gX($req, 'imsi'),
				'persistID'			=> $this->us_get('persistID'),
				'autopersistlink'	=> true,
				]);

			// take status
			$this->us_set('mp:ibi:status', $res->status);

			// on success
			if($res->status == 200){

				// save mobileID to session
				$this->us_set('mp:identify:mobileID', $res->data->mobileID);

				// also save identification as confirmed, which skips the need for a tan confirmation to validate user
				$this->us_set('mp:identify:confirmed', true);

				// add tracker session data
				$this->tracker_add_session_data([
					'mobileID'	=> $res->data->mobileID,
					'operatorID'=> $res->data->operatorID ?: null,
					'countryID'	=> $res->data->countryID ?: null,
					]);


				// load this user, if not already happend
				$this->mp_load_env();
				}
			}
		}

	public function mp_client_payment($type, $mode, $req = []){

		// abort if we have no mobileID
		if(!$this->env_get('mp_mobile:mobileID')) return;

		// for Abo or OTP payment
		if(in_array($type, ['abo','otp'])){

			// if this process is finished and resetable (and this is not a ajax call)
			if($this->us_get('mp:'.$type.':finished') and $this->us_get('mp:'.$type.':resetable') and $mode !== 'ajaxstatus'){

				// reset session data
				$this->mp_init_session($type, true);
				}

			// abort if we have no ID and this is a ajax call
			if(!$this->us_get('mp:'.$type.':'.$type.'ID') and $mode === 'ajaxstatus') return;

			// if we have a confirmationURL
			if(h::cX($req, 'confirmationURL')){

				// save it for other processes (without it)
				$this->us_set('mp:'.$type.':confirmationURL', h::gX($req, 'confirmationURL'));
				}

			// if this process is not finished
			if(!$this->us_get('mp:'.$type.':finished')){

				// define param
				$param = [
					'mobileID'			=> $this->env_get('mp_mobile:mobileID'),
					'persistID'			=> $this->us_get('persistID'),
					'confirmationURL'	=> $this->us_get('mp:'.$type.':confirmationURL'),
					$type.'ID'			=> $this->us_get('mp:'.$type.':'.$type.'ID'), // aboID or otpID is 0, if new
					'netm'				=> $req['netm'] ?? null,
					'dimoco'			=> $req['dimoco'] ?? null,
					'submitted'			=> ($mode === 'submitted'),
					'clickrequest'		=> $this->env_get('nexus:is_click_request'),
					'tan'				=> $req['tan'] ?? null,
					] + $this->mp_get_product_param();

				// call client
				$res = ($type == 'abo') ? client::create_abo($param) : client::submit_otp($param);

				// save status
				$this->us_set('mp:'.$type.':status', $res->status);

				// if we have data
				if(isset($res->data)){

					// if we have an aboID or otpID
					if(isset($res->data->{$type.'ID'})){

						// save ID
						$this->us_set('mp:'.$type.':'.$type.'ID', $res->data->{$type.'ID'});
						}

					// if we have a reflectorID
					if(isset($res->data->reflectorID)){

						// save it to session
						$this->us_set('mp:'.$type.':reflectorID', $res->data->reflectorID);
						$this->us_set('mp:'.$type.':startStackID', $res->data->stackID);
						}
					}
				}

			// if the actual status is not one to continue, but the process is not finished
			if(!in_array($this->us_get('mp:'.$type.':status'), [100,102,307]) and !$this->us_get('mp:'.$type.':finished')){

				// for a ajax call, do nothing
				if($mode === 'ajaxstatus') return;

				// save this process is finished
				$this->us_set('mp:'.$type.':finished', true);

				// check if process can be reseted
				if(in_array($this->us_get('mp:'.$type.':status'), [200,201,401,402])){

					// save it
					$this->us_set('mp:'.$type.':resetable', true);
					}

				// if payment was successful
				if($this->us_get('mp:'.$type.':status') == 200){

					// set identify is confirmed (through successful payment process)
					$this->us_set('mp:identify:confirmed', true);
					}

				// finish further processing
				return;
				}

			// if this process needs a redirect
			if($this->us_get('mp:'.$type.':status') == 307){

				// check if reflectorID is missing
				if(!$this->us_get('mp:'.$type.':reflectorID')){

					// log error
					e::logtrigger('Payment process failed, no reflectorID for redirect given: '.h::encode_php($this->us_get('mp:'.$type)));

					// set status and abort further processing
					return $this->us_set('mp:'.$type.':status', 500);
					}

				// if this process has no stackID set for returning
				if(!$this->us_get('mp:'.$type.':returnStackID')){

					// append it
					$res = reflector::stack([
						'reflectorID' 	=> $this->us_get('mp:'.$type.':reflectorID'),
						'url'			=> $this->us_same_url('?rt='.time()),
						]);

					// on error, set status and abort further processing
					if($res->status != 201) return $this->us_set('mp:'.$type.':status', 500);

					// save stackID
					$this->us_set('mp:'.$type.':returnStackID', $res->data->stackID);
					}

				// if this is not a ajax call
				if($mode !== 'ajaxstatus'){

					// get first stack to redirect
					$res = reflector::run_stack([
						'reflectorID'	=> $this->us_get('mp:'.$type.':reflectorID'),
						'stackID'		=> $this->us_get('mp:'.$type.':startStackID'),
						]);

					// on error, set status and abort further processing
					if($res->status != 200) return $this->us_set('mp:'.$type.':status', 500);

					// make redirect
					header('HTTP/1.1 302 Found');
					header('Location:'.$res->data->url);

					// append track data
					$this->env_set('tracker:callinfo:page', $type.'/redirect/'.$this->us_get('mp:'.$type.':startStackID'));

					// exit php processing here
					exit;
					}
				}
			}

		// finish further processing
		return;
		}



	/* Hook: login */
	public function mp_login($loginstr){

		// load login
		$res = mobile_login::get_login(['loginstr'=>$loginstr]);

		// if found
		if($res->status == 200){

			// take mobileID and confirm identification
			$this->us_set('mp:identify:mobileID', $res->data->mobileID);
			$this->us_set('mp:identify:confirmed', true);

			// and redirect to index
			$this->env_set('tracker:callinfo:page', 'mobile_login/200/redirect');
			return $this->response(302, $this->us_url('/'));
			}

		// on error
		return $this->load_page('ibr/error', $res->status == 404 ? 403 : 500);
		}


	/* Hook: Identify */
	public function mp_hook_identify($req = []){

		// check for encrypted mobile user data
		if($this->us_get('nexus:encmud')){

			// try to decrypt data
			$mud_json = crypt::aes_decrypt($this->us_get('nexus:encmud'), $this->encmud_key, ['key_is'=>'plain']);

			// try to decode data as json
			$mud_data = @json_decode($mud_json);

			// if data is an object, it is also valid
			if(is_object($mud_data)){

				// load mud
				$this->mp_client_ibi_with_mud($mud_data);
				}

			// save to other further unprocessed session field
			$this->us_set('nexus:encmud_processed', $this->us_get('nexus:encmud'));

			// unset old session field
			$this->us_delete('nexus:encmud');
			}

		// define restart
		$restart = (bool) h::gX($req, 'restart_identify');

		// if mobileID already given or identify only available with abo|otp product
		if(($this->env_get('mp_mobile:mobileID') and !$restart) or !in_array($this->env_get('payment:type'), ['abo','otp'])){

			// stop process and continue
			return null;
			}

		// if IBR is available
		if($this->env_get('mp_product:param:identify:ibr')){

			// start/continue IBR process
			$this->mp_client_ibr(false, $restart);

			// set ibr status as main identify status
			$this->us_set('mp:identify:status', $this->us_get('mp:ibr:status'));

			// if we are in pending mode
			if($this->us_get('mp:ibr:status') == 102){

				// set pending page
				$page = 'ibr/pending';
				}

			// if IBR process finished and operatorID was detected
			elseif(in_array($this->us_get('mp:ibr:status'), [200, 201])){

				// stop process and continue
				return null;
				}

			// if ibr process finished, but no operatorID was detected
			elseif($this->us_get('mp:ibr:status') == 403){

				// on preidentify
				if(h::gX($req, 'preidentify')){

					// stop process and continue
					return null;
					}

				// if IBI is available
				if($this->env_get('mp_product:param:identify:ibi')){

					// start/continue IBI process
					$this->mp_client_ibi();

					// if IBI process could be started
					if(in_array($this->us_get('mp:ibi:status'), [0, 400])){

						// set IBI status as main identify status
						$this->us_set('mp:identify:status', $this->us_get('mp:ibi:status'));

						// set offer page
						$page = 'ibi/offer';
						}

					// if IBI process successful finished
					elseif(in_array($this->us_get('mp:ibi:status'), [200, 201])){

						// set IBI status as main identify status
						$this->us_set('mp:identify:status', $this->us_get('mp:ibi:status'));

						// stop process and continue
						return null;
						}

					// in any other case (e.g. IBI process fails)
					else{

						// set the previous IBR status as main identify status
						$this->us_set('mp:identify:status', $this->us_get('mp:ibr:status'));

						// set IBR error page (with last status of IBR)
						$page = 'ibr/error';
						}
					}

				// else
				else{

					// set error page
					$page = 'ibr/error';
					}

				}

			// in any other case (e.g. process failed)
			else{

				// on preidentify
				if(h::gX($req, 'preidentify')){

					// stop process and continue
					return null;
					}

				// set error page
				$page = 'ibr/error';
				}

			// load page
			return $this->load_page($page, $this->us_get('mp:identify:status'));
			}

		// if IBI is available
		if($this->env_get('mp_product:param:identify:ibi')){

			// start/continue IBI process
			$this->mp_client_ibi();

			// set IBI status as main identify status
			$this->us_set('mp:identify:status', $this->us_get('mp:ibi:status'));

			// if process could be started
			if(in_array($this->us_get('mp:ibi:status'), [0, 400])){

				$page = 'ibi/offer';
				}

			// if process successful finished
			elseif(in_array($this->us_get('mp:ibi:status'), [200, 201])){

				// stop process and continue
				return null;
				}

			// in any other case (e.g. IBI process fails)
			else{

				// set error page
				$page = 'ibi/error';
				}

			// load page
			return $this->load_page($page, $this->us_get('mp:identify:status'));
			}

		// continue
		return null;
		}

	public function mp_hook_identify_ajax(){

		// if this request creates a new session
		if($this->us->isnew){

			// abort tracker
			$this->tracker_abort();

			// return forbidden
			return $this->response(403);
			}

		// if referer exists and matches domain of app (which means a valid ajax request)
		if(isset($_SERVER['HTTP_REFERER']) and strpos($_SERVER['HTTP_REFERER'], 'http://'.$_SERVER['SERVER_NAME']) === 0){

			// take referer url
			$referer_url = substr($_SERVER['HTTP_REFERER'], strlen('http://'.$_SERVER['SERVER_NAME']));

			// if it is an ajax request from a simulated page
			if(($referer_url == '/sim' or strpos($referer_url, '/sim:') === 0) and preg_match('/^\/sim(?:\:([^\:\?]+)(?:\:([^\:\?]+)(?:\:([^\:\?]+)|)|)|).*$/', $referer_url, $match)){

				// abort tracker
				$this->tracker_abort();

				// take param
				$match = $match + [null,null,0,0];

				// return simulated response
				return $this->response(200, (object)[
					'identify_process_status' => $match[2],
					]);
				}
			}


		// if IBR is available
		if($this->env_get('mp_product:param:identify:ibr')){

			// continue IBR process (this cannot lead into redirect)
			$this->mp_client_ibr(true);

			// add tracker data
			$this->env_set('tracker:callinfo:page', 'ibr/ajax-status/'.$this->us_get('mp:ibr:status'));

			// return result
			return $this->response(200, (object)[
				'identify_process_status' => $this->us_get('mp:ibr:status'),
				]);
			}

		// if IBR is not available, return 404
		return $this->response(404);
		}

	public function mp_hook_identify_confirm(){

		//  if identification is confirmed
		if($this->us_get('mp:identify:confirmed')){

			// continue
			return null;
			}

		// if we have a successful IBI Process
		if($this->us_get('mp:ibi:status') == 200){

			// if we have no mobileID
			if(!$this->us_get('mp:identify:mobileID')){

				// load error page
				return $this->load_page('ibi/error', 500);
				}

			// start or continue process
			$this->mp_client_ibi_tan_confirm();

			// if status is not successful finished
			if($this->us_get('mp:ibi:tan_status') != 204){

				// load ibi confirm tan page (which shows also errors)
				return $this->load_page('ibi/confirm_tan/operator_'.$this->env_get('mp_mobile:operatorID'), $this->us_get('mp:ibi:tan_status'));
				}
			}

		// if identification is confirmed now
		if($this->us_get('mp:identify:confirmed')){

			// continue
			return null;
			}

		// load error page
		return $this->load_page('ibi/error', 403);
		}


	/* Hook: Payment */
	public function mp_hook_payment_not_possible($type){

		// TODO: Die Fehlerseiten könnten spezieller auf die Fälle eingehen

		// if operatorID is given
		if($this->env_get('mp_mobile:operatorID')){

			// check allowed countryID
			if($this->env_get('mp_mobile:countryID') != $this->env_get('mp_product:countryID')){

				// return error page
				return $this->load_page($type.'/error', 406);
				}

			// check if disabled
			if(in_array($this->env_get('mp_mobile:operatorID'), $this->env_get('mp_product:param:disable_operator') ?: [])){

				// run payment redirect switch (operator is already known, so no payment process is needed)
				$page_handle = $this->mp_hook_payment_redirect_switch();
				if($page_handle !== null) return $page_handle;

				// return error page
				return $this->load_page($type.'/error', 423);
				}
			}

		// else if operatorID is not given and no_operator option is not set and also no tan confirmation is configured
		elseif(!$this->env_get('mp_product:param:no_operator_payment_allowed') and !$this->mp_has_page('own_tan') and !$this->mp_has_page('operator_tan')){

			// return error page
			return $this->load_page($type.'/error', 500);
			}

		// check if blacklist level is too high
		if($this->env_get('mp_mobile:blacklistlvl') >= 2){

			// return error page
			return $this->load_page($type.'/error', 405);
			}

		// check if one abo already exists
		if($type == 'abo' and $this->env_get('mp_mobile:abo_list')){

			// define
			$only_unpaid_found = false;
			$one_per_interval = in_array($this->env_get('mp_mobile:operatorID'), $this->env_get('mp_product:param:one_abo_per_interval_operator') ?: []);

			// run abolist
			foreach($this->env_get('mp_mobile:abo_list') as $abo){

				// if abo is confirmed
				if($abo->confirmed){

					// if not terminated
					if(!$abo->terminated){

						// if paid
						if($abo->paid){

							// define next charge time as unlock time and return 423
							$this->us_set('mp:abo:lockstatus', 423);
							$this->us_set('mp:abo:unlocktime', $abo->next_chargeTime);

							// return error page
							return $this->load_page($type.'/locked', 423);
							}

						// or save we found an unpaid abo here
						$only_unpaid_found = true;
						}

					// or (for one per interval option) if abo not ended
					elseif($one_per_interval and !$abo->ended){

						// define unlocktime and return 406
						$this->us_set('mp:abo:lockstatus', 406);
						$this->us_set('mp:abo:unlocktime', h::date($abo->endTime, '+1 sec', 'Y-m-d H:i:s'));

						// return error page
						return $this->load_page($type.'/locked', 406);
						}
					}

				}

			// if we have unpaid abos, return 402
			if($only_unpaid_found){

				// define status
				$this->us_set('mp:abo:lockstatus', 402);

				// return error page
				return $this->load_page($type.'/locked', 402);
				}
			}

		// check for special locks
		if($this->env_get('payment:limit')){

			// load payment limit rule
			$rule = json_decode($this->env_get('payment:limit'), true);

			// if rule is usable
			if($rule and is_array($rule)){

				// define operator
				$operatorID = $this->env_get('mp_mobile:operatorID') ?: 0;

				// load limit for operatorID or take default
				$limit_by = $rule[$operatorID] ?? $rule[0] ?? false;

				// if limit is given
				if($limit_by and h::is($limit_by, '~1,9999999/i')){

					// load daily stat counter for event type
					$res = livestat::get_dh_counter([
						'group'		=> 'traffic_event_domain_'.$this->env_get('nexus:domainID'),
						'name'		=> $type,
						'datetime'	=> 'today',
						]);

					// if result is accessable and limit is reached
					if($res->status == 200 and isset($res->data->sum->{$type}) and $res->data->sum->{$type} >= $limit_by){

						// return error page
						return $this->load_page($type.'/error', 429);
						}
					}
				}
			}

		// no lock
		return null;
		}

	public function mp_hook_payment($payment_params, $options = []){

		// define paymenttype
		$type = $this->env_get('payment:type');

		// if type is not supported
		if(!in_array($type, ['abo','otp','smspay','smsabo','free'])){

			// load error page
			return $this->load_page('error/500');
			}

		// for Abo or OTP payment
		if(in_array($type, ['abo','otp'])){

			// start/return identify process (and return page handle)
			$page_handle = $this->mp_hook_identify([
				'preidentify' 		=> false,
				'restart_identify'	=> h::cG('reidentify'),
				]);
			if($page_handle !== null) return $page_handle;

			// NetM specific paramter from preset
			if($this->env_get('preset:payment:netm')){
				$payment_params['netm'] += $this->env_get('preset:payment:netm');
				}

			// NetM specific PurchaseParameters (usersession URLs)
			foreach(['PurchaseImprint','PurchaseTAC','PurchaseContact','PurchaseAboTool','PurchasePrivacy','PurchaseRevocation','PurchaseFAQ'] as $k){
				if(!empty($payment_params['netm'][$k]) and h::is($payment_params['netm'][$k], '~^\/')){
					$payment_params['netm'][$k] = $this->us_url($payment_params['netm'][$k]);
					}
				}

			// NetM specific PurchaseParameters (build URLs)
			foreach(['PurchaseBanner','PurchaseImage'] as $k){
				if(!empty($payment_params['netm'][$k]) and h::is($payment_params['netm'][$k], '~^\/')){
					$payment_params['netm'][$k] = $this->builder_url($payment_params['netm'][$k]);
					}
				}

			// NetM specific PurchaseParameters (variable placeholders)
			$replaceable = [];
			foreach(['PurchaseDescription','PurchaseAddress','PurchaseCCEmail','PurchaseCCHotline'] as $k){

				// check for placeholders
				if(!empty($payment_params['netm'][$k]) and preg_match_all('/(?:\{([a-zA-Z0-9\-\_\:]{1,255})\})/s', $payment_params['netm'][$k], $match)){

					// run each placeholder
					foreach($match[1] as $placeholder){

						// skip if placeholder already known
						if(isset($replaceable['{'.$placeholder.'}'])) continue;

						// DEPRECATED
						if($placeholder == 'contingent'){
							$replaceable['{contingent}'] = $this->env_get('mp_product:contingent');
							continue;
							}

						// set replacement for placeholder
						$val = $this->env_get($placeholder);
						if(!is_string($val) and !is_int($val) and !is_float($val)) $val = '';
						$replaceable['{'.$placeholder.'}'] = $val;
						}

					// replace placeholder
					$payment_params['netm'][$k] = h::replace_in_str($payment_params['netm'][$k], $replaceable);
					}
				}

			// NetM specific ConfirmationURL
			if(isset($payment_params['confirmationURL'])){
				$payment_params['confirmationURL'] = h::replace_in_str($payment_params['confirmationURL'], [
					'{payment_type}' => $type,
					'{operatorID}'	 => $this->env_get('mp_mobile:operatorID'),
					]);
				}

			// start payment
			$page_handle = ($type == 'abo') ? $this->mp_hook_abo($payment_params) : $this->mp_hook_otp($payment_params);
			if($page_handle !== null) return $page_handle;
			}

		// SMS Abo Payment
		if($type == 'smsabo'){

			// Starte Payment
			$page_handle = $this->mp_hook_smsabo($payment_params);
			if($page_handle !== null) return $page_handle;
			}

		// if payment type supports contingent and if contingent needed
		if(!in_array($type, ['abo','otp','smsabo']) and h::gX($payment_params, 'demand_contingent')){

			// decrement
			$decremented = $this->mp_change_contingent(['by'=>'-1']);

			// on error
			if(!$decremented){

				// log it (seems to be a concurrency problem)
				e::logtrigger('Could not decrement contingent '.$this->env_get('mp_mobile:product:contingent').' (persistID '.$this->us_get('persistID').')');
				}
			}

		// no further processing
		return null;
		}

	public function mp_hook_stateless_confirm_page($type, $fallback_operatorID = null){

		// if paymenttype is not matching
		if($this->env_get('payment:type') != $type){

			// abort tracker
			$this->tracker_abort();

			// log error
			e::logtrigger('Cannot load stateless confirmation page, because payment type is not '.$type.': '.h::encode_php($this->env_get('payment:type')));

			// return 404
			return $this->response(404);
			}

		// if no product or no mobileID and fallback operatorID defined
		if(!$this->env_get('mp_product') or (!$this->env_is('mp_mobile:mobileID') and empty($fallback_operatorID))){

			// load error page
			return $this->load_page($type.'/error', 500);
			}

		// define operatorID
		$operatorID = $this->env_get('mp_mobile:operatorID') ?: $fallback_operatorID;

		// define mdk or own confirmation page
		$page = ($this->mp_has_page('mdk_confirmation', $operatorID) ? $type.'/confirm_mdk/' : $type.'/confirm/').'operator_'.$operatorID;

		// load postprocess function
		$fnres = service::get_product_fn([
			'fn' => 'ob_postprocess_'.$type.'_confirmation_template'
			] + $this->mp_get_product_param());

		// if found, not found or not implemented
		if(in_array($fnres->status, [200, 501, 404])){

			// load page
			return $this->load_page($page, ($this->us_get('mp:'.$type.':status') ? $this->us_get('mp:'.$type.':status') : null), ($fnres->status == 200 ? $fnres->data->fn : null), $this->mp_has_page('mdk_confirmation', $operatorID) ? false : true);
			}

		// everything here is an error, so load error page
		return $this->load_page($type.'/error', 500);
		}

	public function mp_hook_payment_ajax($type){

		// if paymenttype is not matching or this call creates a new session
		if($this->env_get('payment:type') != $type or $this->us->isnew){

			// abort tracker
			$this->tracker_abort();

			// return 404
			return $this->response(404);
			}

		// if referer is set and matches own domain
		if(isset($_SERVER['HTTP_REFERER']) and strpos($_SERVER['HTTP_REFERER'], 'http://'.$_SERVER['SERVER_NAME']) === 0){

			// take referer url
			$referer_url = substr($_SERVER['HTTP_REFERER'], strlen('http://'.$_SERVER['SERVER_NAME']));

			// if it is an ajax request from a simulated page
			if(($referer_url == '/sim' or strpos($referer_url, '/sim:') === 0) and preg_match('/^\/sim(?:\:([^\:\?]+)(?:\:([^\:\?]+)(?:\:([^\:\?]+)|)|)|).*$/', $referer_url, $match)){

				// abort tracker
				$this->tracker_abort();

				// take param
				$match = $match + [null,null,0,0];

				// for abo or otp processes
				if(in_array($type, ['abo','otp'])){

					// return simulated response
					return $this->response(200, (object)[
						$type.'_process_status' => $match[2],
						]);
					}

				// return simulated response
				return $this->response(200, (object)[
					'product_access' => $match[2],
					]);
				}
			}

		// if IBR is available
		if($this->env_get('mp_product:param:identify:ibr')){

			// continue IBR process (this cannot lead into redirect)
			$this->mp_client_ibr(true);
			}

		// for abo or otp processes, if mobileID and associated ID is set
		if(in_array($type, ['abo','otp']) and $this->env_is('mp_mobile:mobileID') and $this->us_get('mp:'.$type.':'.$type.'ID')){

			// continue process, but prevent a redirect
			$this->mp_client_payment($type, 'ajaxstatus');

			// add tracker data
			$this->env_set('tracker:callinfo:page', $type.'/ajax-status/'.$this->us_get('mp:'.$type.':status'));

			// return result
			return $this->response(200, (object)[
				$type.'_process_status' => $this->us_get('mp:'.$type.':status'),
				]);
			}

		// for smsabo processes
		if($type == 'smsabo'){

			// if mobileID is set
			if($this->env_is('mp_mobile:mobileID')){

				// add tracker data
				$this->env_set('tracker:callinfo:page', 'smsabo/ajax-status/'.$this->env_get('mp_mobile:product_access') ? 200 : 102);

				// return result
				return $this->response(200, (object)[
					'product_access' => $this->env_get('mp_mobile:product_access')
					]);
				}

			// add tracker data
			$this->env_set('tracker:callinfo:page', 'smsabo/ajax-status/100');

			// return result
			return $this->response(200, (object)['product_access'=>false]);
			}

		// add tracker data
		$this->env_set('tracker:callinfo:page', $type.'/ajax-status/404');

		// if associated process is not available, return 404
		return $this->response(404);
		}

	public function mp_hook_payment_reconfirm($type){

		// check for operatorID
		if(!$this->env_get('mp_mobile:operatorID')){
			return $this->load_page($type.'/error', 500);
			}

		// save reconfirm value, if sent
		if(h::gR('reconfirm')){
			$this->us_set('mp:payment_reconfirmed', 1);
			}

		// check if operatorID is set for reconfirmation page
		if(in_array($this->env_get('mp_mobile:operatorID'), $this->env_get('mp_product:param:has_reconfirm_page') ?: []) and !$this->us_get('mp:payment_reconfirmed')){
			return $this->load_page($type.'/reconfirm/operator_'.$this->env_get('mp_mobile:operatorID'));
			}

		// no lock
		return null;
		}


	/* Hook: Payment Abo */
	public function mp_hook_abo($req = []){

		// if we have no mobileID here
		if(!$this->env_is('mp_mobile:mobileID')){

			// log error
			e::logtrigger('No mobileID given, when mp_hook_abo() is loaded ('.h::encode_php($this->us_get('persistID')).', mp = '.h::encode_php($this->us_get_like('mp:')).')');

			// load error page
			return $this->load_page('abo/error', 500);
			}

		// calculate demand for an abo
		$demand = (!$this->env_get('mp_mobile:product_access') or (h::gX($req, 'demand_contingent') and $this->env_get('mp_mobile:product_contingent') < 1));

		// define it we have to proceed a previous process
		$proceed = ($this->us_get('mp:abo:aboID') and !$this->us_get('mp:abo:finished'));

		// if no process is needed
		if(!$demand and !$proceed){

			// if a previous process was successful finished (or results in conflict with an already finished abo)
			if($this->us_get('mp:abo:finished') and in_array($this->us_get('mp:abo:status'), [200, 409])){

				// continue with no processing
				return null;
				}

			// check if identify confirmation is needed (and return page handle)
			$page_handle = $this->mp_hook_identify_confirm();
			if($page_handle !== null) return $page_handle;

			// check if payment reconfirmation is needed (and return page handle)
			$page_handle = $this->mp_hook_payment_reconfirm('abo');
			if($page_handle !== null) return $page_handle;

			// continue with no processing
			return null;
			}

		// check if a new process is needed
		if(!$proceed){

			// check if payment is not possible (and return page handle)
			$page_handle = $this->mp_hook_payment_not_possible('abo');
			if($page_handle !== null) return $page_handle;
			}

		// if no status is given, or process already finish and is resetable
		if(!$this->us_get('mp:abo:status') or ($this->us_get('mp:abo:finished') and $this->us_get('mp:abo:resetable'))){

			// (re)start process
			$this->mp_client_payment('abo', 'open', $req);

			// if the actual status is an error status
			if($this->us_get('mp:abo:status') >= 400){

				// on fail state (only for payment)
				if(in_array($this->us_get('mp:abo:status'), [401,402,403])){

					// run payment redirect switch
					$page_handle = $this->mp_hook_payment_redirect_switch();
					if($page_handle !== null) return $page_handle;
					}

				// load error page
				return $this->load_page('abo/error', $this->us_get('mp:abo:status'));
				}
			}

		// if abort payment parameter is set
		if(h::cR('abortpayment')){

			// set payment status to unauthorized
			$this->us_set([
				'mp:abo:status' 	=> 401,
				'mp:abo:finished'	=> true,
				'mp:abo:resetable'	=> true,
				]);

			// load error page
			return $this->load_page('abo/error', $this->us_get('mp:abo:status'));
			}

		// define step values
		$confirmed = h::cR('confirmabo');
		$ordered = ($confirmed or h::cR('orderabo'));

		// if payment confirmation is needed and no param for offer and confirm step is given
		if($this->us_get('mp:abo:status') == 100 and !$ordered){

			// if an offer page should be displayed
			if($this->mp_has_page('mdk_offer') or $this->mp_has_page('own_offer')){

				// load mdk or own offer page
				return $this->mp_has_page('mdk_offer')
					? $this->load_page('abo/offer_mdk/operator_'.$this->env_get('mp_mobile:operatorID'), $this->us_get('mp:abo:status'), null, false)
					: $this->load_page('abo/offer/operator_'.$this->env_get('mp_mobile:operatorID'), $this->us_get('mp:abo:status'));
				}
			}

		// if payment is confirmed, but it should be a tan confirmation
		if($confirmed and $this->mp_has_page('own_tan')){

			// confirmed is equal the valid existance of the tan param
			$confirmed = h::cR('tan', '~1,11/s');

			// if confirmed
			if($confirmed){

				// add tan to req param
				$req['tan'] = h::gR('tan');
				}
			}

		// if payment confirmation is needed and no param for confirm step is given
		if($this->us_get('mp:abo:status') == 100 and !$confirmed){

			// if an confirmation page should be displayed
			if($this->mp_has_page('own_confirmation') or $this->mp_has_page('own_tan')){

				// if own confirmation page
				if($this->mp_has_page('own_confirmation')){

					// return page (with template replace function)
					return $this->load_page('abo/confirm/operator_'.$this->env_get('mp_mobile:operatorID'), $this->us_get('mp:abo:status'), function($template){

						// define replacements for own confirmation page
						$replace = [
							'$PRODUCT_URL'	=> $this->us_same_url('?confirmabo=1'),
							'$ERROR_URL'	=> $this->us_url('/'),
							];

						// return replaced template
						return str_replace(array_keys($replace), array_values($replace), $template);
						});
					}

				// else for tan confirmation page
				else{

					// return page
					return $this->load_page('abo/confirm_tan/operator_'.$this->env_get('mp_mobile:operatorID'), $this->us_get('mp:abo:status'));
					}
				}
			}

		// continue process in submitted state (redirection can happen)
		$this->mp_client_payment('abo', 'submitted', $req);

		// if pending state
		if($this->us_get('mp:abo:status') == 102){

			// load sms or normal pending page
			return $this->mp_has_page('own_sms_pending')
				? $this->load_page('abo/pending_sms/operator_'.$this->env_get('mp_mobile:operatorID'), $this->us_get('mp:abo:status'))
				: $this->load_page('abo/pending', $this->us_get('mp:abo:status'));
			}

		// on success state
		if($this->us_get('mp:abo:status') == 200){

			// show own success page or continue with no processing
			return $this->mp_has_page('own_success')
				? $this->load_page('abo/success/operator_'.$this->env_get('mp_mobile:operatorID'), $this->us_get('mp:abo:status'))
				: null;
			}

		// on conflict state (an abo already exists)
		if($this->us_get('mp:abo:status') == 409){

			// continue with no processing
			return null;
			}

		// on fail state (only for payment)
		if(in_array($this->us_get('mp:abo:status'), [401,402,403])){

			// run payment redirect switch
			$page_handle = $this->mp_hook_payment_redirect_switch();
			if($page_handle !== null) return $page_handle;
			}

		// everything here means error, so load error page
		return $this->load_page('abo/error', $this->us_get('mp:abo:status'));
		}

	public function mp_hook_stateless_abo_confirm_page($fallback_operatorID = null){

		// load confirmation page with payment type abo
		return $this->mp_hook_stateless_confirm_page('abo', $fallback_operatorID);
		}

	public function mp_hook_terminate_abo($options = []){

		// if we have no mobileID here
		if(!$this->env_is('mp_mobile:mobileID')){

			// log error
			e::logtrigger('No mobileID given, when mp_hook_terminate_abo() is loaded ('.h::encode_php($this->us_get('persistID')).', mp = '.h::encode_php($this->us_get_like('mp:')).')');

			// load error page
			return $this->load_page('abo/error', 500);
			}

		// define some values
		$active_tID = 0;
		$force_env_reload = false;

		// if a termination is requested
		if(h::cR('terminate_abo', '~1,4294967295/i')){

			// take aboID
			$tID = h::gR('terminate_abo');

			// if there is already an terminate status
			if($this->us_get('mp:abo_term:status:'.$tID)){

				// define aboID as active termination
				$active_tID = $tID;
				}

			// if not
			else{

				// run through abo list
				foreach($this->env_get('mp_mobile:abo_list') as $abo){

					// if requested aboID matches abo
					if($abo->aboID == $tID){

						// save abo in environment
						$this->env_set('mp_terminateable_abo', $abo);

						// if termination is not confirmed
						if(!h::cR('termination_confirmed')){

							// load confirmation page for termination
							return $this->load_page('abo_terminate/confirm');
							}

						// terminate abo
						$res = client::terminate_abo([
							'aboID'	=> $abo->aboID,
							]);

						// save status of termination
						$this->us_set('mp:abo_term:status:'.$tID, $res->status);

						// if we have reflectorID defined
						if(isset($res->data->reflectorID)){

							// save it to session
							$this->us_set('mp:abo_term:reflectorID', $res->data->reflectorID);
							$this->us_set('mp:abo_term:startStackID', $res->data->stackID);
							}

						// define aboID as active termination
						$active_tID = $tID;

						// define environment to be reloaded
						$force_env_reload = true;

						// break here
						break;
						}
					}
				}

			// if this process needs a redirect
			if($this->us_get('mp:abo_term:status:'.$active_tID) == 307){

				// check if reflectorID is missing
				if(!$this->us_get('mp:abo_term:reflectorID')){

					// log error
					e::logtrigger('Terminate Abo process failed, no reflectorID for redirect given: '.h::encode_php($this->us_get('mp:abo')));

					// set status and abort further processing
					return $this->us_set('mp:abo:status', 500);
					}

				// if this process has no stackID set for returning
				if(!$this->us_get('mp:abo_term:returnStackID')){

					// append it
					$res = reflector::stack([
						'reflectorID' 	=> $this->us_get('mp:abo_term:reflectorID'),
						'url'			=> $this->us_same_url('?rt='.time()),
						]);

					// on error, set status and abort further processing
					if($res->status != 201) return $this->us_set('mp:abo:status', 500);

					// save stackID
					$this->us_set('mp:abo_term:returnStackID', $res->data->stackID);
					}

				// get first stack to redirect
				$res = reflector::run_stack([
					'reflectorID'	=> $this->us_get('mp:abo_term:reflectorID'),
					'stackID'		=> $this->us_get('mp:abo_term:startStackID'),
					]);

				// on error, set status and abort further processing
				if($res->status != 200) return $this->us_set('mp:abo:status', 500);

				// make redirect
				header('HTTP/1.1 302 Found');
				header('Location:'.$res->data->url);

				// append track data
				$this->env_set('tracker:callinfo:page', 'abo/redirect/'.$this->us_get('mp:abo_term:startStackID'));

				// exit php processing here
				exit;
				}

			}

		// if an active termination is set and the silent option is not set
		if($active_tID and !h::gX($options, 'silent')){

			// if environment needs to be reloaded
			if($force_env_reload){

				// reload
				$this->mp_load_env(true);
				}

			// for each abo
			foreach($this->env_get('mp_mobile:abo_list') as $abo){

				// if abo matches active termination
				if($abo->aboID == $active_tID){

					// save abo in environment
					$this->env_set('mp_terminateable_abo', $abo);

					// if abo is already terminated
					if($abo->terminated){

						// load success page
						return $this->load_page('abo_terminate/success');
						}

					// it status of active termination is given and seems an error
					if($this->us_get('mp:abo_term:status:'.$active_tID) and $this->us_get('mp:abo_term:status:'.$active_tID) >= 400){

						// load error page
						return $this->load_page('abo_terminate/error', $this->us_get('mp:abo_term:status:'.$active_tID));
						}

					// load pending page
					return $this->load_page('abo_terminate/pending');
					}
				}
			}

		// continue with no processing
		return null;
		}

	public function mp_hook_abo_ajax(){

		// load ajax status with payment type abo
		return $this->mp_hook_payment_ajax('abo');
		}


	/* Hook: Payment OTP */
	public function mp_hook_otp($req){

		// if we have no mobileID here
		if(!$this->env_is('mp_mobile:mobileID')){

			// log error
			e::logtrigger('No mobileID given, when mp_hook_otp() is loaded ('.h::encode_php($this->us_get('persistID')).', mp = '.h::encode_php($this->us_get_like('mp:')).')');

			// load error page
			return $this->load_page('otp/error', 500);
			}

		// calculate demand for abo
		$demand = (!$this->env_get('mp_mobile:product_access') or (h::gX($req, 'demand_contingent') and $this->env_get('mp_mobile:product_contingent') < 1));

		// define it we have to proceed a previous process
		$proceed = ($this->us_get('mp:otp:otpID') and !$this->us_get('mp:otp:finished'));

		// if no process is needed
		if(!$demand and !$proceed){

			// if a previous process was successful finished
			if($this->us_get('mp:otp:finished') and $this->us_get('mp:otp:status') == 200){

				// continue with no processing
				return null;
				}

			// check if identify confirmation is needed (and return page handle)
			$page_handle = $this->mp_hook_identify_confirm();
			if($page_handle !== null) return $page_handle;

			/// check if payment reconfirmation is needed (and return page handle)
			$page_handle = $this->mp_hook_payment_reconfirm('otp');
			if($page_handle !== null) return $page_handle;

			// continue with no processing
			return null;
			}

		// check if a new process is needed
		if(!$proceed){

			// check if payment is not possible (and return page handle)
			$page_handle = $this->mp_hook_payment_not_possible('otp');
			if($page_handle !== null) return $page_handle;
			}

		// if no status is given, or process already finish and is resetable
		if(!$this->us_get('mp:otp:status') or ($this->us_get('mp:otp:finished') and $this->us_get('mp:otp:resetable'))){

			// (re)start process
			$this->mp_client_payment('otp', 'open', $req);

			// if the actual status is an error status
			if($this->us_get('mp:otp:status') >= 400){

				// on fail state (only for payment)
				if(in_array($this->us_get('mp:otp:status'), [401,402,403])){

					// run payment redirect switch
					$page_handle = $this->mp_hook_payment_redirect_switch();
					if($page_handle !== null) return $page_handle;
					}

				// load error page
				return $this->load_page('otp/error', $this->us_get('mp:otp:status'));
				}
			}

		// if abort payment parameter is set
		if(h::cR('abortpayment')){

			// set payment status to unauthorized
			$this->us_set([
				'mp:otp:status' 	=> 401,
				'mp:otp:finished'	=> true,
				'mp:otp:resetable'	=> true,
				]);

			// load error page
			return $this->load_page('otp/error', $this->us_get('mp:otp:status'));
			}

		// if payment confirmation is needed and no param for offer and confirm step is given
		if($this->us_get('mp:otp:status') == 100 and !h::cR('orderotp') and !h::cR('confirmotp')){

			// if an offer page should be displayed
			if($this->mp_has_page('own_offer')){

				// load own offer page
				return $this->load_page('otp/offer/operator_'.$this->env_get('mp_mobile:operatorID'), $this->us_get('mp:otp:status'));
				}
			}

		// if payment confirmation is needed and no param for confirm step is given
		if($this->us_get('mp:otp:status') == 100 and !h::cR('confirmotp')){

			// if an confirmation page should be displayed
			if($this->mp_has_page('mdk_confirmation') or $this->mp_has_page('own_confirmation')){

				// define post processing function for templates
				$postprocessing_fn = function($template){

					// define replacements for own confirmation page
					$replace = [
						'$PRODUCT_URL' 	=> $this->us_same_url('?confirmotp=1'),
						'$ERROR_URL' 	=> $this->us_url('/'),
						];

					// return replaced template
					return str_replace(array_keys($replace), array_values($replace), $template);
					};

				// load mdk or own confirmation page
				return $this->mp_has_page('mdk_confirmation')
					? $this->load_page('otp/confirm_mdk/operator_'.$this->env_get('mp_mobile:operatorID'), $this->us_get('mp:otp:status'), $postprocessing_fn, false)
					: $this->load_page('otp/confirm/operator_'.$this->env_get('mp_mobile:operatorID'), $this->us_get('mp:otp:status'), $postprocessing_fn);
				}
			}

		// continue process in submitted state (redirection can happen)
		$this->mp_client_payment('otp', 'submitted', $req);

		// if pending state
		if($this->us_get('mp:otp:status') == 102){

			// load sms or normal pending page
			return $this->mp_has_page('own_sms_pending')
				? $this->load_page('otp/pending_sms/operator_'.$this->env_get('mp_mobile:operatorID'), $this->us_get('mp:otp:status'))
				: $this->load_page('otp/pending', $this->us_get('mp:otp:status'));
			}

		// on success state
		if($this->us_get('mp:otp:status') == 200){

			// show own success page or continue with no processing
			return $this->mp_has_page('own_success')
				? $this->load_page('otp/success/operator_'.$this->env_get('mp_mobile:operatorID'), $this->us_get('mp:otp:status'))
				: null;
			}

		// on fail state (only for payment)
		if(in_array($this->us_get('mp:otp:status'), [401,402,403])){

			// run payment redirect switch
			$page_handle = $this->mp_hook_payment_redirect_switch();
			if($page_handle !== null) return $page_handle;
			}

		// everything here means error, so load error page
		return $this->load_page('otp/error', $this->us_get('mp:otp:status'));
		}

	public function mp_hook_stateless_otp_confirm_page($fallback_operatorID = null){

		// load confirmation page with payment type otp
		return $this->mp_hook_stateless_confirm_page('otp', $fallback_operatorID);
		}

	public function mp_hook_otp_ajax(){

		// load ajax status with payment type otp
		return $this->mp_hook_payment_ajax('otp');
		}


	/* Hook: Payment SMSAbo */
	public function mp_hook_smsabo($req){

		// if we have product access
		if($this->env_get('mp_mobile:product_access')){

			// but demand contigent and having 0
			if(h::gX($req, 'demand_contingent') and $this->env_get('mp_mobile:product_contingent') < 1){

				// return locked state
				return $this->load_page('abo/locked', 423);
				}

			// no more processing
			return null;
			}

		// return offer page
		return $this->load_page('abo/offer_sms/operator_'.($this->env_get('mp_mobile:operatorID') ?: 0));
		}

	public function mp_hook_smsabo_ajax(){

		// load ajax status with payment type abo
		return $this->mp_hook_payment_ajax('smsabo');
		}


	/* Hook: Payment Redirect Switch */
	public function mp_hook_payment_redirect_switch(){

		// if we have no operatorID
		if(!$this->env_get('mp_mobile:operatorID')){

			// first try to reload mobile environment
			$this->mp_load_env(true);
			}

		// define operatorID
		$operatorID = $this->env_get('mp_mobile:operatorID') ?: 0;

		// define redirect
		$redirect = $this->env_get('adtarget:prs_operatorID_'.$operatorID);

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

				// build url
				$redirect = '{REQUEST_SCHEME}://'.$adtarget->fqdn.($adtarget->hash ? '/'.$adtarget->hash : '').'?{CLICK_DATA}';

				// encode mobile user data (mud) as json
				$mud_json = json_encode((object)[
					'msisdn'		=> $this->env_get('mp_mobile:msisdn'),
					'operatorID'	=> $this->env_get('mp_mobile:operatorID'),
					'confirmTime'	=> $this->env_get('mp_mobile:confirmTime'),
					]);

				// encrypt data
				$mud_string = crypt::aes_encrypt($mud_json, $this->encmud_key, ['key_is'=>'plain']);

				// add data to special param, which allows target to load mobile user data
				$redirect .= '&encmud='.urlencode($mud_string);

				// define redirect
				$this->env_set('nexus:redirect', $redirect);
				$this->env_set('nexus:redirect_title', 'pp/'.$adtarget->pageID);

				// run environment redirect
				$this->env_redirect();
				}
			}

		// if redirect targets an url
		elseif(h::is($redirect, '~^(?:http|https|{REQUEST_SCHEME}):\/\/.*$')){

			// define redirect
			$this->env_set('nexus:redirect', $redirect);
			$this->env_set('nexus:redirect_title', 'pp/custom');

			// run environment redirect
			$this->env_redirect();
			}

		// do nothing
		return null;
		}


	/* Hook: Simulate */
	public function mp_simulate_page($page, $var1, &$postFn, &$use_wrapper = null){

		// simulate mobileID
		if(h::cR('force_mobileID', '~1,4294967295/i')){
			$this->us_set('mp:identify:mobileID', h::gR('forceMobileID'));
			$this->us_set('mp:identify:confirmed', true);
			$this->mp_load_env(true);
			}

		// simulate page details
		if($page == 'ibr/error'){
			$this->us_set('mp:ibr:status', $var1);
			}

		if(strpos($page, '_mdk') !== false){
			$use_wrapper = false;
			}

		if(substr($page, 0, 11) == 'abo/confirm' or substr($page, 0, 11) == 'otp/confirm'){

			$postFn = function($template){
				return h::replace_in_str($template, [
					'$PRODUCT_URL'	=> $this->us_url('/sim'),
					'$ERROR_URL'	=> $this->us_url('/sim'),
					'$SCRIPT_URL'	=> $this->builder_url('/debug/ppbutton.html'),
					]);
				};
			}

		if($page == 'abo/error'){
			$this->us_set('mp:abo:status', $var1);
			}

		if($page == 'abo/locked'){
			$this->us_set('mp:abo:lockstatus', $var1);
			$this->us_set('mp:abo:unlocktime', h::dtstr('+3 day'));
			}

		if(substr($page, 0, 14) == 'abo_terminate/'){
			$this->env_set('mp_terminateable_abo', (object)[
				'ID' 			=> 0,
				'aboID' 		=> 0,
				'productID' 	=> 0,
				'persistID'		=> 0,
				'createTime' 	=> h::dtstr('-18 day', 'Y-m-d H:i:s'),
				'confirmTime' 	=> h::dtstr('-18 day', 'Y-m-d H:i:s'),
				'terminateTime' => h::dtstr('now', 'Y-m-d H:i:s'),
				'endTime' 		=> h::dtstr('+2 day', 'Y-m-d').' 23:59:59',
				'confirmed' 	=> true,
				'terminated' 	=> true,
				'ended'			=> false,
				'paid'			=> true,
				]);
			}

		if($page == 'otp/error'){
			$this->us_set('mp:otp:status', $var1);
			}

		if(substr($page, 0, 7) == 'account'){
			if(!$this->env_get('mp_mobile') or !$this->env_get('mp_mobile:abo_list')){
				$this->env_reset_data('mp_mobile', [
					'ID'		=> 0,
					'mobileID'	=> 0,
					'msisdn'	=> 49160000000,
					'natnumber' => "0160000000",
					'operatorID'=> 0,
					'product_access' => true,
					'product_contingent' => 5,
					'abo_list'	=> [
						(object)[
							'ID' 			=> 0,
							'aboID'			=> 0,
							'productID' 	=> 0,
							'mobileID'		=> 0,
							'persistID'		=> 0,
							'createTime'	=> h::dtstr('-14 day', 'Y-m-d H:i:s'),
							'confirmTime'	=> h::dtstr('-14 day', 'Y-m-d H:i:s'),
							'terminateTime'	=> '0000-00-00 00:00:00',
							'endTime'		=> '0000-00-00 00:00:00',
							'confirmed'		=> true,
							'terminated'	=> false,
							'ended'			=> false,
							'paid'			=> true,
							],
						(object)[
							'ID' 			=> 0,
							'aboID'			=> 0,
							'productID'		=> 0,
							'mobileID'		=> 0,
							'persistID'		=> 0,
							'createTime'	=> h::dtstr('-10 day', 'Y-m-d H:i:s'),
							'confirmTime'	=> h::dtstr('-10 day', 'Y-m-d H:i:s'),
							'terminateTime'	=> '0000-00-00 00:00:00',
							'endTime'		=> '0000-00-00 00:00:00',
							'confirmed'		=> true,
							'terminated'	=> false,
							'ended'			=> false,
							'paid'			=> false,
							],
						(object)[
							'ID' 			=> 0,
							'aboID'			=> 0,
							'productID'		=> 0,
							'mobileID'		=> 0,
							'persistID'		=> 0,
							'createTime'	=> h::dtstr('-5 day', 'Y-m-d H:i:s'),
							'confirmTime'	=> h::dtstr('-5 day', 'Y-m-d H:i:s'),
							'terminateTime'	=> '0000-00-00 00:00:00',
							'endTime'		=> '0000-00-00 00:00:00',
							'confirmed'		=> true,
							'terminated'	=> false,
							'ended'			=> false,
							'paid'			=> true,
							],
						(object)[
							'ID' 			=> 0,
							'aboID'			=> 0,
							'productID'		=> 0,
							'mobileID'		=> 0,
							'persistID'		=> 0,
							'createTime'	=> h::dtstr('-21 day', 'Y-m-d H:i:s'),
							'confirmTime'	=> h::dtstr('-21 day', 'Y-m-d H:i:s'),
							'terminateTime'	=> h::dtstr('now', 'Y-m-d H:i:s'),
							'endTime'		=> h::dtstr('+5 day', 'Y-m-d').' 23:59:59',
							'confirmed'		=> true,
							'terminated'	=> true,
							'ended'			=> false,
							'paid'			=> true,
							],
						(object)[
							'ID' 			=> 0,
							'aboID'			=> 0,
							'productID'		=> 0,
							'mobileID'		=> 0,
							'persistID'		=> 0,
							'createTime'	=> h::dtstr('-28 day', 'Y-m-d H:i:s'),
							'confirmTime'	=> h::dtstr('-28 day', 'Y-m-d H:i:s'),
							'terminateTime'	=> h::dtstr('-8 day', 'Y-m-d H:i:s'),
							'endTime'		=> h::dtstr('-7 day', 'Y-m-d').' 23:59:59',
							'confirmed'		=> true,
							'terminated'	=> true,
							'ended'			=> true,
							'paid'			=> true,
							],
						],
					'otp_list'=>[],
					]);
				}
			}

		}

	}
