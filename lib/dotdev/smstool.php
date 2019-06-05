<?php
/*****
 * Version 1.0.2016-09-11
**/
namespace dotdev;

use \tools\error as e;
use \tools\helper as h;
use \dotdev\nexus\service;

class smstool {
	use \tools\pdo_trait,
		\tools\libcom_trait;

	protected static function pdo_config(){
		return ['mt_smstool', [
			's_job'				=> "SELECT * FROM `job` WHERE `ID` = ? LIMIT 1",
			's_sms'				=> "SELECT * FROM `sms` WHERE `ID` = ? LIMIT 1",

			'l_job_actual'		=> "SELECT * FROM `job` WHERE `status` = 102 OR `createTime` >= ? OR `startTime` >= ? OR `statusTime` >= ? ORDER BY `startTime` ASC",
			'l_job_history'		=> "SELECT * FROM `job` WHERE `status` != 102 ORDER BY `startTime` DESC LIMIT ?",

			'c_job_sms_stati'	=> "SELECT `status`, COUNT(*) as `anz` FROM `sms` WHERE `jobID` = ? GROUP BY `status`",

			'l_job_active'		=> "SELECT * FROM `job` WHERE `status` = 102 AND `startTime` < ?",
			'l_sms_for_send'	=> "SELECT * FROM `sms` WHERE `jobID` = ? AND `status` = 102 LIMIT ?",

			'i_job'				=> "INSERT INTO `job` (`createTime`,`startTime`,`serviceID`,`status`,`statusTime`,`smspercall`,`text`) VALUES (?,?,?,?,?,?,?)",
			'i_sms'				=> "INSERT INTO `sms` (`jobID`,`msisdn`,`status`,`statusTime`) VALUES (?,?,?,?)",

			'u_job'				=> "UPDATE `job` SET `startTime` = ?, `smspercall` = ?, `text` = ?, `status` = ?, `statusTime` = ? WHERE `ID` = ?",
			'u_job_status'		=> "UPDATE `job` SET `status` = ?, `statusTime` = ? WHERE `ID` = ?",

			'u_sms_status'		=> "UPDATE `sms` SET `status` = ?, `statusTime` = ? WHERE `ID` = ?",


			]];
		}

	protected static function _expand_job($job){
		$job->msisdn_count = 0;
		$job->msisdn_done = 0;
		$job->msisdn_status = [];

		$count = self::pdo('c_job_sms_stati', $job->ID);
		if($count){
			foreach($count as $entry){
				$job->msisdn_count += $entry->anz;
				if(!in_array($entry->status, [0,102])) $job->msisdn_done += $entry->anz;
				$job->msisdn_status[$entry->status] = $entry->anz;
				}
			}
		if($count === false) self::response(560);

		return $job;
		}

	public static function create_job($req){
		$mand = h::eX($req, [ // mandatory
			'startTime' 	=> '~Y-m-d H:i:s/d',
			'serviceID'		=> '~1,65535/i',
			'smspercall'	=> '~0,65535/i',
			'text'			=> '~^.{1,800}$',
			'msisdn_list',
			], $e);
		if($e) return self::response(400, $e);

		// ServiceID testen
		$res = service::get_service(['serviceID'=>$mand['serviceID']]);
		if($res->status != 200) return $res;
		$service = $res->data;

		// Reduziert den String auf Komma getrennte Zahlen. Leerzeichen trennen somit auch. Am Ende werden die Kommas links und rechts getrimmt
		$mand['msisdn_list'] = trim(preg_replace('/[^0-9]+/', ',', $mand['msisdn_list']), ',');

		// Wenn nun keine Zahl übrig ist, ist der String fehlerhaft
		if(empty($mand['msisdn_list'])) return self::response(400, ['msisdn_list_1']);

		// MSISDN checken
		$mand['msisdn_list'] = explode(',', $mand['msisdn_list']);
		$msisdn_list = $wrong_msisdn_list = [];
		foreach($mand['msisdn_list'] as $k => $msisdn){
			$extract = h::gX(['test'=>$msisdn], 'test', '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$');
			if($extract) $msisdn_list[$extract[0]] = 1;
			else $wrong_msisdn_list[] = $msisdn;
			unset($mand['msisdn_list'][$k]);
			}

		// Wenn nun keine Zahl übrig ist, ist der String fehlerhaft
		if(empty($msisdn_list)) return self::response(400, ['msisdn_list_2']);

		// Zeiten
		$nowTime = h::dtstr('now');

		// Job erstellen
		$jobID = self::pdo('i_job', [$nowTime, $mand['startTime'], $service->serviceID, 0, $nowTime, $mand['smspercall'], $mand['text']]);
		if(!$jobID) return self::response(560);

		// MSISDN hinzufügen
		$added = 0;
		foreach($msisdn_list as $msisdn => $t){
			$smsID = self::pdo('i_sms', [$jobID, $msisdn, 102, $nowTime]);
			if(!$smsID) break;
			$added++;
			}

		// Job auf aktiv stellen
		$nowTime = h::dtstr('now');
		$upd = self::pdo('u_job_status', [102, $nowTime, $jobID]);
		if(!$upd) return self::response(560);

		// Alles okay!
		return self::response(200, ['jobID'=>$jobID, 'added'=>$added, 'wrong'=>$wrong_msisdn_list]);
		}

