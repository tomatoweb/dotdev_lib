<?php
/*****
 * Version 1.1.2019-02-12
**/
namespace dotdev\traffic;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\traffic\event;

class service {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_traffic:event', [

			'u_event_inc_cost'		=> "UPDATE `event` SET `cost` = `cost` + ? WHERE `eventID` = ?",
			]];
		}


	/* translation tables */
	protected static $translation = [

		// locked click status translation
		'bc_status'			=> ['bc_status', 'val', 0],
		'bc_status_name'	=> ['bc_status', 'key', 'Unknown'],

		// locked session status translation
		'bs_status'			=> ['bs_status', 'val', 0],
		'bs_status_name'	=> ['bs_status', 'key', 'Unknown'],

		];

	protected static $translation_list = [

		// blocked click status translation
		'bc_status' => [
			1	=> 'Referer Blacklist',
			2	=> 'IP Blacklist',
			3	=> 'No Referer found',
			4	=> 'No ClickData found',
			5	=> 'No Referer and no ClickData found',
			6	=> 'Adtarget status defines using main page',
			],

		// blocked session status translation
		'bs_status' => [
			1	=> 'Session does not exist',
			2	=> 'Session has no or different pageID',
			3	=> 'Session has different unique hash',
			],

		];


	/* internal helper function */
	public static function _translate_to($key, $val, $error_reporting = true){

		// if key does no exist in translation
		if(!isset(self::$translation[$key]) or !is_array(self::$translation[$key])){

			// log error
			e::logtrigger('DEBUG: translate key '.h::encode_php($key).' (with value '.h::encode_php($val).') does not exist');

			// return null
			return null;
			}

		// take settings
		list($table_name, $table_key, $default) = self::$translation[$key] + [null, null, null];

		// if table does not exist
		if(!isset(self::$translation_list[$table_name])){

			// log error
			e::logtrigger('DEBUG: table '.h::encode_php($table_name).' of translate key '.h::encode_php($key).' (with value '.h::encode_php($val).') does not exist');

			// return null
			return null;
			}


		// define result
		$result = false;

		// if key of table is searched using its value
		if($table_key == 'val'){

			// search value
			$result = array_search($val, self::$translation_list[$table_name]);
			}

		// if value of table is searched using its key
		elseif($table_key == 'key'){

			// search key
			$result = self::$translation_list[$table_name][$val] ?? false;
			}


		// if table does not exist
		if($result === false){

			// log error
			if($error_reporting) e::logtrigger('DEBUG: Cannot find '.h::encode_php($table_key).' with translate key '.h::encode_php($key).' (with value '.h::encode_php($val).') in table '.h::encode_php($table_name).' does not exist');

			// return null
			return null;
			}

		// return result
		return $result;
		}


	/* object: translation EXPERIMENTAL */
	public static function get_translation($req = []){

		// optional
		$opt = h::eX($req, [
			'table'		=> '~^(?:'.implode('|', array_keys(self::$translation_list)).')$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: table
		if(isset($opt['table'])){

			// define result
			$result = [];

			// for each entry
			foreach(self::$translation_list[$opt['table']] as $ID => $name){

				// add entry to result
				$result[] = (object)[
					'ID'	=> $ID,
					'name'	=> $name,
					];
				}

			// return result
			return self::response(200, $result);
			}

		// param order 2: no param
		if(empty($req)){

			// define result
			$result = [];

			// for each table
			foreach(self::$translation_list as $table_name => $table){

				// create table
				$sub_table = [];

				// for each entry
				foreach($table as $ID => $name){

					// add entry to result
					$sub_table[] = (object)[
						'ID'	=> $ID,
						'name'	=> $name,
						];
					}

				// take table
				$result[] = (object)[
					'table'	=> $table_name,
					'list'	=> $sub_table,
					];
				}

			// return result
			return self::response(200, $result);
			}

		// other request param invalid
		return self::response(400, 'need table or no parameter');
		}



	/* service: base */
	public static function service_request($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'		=> '~^(?:event_update)$',
			'data'		=> '~/l',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: event_update
		if($mand['type'] == 'event_update'){

			// check for 0.001 payout param
			// happens for events which should be shown in adzoona for affiliates (only events with payouts will be shown for affiliates)
			// e.g. install with 0.001 payout will also trigger postback (with incr_cost_eur=0.001), but should not calculated in xAdmin
			if(h::cX($mand['data'], 'incr_cost_eur', '0.001')){

				// return success (= skipping)
				return self::response(204);
				}

			// call associated service function
			return self::service_event_update($mand['data']);
			}

		// return bad request
		return self::response(400, ['type']);
		}

	public static function service_event_update($req = []){

		// mandatory
		$mand = h::eX($req, [
			'eventID'		=> '~1,4294967295/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'incr_cost_eur'			=> '~0,99999/f',
			'incr_cost_eur_comma'	=> '~[0-9]{1,5}(?:,[0-9]{2}|)',
			'incr_cost_eur_cent'	=> '~0,99999/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'incr_cost_eur'	=> 0,
			];

		// if cost is given comma separated
		if(isset($opt['incr_cost_eur_comma'])){

			// convert
			$opt['incr_cost_eur'] = (float) str_replace(',', '.', $opt['incr_cost_eur_comma']);
			}

		// if cost is given in eur_cent
		elseif(isset($opt['incr_cost_eur_cent'])){

			// convert
			$opt['incr_cost_eur'] = $opt['incr_cost_cent'] / 100;
			}

		// if (no empty) cost value is given
		if($opt['incr_cost_eur']){

			// round value to cent
			$opt['incr_cost_eur'] = round($opt['incr_cost_eur'], 2);

			// update entry (incrementing cost)
			$upd = self::pdo('u_event_inc_cost', [$opt['incr_cost_eur'], $mand['eventID']]);

			// on error
			if($upd === false) return self::response(560);
			}

		// return success
		return self::response(204);
		}

	}