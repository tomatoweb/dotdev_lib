<?php
/*****
 * Version 1.7.2018-12-19
**/
namespace dotdev;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \tools\http;
use \dotdev\nexus\base as nexus_base;

class cronjob {
	use \tools\pdo_trait,
		\tools\libcom_trait,
		\tools\redis_trait;

	/* some static values */
	protected static $config_file = '/cronjob/config.txt';
	protected static $config_tabs = '/cronjob/tabs';
	protected static $active_jobID;


	/* PDO Config */
	protected static function pdo_config(){
		return ['mt_cronjob', [
			'l_job'					=> "SELECT * FROM `job`",
			's_job'					=> "SELECT * FROM `job` WHERE `jobID` = ? LIMIT 1",
			'l_jobs_enabled'		=> "SELECT * FROM `job` WHERE `enabled` = 1",

			's_log'					=> "SELECT * FROM `log` WHERE `logID` = ? LIMIT 1",
			'l_job_log_intime'		=> "SELECT * FROM `log` WHERE `jobID` = ? AND `createTime` BETWEEN ? AND ?",
			'l_job_log_last'		=> "SELECT * FROM `log` WHERE `jobID` = ? ORDER BY `createTime` DESC LIMIT ?",

			'i_job'					=> "INSERT INTO `job` (`name`,`enabled`,`runonce`,`minute`,`hour`,`day`,`month`,`weekday`,`lockSeconds`,`server`,`ns`,`param`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
			'i_log'					=> "INSERT INTO `log` (`jobID`,`createTime`,`data`) VALUES (?,?,?)",

			'u_job'					=> "UPDATE `job` SET `name` = ?, `enabled` = ?, `runonce` = ?, `minute` = ?, `hour` = ?, `day` = ?, `month` = ?, `weekday` = ?, `lockSeconds` = ?, `unlockTime` = ?, `server` = ?, `ns` = ?, `param` = ? WHERE `jobID` = ?",
			'u_log'					=> "UPDATE `log` SET `finishTime` = ?,`status` = ?,`data` = ? WHERE `logID` = ?",
			]];
		}


	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_service');
		}

	public static function redis_fallback(){

		// return session DB as local fallback
		return redis::load_resource('mt_session');
		}


	/* server name */
	public static function get_server_name($req = []){

		// define static for cache
		static $name = null;

		// load service url
		if(!$name){

			// define server name file
			$file = $_SERVER['ENV_PATH'].'/config/server_name.txt';

			// check for file
			if(is_file($file)){

				$name = file_get_contents($file);
				}

			// set undefined, if not set
			if(!$name) $name = 'undefined';
			}

		// return result
		return self::response(200, (object)['name' => $name]);
		}


	/* Object: job */
	public static function get_job($req = []){

		// alternative
		$alt = h::eX($req, [
			'jobID'		=> '~1,65535/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);


		// param order 1: jobID
		if(isset($alt['jobID'])){

			// init redis
			$redis = self::redis();

			// define cache key
			$cache_key = 'job:by_jobID:'.$alt['jobID'];

			// if redis accessable and entry exists
			if($redis and $redis->exists($cache_key)){

				// take entry
				$entry = $redis->get($cache_key);
				}

			// else
			else{

				// search in DB
				$entry = self::pdo('s_job', [$alt['jobID']]);

				// on error
				if(!$entry) return self::response($entry === false ? 560 : 404);

				// if redis accessable
				if($redis){

					// cache entry
					$redis->set($cache_key, $entry, ['ex'=>21600, 'nx']); // 6 hours
					}
				}

			// decode param
			$entry->param = $entry->param ? json_decode($entry->param) : null;

			// TEMP: add new server value, if missing
			if(!isset($entry->server)) $entry->server = '';

			// return result
			return self::response(200, $entry);
			}

		// param order 2: no param
		if(empty($req)){

			// get list
			$list = self::pdo('l_job');

			// on error
			if($list === false) return self::response(560);

			// decode param
			foreach($list as $entry){
				$entry->param = $entry->param ? json_decode($entry->param) : null;

				// TEMP: add new server value, if missing
				if(!isset($entry->server)) $entry->server = '';
				}

			// return result
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need jobID or no parameter');
		}

	public static function create_job($req = []){

		// mandatory
		$mand = h::eX($req, [
			'name'			=> '~1,64/s',
			'enabled'		=> '~/b',
			'runonce'		=> '~/b',
			'minute'		=> '~^[0-9\,\-\/\*]{1,60}$',
			'hour'			=> '~^[0-9\,\-\/\*]{1,60}$',
			'day'			=> '~^[0-9\,\-\/\*]{1,60}$',
			'month'			=> '~^[0-9\,\-\/\*]{1,60}$',
			'weekday'		=> '~^[0-6\,\-\/\*]{1,60}$',
			'lockSeconds'	=> '~0,65535/i',
			'ns'			=> '~^[a-zA-Z0-9\\\_\:]{1,255}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'server'		=> '~0,255/s',
			'param'			=> '~/l',
			], $error, true);

		// additional check
		if(isset($mand['ns']) and !is_callable($mand['ns'])) $error[] = 'ns';

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'server'		=> '',
			];

		// encode param
		$opt['param'] = isset($opt['param']) ? json_encode($opt['param']) : 'null';

		// insert
		$jobID = self::pdo('i_job', [$mand['name'], $mand['enabled'] ? 1 : 0, $mand['runonce'] ? 1 : 0, $mand['minute'], $mand['hour'], $mand['day'], $mand['month'], $mand['weekday'], $mand['lockSeconds'], $opt['server'], $mand['ns'], $opt['param']]);

		// on error
		if(!$jobID) return self::response(560);

		// rebuild
		$res = self::rebuild();

		// return result
		return ($res->status == 204) ? self::response(201, (object)['jobID'=>$jobID]) : $res;
		}

	public static function update_job($req = []){

		// mandatory
		$mand = h::eX($req, [
			'jobID'			=> '~1,65535/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'name'			=> '~1,64/s',
			'enabled'		=> '~/b',
			'runonce'		=> '~/b',
			'minute'		=> '~^[0-9\,\-\/\*]{1,60}$',
			'hour'			=> '~^[0-9\,\-\/\*]{1,60}$',
			'day'			=> '~^[0-9\,\-\/\*]{1,60}$',
			'month'			=> '~^[0-9\,\-\/\*]{1,60}$',
			'weekday'		=> '~^[0-6\,\-\/\*]{1,60}$',
			'lockSeconds'	=> '~0,65535/i',
			'unlockTime'	=> '~Y-m-d H:i:s/d',
			'server'		=> '~0,255/s',
			'ns'			=> '~^[a-zA-Z0-9\\\_\:]{1,255}$',
			'param'			=> '~/l',
			], $error, true);

		// optional 2
		$opt2 = h::eX($req, [
			'reset_unlockTime'=>'~/b',
			'rebuild'		=>'~/b',
			'unset_param'	=> '~/b',
			], $error, true);

		// additional check
		if(isset($opt['ns']) and !is_callable($opt['ns'])) $error[] = 'ns';

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_job([
			'jobID'	=> $mand['jobID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// special
		if(!empty($opt2['reset_unlockTime'])){
			$opt['unlockTime'] = '0000-00-00 00:00:00';
			}

		// replace params
		foreach($opt as $k => $v){
			if(in_array($k, ['enabled', 'runonce'])) $v = $v ? 1 : 0;
			$entry->{$k} = $v;
			}

		// encode param
		if($entry->param !== '') $entry->param = json_encode($entry->param);

		// unset param, if no_param is true
		if(!empty($opt2['unset_param'])) $entry->param = 'null';

		// update entry
		$upd = self::pdo('u_job', [$entry->name, $entry->enabled, $entry->runonce, $entry->minute, $entry->hour, $entry->day, $entry->month, $entry->weekday, $entry->lockSeconds, $entry->unlockTime, $entry->server, $entry->ns, $entry->param, $entry->jobID]);

		// on error
		if($upd === false) return self::response(560);

		// init redis
		$redis = self::redis();

		// if redis is accessable
		if($redis){

			// update entry
			$redis->set('job:by_jobID:'.$entry->jobID, $entry, ['ex'=>21600]); // 6 hours
			}

		// for specific keys
		foreach(['enabled','minute','hour','day','month','weekday'] as $k){

			// if specific param is given
			if(isset($opt[$k])){

				// rebuild
				$res = self::rebuild();

				// on error
				if($res->status != 204) e::logtrigger('Cronjob rebuild failed with status '.$res->status);

				// skip further detection
				break;
				}
			}

		// return success
		return self::response(204);
		}


	/* Object: active_job */
	public static function get_active_job($req = []){

		// abort if no active job exists
		if(!self::$active_jobID) return self::response(406);

		// convert to array
		if(is_object($req)) $req = (array) $req;

		// add active jobID
		if(is_array($req)) $req['jobID'] = self::$active_jobID;

		// forward to original function
		return self::get_job($req);
		}

	public static function update_active_job($req = []){

		// abort if no active job exists
		if(!self::$active_jobID) return self::response(406);

		// convert to array
		if(is_object($req)) $req = (array) $req;

		// add active jobID
		if(is_array($req)) $req['jobID'] = self::$active_jobID;

		// forward to original function
		return self::update_job($req);
		}


	/* Object: log */
	public static function get_log($req = []){

		// alternative
		$alt = h::eX($req, [
			'logID'		=> '~1,4294967295/i',
			'jobID'		=> '~1,65535/i',
			'from'		=> '~Y-m-d H:i:s/d',
			'to'		=> '~Y-m-d H:i:s/d',
			'last'		=> '~1,100/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// param order 1: logID
		if(isset($alt['logID'])){

			// load entry from DB
			$entry = self::pdo('s_log', $alt['logID']);

			// on error
			if($entry === false) return self::response(560);

			// return result
			return self::response(200, $entry);
			}

		// param order 2: jobID + from + to
		if(isset($alt['jobID']) and isset($alt['from']) and isset($alt['to'])){

			// load list from DB
			$list = self::pdo('l_job_log_intime', [$alt['jobID'], $alt['from'], $alt['to']]);

			// on error
			if($list === false) return self::response(560);

			// return result
			return self::response(200, $list);
			}

		// param order 2: jobID + last
		if(isset($alt['jobID']) and isset($alt['last'])){

			// load list from DB
			$list = self::pdo('l_job_log_last', [$alt['jobID'], $alt['last']]);

			// on error
			if($list === false) return self::response(560);

			// convert to list, if LIMIT was 1
			if($alt['last'] == 1) $list = $list ? [$list] : [];

			// return result
			return self::response(200, $list);
			}

		// other request param invalid
		return self::response(400, 'need logID or jobID+from+to parameter');
		}


	/* system */
	public static function run_job($req = []){

		// mandatory
		$mand = h::eX($req, [
			'jobID'	=> '~1,65535/i',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// load entry
		$res = self::get_job([
			'jobID'	=> $mand['jobID'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take entry
		$entry = $res->data;

		// check if job is enabled
		if(!$entry->enabled) return self::response(403);

		// if job is defined for specific server
		if($entry->server){

			// explode server
			$server_list = explode('|', $entry->server);

			// load server name
			$res = self::get_server_name();

			// on error
			if($res->status != 200) return self::response(570, $res);

			// define server name
			$server_name = $res->data->name;

			// check if server name is defined for job
			if(!in_array($server_name, $server_list)) return self::response(403);
			}

		// check if job is locked
		if($entry->unlockTime != '0000-00-00 00:00:00' and h::date('now') < h::date($entry->unlockTime)) return self::response(409);

		// prepare job update
		$upd = [];

		// check if job is marked as runonce only
		if($entry->runonce){
			$upd['enabled'] = $entry->enabled = 0;
			}

		// check if job should be locked
		if($entry->lockSeconds){
			$upd['unlockTime'] = $entry->unlockTime = h::dtstr('now +'.$entry->lockSeconds.' sec');
			}

		// check if we have to update job first
		if($upd){

			// update job
			$res = self::update_job([
				'jobID'	=>$mand['jobID'],
				] + $upd);

			// on error
			if($res->status != 204) return $res;
			}

		// create new log
		$logID = self::pdo('i_log', [$entry->jobID, h::dtstr('now'), '']);

		// on error
		if(!$logID) return self::response(560);

		// set active job
		self::$active_jobID = $entry->jobID;

		// run job
		$res = $entry->param ? call_user_func($entry->ns, $entry->param) : call_user_func($entry->ns);

		// unset active job
		self::$active_jobID = null;

		// extract log data
		$data = 'null';
		if(isset($res->data)) $data = json_encode($res->data);
		elseif(isset($res->error)) $data = json_encode($res->error);

		// update log
		$upd = self::pdo('u_log', [h::dtstr('now'), $res->status, $data, $logID]);

		// on error
		if(!$upd) return self::response(560);

		// return success
		return self::response(204);
		}

	public static function rebuild($req = []){

		// optional
		$opt = h::eX($req, [
			'unset_only'=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// check if rebuilding cronjob is allowed (must be configured through nginx)
		if(!h::gX($_SERVER, 'CRONJOB_BUILD_ALLOWED')) return self::response(403);

		// define cronjob.txt lines
		$lines = [];

		// if running user has it own crontab.txt configuration
		if(!empty($_SERVER['HOME']) and is_file($_SERVER['HOME'].'/crontab.txt')){

			// read crontab.txt and explode lines
			$lines = explode("\n", file_get_contents($_SERVER['HOME'].'/crontab.txt'));
			}

		// add comment for xadmin generatad cronjobs
		$lines[] = '# xadmin generated cronjobs';

		// create empty crontab list
		$upd = file_put_contents($_SERVER['DATA_PATH'].self::$config_file, implode("\n", $lines)."\n");

		// on error
		if($upd === false) return self::response(500, 'Error write config to '.$_SERVER['DATA_PATH'].self::$config_file);

		// unset crontab with empty list
		exec('crontab '.$_SERVER['DATA_PATH'].self::$config_file);

		// if unset_only, we do not build the jobs and exit here
		if(!empty($opt['unset_only'])) return self::response(204, $lines);

		// get jobs
		$list = self::pdo('l_jobs_enabled');

		// on error
		if($list === false) return self::response(560);

		// define server variables
		$server_variables = [
			'HTTP_ACCEPT' 				=> '',
			'HTTP_ACCEPT_ENCODING' 		=> '',
			'HTTP_HOST'					=> '',
			'HTTP_USER_AGENT'			=> 'PHP CLI CronJob',
			'HTTP_REFERER'				=> '',
			'DOCUMENT_URI'				=> '',
			'QUERY_STRING'				=> '',
			'SERVER_ADMIN'				=> $_SERVER['SERVER_ADMIN'],
			'SERVER_NAME'				=> '',
			'REMOTE_ADDR'				=> '127.0.0.1',
			'REQUEST_URI'				=> '/',
			'REQUEST_METHOD'			=> 'GET',
			'PDO_CONNECTION_TIMEOUT'	=> 86400,
			'ENV_PATH'					=> $_SERVER['ENV_PATH'],
			'LOG_PATH'					=> $_SERVER['LOG_PATH'],
			'DATA_PATH'					=> $_SERVER['DATA_PATH'],
			'PDO_PATH'					=> $_SERVER['PDO_PATH'],
			'REDIS_PATH'				=> $_SERVER['REDIS_PATH'],
			'CRONJOB_BUILD_ALLOWED'		=> true,
			];

		// php header
		$cmd_header = '#!/usr/bin/php'.PHP_EOL.'<?php'.PHP_EOL.'$_POST = $_GET = $_REQUEST = [];'.PHP_EOL;

		// add server variables
		foreach($server_variables as $key => $val){
			$cmd_header .= '$_SERVER["'.$key.'"] = '.h::encode_php($val).';'.PHP_EOL;
			}

		// autoloader
		$cmd_header .= 'include $_SERVER[\'ENV_PATH\'].\'/config/php/autoload.php\';'.PHP_EOL;

		// foreach job
		foreach($list as $job){

			// define php-file accessable for conjob.txt
			$file = $_SERVER['DATA_PATH'].self::$config_tabs.'/'.$job->jobID.'.php';

			// define cmd in php-file
			$cmd = $cmd_header.'\\'.__NAMESPACE__.'\\cronjob::run_job(["jobID"=>'.$job->jobID.']);'.PHP_EOL;

			// save php-file
			if(!file_put_contents($file, $cmd) or !chmod($file, 0744)) return self::response(500, 'Error creating file '.$file);

			// define job in cronjob.txt
			$lines[] = $job->minute.' '.$job->hour.' '.$job->day.' '.$job->month.' '.$job->weekday.' '.$file.' >/dev/null 2>&1';
			}

		// define new list in cronjob.txt
		$upd = file_put_contents($_SERVER['DATA_PATH'].self::$config_file, implode("\n", $lines)."\n");

		// on error
		if($upd === false) return self::response(500, 'Error write config to '.$_SERVER['DATA_PATH'].self::$config_file);

		// update crontab with new list
		exec('crontab '.$_SERVER['DATA_PATH'].self::$config_file);

		//touch(dirname($_SERVER['DATA_PATH'].self::$config_file));

		// finished
		return self::response(204, $lines);
		}

	public static function run_script($req = []){

		// mandatory
		$mand = h::eX($req, [
			'file'	=> '~^\/[a-zA-Z0-9\-\.\_\/]{1,250}\.php$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// define script file
		$file = $_SERVER['ENV_PATH'].$mand['file'];

		// return error, if script file not found
		if(!is_file($file)) return self::response(500, 'Script '.$mand['file'].' not found');

		// execute script
		$data = include($file);

		// return script result
		return self::response(200, $data);
		}


	/* config */
	public static function get_config($req = []){

		// define defaults
		$result = [
			'redisjob_stop'	=> false,
			];

		// init redis
		$redis = self::redis();

		// if redis not accessable, abort here
		if(!$redis) return self::response(503);

		// define cache key
		$cache_key = 'cronjob:config';

		// if setting exists
		if($redis->exists($cache_key)){

			// take setting
			$setting = $redis->hGetAll($cache_key);
			if(!$setting) $setting = [];

			// for each given key
			foreach($setting as $key => $val){

				// skip if key does not exist in config
				if(!isset($result[$key])){

					// delete deprecated key and continue
					$redis->hDel($cache_key, $key);
					continue;
					}

				// overwrite config
				$result[$key] = $val;
				}
			}

		// return result
		return self::response(200, (object) $result);
		}

	public static function set_config($req = []){

		// optional
		$opt = h::eX($req, [
			'redisjob_stop'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if no option given, return success
		if(!$opt) return self::response(204);

		// init redis
		$redis = self::redis();

		// if redis not accessable, abort here
		if(!$redis) return self::response(503);

		// define cache key
		$cache_key = 'cronjob:config';

		// set options
		$redis->hMSet($cache_key, $opt);

		// return success
		return self::response(204);
		}

	public static function reset_config($req = []){

		// optional
		$opt = h::eX($req, [
			'redisjob_stop'	=> '~/b',
			'redisjob_logactive'=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// init redis
		$redis = self::redis();

		// if redis not accessable, abort here
		if(!$redis) return self::response(503);

		// define cache key
		$cache_key = 'cronjob:config';

		// expire setting
		$redis->setTimeout($cache_key, 0);

		// return success
		return self::response(204);
		}


	/* redis job */
	public static function get_redisjob($req = []){

		// optional
		$opt = h::eX($req, [
			'lvl'			=> '~1,3/i',
			'fn'			=> '~/s',
			'check_mand'	=> '~/c',
			'check_opt'		=> '~/c',
			'check_failed'	=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'lvl'			=> null,
			'fn'			=> null,
			'check_mand'	=> null,
			'check_opt'		=> null,
			'check_failed'	=> false,
			];

		// define cache keys
		$cache_key = [
			1	=> 'job:redispipe',
			2	=> 'job:redispipe:2',
			3	=> 'job:redispipe:3',
			];

		// init redis and define cache key
		$redis = self::redis();

		// if redis not accessable, abort here
		if(!$redis) return self::response(503);

		// define result
		$result = [];

		// for each level
		for($i = 1; $i <= 3; $i++){

			// if level is defined, skip if not matched
			if($opt['lvl'] and $i != $opt['lvl']) continue;

			// for each job
			foreach($redis->lRange($cache_key[$i], 0, -1) as $job){

				// if function filter is defined, skip if not matched
				if($opt['fn'] and !h::is($job->fn, $opt['fn'])) continue;

				// if check filter for function param is defined
				if($opt['check_mand'] or $opt['check_opt']){

					// define param error
					$job->param_error = [];

					// if mandatory param given, run check
					if($opt['check_mand']) h::eX($job->param ?? [], $opt['check_mand'], $job->param_error);

					// if optional param given, run check
					if($opt['check_opt']) h::eX($job->param ?? [], $opt['check_opt'], $job->param_error, true);

					// skip entry if not matching wanted check result
					if(($opt['check_failed'] and !$job->param_error) or (!$opt['check_failed'] and $job->param_error)) continue;
					}

				// add job to result
				$result[] = $job;
				}
			}

		// return result
		return self::response(200, $result);
		}

	public static function get_redisjob_status($req = []){

		// define result
		$result = (object)[
			'lvl_1_count'	=> null,
			'lvl_2_count'	=> null,
			'lvl_3_count'	=> null,
			'fallback_count'=> null,
			];

		// define cache keys
		$cache_key_level_1 = 'job:redispipe';
		$cache_key_level_2 = 'job:redispipe:2';
		$cache_key_level_3 = 'job:redispipe:3';

		// define fallback cache key
		$fallback_cache_key = 'job:fallback:redispipe';

		// init redis and define cache key
		$redis = self::redis();

		// if redis is availabled
		if($redis){

			// add result
			$result->lvl_1_count = $redis->lSize($cache_key_level_1);
			$result->lvl_2_count = $redis->lSize($cache_key_level_2);
			$result->lvl_3_count = $redis->lSize($cache_key_level_3);
			}

		// init fallback connection
		$fallback_redis = self::redis_fallback();

		// if fallback redis is availabled
		if($fallback_redis){

			// add result
			$result->fallback_count = $fallback_redis->lSize($fallback_cache_key);
			}

		// return result
		return self::response(200, $result);
		}

	public static function add_redisjob($req = []){

		// mandatory
		$mand = h::eX($req, [
			'fn'				=> '~^[a-zA-Z0-9\\\_\:]{1,255}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'param'				=> '~/l',
			'start_at'			=> '~U/d',
			'lvl'				=> '~1,3/i',
			'servercom_serverID'=> '~1,255/i',
			'servercom_ipv4'	=> '~^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$', // close enough check
			'servercom_fqdn'	=> '~1,60/s',
			], $error, true);

		// additional check
		if(isset($mand['fn']) and !is_callable($mand['fn'])) $error[] = 'fn';

		// on error
		if($error) return self::response(400, $error);

		// define timestamp
		$unix_now = h::dtstr('now', 'U');

		// for each server identification key
		foreach(['servercom_serverID' => 'serverID', 'servercom_ipv4' => 'ipv4'] as $key => $askey){

			// skip if not set
			if(!isset($opt[$key])) continue;

			// if servercom_fqdn is already set
			if(isset($opt['servercom_fqdn'])) return self::response(400, 'param servercom_fqdn cannot be combined with serverID or server_ipv4');

			// try to load server
			$res = nexus_base::get_server([
				$askey	=> $opt[$key],
				]);

			// on error
			if($res->status != 200) return self::response(500, 'Server with key '.$key.' = '.h::encode_php($opt[$key]).' for RedisJob not found: '.$res->status.' (fn '.$mand['fn'].')');

			// if servercom_fqdn not set, return error
			if(!$res->data->servercom_fqdn) return self::response(500, 'serverID '.$res->data->serverID.' for RedisJob has no servercom_fqdn (fn '.$mand['fn'].')');

			// set servercom_fqdn variable
			$opt['servercom_fqdn'] = $res->data->servercom_fqdn;

			// skip further processing
			break;
			}

		// define entry
		$job = (object)[
			'time'		=> $unix_now,
			'rand'		=> mt_rand(0, 100000),
			'start_at'	=> $opt['start_at'] ?? $unix_now, // set as unixtime
			'fn'		=> $mand['fn'],
			'param'		=> $opt['param'] ?? null,
			'lvl'		=> $opt['lvl'] ?? 1,
			'servercom'	=> $opt['servercom_fqdn'] ?? null,
			];

		// define cache key (considering level of importance)
		$cache_key = ($job->lvl == 1) ? 'job:redispipe' : 'job:redispipe:'.$job->lvl;

		// init redis
		$redis = self::redis();

		// if redis not available
		if(!$redis){

			// define fallback cache key
			$cache_key = 'job:fallback:redispipe';

			// init fallback connection to save redisjobs there
			$redis = self::redis_fallback();

			// if redis fallback also not available
			if(!$redis){

				// abort with error
				return self::response(503);
				}
			}

		// append entry to redis pipe
		$redis->rPush($cache_key, $job);

		// return success
		return self::response(204);
		}

	public static function run_redisjobs($req = []){

		/*
		This function process redisjobs from lists with different priority. This process counts
		all entries in all list at start of this process and tries to execute that often. These
		list can be de-/increased while processing.
		The param "min_lifetime" defines how long this process should retry every second (or
		seconds defined by param "sleep_seconds") to find new jobs, if there were none before.

		One of the following rules has to match, before a run tries to find a job in a list with
		lower priority:
			- the actual list is empty
			- the found job is invalid
			- the found job has a start time which is not reached yet

		This rules should allow to set a priority to process level 1 before level 2 and level 2 before
		level 3 jobs. Delayed jobs are an exception, but they are also automatically sorted at the end
		of the list, since each run does that when the start time of the job is no reached yet. Even
		on heavy workloads jobs with lower priority become executed, when delayed jobs exists.

		Another	side effect is the accumulation of processes, when list fills faster than jobs process
		the entries. This effect can be manipulated by the CronJob frequency for RedisJob and by the
		"min_lifetime" param.
		*/

		// optional
		$opt = h::eX($req, [
			'min_lifetime'	=> '~U/d',
			'sleep_seconds'	=> '~1,60/i',
			'log_intense'	=> '~0,60/f',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define cache keys
		$cache_key_lvl = [
			1	=> 'job:redispipe',
			2	=> 'job:redispipe:2',
			3	=> 'job:redispipe:3',
			];

		// init redis and define cache key
		$redis = self::redis();

		// if redis not accessable, abort here
		if(!$redis) return self::response(503);

		// define results
		$result = [];

		// load config
		$res = self::get_config();

		// if config could not be loaded
		if($res->status != 200) return self::response(500, 'Cronjob runtime config could not be loaded: '.$res->status);

		// take config
		$config = $res->data;

		// abort here, if redisjob is set on stop
		if($config->redisjob_stop) return self::response(200, $result);

		// define max runs with length of all lists (this considering the actual workload)
		$max_run = max($redis->lSize($cache_key_lvl[1]) + $redis->lSize($cache_key_lvl[2]) + $redis->lSize($cache_key_lvl[3]), !empty($opt['min_lifetime']) ? 1 : 0);

		// repeatly take jobs until max_run is reached
		for($i = 1; $i <= $max_run; $i++){

			// define not having a job
			$job = false;

			// for each level
			for($lvl = 1; $lvl <= 3; $lvl++){

				// define level cache key
				$cache_key = $cache_key_lvl[$lvl];

				// get and remove first entry in list
				$job = $redis->lPop($cache_key);

				// check if job is basically invalid
				if(is_object($job) and (!isset($job->start_at) or empty($job->fn) or !is_callable($job->fn))){

					// discard this job
					$job = false;
					}

				// check if start_at time is not reached
				if($job !== false and $job->start_at > time()){

					// append job at end of list
					$redis->rPush($cache_key, $job);

					// unset having a job
					$job = false;
					}

				// if job is found, abort searching here
				if($job !== false) break;
				}

			// if job is given
			if($job !== false){

				// save time
				$job_starttime = microtime(true);

				// if servercom variable is set
				if(!empty($job->servercom)){

					// execute job on remote server
					$curl_obj = http::curl_obj([
						'url'		=> 'http://'.$job->servercom.'/com/nsexec.json',
						'ipv4only'	=> true,
						'method'	=> 'POST',
						'jsonencode'=> true,
						'post'		=> [
							'ns'	=> $job->fn,
							] + (isset($job->param) ? [
							'data'	=> $job->param,
							] : []),
						]);

					// if request was basically ok
					if($curl_obj->httpcode == 200){

						// if content is json
						if($curl_obj->contenttype == "text/json; charset=utf-8"){

							// convert json to res object
							$res = json_decode($curl_obj->content);
							}

						// else
						else{

							// define res object
							$res = self::response(500, 'Cannot use contenttype '.$curl_obj->contenttype.' in servercom response for '.$job->fn.' on '.h::encode_php($job->servercom));
							}
						}
					// else
					else{

						// define res object
						$res = self::response(500, 'Servercom request ends in httpcode '.$curl_obj->httpcode.' for '.$job->fn.' on '.h::encode_php($job->servercom));
						}
					}

				// else normal processing
				else{

					// execute job on this server
					$res = isset($job->param) ? call_user_func($job->fn, $job->param) : call_user_func($job->fn);
					}

				// save time
				$job_finishtime = microtime(true);

				// define server
				$server = $job->servercom ? $job->servercom : 'localhost';

				// count function response status
				if(!isset($result[$server])) $result[$server] = [];
				if(!isset($result[$server][$job->fn])) $result[$server][$job->fn] = [];
				if(!isset($result[$server][$job->fn][$res->status])) $result[$server][$job->fn][$res->status] = 0;
				$result[$server][$job->fn][$res->status]++;

				// after processing, reinit redis (connection could be lost if job function needs to long)
				$redis = self::redis();

				// if redis not accessable anymore, abort here
				if(!$redis) break;

				// calculate job runtime
				$job_runtime = $job_finishtime - $job_starttime;

				// if job takes unusual long
				if(isset($opt['log_intense']) and $job_runtime > $opt['log_intense']){

					// log error
					e::logtrigger('BENCH: intense redisjob detected: '.$job->fn.' ('.round($job_runtime * 1000, 2).' ms)');
					}
				}

			// if minimal lifetime is define and max_run is reached
			if($i == $max_run and !empty($opt['min_lifetime']) and time() < $opt['min_lifetime']){

				// sleep a second
				sleep($opt['sleep_seconds'] ?? 1);

				// load config
				$res = self::get_config();

				// if config could not be loaded
				if($res->status != 200) return self::response(500, 'Cronjob runtime config could not be loaded: '.$res->status);

				// take config
				$config = $res->data;

				// abort here, if redisjob is set on stop
				if($config->redisjob_stop) return self::response(200, $result);

				// increment max runs with length of all lists
				$max_run += max($redis->lSize($cache_key_lvl[1]) + $redis->lSize($cache_key_lvl[2]) + $redis->lSize($cache_key_lvl[3]), 1);
				}
			}

		// return success
		return self::response(200, $result);
		}

	public static function readd_fallback_redisjob($req = []){

		// define fallback cache key
		$fallback_cache_key = 'job:fallback:redispipe';

		// init normal redis connection (only to check if it is available)
		$redis = self::redis();

		// init fallback connection
		$fallback_redis = self::redis_fallback();

		// if redis not available
		if(!$redis and !$fallback_redis){

			// abort with error
			return self::response(503);
			}

		// define result
		$stat = (object)[
			'found'		=> 0,
			'invalid'	=> 0,
			'readded'	=> 0,
			'aborted'	=> 0,
			];

		// while there are saved redisjobs
		while($fallback_redis->lSize($fallback_cache_key)){

			// get and remove first entry in list
			$job = $fallback_redis->lPop($fallback_cache_key);

			// if job has no level defined
			if(!isset($job->lvl)){

				// count
				$stat->invalid++;

				// skip job
				continue;
				}

			// count
			$stat->found++;

			// define cache key (considering level of importance)
			$cache_key = ($job->lvl == 1) ? 'job:redispipe' : 'job:redispipe:'.$job->lvl;

			// append entry to redis pipe
			$added = ($redis->rPush($cache_key, $job) !== false);

			// if job could not added back as redisjob
			if(!$added){

				// re append job at end of fallback list
				$fallback_redis->rPush($fallback_cache_key, $job);

				// count
				$stat->aborted++;

				// abort further processing
				break;
				}

			// count
			$stat->readded++;
			}

		// return result
		return self::response(200, $stat);
		}

	public static function add_common_redisjob($req = []){

		// mandatory
		$mand = h::eX($req, [
			'redisjob_fn'			=> '~^[a-zA-Z0-9\\\_\:]{1,255}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'redisjob_start'		=> '~U/d',
			'redisjob_lvl'			=> '~1,3/i',
			'redisjob_abort_not'	=> '~sequential/a',
			'redisjob_retry_on'		=> '~sequential/a',
			'redisjob_max_try'		=> '~1,100/i',
			'redisjob_try'			=> '~1,100/i',
			], $error, true);

		// additional check
		if(isset($mand['redisjob_fn']) and !is_callable($mand['redisjob_fn'])) $error[] = 'redisjob_fn';

		// on error
		if($error) return self::response(400, $error);

		// convert $req to array
		$req = (array) $req;

		// define defaults
		$opt += [
			'redisjob_start'		=> null,
			'redisjob_lvl'			=> 1,
			'redisjob_abort_not'	=> [],
			'redisjob_retry_on'		=> [],
			'redisjob_max_try'		=> 5,
			];

		// if redisjob_try is not defined, create job first
		if(!isset($opt['redisjob_try'])){

			// remove param from $req
			unset($req['redisjob_start']);

			// create job
			$res = cronjob::add_redisjob([
				'fn'			=> '\\'.__METHOD__,
				'param'			=> [
					'redisjob_try'	=> 1,
					] + $req,
				'lvl'			=> $opt['redisjob_lvl'],
				'start_at'		=> $opt['redisjob_start'],
				]);

			// return success
			return self::response(204);
			}

		// copy request param
		$fn_req = $req;

		// remove special param
		foreach($mand+$opt as $key => $val){

			// unset param
			unset($fn_req[$key]);
			}

		// and execute job with request param
		$res = call_user_func($mand['redisjob_fn'], $fn_req);

		// on unexpected error
		if($opt['redisjob_abort_not'] and !in_array($res->status, $opt['redisjob_abort_not'])){

			// abort with error
			return self::response(500, 'Delayed '.$mand['redisjob_fn'].' aborted: '.$res->status.' ('.h::encode_php($fn_req).')');
			}

		// if session could not be found
		if($opt['redisjob_retry_on'] and in_array($res->status, $opt['redisjob_retry_on'])){

			// if max retry reached
			if($opt['redisjob_try'] > $opt['redisjob_max_try']){

				// abort with error
				return self::response(500, 'Delayed '.$mand['redisjob_fn'].' max retry '.$opt['redisjob_max_try'].' reached: '.$res->status.' ('.h::encode_php($fn_req).')');
				}

			// put back job
			$res = cronjob::add_redisjob([
				'fn'		=> '\\'.__METHOD__,
				'param'		=> [
					'redisjob_try'	=> $opt['redisjob_try']+1,
					] + $req,
				'lvl'		=> $opt['redisjob_lvl'],
				'start_at'	=> '+'.$opt['redisjob_try'].'0 sec',
				]);
			}

		// return success
		return self::response(204);
		}


	/* test function for jobs */
	public static function test_fn($req = []){

		// optional
		$opt = h::eX($req, [
			'sleep_seconds'	=> '~1,60/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if defined, sleep
		if(isset($opt['sleep_seconds'])) sleep($opt['sleep_seconds']);

		// log function
		e::logtrigger('TEST: test_fn triggered with param: '.h::encode_php($req));

		// return success
		return self::response(204);
		}

	}
