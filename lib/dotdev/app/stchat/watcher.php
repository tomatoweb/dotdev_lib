<?php
/*****
 * Version 1.0.2018-07-24
**/
namespace dotdev\app\stchat;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;

class watcher {
	use \tools\libcom_trait,
		\tools\redis_trait;


	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_service');
		}


	/* Helper to detect Chattool errors and restarting apache */
	public static function auto_instability_restart($req = []){

		// mandatory
		$mand = h::eX($req, [
			'log_subpath'	=> '~1,200/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// define today
		$today_str = h::dtstr('today', 'Y-m-d');

		// define complete log path (like /log/apache/{Y-m-d}/stchat.chattool.net_access.log)
		$log_file = $_SERVER['ENV_PATH'].'/log/apache/'.$today_str.'/'.$mand['log_subpath'];

		// define marker-file for apache restart
		$restart_file = $_SERVER['ENV_PATH'].'/data/cronjob/apache_restart_marker.txt';

		// define cache keys
		$cache_key_last_restart = 'stchat:auto_instability_restart:last_restart';
		$cache_key_error = 'stchat:auto_instability_restart:error';

		// define result
		$result = (object)[
			'restart'	=> false,
			'time'		=> null,
			'error'		=> 'No previous error line found',
			];

		// if error log is given, but restark marker is not set yet
		if(is_file($log_file) and !is_file($restart_file)){

			// read log data
			$error_lines = explode("\n", file_get_contents($log_file));

			// init redis
			$redis = self::redis();

			// if redis is not accessable, abort
			if(!$redis) return self::response(500, 'Cannot check stchat stability, Redis not available');

			// load last restart time
			$last_restart = $redis->get($cache_key_last_restart);
			$last_restart = $last_restart ? h::date($last_restart) : h::date('today');

			// define pid repeated errors
			$last_pid = (object)[
				'pid'	=> 0,
				'common'=> '',
				'count'	=> 0,
				];

			// run lines
			foreach($error_lines as $line){

				// extract line  ([Mon Jul 23 17:56:41.087964 2018] [:error] [pid 4086:tid 140558307653376] Attempt to reload ...)
				$extracted = preg_match('/^\[([^\[\]]+)\] \[[:]{0,1}([^\[\]]+)\] \[pid ([0-9]+):[^\[\]]+\] (([^\"\(]+).*)$/', $line, $matches);

				// skip unmatched lines
				if(!$extracted) continue;

				// take info
				list(, $datestr, $error_type, $pid, $error_str, $error_common_part) = $matches;

				// define line time with extracting H:i:s from: "Mon Jul 23 17:56:41.087964 2018"
				$line_time = h::date($today_str.' '.substr($datestr, strpos($datestr, ':')-2, 8));

				// if line is not newer than last restart, skip line
				if($line_time <= $last_restart) continue;

				// check if there is a corrupt thread
				if(strpos($error_common_part, 'Attempt to reload') !== false){

					// save this line as error line
					$result->error = $line;

					// define restart and abort search
					$result->restart = true;
					break;
					}

				// if pid is different
				if($last_pid->pid != $pid){

					// reset to new pid
					$last_pid->pid = $pid;
					$last_pid->common = '';
					$last_pid->count = 0;
					}

				// if common part of error is different
				if($last_pid->common != $error_common_part){

					// reset to new common part
					$last_pid->common = $error_common_part;
					$last_pid->count = 0;
					}

				// increment repeated found
				$last_pid->count++;

				// if error occurs at least 3 times in a row (in the same pid)
				if($last_pid->count >= 3){

					// save this line as error line
					$result->error = $line;

					// define restart and abort search
					$result->restart = true;
					break;
					}
				}
			}

		// if restart is planed
		if($result->restart){

			// save restart time
			$result->time = h::dtstr('now');

			// write restart file
			$restart_set = file_put_contents($restart_file, $result->time);

			// on error
			if(!$restart_set) return self::response(500, 'Cannot write stchat restart file');

			// set last restart time (to this minute +3 minutes in future to allow cronjob a normal restart without retriggering)
			$redis->set($cache_key_last_restart, h::date(h::dtstr('now', 'Y-m-d H:i:').'00', '+3 min', 'Y-m-d H:i:s')); // unlimited

			// save last error before reload error
			$redis->set($cache_key_error.':'.$result->time, $result->error, ['ex'=>2419200]); // 28 days

			// return result
			return self::response(200, $result);
			}

		// return result
		return self::response(204);
		}

	public static function auto_instability_restart_log($req = []){

		// define result
		$result = (object)[
			'last_restart'	=> null,
			'log'			=> [],
			];

		// define cache names
		$cache_key_last_restart = 'stchat:auto_instability_restart:last_restart';
		$cache_key_log_prefix = 'stchat:auto_instability_restart:error:';

		// init redis
		$redis = self::redis();

		// if redis is not accessable, abort
		if(!$redis) return self::response(500, 'Cannot check stchat stability, Redis not available');

		// load last reset
		$result->last_restart = $redis->get($cache_key_last_restart);

		// load all entries
		$res = self::redis_get([
			'search' => $cache_key_log_prefix.'*',
			]);

		// take log list
		$log_list = ($res->status == 200) ? $res->data : [];

		// if logs are given
		if(is_array($log_list) and !empty($log_list)){

			// foreach entry
			foreach($log_list as $key => $entry){

				// extract date
				$date = substr($key, strlen($cache_key_log_prefix));

				// append error to result
				$result->log[$date] = str_replace("\\n", "\n", $entry);
				}
			}

		// sort result
		ksort($result->log);

		// return result
		return self::response(200, $result);
		}
	}