	public static function get_job($req){
		$mand = h::eX($req, [ // mandatory
			'jobID'			=> '~1,65535/i',
			], $e, true);
		if($e) return self::response(400, $e);

		$job = self::pdo('s_job', $mand['jobID']);
		if(!$job) return self::response($job === false ? 560 : 404);

		return self::response(200, self::_expand_job($job));
		}

	public static function update_job($req){
		$res = self::get_job($req);
		if($res->status != 200) return $res;
		$job = $res->data;

		$alt = h::eX($req, [ // alternativ
			'startTime' 	=> '~Y-m-d H:i:s/d',
			'smspercall'	=> '~0,65535/i',
			'text'			=> '~^.{1,800}$',
			'status'		=> '~0,999/i',
			], $e, true);
		if($e) return self::response(400, $e);
		elseif(empty($alt)) return self::response(400, ['startTime|smspercall|text|status']);

		foreach($alt as $key => $val){
			$job->{$key} = $val;
			}

		$job->statusTime = h::dtstr('now');

		$upd = self::pdo('u_job', [$job->startTime, $job->smspercall, $job->text, $job->status, $job->statusTime, $job->ID]);
		if(!$upd) return self::response(560);

		return self::response(204);
		}

	public static function list_job_actual($req){
		$mand = h::eX($req, [ // mandatory
			'time' 			=> '~Y-m-d H:i:s/d',
			], $e, true);
		if($e) return self::response(400, $e);

		$list = self::pdo('l_job_actual', [$mand['time'], $mand['time'], $mand['time']]);
		if($list === false) return self::response(560);

		foreach($list as $job){
			self::_expand_job($job);
			}

		return self::response(200, $list);
		}

	public static function list_job_history($req){
		$mand = h::eX($req, [ // mandatory
			'max' 			=> '~1,50/i',
			], $e, true);
		if($e) return self::response(400, $e);

		$list = self::pdo('l_job_history', $mand['max']);
		if($list === false) return self::response(560);

		foreach($list as $job){
			self::_expand_job($job);
			}

		return self::response(200, $list);
		}

	public static function call_joblist(){
		// Zeiten
		$callTime = h::dtstr('now');

		$job_list = self::pdo('l_job_active', $callTime);
		if(!$job_list) return self::response($job_list === false ? 560 : 404);

		$job_stats = [];

		foreach($job_list as $job){
			$job_stats[$job->ID] = '...';

			$sms_list = self::pdo('l_sms_for_send', [$job->ID, $job->smspercall]);
			if($sms_list === false){
				self::response(560);
				break;
				}

			if(empty($sms_list)){
				$upd = self::pdo('u_job_status', [200, $callTime, $job->ID]);
				if(!$upd){
					self::response(560);
					break;
					}

				$job_stats[$job->ID] = 'Job abgeschlossen';
				continue;
				}

			// SMS lock
			foreach($sms_list as $key => $sms){
				$sms = self::pdo('s_sms', $sms->ID);
				if($sms === false){
					self::response(560);
					break 2;
					}
				if($sms->status != 102){
					unset($sms_list[$key]);
					continue;
					}
				$upd = self::pdo('u_sms_status', [423, h::dtstr('now'), $sms->ID]);
				if(!$upd){
					self::response(560);
					break 2;
					}
				}

			$sms_stats = [];
			foreach($sms_list as $sms){
				$res = self::send_sms(['msisdn'=>$sms->msisdn, 'serviceID'=>$job->serviceID, 'text'=>$job->text]);

				$sms_stats[$res->status] = !isset($sms_stats[$res->status]) ? 1 : $sms_stats[$res->status]+1;

				$upd = self::pdo('u_sms_status', [$res->status, h::dtstr('now'), $sms->ID]);
				if(!$upd){
					self::response(560);
					break 2;
					}
				}

			$job_stats[$job->ID] = 'SMS versendet: '.h::encode_php($sms_stats);
			}

		return !empty($job_stats) ? self::response(200, $job_stats) : self::response(500, 'Abbruch aufgrund Fehler');
		}

	public static function send_sms($req){
		static $service_cache = [];

		$mand = h::eX($req, [ // mandatory
			'msisdn'		=> '~^(?:\+|00|)([1-9]{1}[0-9]{5,14})$',
			'serviceID'		=> '~1,65535/i',
			'text'			=> '~^.{1,800}$',
			], $e);
		$opt = h::eX($req, [ // optional
			'senderString'	=>'~^[0-9]{1,16}$|^[a-zA-Z0-9]{1,11}$',
			'persistID'
			], $e, true);
		if($e) return self::response(400, $e);

		if(isset($service_cache[$mand['serviceID']])){
			$service = $service_cache[$mand['serviceID']];
			}
		else{
			$res = service::get_service(['serviceID'=>$mand['serviceID']]);
			if($res->status !== 200) return $res;
			$service = $service_cache[$mand['serviceID']] = $res->data;
			}

		$serviceFn = $service->ns.'::send_sms';
		if(!is_callable($serviceFn)) return self::response(501, 'Service method unavailable');

		$res = call_user_func($serviceFn, ['msisdn'=>$mand['msisdn'][0], 'text'=>$mand['text'], 'serviceID'=>$service->serviceID] + $opt);
		return $res; // 204 ist erfolgreich!
		}

	}
