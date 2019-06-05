<?php
/*****
 * Version 1.0.2018-06-25
**/
namespace dotdev\mobile;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\mobile;

class export {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	protected static function pdo_config(){
		return ['mt_mobile:export', [

			// queries: export
			'l_msisdn_blacklisted'			=> "SELECT m.msisdn, m.operatorID, m.createTime, IF(m.confirmTime != '0000-00-00 00:00:00', 1, 0) AS `confirmed`, COALESCE(i.blacklistlvl, 0) AS `blacklistlvl`, i.info
												FROM `mobile` m
												LEFT JOIN `mobile_info` i ON i.mobileID = m.ID
												WHERE m.msisdn IS NOT NULL AND i.blacklistlvl IS NOT NULL
												",

			'l_persistlinks_without_session'=> "SELECT l.persistID, l.createTime, l.mobileID, m.operatorID
												FROM `persistlink` l
												INNER JOIN `mobile` m ON m.ID = l.mobileID
												LEFT JOIN `mt_traffic`.`session` s ON s.persistID = l.persistID
												WHERE l.createTime BETWEEN ? AND ? AND s.pageID IS NULL
												",
			]];
		}


	public static function export_blacklisted($req = []){

		// load list
		$list = self::pdo('l_msisdn_blacklisted');

		// on error
		if($list === false) return self::response(560);

		// return result
		return self::response(200, $list);
		}

	public static function import_blacklisted($req = []){

		// mandatory
		$mand = h::eX($req, [
			'list'		=> '~!empty/a',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// run each entry
		foreach($mand['list'] as $key => $entry){

			// check
			$sub_mand = h::eX($entry, [
				'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
				'operatorID'	=> '~1,65535/i',
				'blacklistlvl'	=> '~0,255/i',
				], $error);

			// check
			$sub_opt = h::eX($entry, [
				'createTime'	=> '~Y-m-d H:i:s/d',
				'confirmed'		=> '~/b',
				'imsi'			=> '~^[1-9]{1}[0-9]{5,15}$',
				'info'			=> '~/s',
				], $error, true);

			// on error
			if($error) return self::response(400, 'invalid entry '.$key.' of list');

			// extract shorter msisdn
			$sub_mand['msisdn'] = $sub_mand['msisdn'][0];

			// rewrite entry
			$mand['list'][$key] = $sub_mand + $sub_opt;
			}


		// stat
		$stat = (object)[
			'entries'	=> count($mand['list']),
			'result'	=> [],
			];

		// run entries
		foreach($mand['list'] as $entry_arr){

			// load entry
			$res = mobile::get_mobile([
				'msisdn' => $entry_arr['msisdn'],
				]);

			// on error
			if(!in_array($res->status, [200, 404])){

				// save to result
				if(!isset($stat->result['load_'.$res->status])) $stat->result['load_'.$res->status] = 0;
				$stat->result['load_'.$res->status]++;

				// skip processing entry
				continue;
				}

			// take entry, if exits
			$mobile = ($res->status == 200) ? $res->data : null;

			// if entry exists
			if($res->status == 200){

				// update mobile data
				$res = mobile::update_mobile([
					'mobileID'	=> $res->data->mobileID,
					] + $entry_arr);

				// save to result
				if(!isset($stat->result['update_'.$res->status])) $stat->result['update_'.$res->status] = 0;
				$stat->result['update_'.$res->status]++;
				}

			// else if entry does not exists
			else{

				// create entry with given param
				$res = mobile::create_mobile($entry_arr);

				// save to result
				if(!isset($stat->result['create_'.$res->status])) $stat->result['create_'.$res->status] = 0;
				$stat->result['create_'.$res->status]++;
				}
			}

		// return success
		return self::response(200, $stat);
		}


	public static function get_export_list($req = []){

		// mandatory
		$mand = h::eX($req, [
			'type'		=> '~^(?:persistlinks_without_session)$',
			], $error);

		// alternativ
		$alt = h::eX($req, [
			'from'		=> '~Y-m-d H:i:s/d',
			'to'		=> '~Y-m-d H:i:s/d',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// create missing range points
		if(!isset($alt['from'])) $alt['from'] = '2000-01-01 00:00:00';
		if(!isset($alt['to'])) $alt['to'] = h::dtstr('now');

		// get list
		$list = self::pdo('l_persistlinks_without_session', [$alt['from'], $alt['to']]);

		// on error
		if($list === false) return self::response(560);

		// return result
		return self::response(200, $list);
		}

	}
