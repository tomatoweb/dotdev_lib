<?php
/*****
 * Version 1.1.2019-04-15
**/
namespace dotdev\apk;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \dotdev\cronjob;
use \dotdev\nexus\base as nexus_base;
use \dotdev\nexus\catlop as nexus_catlop;
use \dotdev\nexus\service as nexus_service;
use \dotdev\nexus\domain as nexus_domain;
use \dotdev\nexus\publisher as nexus_publisher;
use \dotdev\nexus\levelconfig as nexus_levelconfig;
use \dotdev\nexus\adjust as nexus_adjust;
use \dotdev\persist;
use \dotdev\mobile;
use \dotdev\mobile\abo;
use \dotdev\mobile\otp;
use \dotdev\apk\generator as apk_generator;
use \dotdev\traffic\session as traffic_session;
use \dotdev\traffic\event as traffic_event;

class share {
	use \tools\libcom_trait,
		\tools\redis_trait;

	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_nexus');
		}


	/* APK configuration */
	public static function get_project($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'	=> '~^[a-z0-9_]{1,32}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'pageID'	=> '~0,65535/i',
			'persistID'	=> '~0,18446744073709551615/i',
			'build'		=> '~0,16777215/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// load apk
		$res = nexus_catlop::get_apk([
			'project'	=> $mand['project'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take apk
		$apk = $res->data;

		// take config
		$config = $apk->config;

		// for dynamic values
		foreach(['pageID','persistID','setting'] as $key){

			// if key does not exist, skip
			if(!isset($config[$key])) continue;

			// translate option
			$config[$key] = self::_translate_config_option($key, $config[$key], $config, $opt);
			}

		// define if new build is available
		$newer_build_available = isset($opt['build']) ? $apk->apk_build > $opt['build'] : false;
		$build_is_same = isset($opt['build']) ? $apk->apk_build == $opt['build'] : false;

		// define if apk could be updated and an update is available
		$updateable = (h::gX($config, 'setting:updateable') and $newer_build_available) ? true : false;

		// define if apk could update config (for now only if no update is available and build is the same)
		$config_updateable = $updateable ? false : $build_is_same;


		// define adjust app
		$adjust_app = null;

		// if adjust_app is defined in apk
		if($apk->adjust_app){

			// try to load adjust app
			$res = nexus_adjust::get_adjust_app([
				'adjust_app'	=> $apk->adjust_app,
				]);

			// on unexpected error
			if(!in_array($res->status, [200,404])) return self::response(570, $res);

			// if event was not found, return Precondition Failed
			if($res->status == 404) return self::response(412);

			// take adjust_app
			$adjust_app = $res->data;
			}

		// other request param invalid
		return self::response(200, (object)[
			'project'			=> $apk->project,
			'status'			=> $apk->status,
			'name'				=> $apk->name,
			'date'				=> h::dtstr($apk->apk_date, 'Y-m-d'),
			'version'			=> $apk->apk_version,
			'build'				=> $apk->apk_build,
			'size'				=> $apk->apk_size,
			'updateable'		=> $updateable,
			'config_updateable'	=> $config_updateable,
			'adjust_app'		=> $adjust_app ? $adjust_app->adjust_app : null,
			'adjust_secret'		=> $adjust_app ? $adjust_app->secret : null,
			]);
		}

	public static function get_config($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'	=> '~^[a-z0-9_]{1,32}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'pageID'	=> '~0,65535/i',
			'persistID'	=> '~0,18446744073709551615/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// define cache key
		$cache_key = 'apk:rendered_config_by_project:'.$mand['project'];

		// init redis
		$redis = self::redis();

		// if redis accessable and config exists
		if($redis and $redis->exists($cache_key)){

			// load config
			$config = $redis->get($cache_key);
			}

		// else render config
		else{

			// load apk
			$res = nexus_catlop::get_apk([
				'project'	=> $mand['project'],
				]);

			// on error
			if($res->status != 200) return $res;

			// take apk
			$apk = $res->data;

			// define config
			$config = [
				'project'	=> [
					'project'	=> $apk->project,
					'status'	=> $apk->status,
					'name'		=> $apk->name,
					'date'		=> h::dtstr($apk->apk_date, 'Y-m-d'),
					'version'	=> $apk->apk_version,
					'build'		=> $apk->apk_build,
					'size'		=> $apk->apk_size,
					],
				] + $apk->config;


			// cache found countries
			$found_country_list = [];

			// cache found bragi poolIDs
			$found_bragi_poolID_list = [];


			// possible key for translation
			$translate_keys = [
				'service_url'	=> 'mtservice_url',
				'service_key'	=> 'sslcert_publickey',
				];

			// for basic keys
			foreach($translate_keys as $key => $type){

				// skip if key not exists
				if(!isset($config[$key])) continue;

				// translate option
				$config[$key] = self::_translate_config_option($type, $config[$key], $config, $opt);
				}

			// if server object exists
			if(isset($config['server']) and is_array($config['server'])){

				// for basic keys
				foreach($translate_keys as $key => $type){

					// skip if key not exists
					if(!isset($config['server'][$key])) continue;

					// translate option
					$config['server'][$key] = self::_translate_config_option($type, $config['server'][$key], $config, $opt);
					}

				// check for profile_poolID
				if(h::cX($config['server'], 'profile_poolID', '~1,65535/i') and !in_array($config['server']['profile_poolID'], $found_bragi_poolID_list)) $found_bragi_poolID_list[] = $config['server']['profile_poolID'];
				if(h::cX($config['server'], 'profile_men_poolID', '~1,65535/i') and !in_array($config['server']['profile_men_poolID'], $found_bragi_poolID_list)) $found_bragi_poolID_list[] = $config['server']['profile_men_poolID'];

				// if server country object exists
				if(isset($config['server']['country']) and is_array($config['server']['country'])){

					// for each countryID
					foreach($config['server']['country'] as $country_code => $set){

						// skip if set is not an array
						if(!is_array($set)) continue;

						// save country
						if(!in_array($country_code, $found_country_list)) $found_country_list[] = $country_code;

						// check for profile_poolID
						if(h::cX($set, 'profile_poolID', '~1,65535/i') and !in_array($set['profile_poolID'], $found_bragi_poolID_list)) $found_bragi_poolID_list[] = $set['profile_poolID'];
						if(h::cX($set, 'profile_men_poolID', '~1,65535/i') and !in_array($set['profile_men_poolID'], $found_bragi_poolID_list)) $found_bragi_poolID_list[] = $set['profile_men_poolID'];

						// for basic keys
						foreach($translate_keys as $key => $type){

							// skip if key not exists
							if(!isset($set[$key])) continue;

							// translate option
							$config['server']['country'][$country_code][$key] = self::_translate_config_option($type, $set[$key], $config, $opt);
							}
						}
					}
				}

			// if product object exists
			if(isset($config['product']) and is_array($config['product'])){

				// possible key for translation
				$translate_keys = [
					'service_url'	=> 'mtservice_url',
					'service_key'	=> 'sslcert_publickey',
					'smspay'		=> 'smspay',
					'paypal'		=> 'otp',
					'paysafecard'	=> 'otp',
					'sofort'		=> 'otp',
					'webflow'		=> 'abo',
					'dcbflow'		=> 'abo',
					'pageID'		=> 'pageID', // DEPRECATED
					];

				// for each countryID-operatorID set
				foreach($config['product'] as $cID_oID => $set){

					// define countryID and operatorID
					list($countryID, $operatorID) = explode('-', $cID_oID);

					// skip if set is not an array
					if(!is_array($set)) continue;

					// save country
					if(!in_array($countryID, $found_country_list)) $found_country_list[] = $countryID;

					// for basic keys
					foreach($translate_keys as $key => $type){

						// skip if key not exists
						if(!isset($set[$key])) continue;

						// translate option
						$config['product'][$cID_oID][$key] = self::_translate_config_option($type, $set[$key], $config, $opt);
						}

					// check for profile_poolID
					if(h::cX($set, 'profile_poolID', '~1,65535/i') and !in_array($set['profile_poolID'], $found_bragi_poolID_list)) $found_bragi_poolID_list[] = $set['profile_poolID'];
					if(h::cX($set, 'profile_men_poolID', '~1,65535/i') and !in_array($set['profile_men_poolID'], $found_bragi_poolID_list)) $found_bragi_poolID_list[] = $set['profile_men_poolID'];

					// DEPRECATED save product, if productID and type exists
					if(h::cX($set, 'productID', '~1,65535/i') and h::cX($set, 'type', '~/s')){

						// translate option (which saves product in product_list)
						self::_translate_config_option($set['type'], $set['productID'], $config, $opt);
						}
					}
				}


			// insert related tables with found countries
			$config['country_table'] = self::_translate_config_option('country_table', $found_country_list, $config, $opt);
			$config['operator_table'] = self::_translate_config_option('operator_table', $config['country_table'], $config, $opt);
			$config['mccmnc_table'] = self::_translate_config_option('hni_table', $config['country_table'], $config, $opt);

			// insert bragi related list, if not empty
			if($found_bragi_poolID_list) $config['pool_list'] = $found_bragi_poolID_list;

			// if product object exists
			if(isset($config['product']) and is_array($config['product']) and isset($config['server'])){

				// for each countryID-operatorID set
				foreach($config['country_table'] as $country){

					// define server and product key
					$server_key = $country['code'];
					$product_key = $country['countryID'].'-0';

					// define base server setting
					$base_server = [];

					// for each base key
					foreach($config['server'] as $key => $val){

						// skip special country key
						if($key == 'country') continue;

						// take server setting
						$base_server[$key] = $val;
						}

					// if country setting does not exist in product, create it
					if(!isset($config['product'][$product_key]) or !is_array($config['product'][$product_key])) $config['product'][$product_key] = [];

					// prepend server config to product config (replacing same values there)
					$config['product'][$product_key] = $base_server + $config['product'][$product_key];

					// if country specific server setting exists
					if(isset($config['server']['country'][$server_key]) and is_array($config['server']['country'][$server_key])){

						// prepend country specific server config to product config (replacing same values there)
						$config['product'][$product_key] = $config['server']['country'][$server_key] + $config['product'][$product_key];
						}
					}
				}

			// if redis accessable
			if($redis){

				// cache entry
				$redis->set($cache_key, $config, ['ex'=>21600, 'nx']); // 6 hours
				}
			}

		// for dynamic values
		foreach(['pageID','persistID','setting'] as $key){

			// if key does not exist, skip
			if(!isset($config[$key])) continue;

			// translate option
			$config[$key] = self::_translate_config_option($key, $config[$key], $config, $opt);
			}


		// return result as object
		return self::response(200, (object) $config);
		}

	protected static function _translate_config_option($type, $val, &$config = [], $opt = []){

		// type: pageID translation
		if($type == 'pageID'){

			// MIGRATION: migrate special pageIDs to new pageIDs
			if(isset($opt['pageID']) and isset($config['pageID_migration']) and is_array($config['pageID_migration']) and isset($config['pageID_migration'][$opt['pageID']])){

				// take new pageID
				$opt['pageID'] = $config['pageID_migration'][$opt['pageID']];
				}

			// return result
			return isset($opt['pageID']) ? $opt['pageID'] : $val;
			}

		// type: pageID translation
		if($type == 'persistID'){

			// return result
			return isset($opt['persistID']) ? $opt['persistID'] : $val;
			}

		// type: MTService URL
		if($type == 'mtservice_url'){

			// if value is unparseable, return null
			if(!h::is($val, '~1,255/i')) return null;

			// load firm
			$res = nexus_base::get_firm([
				'firmID'	=> $val,
				]);

			// on error
			if($res->status == 200){

				// return result
				return 'http://'.$res->data->mtservice_fqdn;
				}

			// return null (entry not found or error)
			return null;
			}

		// type: SSLCert PublicKey
		if($type == 'sslcert_publickey'){

			// if value is unparseable, return null
			if(!h::is($val, '~1,255/i')) return null;

			// load sslcert
			$res = nexus_catlop::get_sslcert([
				'firmID'	=> $val,
				'default'	=> true,
				]);

			// on error
			if($res->status == 200){

				// return result
				return $res->data->public_key;
				}

			// return null (entry not found or error)
			return null;
			}

		// type: country table
		if($type == 'country_table'){

			// if value is unparseable, return default
			if(!is_array($val)) return [];

			// define result
			$result = [];

			// for each countryID
			foreach($val as $country_identifier){

				// define country key
				$country_key = null;

				// if country identifier is a countryID
				if(h::is($country_identifier, '~1,255/i')) $country_key = 'countryID';

				// if country identifier is a country code
				elseif(h::is($country_identifier, '~^[a-zA-Z]{2}$')) $country_key = 'code';

				// if country identifier is invalid, skip entry
				if(!$country_key) continue;

				// load country table
				$res = nexus_base::get_country([
					$country_key	=> $country_identifier,
					]);

				// on error
				if($res->status != 200) continue;

				// take entry
				$entry = $res->data;

				// append entry
				$result[$entry->code] = [
					'countryID'	=> $entry->countryID,
					'code'		=> $entry->code,
					'prefix_nat'=> $entry->prefix_nat,
					'prefix_int'=> $entry->prefix_int,
					'mcc'		=> $entry->mcc,
					'currency'	=> $entry->currency,
					];
				}

			// return result
			return $result;
			}

		// type: operator table
		if($type == 'operator_table'){

			// if value is unparseable, return default
			if(!is_array($val)) return [];

			// define result
			$result = [];

			// for each countryID
			foreach($val as $country){

				// load operator table
				$res = nexus_base::get_operator([
					'countryID'	=> $country['countryID'],
					]);

				// on error
				if($res->status != 200) continue;

				// for each entry
				foreach($res->data as $entry){

					// skip ignored
					if($entry->ignore) continue;

					// append entry
					$result[$entry->operatorID] = [
						'name'			=> $entry->name,
						'color'			=> $entry->color,
						];
					}
				}

			// return result
			return $result;
			}

		// type: hni table
		if($type == 'hni_table'){

			// if value is unparseable, return default
			if(!is_array($val)) return [];

			// define result
			$result = [];

			// for each countryID
			foreach($val as $country){

				// load hni table
				$res = nexus_base::get_operator_hni([
					'countryID'	=> $country['countryID'],
					]);

				// on error
				if($res->status != 200) continue;

				// for each entry
				foreach($res->data as $entry){

					// append hni entry
					$result[$entry->hni] = [
						'code'		=> $entry->code,
						'countryID'	=> $entry->countryID,
						'operatorID'=> $entry->operatorID,
						];
					}
				}

			// return result
			return $result;
			}

		// type: product
		if(in_array($type, ['abo','smsabo','otp','smspay'])){

			// define single or list
			$as_list = is_array($val);

			// prepare as list
			$productID_list = $as_list ? $val : [$val];

			// create separate product list in config
			if(!isset($config['product_list'])) $config['product_list'] = [];

			// define result
			$result = [];

			// for each productID
			foreach($productID_list as $productID){

				// if value is unparseable, skip
				if(!h::is($productID, '~1,65535/i')) continue;

				// add product to separate product list
				if(!isset($config['product_list'][$type])) $config['product_list'][$type] = [];
				if(!in_array($productID, $config['product_list'][$type])) $config['product_list'][$type][] = $productID;

				// load product
				$res = nexus_service::get_product([
					'type'		=> $type,
					'productID'	=> $productID,
					]);

				// on success
				if($res->status == 200){

					// define basics of product
					$product = [
						'price'		=> $res->data->price,
						'currency'	=> $res->data->currency,
						];

					// for smspay products
					if($type == 'smspay'){
						$product += [
							'shortcode'	=> $res->data->param['shortnumber'] ?? null,
							'keyword'	=> $res->data->param['keyword'] ?? null,
							];
						}

					// for OTP products
					elseif($type == 'otp'){
						$product += [
							'productID'		=> $res->data->productID,
							'type'			=> $type,
							'expire'		=> $res->data->expire,
							'contingent'	=> $res->data->contingent,
							'description'	=> $res->data->param['Description'] ?? null,
							'hotline'		=> $res->data->param['hotline'] ?? null,
							];
						}

					// for abo like products
					elseif($type == 'abo' or $type == 'smsabo'){
						$product += [
							'productID'		=> $res->data->productID,
							'type'			=> $type,
							'interval'		=> $res->data->interval,
							'charges'		=> $res->data->charges,
							'description'	=> $res->data->param['Description'] ?? null,
							'extAboManagementUrl' => $res->data->param['extAboManagementUrl'] ?? null,
							'hotline'		=> $res->data->param['hotline'] ?? null,
							'mdk' 			=> $res->data->param['mdk'] ?? null,
							];
						}

					// append product to result
					$result[] = $product;
					}
				}

			// return result as list or single entry (or null, if not found)
			return $as_list ? $result : ($result[0] ?? null);
			}

		// type: setting
		if($type == 'setting' and is_array($val)){

			// define levelconfig keys and data to collect/replace
			$lc_keys = [];
			$lc_data = [];

			// for each setting key
			foreach($config['setting'] as $setting_key => $setting_val){

				// skip each but not empty strings
				if(!is_string($setting_val) or empty($setting_val)) continue;

				// skip if not a levelconfig key
				if(!preg_match('/^([a-zA-Z0-9\_]{1,40}\:[a-zA-Z0-9\_]{1,80})\|(json|string|int|float)\|(.*)$/', $setting_val, $match)) continue;

				// add to collection
				$lc_keys[$match[1]] = [$setting_key, $match[2], $match[3]];
				}

			// if pageID and setting given
			if(!empty($config['pageID'])){

				// load levelconfig of adtarget
				$res = nexus_levelconfig::get_levelconfig([
					'level'		=> 'user-inherited',
					'pageID'	=> $config['pageID'],
					'keys'		=> array_keys($lc_keys),
					]);

				// on error
				if(!in_array($res->status, [200,404])) return self::response(570, $res);

				// if levelconfig found
				if($res->status == 200){

					// take levelconfig data
					$lc_data = $res->data;
					}
				}

			// for each collected levelconfig key
			foreach($lc_keys as $lc_key => $set){

				// define type and default
				list($setting_key, $type, $default) = $set;

				// define data
				$data = $lc_data[$lc_key] ?? $default;

				// convert format
				if($type == 'json') $data = json_decode($data, true);
				elseif($type == 'int') $data = (int) $data;
				elseif($type == 'float') $data = (int) $data;
				else $data = (string) $data;

				// define default
				$val[$setting_key] = $data;
				}

			// return result
			return $val;
			}

		// return unchanged value
		return $val;
		}


	/* APK download & update */
	public static function prepare_download($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'	=> '~^[a-z0-9_]{1,32}$',
			'pageID'	=> '~0,65535/i',
			'persistID'	=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'type'		=> '~^(?:download|update)$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'type'		=> 'update',
			];

		// load apk
		$res = nexus_catlop::get_apk([
			'project'	=> $mand['project'],
			]);

		// on error
		if($res->status == 404) return self::response(412);
		if($res->status != 200) return $res;

		// take apk
		$apk = $res->data;

		// if project status is maintenance, download is disabled (locked)
		if($apk->status == 'maintenance') return self::response(423);

		// if project status is archive, download is disabled (gone)
		if($apk->status == 'archive') return self::response(410);


		// fallback: if pageID is not given
		if(!$mand['pageID']){

			// load session
			$res = traffic_session::get_session([
				'persistID'	=> $mand['persistID'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if not found
			if($res->status == 200){

				// take pageID
				$mand['pageID'] = $res->data->pageID;
				}

			// if still no pageID given, return bad request
			if(!$mand['pageID']) return self::response(400, ['pageID']);
			}


		// define pageID (maybe replaced through migration table)
		$pageID = h::gX($apk->config, 'pageID_migration:'.$mand['pageID']) ?: $mand['pageID'];


		// load levelconfig of adtarget
		$res = nexus_levelconfig::get_levelconfig([
			'level'		=> 'user-inherited',
			'pageID'	=> $pageID,
			'keys'		=> ['domain:apkres'],
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take entry
		$apkres_server_url = $res->data['domain:apkres'] ?? null;

		// if fqdn not set, return precondition failed
		if(!$apkres_server_url) return self::response(412);


		// create preparation
		$res = apk_generator::create_preparation([
			'project'	=> $mand['project'],
			'pageID'	=> $pageID,
			'persistID'	=> $mand['persistID'],
			'type'		=> $opt['type'],
			]);

		// on error
		if(!in_array($res->status, [201, 409])) return self::response(570, $res);

		// take download key
		$download_key = $res->data->download_key;


		// if no protocol detected
		if(!in_array(substr($apkres_server_url, 0, strpos($apkres_server_url, '://')), ['http','https'])){

			// prepend protocol to server url
			$apkres_server_url = $_SERVER['REQUEST_SCHEME'].'://'.$apkres_server_url;
			}

		// define redirection url
		$redirection_url = $apkres_server_url.'/download/'.$download_key.'/'.$apk->download_as;

		// if already create
		if($res->status == 409) return self::response(409, (object)['url'=>$redirection_url]);

		// return result
		return self::response(307, (object)['url'=>$redirection_url]);
		}

	public static function get_prepared_download($req = []){

		// mandatory
		$mand = h::eX($req, [
			'download_key'	=> '~^[a-z0-9]{40}$',
			'savepath'		=> '~^[a-zA-Z0-9\-\_]{1,60}\/(?:[a-zA-Z0-9\-\_\/]{1,240}\/|)$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load preparation
		$res = apk_generator::get_preparation([
			'download_key'	=> $mand['download_key'],
			]);

		// on error
		if($res->status == 404) return $res;
		elseif($res->status != 200) return self::response(570, $res);

		// take preparation
		$preparation = $res->data;


		// check if apk is generated
		if(empty($preparation->generated_apk)){

			// generate apk
			$res = apk_generator::generate_signed_apk([
				'project'		=> $preparation->project,
				'savepath'		=> $mand['savepath'],
				'pageID'		=> $preparation->pageID,
				'persistID'		=> $preparation->persistID,
				]);

			// on error
			if(in_array($res->status, [409, 410, 423])) return self::response($res->status);
			if($res->status != 200) return self::response(570, $res);

			// take file
			$preparation->generated_apk = $res->data->file;

			// update preparation
			$res = apk_generator::update_preparation([
				'download_key'	=> $mand['download_key'],
				'generated_apk'	=> $preparation->generated_apk,
				]);

			// on error
			if($res->status != 204) return self::response(570, $res);

			// add redisjob for
			$res = traffic_event::delayed_event_trigger([
				'type'			=> $preparation->type,
				'createTime'	=> h::dtstr('now'),
				'persistID'		=> $preparation->persistID,
				]);

			// on error
			if($res->status != 204) return self::response(500, 'APK creating delayed trigger event failed with: '.$res->status.' ('.h::encode_php(['type'=>$preparation->type, 'persistID'=>$preparation->persistID]).')');
			}

		// return result
		return self::response(200, (object)[
			'file'	=> $preparation->generated_apk,
			]);
		}


	/* APK helper */
	public static function get_servertime($req = []){

		// optional
		$opt = h::eX($req, [
			'format'	=> '~1,100/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define default
		$opt += [
			'format'	=> 'Y-m-d H:i:s',
			];

		// generate time
		$servertime = h::dtstr('now', $opt['format']);

		// return result
		return self::response(200, (object)['servertime' => $servertime]);
		}


	/* APK shared abstraction for session and event */
	public static function open_session($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'			=> '~^[a-z0-9_]{1,32}$',
			'pageID'			=> '~0,65535/i',
			'unique_hash'		=> '~^[a-z0-9]{40}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'persistID'			=> '~1,18446744073709551615/i',
			'imsi'				=> '~^[1-7]{1}[0-9]{5,15}$',
			'hni'				=> '~20000,79999/i',
			'countryID'			=> '~1,255/i',
			'operatorID'		=> '~1,65535/i',
			'device'			=> '~1,255/s',
			'new_unique_hash'	=> '~^[a-z0-9]{40}$',
			'publisher_switch'	=> '~^[a-z]{1,16}$',
			'publisher_referer'	=> '~/s',
			'publisher_request'	=> '~/c',
			'runtime_update'	=> '~/b',
			'build'				=> '~0,16777215/i',
			], $error, true);

		// special check
		if(isset($mand['pageID']) and !$mand['pageID'] and !isset($opt['persistID'])) $error[] = 'pageID';

		// on error
		if($error) return self::response(400, $error);


		// for each of these keys (in this order, allow only the first found and unset the other)
		foreach(['imsi','hni','operatorID','countryID'] as $key){

			// skip if not found
			if(!isset($opt[$key])) continue;

			// define unsetting every following found key ... or unset found key as it was a following key
			if(!empty($one_found)) unset($opt[$key]);
			$one_found = true;
			}

		// if publishers referer exists, but no request data
		if(isset($opt['publisher_referer']) and !isset($opt['publisher_request'])){

			// explode for GET-param
			list($url, $get_str) = explode('?', $opt['publisher_referer']) + [null, null];

			// if no GET-param found, check if string was plain GET-param (assume typical '/' is missing, but '=' exists, which is impossible for a domain or complete URL)
			if($get_str === null and strpos($url, '/') === false and strpos($url, '=') !== false){

				// switch param
				$get_str = $url;
				$url = null;
				}

			// overwrite referer with url only
			$opt['publisher_referer'] = $url;

			// if GET-Param was found
			if($get_str){

				// parse GET-Param
				parse_str($get_str, $opt['publisher_request']);

				// if request param seems invalid, unset it
				if(!h::is($opt['publisher_request'], '~/c')) unset($opt['publisher_request']);
				}
			}

		// define defaults
		$opt += [
			'persistID'			=> null,
			'imsi'				=> null,
			'hni'				=> null,
			'countryID'			=> 0,
			'operatorID'		=> 0,
			'device'			=> null,
			'publisher_switch'	=> null,
			'publisher_referer'	=> null,
			'publisher_request'	=> null,
			'build'				=> null,
			];


		// load apk
		$res = nexus_catlop::get_apk([
			'project'	=> $mand['project'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take apk
		$apk = $res->data;


		// define pageID translation
		$pageID_translation = $apk->config['pageID_migration'] ?? [];

		// translate pageID
		if($mand['pageID'] and isset($pageID_translation[$mand['pageID']])) $mand['pageID'] = $pageID_translation[$mand['pageID']];


		// define session conflic data
		$conflict = null;

		// if persistID exists
		if(isset($opt['persistID'])){

			// check persistID
			$res = self::_check_session($mand, $opt, $pageID_translation);

			// on error
			if(!in_array($res->status, [204, 409])) return $res;

			// if persistID is accepted
			if($res->status == 204){

				// if prevent event option is not set
				if(empty($opt['runtime_update'])){

					// add redisjob for delayed session_open creation (don't check $res for failures)
					$res = traffic_session::delayed_create_session_open([
						'persistID'		=> $opt['persistID'],
						'createTime'	=> h::dtstr($_SERVER['REQUEST_TIME']),
						'apkID'			=> $apk->apkID,
						'apk_build'		=> $opt['build'] ?? null,
						]);
					}

				// return result
				return self::response(200, (object)[
					'persistID'	=> $opt['persistID'],
					'pageID'	=> $mand['pageID']
					]);
				}

			// on conflict
			if($res->status == 409){

				// take data
				$conflict = $res->data;
				}
			}


		// load country and operator info data
		$res = self::get_country_operator_info([
			'imsi'			=> $opt['imsi'],
			'hni'			=> $opt['hni'],
			'countryID'		=> $opt['countryID'],
			'operatorID'	=> $opt['operatorID'],
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take data
		$opt['countryID'] = $res->data->countryID;
		$opt['operatorID'] = $res->data->operatorID;


		// if no pageID is given, but countryID is set
		if(!$mand['pageID'] and $opt['countryID']){

			// load apk server config for countryID
			$res = apk_share::get_config_server([
				'project'	=> $mand['project'],
				'countryID'	=> $opt['countryID'],
				'persistID'	=> $opt['persistID'] ?? null,
				]);

			// on success and defined fallback_pageID
			if($res->status == 200 and !empty($res->data->fallback_pageID)){

				// take as pageID
				$mand['pageID'] = $res->data->fallback_pageID;
				}
			}

		// if no pageID is given
		if(!$mand['pageID']){

			// return precondition failed
			return self::response(412);
			}


		// create a new persistID
		$res = persist::create([
			'createTime'	=> h::dtstr($_SERVER['REQUEST_TIME']),
			]);

		// on error
		if($res->status != 200) return self::response(570, $res);

		// take persist entry as new
		$new = $res->data;


		// load adtarget
		$res = nexus_domain::get_adtarget([
			'pageID'	=> $mand['pageID'],
			]);

		// on error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if not found, return precondition failed
		if($res->status == 404) return self::response(412);

		// take entry
		$adtarget = $res->data;


		// save creation info for faster results when reopening with same persistID
		$res = traffic_session::set_redis_info([
			'type'		=> 'apk:'.$mand['project'].':open_session',
			'persistID'	=> $new->persistID,
			'until'		=> '+30 min',
			'data'		=> (object)[
				'pageID'		=> $adtarget->pageID,
				'unique_hash'	=> $mand['unique_hash'],
				],
			]);

		// define session_create param
		$session_create = [
			'persistID'		=> $new->persistID,
			'createTime'	=> $new->createTime,
			'domainID'		=> $adtarget->domainID,
			'pageID'		=> $adtarget->pageID,
			'publisherID'	=> $adtarget->publisherID,

			'countryID'		=> $opt['countryID'],
			'operatorID'	=> $opt['operatorID'],

			'ipv4'			=> (strpos($_SERVER['REMOTE_ADDR'], ':') === false) ? $_SERVER['REMOTE_ADDR'] : null,
			'ipv6'			=> (strpos($_SERVER['REMOTE_ADDR'], ':') !== false) ? $_SERVER['REMOTE_ADDR'] : null,
			'useragent'		=> !empty($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,

			'unique_hash'	=> $mand['unique_hash'],
			'unique_device'	=> $opt['device'] ?? null,

			// options
			'ipv4_range_detection'	=> empty($opt['countryID']),
			'delayed_parsing'		=> true,
			];

		// define click_create param
		$click_create = [];

		// if publisher_switch is defined
		if($opt['publisher_switch']){

			// check for new publisher and click data
			$res = self::_check_publisher_and_click($mand, $opt);

			// on error
			if($res->status != 200) return $res;

			// take result
			$switch_update = $res->data;

			// if publisherID was found and is different in session
			if($switch_update->publisherID){

				// overwrite publisherID
				$session_create['publisherID'] = $switch_update->publisherID;

				// add additional param
				$session_create += [
					'publisher_uncover_key'	=> substr($switch_update->uncover_key, 0, 64),
					'publisher_uncover_name'=> substr($switch_update->uncover_name, 0, 120),
					'publisher_affiliate_key'=> $switch_update->affiliate_key,
					];
				}

			// if click should be inserted
			if($switch_update->click){

				// define click creation
				$click_create += [
					'createTime'=> $new->createTime,
					'request'	=> $switch_update->click,
					'referer'	=> $switch_update->click_referer,
					];
				}
			}


		// add redisjob for delayed session creation (don't check $res for failures)
		$res = traffic_session::delayed_create_session($session_create);

		// if click creation is defined
		if($click_create){

			// set job to create click
			$res = traffic_session::delayed_create_click([
				'persistID'	=> $new->persistID,
				] + $click_create);
			}

		// if session had a conflict before
		if($conflict){

			// add redisjob for delayed session creation (don't check $res for failures)
			$res = traffic_session::delayed_create_blocked_session([
				'persistID'		=> $conflict->persistID,
				'status'		=> $conflict->status,
				'createTime'	=> $new->createTime,
				'new_persistID'	=> $new->persistID,
				'apkID'			=> $apk->apkID,
				'apk_build'		=> $opt['build'] ?? null,
				'data'			=> $conflict->data ?? null,
				]);
			}

		// add redisjob for delayed session_open creation (don't check $res for failures)
		$res = traffic_session::delayed_create_session_open([
			'persistID'		=> $new->persistID,
			'createTime'	=> $new->createTime,
			'apkID'			=> $apk->apkID,
			'apk_build'		=> $opt['build'] ?? null,
			]);


		// return result
		return self::response(200, (object)[
			'persistID'		=> $new->persistID,
			'pageID'		=> $mand['pageID'],
			]);
		}

	protected static function _check_session(&$mand, &$opt, $pageID_translation){

		// try to load cached redis info (as a shortcut for checking persistID)
		$res = traffic_session::get_redis_info([
			'type'		=> 'apk:'.$mand['project'].':open_session',
			'persistID'	=> $opt['persistID'],
			]);

		// define cached_open (if a valid open_session object was found)
		$cached_open = ($res->status == 200 and isset($res->data->unique_hash) and isset($res->data->pageID)) ? $res->data : null;

		// if publisher_switch is not set (which needs a publisherID revalidation), but if cached_open is defined
		if(!$opt['publisher_switch'] and $cached_open){

			// if hash matches given hash
			if($cached_open->unique_hash != $mand['unique_hash']){

				// return conflict (with status 3: Session has different unique hash)
				return self::response(409, (object)['persistID'=>$opt['persistID'], 'status'=>3]);
				}

			// if no pageID given
			if(!empty($cached_open->pageID) and !$mand['pageID']){

				// take pageID
				$mand['pageID'] = $cached_open->pageID;
				}

			// if pageID does not match
			if(empty($cached_open->pageID) or $cached_open->pageID != $mand['pageID']){

				// return conflict (with status 2: Session has no or different pageID)
				return self::response(409, (object)['persistID'=>$opt['persistID'], 'status'=>2]);
				}

			// if new unique_hash exists
			if(isset($opt['new_unique_hash']) and $cached_open->unique_hash != $opt['new_unique_hash']){

				// delayed update unique
				$res = traffic_session::delayed_update_session_unique([
					'persistID'		=> $opt['persistID'],
					'hash'			=> $opt['new_unique_hash'],
					'device'		=> $opt['device'], // could be null
					]);

				// save creation info for faster results when reopening with same persistID
				$res = traffic_session::set_redis_info([
					'type'		=> 'apk:'.$mand['project'].':open_session',
					'persistID'	=> $opt['persistID'],
					'until'		=> '+30 min',
					'data'		=> (object)[
						'pageID'		=> $cached_open->pageID,
						'unique_hash'	=> $opt['new_unique_hash'],
						],
					]);
				}

			// return session accepted
			return self::response(204);
			}

		// load session
		$res = traffic_session::get_session([
			'persistID'	=> $opt['persistID'],
			'with_data'	=> 'unique',
			]);

		// if not found, but cached_open exists, then the session is already in creation
		if($res->status == 404 and $cached_open){

			// define tries to reload session (max 8 tries in max 8 seconds)
			$tries = 8;

			// while session is not found and retry is allowed
			while($res->status == 404 and $tries > 0){

				// reduce try
				$tries--;

				// wait a second
				sleep(1);

				// reload session
				$res = traffic_session::get_session([
					'persistID'	=> $opt['persistID'],
					'with_data'	=> 'unique',
					]);
				}
			}

		// on error
		if(!in_array($res->status, [200, 404])) return self::response(570, $res);

		// if not found
		if($res->status == 404){

			// if session should exist but still not loadable, return 429 (which indicates the client to wait and retry)
			if($cached_open) return self::response(429);

			// return conflict (with status 1: Session does not exist)
			return self::response(409, (object)['persistID'=>$opt['persistID'], 'status'=>1]);
			}

		// take entry
		$session = $res->data;

		// translate sessions pageID (this avoids mismatching pageIDs through translation)
		if($session->pageID and isset($pageID_translation[$session->pageID])) $session->pageID = $pageID_translation[$session->pageID];

		// if pageID is 0
		if($mand['pageID'] == 0){

			// take sessions pageID
			$mand['pageID'] = $session->pageID;
			}

		// if unique hash exists and is different than given hash
		if($session->hash and $session->hash != $mand['unique_hash']){

			// return conflict (with status 3: Session has different unique hash)
			return self::response(409, (object)['persistID'=>$opt['persistID'], 'status'=>3]);
			}

		// if session has no pageID or it does not match the requested
		if(!$session->pageID or $session->pageID != $mand['pageID']){

			// return conflict (with status 2: Session has no or different pageID)
			return self::response(409, (object)['persistID'=>$opt['persistID'], 'status'=>2]);
			}


		// if no unique hash exists
		if(!$session->hash){

			// add redisjob for delayed session_unique creation (don't check $res for failures)
			$res = traffic_session::delayed_create_session_unique([
				'persistID'		=> $session->persistID,
				'hash'			=> $mand['unique_hash'],
				'createTime'	=> h::dtstr($_SERVER['REQUEST_TIME']),
				'device'		=> $opt['device'], // could be null
				]);
			}

		// else if new unique_hash exists
		elseif(isset($opt['new_unique_hash']) and $session->hash != $opt['new_unique_hash']){

			// delayed update unique
			$res = traffic_session::delayed_update_session_unique([
				'persistID'		=> $session->persistID,
				'hash'			=> $opt['new_unique_hash'],
				'device'		=> $opt['device'], // could be null
				]);
			}

		// define delayed update or creation
		$session_update = [];
		$click_create = [];


		// if session has no mobileID yet
		if(!$session->mobileID){

			// load country and operator info data
			$res = self::get_country_operator_info([
				'imsi'			=> $opt['imsi'],
				'hni'			=> $opt['hni'],
				'countryID'		=> $opt['countryID'],
				'operatorID'	=> $opt['operatorID'],
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// take data
			$opt['countryID'] = $res->data->countryID;
			$opt['operatorID'] = $res->data->operatorID;

			// if sessions countryID/operatorID data is different
			if(($opt['operatorID'] and $session->operatorID != $opt['operatorID']) or ($opt['countryID'] and $session->countryID != $opt['countryID'])){

				// add data to update session
				$session_update += [
					'countryID'		=> $opt['countryID'],
					'operatorID'	=> $opt['operatorID'],
					];
				}
			}


		// if publisher_switch is defined
		if($opt['publisher_switch']){

			// check for new publisher and click data
			$res = self::_check_publisher_and_click($mand, $opt);

			// on error
			if($res->status != 200) return $res;

			// take result
			$switch_update = $res->data;

			// if publisherID was found and is different in session
			if($switch_update->publisherID and $switch_update->publisherID != $session->publisherID){

				// load publisher
				$res = nexus_publisher::get_publisher([
					'publisherID'	=> $session->publisherID,
					]);

				// define ownerID
				$session->publisher_ownerID = ($res->status == 200) ? $res->data->ownerID : 0;

				// also if sessions publisherID is not owned by switch publisher (avoid switching between same owner)
				if(!$session->publisher_ownerID or $switch_update->publisherID != $session->publisher_ownerID){

					// define session update
					$session_update += [
						'publisherID'			=> $switch_update->publisherID,
						'publisher_uncover_key'	=> substr($switch_update->uncover_key, 0, 64),
						'publisher_uncover_name'=> substr($switch_update->uncover_name, 0, 64),
						'publisher_affiliate_key'=> $switch_update->affiliate_key,
						];
					}
				}

			// if click should be inserted
			if($switch_update->click){

				// define click creation
				$click_create += [
					'createTime'=> h::dtstr($_SERVER['REQUEST_TIME']),
					'request'	=> $switch_update->click,
					'referer'	=> $switch_update->click_referer,
					];
				}
			}


		// if there is session data to update
		if($session_update){

			// set job to update session
			$res = traffic_session::delayed_update_session([
				'persistID'		=> $session->persistID,
				] + $session_update);
			}

		// if click creation is defined
		if($click_create){

			// set job to create click
			$res = traffic_session::delayed_create_click([
				'persistID'	=> $session->persistID,
				] + $click_create);
			}

		// save creation info for faster results when reopening with same persistID
		$res = traffic_session::set_redis_info([
			'type'		=> 'apk:'.$mand['project'].':open_session',
			'persistID'	=> $opt['persistID'],
			'until'		=> '+30 min',
			'data'		=> (object)[
				'pageID'		=> $mand['pageID'],
				'unique_hash'	=> $opt['new_unique_hash'] ?? $mand['unique_hash'],
				],
			]);

		// return session accepted
		return self::response(204);
		}

	protected static function _check_publisher_and_click(&$mand, &$opt){

		// define result
		$result = (object)[
			'publisherID'	=> null, // switched publisherID (e.g. OrgPub -> NewPub)
			'uncover_key'	=> null, // defines affiliate identifier of new publisher (e.g. ab12345 in NewPub)
			'uncover_name'	=> null, // affiliate name of new publiser (e.g. CompanyName in NewPub)
			'affiliate_key'	=> null, // additional identifier of affiliates affiliate of new publisher
			'click'			=> null, // defines new click (data)
			'click_referer'	=> null, // defines referer of new click
			];


		// load rendered apk config
		$res = self::get_config([
			'project'	=> $mand['project'],
			'pageID'	=> $mand['pageID'],
			'persistID'	=> $opt['persistID'],
			]);

		// on unexpected error
		if($res->status != 200) return self::response(570, $res);

		// define publisherID
		$result->publisherID = null;

		// check for valid publisherID
		if(h::cX($res, 'data:setting:publisher_switch:'.$opt['publisher_switch'], '~1,65535/i')){

			// take publisherID
			$result->publisherID = h::gX($res, 'data:setting:publisher_switch:'.$opt['publisher_switch']);
			}

		// if no publisherID is defined, abort here
		if(!$result->publisherID) return self::response(200, $result);

		// define needed keys
		$lc_keys = ['pub:click_rule','pub:click_param','pub:uncover_param','pub:uncover_name_param','pub:affiliate_param'];

		// load levelconfig of adtarget
		$res = nexus_levelconfig::get_levelconfig([
			'level'		=> 'pub',
			'publisherID'=> $result->publisherID,
			'keys'		=> $lc_keys,
			]);

		// on error
		if(!in_array($res->status, [200,404])) return self::response(570, $res);

		// take levelconfig data
		$lc_data = ($res->status == 200) ? $res->data : [];

		// check and set defaults
		foreach($lc_keys as $key){

			// decode json or set null
			$lc_data[$key] = h::is($lc_data[$key], '~^[\{\[].*[\}\]]$') ? json_decode($lc_data[$key]) : null;

			// for pub:click_rule
			if($key == 'pub:click_rule'){

				// set default when wrong configured
				if(!isset($lc_data[$key]) or !is_object($lc_data[$key])) $lc_data[$key] = (object)[];
				if(!isset($lc_data[$key]->referer)) $lc_data[$key]->referer = false;
				if(!isset($lc_data[$key]->pubdata)) $lc_data[$key]->pubdata = true;
				}

			// for other keys, if value is missing or is no array
			elseif(!isset($lc_data[$key]) or !is_array($lc_data[$key])){

				// define it as empty array
				$lc_data[$key] = [];
				}
			}

		// if publisher defines click param and request data exists
		if($lc_data['pub:click_param'] and !empty($opt['publisher_request'])){

			// define last click
			$last_click = null;

			// if persistID is defined
			if($opt['persistID']){

				// check click data
				$res = traffic_session::get_click([
					'persistID'		=> $opt['persistID'],
					'last_only'		=> true,
					'parse_data'	=> true,
					]);

				// on unexpected error
				if(!in_array($res->status, [200,404])) return self::response(570, $res);

				// take last click
				$last_click = ($res->status == 200) ? $res->data : null;
				}

			// for each check param
			foreach($lc_data['pub:click_param'] as $key){

				// skip key if is not given in request data
				if(!h::cX($opt['publisher_request'], $key)) continue;

				// take key value
				$key_value = h::gX($opt['publisher_request'], $key);

				// skip if key is the same as in last click request
				if($last_click and h::cX($last_click, 'request:'.$key, $key_value)) continue;

				// define click create data
				$result->click = $opt['publisher_request'];
				$result->click_referer = $opt['publisher_referer'];

				// for each check param
				foreach($lc_data['pub:affiliate_param'] as $name_key){

					// skip key if is not given in request data
					if(!h::cX($opt['publisher_request'], $name_key)) continue;

					// take uncover_param
					$result->affiliate_key = h::gX($opt['publisher_request'], $name_key);

					// skip further checking
					break;
					}

				// skip further checking
				break;
				}
			}

		// if no click was detected, but rule says it has to
		if(!$result->click and $lc_data['pub:click_rule']->pubdata){

			// unset publisherID
			$result->publisherID = null;
			}

		// if new click is defined and uncover_param is possible
		if($result->click and $lc_data['pub:uncover_param']){

			// for each check param
			foreach($lc_data['pub:uncover_param'] as $key){

				// skip key if is not given in request data
				if(!h::cX($opt['publisher_request'], $key)) continue;

				// take uncover_param
				$result->uncover_key = h::gX($opt['publisher_request'], $key);

				// for each check param
				foreach($lc_data['pub:uncover_name_param'] as $name_key){

					// skip key if is not given in request data
					if(!h::cX($opt['publisher_request'], $name_key)) continue;

					// take uncover_param
					$result->uncover_name = preg_replace('/[^a-zA-Z0-9\-\_]/', '_', h::gX($opt['publisher_request'], $name_key));

					// skip further checking
					break;
					}

				// skip further checking
				break;
				}
			}

		// DIRTY HACK: for adjust's stupid "tracker_token" and "tracker_name" behaviour
		if($opt['publisher_switch'] == 'adjust'){

			// define stop pos to detect real tracker_name
			$stop_pos = strpos($result->uncover_name, '__');

			// reduce uncover_name to real tracker_name
			$adjust_uncover_key = $stop_pos ? substr($result->uncover_name, 0, $stop_pos) : $result->uncover_name;

			// remove additional unwanted name suffixes
			if(preg_match('/^(.*)_([A-Z]{2})$/', $adjust_uncover_key, $match)) $adjust_uncover_key = $match[1];

			// specific renaming for consistence
			$renaming = [
				'Motive_Interactive'	=> 'Motive_Interactive_Inc',
				'Curate'				=> 'Curate_Mobile_Ltd',
				];

			// do renaming
			foreach($renaming as $from => $to){
				if($adjust_uncover_key != $from) continue;
				$adjust_uncover_key = $to;
				break;
				}

			// set new useful uncover_key
			$result->uncover_key = preg_replace('/[^a-z0-9\_]/', '_', strtolower($adjust_uncover_key));

			// set new uncover_name
			$result->uncover_name = $adjust_uncover_key;
			}


		// return result
		return self::response(200, $result);
		}

	public static function trigger_event($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'persistID'		=> '~1,18446744073709551615/i',
			'type'			=> '~^(?:install|error)$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// add redisjob for
		$res = traffic_event::delayed_event_trigger([
			'type'			=> $mand['type'],
			'persistID'		=> $mand['persistID'],
			'createTime'	=> h::dtstr($_SERVER['REQUEST_TIME']),
			'redisjob_start'=> '+3 sec',
			]);

		// on error
		if($res->status != 204) return self::response(500, 'APK creating delayed trigger event failed with: '.$res->status.' ('.h::encode_php($mand).')');

		// return success
		return self::response(204);
		}


	/* shared helper functions */
	public static function get_mobile($req = []){

		// alternative
		$alt = h::eX($req, [
			'mobileID'	=> '~1,4294967295/i',
			'persistID'	=> '~1,18446744073709551615/i',
			'msisdn'	=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'imsi'		=> '~^[1-7]{1}[0-9]{5,15}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(!$alt) return self::response(400, 'Need mobileID, persistID, msisdn or imsi param');


		// load mobile
		foreach(['mobileID','persistID','msisdn','imsi'] as $search_key){

			// skip if key is not set
			if(!isset($alt[$search_key])) continue;

			// load mobile with given value
			$res = mobile::get_mobile([
				$search_key		=> $alt[$search_key],
				]);

			// if no mobile found
			if($res->status == 404) continue;

			// on error
			if($res->status != 200) return self::response(570, $res);

			// if found, return result
			return $res;
			}

		// if no mobile found, return not found
		return self::response(404);
		}

	public static function get_mobile_info($req = []){

		// mandatory
		$mand = h::eX($req, [
			'persistID'			=> '~1,18446744073709551615/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'imsi'				=> '~^[1-7]{1}[0-9]{5,15}$',
			'load_imsi_mobile'	=> '~/b',
			'countryID'			=> '~1,255/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// load mobile
		$res = self::get_mobile([
			'persistID'	=> $mand['persistID'] ?? null,
			'imsi'		=> (isset($opt['imsi']) and !empty($opt['load_imsi_mobile'])) ? $opt['imsi'] : null,
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return $res;

		// define mobile
		$mobile = ($res->status == 200) ? $res->data : null;


		// order 1: mobile
		if($mobile){

			// return result
			return self::response(200, (object)[
				'mobileID'	=> $mobile->mobileID,
				'countryID'	=> $mobile->countryID,
				'operatorID'=> $mobile->operatorID,
				]);
			}

		// order 2: imsi
		if(isset($opt['imsi'])){

			// load operator with hni
			$res = nexus_base::get_operator([
				'hni'	=> (int) substr((string) $opt['imsi'], 0, 5),
				]);

			// on unexpected error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if operator is found
			if($res->status == 200){

				// return result
				return self::response(200, (object)[
					'mobileID'		=> null,
					'countryID'		=> $res->data->countryID,
					'operatorID'	=> $res->data->operatorID,
					]);
				}


			// load country with mcc
			$res = nexus_base::get_country([
				'mcc'	=> (int) substr((string) $opt['imsi'], 0, 3),
				]);

			// on unexpected error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if country is found
			if($res->status == 200){

				// return result
				return self::response(200, (object)[
					'mobileID'		=> null,
					'countryID'		=> $res->data->countryID,
					'operatorID'	=> null,
					]);
				}
			}

		// order 3: countryID
		if(isset($opt['countryID'])){

			// return result
			return self::response(200, (object)[
				'mobileID'		=> null,
				'countryID'		=> $opt['countryID'],
				'operatorID'	=> null,
				]);
			}

		// return failed dependency
		return self::response(424);
		}

	public static function get_payment_data($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'	=> '~^[a-z0-9_]{1,32}$',
			], $error);

		// alternative
		$alt = h::eX($req, [
			'mobileID'	=> '~1,4294967295/i',
			'persistID'	=> '~1,18446744073709551615/i',
			'msisdn'	=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'imsi'		=> '~^[1-7]{1}[0-9]{5,15}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(!$alt) return self::response(400, 'Need mobileID, persistID, msisdn or imsi param');

		// fix msisdn param
		if(isset($alt['msisdn'])) $alt['msisdn'] = $alt['msisdn'][0];

		// load config
		$res = nexus_catlop::get_apk([
			'project'	=> $mand['project'],
			]);

		// if configuration could not be found, return precondition failed error
		if($res->status == 404) return self::response(412);

		// on other error
		if($res->status != 200) return $res;

		// take apk
		$apk = $res->data;


		// load rendered config
		$res = self::get_config([
			'project'	=> $mand['project'],
			'persistID'	=> $alt['persistID'] ?? null,
			]);

		// on error
		if(!in_array($res->status, [200, 404])) return $res;

		// if not found, return precondition failed
		if($res->status == 404) return self::response(412);

		// take apk config
		$config = (array) $res->data;



		// define product configuration
		$product_config = (object)[
			'abo'	=> [],
			'otp'	=> [],
			];

		// if product configuration is defined
		if(is_array($config) and isset($config['product_list']) and is_array($config['product_list'])){

			// for supported types
			foreach(['abo'=>'abo', 'smsabo'=>'abo', 'otp'=>'otp'] as $type => $sort_type){

				// skip invalid config
				if(!isset($config['product_list'][$type]) or !is_array($config['product_list'][$type])) continue;

				// for each entry
				foreach($config['product_list'][$type] as $productID){

					// skip invalid config
					if(!h::is($productID, '~1,65535/i')) continue;

					// skip if productID already exists
					if(in_array($productID, $product_config->{$sort_type})) continue;

					// take productID
					$product_config->{$sort_type}[] = $productID;
					}
				}
			}


		// define result
		$result = (object)[
			'apkID'				=> $apk->apkID,
			'product_access'	=> false,
			'product_contingent'=> 0,
			'mobileID'			=> 0,
			'msisdn'			=> null,
			'imsi'				=> null,
			'operatorID'		=> 0,
			'countryID'			=> 0,
			'blacklisted'		=> false,
			'mp_status'			=> 0,
			'active_subscriptions'=> 0,
			'contingent_list'	=> [
				'abo'			=> [],
				'otp'			=> [],
				],
			];

		// load mobile
		$res = self::get_mobile([
			'mobileID'	=> $alt['mobileID'] ?? null,
			'persistID'	=> $alt['persistID'] ?? null,
			'msisdn'	=> $alt['msisdn'] ?? null,
			'imsi'		=> $alt['imsi'] ?? null,
			]);

		// on unexpected error
		if(!in_array($res->status, [200, 404])) return $res;

		// if no mobile found, return result
		if($res->status == 404) return self::response(200, $result);

		// define mobile
		$mobile = $res->data;

		// add mobile values
		$result->mobileID = $mobile->mobileID;
		if(!empty($mobile->msisdn)) $result->msisdn = $mobile->msisdn;
		if(!empty($mobile->imsi)) $result->imsi = $mobile->imsi;
		$result->operatorID = $mobile->operatorID;
		$result->countryID = $mobile->countryID;
		if(!empty($mobile->blacklistlvl)) $result->blacklisted = true;
		if(!empty($mobile->mp_status)) $result->mp_status = $mobile->mp_status;


		// for abo products
		if($product_config->abo){

			// load abo list
			$res = abo::get([
				'mobileID'		=> $result->mobileID,
				'productID_list'=> $product_config->abo,
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// run each abo
			foreach($res->data as $abo){

				// if product is not confirmed
				if(!$abo->confirmed) continue;

				// if abo is not ended and not refunded
				if(!$abo->ended and !$abo->refunded){

					// if abo not terminated, increment active payments
					if(!$abo->terminated) $result->active_subscriptions++;

					// load product
					$res = nexus_service::get_product([
						'type'		=> 'abo',
						'productID'	=> $abo->productID,
						]);

					// on error
					if($res->status !== 200){
						return self::response(500, 'Cannot load productID '.$abo->productID.' of aboID '.$abo->aboID.': '.$res->status);
						}

					// take product
					$product = $res->data;

					// define generally access is allowed
					$result->product_access = true;

					// run through each charge of actual interval
					for($len = count($abo->charges), $pos = $len - $product->charges; $pos < $len; $pos++){

						// if charge is paid and has contingent
						if($abo->charges[$pos]->paid and $abo->charges[$pos]->contingent > 0){

							// add contingent of charge
							$result->product_contingent += $abo->charges[$pos]->contingent;

							// add aboID to useable contingent aboIDs
							if(!in_array($abo->charges[$pos]->chargeID, $result->contingent_list['abo'])) $result->contingent_list['abo'][] = $abo->charges[$pos]->chargeID;
							}
						}

					// if abo is not paid and in it's first charge
					if(!$abo->paid and !$abo->terminated and count($abo->charges) == 1){

						// if low money contingent is allowed
						if(!empty($product->param['low_money_contingent'])){

							// add low money contingent
							$result->product_contingent += $product->param['low_money_contingent'];

							// add aboID to useable contingent aboIDs
							if(!in_array($abo->charges[0]->chargeID, $result->contingent_list['abo'])) $result->contingent_list['abo'][] = $abo->charges[0]->chargeID;
							}
						}

					// unset product
					unset($product);
					}
				}
			}

		// for OTP products
		if($product_config->otp){

			// load otp list
			$res = otp::get([
				'mobileID'		=> $result->mobileID,
				'productID_list'=> $product_config->otp,
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// run each entry
			foreach($res->data as $otp){

				// if otp is paid and contingent left, but not refunded or expired
				if($otp->paid and $otp->contingent > 0 and !$otp->refunded and !$otp->expired){

					// define generally access is allowed
					$result->product_access = true;

					// add contingent, if given
					$result->product_contingent += $otp->contingent;

					// add otpID to useable contingent otpIDs
					if(!in_array($otp->otpID, $result->contingent_list['otp'])) $result->contingent_list['otp'][] = $otp->otpID;
					}
				}
			}


		// return result
		return self::response(200, $result);
		}

	public static function get_config_server($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'hni'			=> '~20000,79999/i',
			'countryID'		=> '~1,255/i',
			'rendered'		=> '~/b',
			'pageID'		=> '~0,65535/i',
			'persistID'		=> '~0,18446744073709551615/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(!isset($opt['hni']) and !isset($opt['countryID'])) return self::response(400, 'Need hni or countryID');

		// define defaults
		$opt += [
			'countryID'		=> 0,
			];


		// if rendered config is wanted
		if(!empty($opt['rendered'])){

			// load apk
			$res = self::get_config([
				'project'	=> $mand['project'],
				'pageID'	=> $opt['pageID'] ?? null,
				'persistID'	=> $opt['persistID'] ?? null,
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return $res;

			// if not found, return precondition failed
			if($res->status == 404) return self::response(412);

			// take apk config
			$config = $res->data;
			}

		// else the base config is taken
		else{

			// load apk
			$res = nexus_catlop::get_apk([
				'project'	=> $mand['project'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return $res;

			// if not found, return precondition failed
			if($res->status == 404) return self::response(412);

			// take apk config
			$config = $res->data->config;
			}


		// if hni should be defined through hni
		if(isset($opt['hni']) and !$opt['countryID']){

			// load operator with hni
			$res = nexus_base::get_operator([
				'hni'	=> $opt['hni'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if operator is found
			if($res->status == 200){

				// take countryID
				$opt['countryID'] = $res->data->countryID;
				}

			// if not
			else{

				// load country with mcc
				$res = nexus_base::get_country([
					'mcc'	=> (int) substr((string) $opt['hni'], 0, 3),
					]);

				// on error
				if(!in_array($res->status, [200, 404])) return self::response(570, $res);

				// if countryID not found, return failed dependency
				if($res->status == 404) return self::response(424);

				// take countryID
				$opt['countryID'] = $res->data->countryID;
				}
			}

		// if server config does not exist
		if(!h::cX($config, 'server', '~/c')) return self::response(424);

		// take server config
		$server_config = (array) h::gX($config, 'server');

		// take special country settings
		$country_set = $server_config['country'] ?? null;

		// unset special country key
		unset($server_config['country']);

		// if countryID is defined
		if($opt['countryID'] and $country_set){

			// load country
			$res = nexus_base::get_country([
				'countryID'	=> $opt['countryID'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if countryID not found, return failed dependency
			if($res->status == 404) return self::response(424);

			// take country
			$country = $res->data;

			// if country specific server setting exists
			if(h::cX($country_set, $country->code, '~/c')){

				// merge country specific server config
				$server_config = (array) h::gX($country_set, $country->code) + $server_config;
				}
			}

		// return result
		return self::response(200, (object) $server_config);
		}

	public static function get_config_product($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'hni'			=> '~20000,79999/i',
			'countryID'		=> '~1,255/i',
			'operatorID'	=> '~0,65535/i',
			'rendered'		=> '~/b',
			'pageID'		=> '~0,65535/i',
			'persistID'		=> '~0,18446744073709551615/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		if(!isset($opt['hni']) and !isset($opt['countryID']) and empty($opt['operatorID'])) return self::response(400, 'Need hni or countryID or operatorID');

		// define defaults
		$opt += [
			'countryID'		=> 0,
			'operatorID'	=> 0,
			];


		// if rendered config is wanted
		if(!empty($opt['rendered'])){

			// load apk
			$res = self::get_config([
				'project'	=> $mand['project'],
				'pageID'	=> $opt['pageID'] ?? null,
				'persistID'	=> $opt['persistID'] ?? null,
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return $res;

			// if not found, return precondition failed
			if($res->status == 404) return self::response(412);

			// take apk config
			$config = $res->data;
			}

		// else the base config is taken
		else{

			// load apk
			$res = nexus_catlop::get_apk([
				'project'	=> $mand['project'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return $res;

			// if not found, return precondition failed
			if($res->status == 404) return self::response(412);

			// take apk config
			$config = $res->data->config;
			}


		// if hni should be defined through hni
		if(isset($opt['hni']) and !$opt['countryID'] and !$opt['operatorID']){

			// load operator with hni
			$res = nexus_base::get_operator([
				'hni'	=> $opt['hni'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if operator is found
			if($res->status == 200){

				// take countryID and operatorID
				$opt['countryID'] = $res->data->countryID;
				$opt['operatorID'] = $res->data->operatorID;
				}

			// if not
			else{

				// load country with mcc
				$res = nexus_base::get_country([
					'mcc'	=> (int) substr((string) $opt['hni'], 0, 3),
					]);

				// on error
				if(!in_array($res->status, [200, 404])) return self::response(570, $res);

				// if countryID not found, return failed dependency
				if($res->status == 404) return self::response(424);

				// take countryID
				$opt['countryID'] = $res->data->countryID;
				}
			}

		// if countryID should be defined through operatorID
		if($opt['operatorID'] and !$opt['countryID']){

			// load operator with operatorID
			$res = nexus_base::get_operator([
				'operatorID'	=> $opt['operatorID'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if countryID not found, return failed dependency
			if($res->status == 404) return self::response(424);

			// take countryID
			$opt['countryID'] = $res->data->countryID;
			}


		// define product config
		$product_config = [];

		// if country default
		if($opt['countryID'] and h::cX($config, 'product:'.$opt['countryID'].'-0', '~/c')){

			// merge country specific product config
			$product_config = (array) h::gX($config, 'product:'.$opt['countryID'].'-0') + $product_config;
			}

		// if country default
		if($opt['countryID'] and $opt['operatorID'] and h::cX($config, 'product:'.$opt['countryID'].'-'.$opt['operatorID'], '~/c')){

			// merge country specific product config
			$product_config = (array) h::gX($config, 'product:'.$opt['countryID'].'-'.$opt['operatorID']) + $product_config;
			}

		// return result
		return self::response(200, (object) $product_config);
		}

	public static function get_country_operator_info($req = []){

		// optional
		$opt = h::eX($req, [
			'imsi'		=> '~^[1-7]{1}[0-9]{5,15}$',
			'hni'		=> '~20000,79999/i',
			'try_mobile'=> '~/b',
			'countryID'	=> '~0,65535/i',
			'operatorID'=> '~0,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define result
		$result = (object)[
			'countryID'	=> $opt['countryID'] ?? 0,
			'operatorID'=> $opt['operatorID'] ?? 0,
			];


		// if mobile data could be used and imsi exists
		if(!empty($opt['try_mobile']) and isset($opt['imsi'])){

			// try to load imsi
			$res = mobile::get_mobile([
				'imsi'	=> $opt['imsi'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if found and operatorID is defined
			if($res->status == 200 and $res->data->operatorID){

				// take countryID and operatorID
				$result->countryID = $res->data->countryID;
				$result->operatorID = $res->data->operatorID;

				// return result
				return self::response(200, $result);
				}
			}

		// if imsi is defined
		if(isset($opt['imsi'])){

			// convert imsi to hni
			$opt['hni'] = (int) substr((string) $opt['imsi'], 0, 5);
			}

		// if hni is defined
		if(isset($opt['hni'])){

			// load operator of hni
			$res = nexus_base::get_operator([
				'hni'	=> $opt['hni'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if operator is found
			if($res->status == 200){

				// take countryID and operatorID
				$result->countryID = $res->data->countryID;
				$result->operatorID = $res->data->operatorID;
				}

			// if not found
			else{

				// load country of mcc of hni
				$res = nexus_base::get_country([
					'mcc'	=> (int) substr((string) $opt['hni'], 0, 3),
					]);

				// on error
				if(!in_array($res->status, [200, 404])) return self::response(570, $res);

				// if found
				if($res->status == 200){

					// take data
					$result->countryID = $res->data->countryID;
					$result->operatorID = 0;
					}
				}
			}

		// if no countryID defined, but operatorID
		if(!$result->countryID and $result->operatorID){

			// load operator of operatorID
			$res = nexus_base::get_operator([
				'operatorID'	=> $result->operatorID,
				]);

			// on error
			if(!in_array($res->status, [200, 404])) return self::response(570, $res);

			// if operator is found
			if($res->status == 200){

				// take countryID
				$result->countryID = $res->data->countryID;
				}
			}

		// return result
		return self::response(200, $result);
		}

	}
