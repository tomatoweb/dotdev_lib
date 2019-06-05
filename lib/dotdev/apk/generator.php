<?php
/*****
 * Version 1.0.2018-06-21
**/
namespace dotdev\apk;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \dotdev\cronjob;
use \dotdev\nexus\catlop as nexus_catlop;
use \dotdev\apk\share as apk_share;

class generator {
	use \tools\libcom_trait,
		\tools\redis_trait;

	/* Redis */
	public static function redis(){

		return redis::load_resource('mt_service');
		}


	/* object: preparation */
	public static function get_preparation($req = []){

		// mandatory
		$mand = h::eX($req, [
			'download_key'	=> '~^[a-z0-9]{40}$',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// define cache key
		$cache_key = 'prepared_apk_download:'.$mand['download_key'];

		// init redis
		$redis = self::redis();

		// if redis is not accessable
		if(!$redis) return self::response(500, 'Redis is not accessable for get_preparation ('.h::encode_php($mand).')');

		// if entry not exists
		if(!$redis->exists($cache_key)){

			// return not found
			return self::response(404);
			}

		// load entry
		$entry = $redis->get($cache_key);

		// return result
		return self::response(200, $entry);
		}

	public static function create_preparation($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'	=> '~^[a-z0-9_]{1,32}$',
			'pageID'	=> '~1,65535/i',
			'persistID'	=> '~0,18446744073709551615/i',
			'type'		=> '~^(?:download|update)$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'lifetime'	=> '~1,86400/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'lifetime'	=> 1800, // 30 min
			];

		// define key for download persistance (on persistID or loose on randomized string)
		$persist_key = $mand['persistID'] ?: h::rand_str(40);

		// define download key as hash of given information
		$download_key = sha1('a'.$mand['pageID'].'p'.$persist_key);

		// define cache key
		$cache_key = 'prepared_apk_download:'.$download_key;

		// init redis
		$redis = self::redis();

		// if redis is not accessable
		if(!$redis) return self::response(500, 'Redis is not accessable for create_preparation ('.h::encode_php($mand).')');

		// if entry already exists
		if($redis->exists($cache_key)){

			// return conflict (apk already prepared)
			return self::response(409, (object)['download_key' => $download_key]);
			}

		// define new entry
		$entry = (object)[
			'project'	=> $mand['project'],
			'pageID'	=> $mand['pageID'],
			'persistID'	=> $mand['persistID'],
			'type'		=> $mand['type'],
			'lifetime'	=> h::dtstr(time() + $opt['lifetime']),
			];

		// cache entry
		$success = $redis->set($cache_key, $entry, ['ex'=>$opt['lifetime'], 'nx']);

		// return success
		return self::response($success ? 201 : 409, (object)['download_key' => $download_key]);
		}

	public static function update_preparation($req = []){

		// mandatory
		$mand = h::eX($req, [
			'download_key'	=> '~^[a-z0-9]{40}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'generated_apk'	=> '~1,300/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define cache key
		$cache_key = 'prepared_apk_download:'.$mand['download_key'];

		// init redis
		$redis = self::redis();

		// if redis is not accessable
		if(!$redis) return self::response(500, 'Redis is not accessable for update_preparation ('.h::encode_php($mand).')');

		// load entry
		$entry = $redis->get($cache_key);

		// replace params
		foreach($opt as $k => $v){
			$entry->{$k} = $v;
			}

		// take ttl
		$ttl = $redis->ttl($cache_key);

		// update entry
		$success = $redis->set($cache_key, $entry, ['ex'=>$ttl]);

		// return success
		return self::response(204);
		}


	/* APK Generator */
	public static function generate_signed_apk($req = []){

		// mandatory
		$mand = h::eX($req, [
			'project'		=> '~^[a-z0-9_]{1,32}$',
			'savepath'		=> '~^[a-zA-Z0-9\-\_]{1,60}\/(?:[a-zA-Z0-9\-\_\/]{1,240}\/|)$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'pageID'		=> '~0,65535/i',
			'persistID'		=> '~0,18446744073709551615/i',
			'benchmark'		=> '~/b',
			'with_tsa'		=> '~/b',
			'compress'		=> '~/b',
			'ignore_status'	=> '~/b',
			'lifetime'		=> '~1,86400/i',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// define defaults
		$opt += [
			'lifetime'		=> 1800, // 30 min
			];

		// define benchmark
		$bench = [];

		// enabled benchmark, if option is set
		if(!empty($opt['benchmark'])) $bench['start'] = microtime(true);


		// load apk
		$res = nexus_catlop::get_apk([
			'project'	=> $mand['project'],
			]);

		// on error
		if($res->status != 200) return $res;

		// take apk
		$apk = $res->data;

		// if project status is maintenance, download is disabled (locked)
		if($apk->status == 'maintenance' and empty($opt['ignore_status'])) return self::response(423);

		// if project status is archive, download is disabled (gone)
		if($apk->status == 'archive' and empty($opt['ignore_status'])) return self::response(410);


		// define benchmark time
		if($bench) $bench['apk_loaded'] = microtime(true);


		// define source path
		$source_path = $_SERVER['DATA_PATH'].'/downloads/';
		$target_path = $_SERVER['DATA_PATH'].'/'.$mand['savepath'];


		// define path for generation
		$generate_target_path = $target_path;

		// if config should be added
		if($apk->config_to){

			// add directory-path of config file
			$generate_target_path .= substr($apk->config_to, 0, strrpos($apk->config_to, '/') ?: 0);
			}

		// check if path already exists
		if(is_dir($generate_target_path)) return self::response(409);


		// create path (incl. assets subdir)
		$cmd_result = @mkdir($generate_target_path, 0755, true);

		// on error
		if(!$cmd_result) return self::response(500, 'DEBUG: Failed to create path '.h::encode_php($generate_target_path).' for APK '.$mand['project'].' ('.h::encode_php($opt).')');

		// save generator_marker file to allow deletion of generated directory
		$cmd_result = file_put_contents($target_path.'marker_'.$mand['project'], time());

		// on error
		if(!$cmd_result) return self::response(500, 'DEBUG: Failed to create generator_marker file in path '.h::encode_php($generate_target_path).' for APK '.$mand['project'].' ('.h::encode_php($opt).')');

		// add redisjob for apk deletion (send request with servercom back to this server, who hosts the generated apk) (dont check $res for failures)
		$res = cronjob::add_redisjob([
			'fn'				=> '\\dotdev\\apk\\generator::delete_signed_apk',
			'param'				=> [
				'savepath'		=> $mand['savepath'],
				'marker'		=> 'marker_'.$mand['project'],
				],
			'lvl'				=> 3,
			'start_at'			=> time() + $opt['lifetime'],
			'servercom_ipv4'	=> $_SERVER['SERVER_ADDR'],
			]);


		// define benchmark time
		if($bench) $bench['path_prepared'] = microtime(true);


		// if config should be added
		if($apk->config_to){

			// load apk config
			$res = apk_share::get_config([
				'project'	=> $mand['project'],
				'pageID'	=> $opt['pageID'] ?? null,
				'persistID'	=> $opt['persistID'] ?? null,
				]);

			// on error
			if($res->status != 200) return $res;

			// take config
			$config = $res->data;

			// convert apk config to json
			$config_as_json = json_encode($config);

			// save config to
			$cmd_result = file_put_contents($target_path.$apk->config_to, $config_as_json);

			// on error
			if(!$cmd_result) return self::response(500, 'DEBUG: Failed to create '.h::encode_php($apk->config_to).' in path '.h::encode_php($generate_target_path).' for APK '.$mand['project'].' ('.h::encode_php($opt).')');

			// define benchmark time
			if($bench) $bench['config_prepared'] = microtime(true);
			}


		// copy unsigned apk file
		$cmd_result = copy($source_path.$apk->apk_file, $target_path.'source_'.$apk->download_as);

		// on error
		if(!$cmd_result) return self::response(500, 'DEBUG: Failed to copy '.h::encode_php($apk->apk_file).' for APK '.$mand['project'].' ('.h::encode_php($opt).')');

		// copy keystore file
		$cmd_result = copy($source_path.$apk->keystore_file, $target_path.'PlayStoreKeystore');

		// on error
		if(!$cmd_result) return self::response(500, 'DEBUG: Failed to copy '.h::encode_php($apk->keystore_file).' for APK '.$mand['project'].' ('.h::encode_php($opt).')');

		// define benchmark time
		if($bench) $bench['files_copied'] = microtime(true);


		// if config should be added
		if($apk->config_to){

			// use aapt to add config file to unsigned apk
			$cmd_result = shell_exec('cd '.$target_path.' && aapt add "'.$target_path.'source_'.$apk->download_as.'" "'.$apk->config_to.'" && cd -');

			// on error
			if(strpos($cmd_result, "'".$apk->config_to."'...") === false) return self::response(500, 'DEBUG: Failed to use aapt to add '.h::encode_php($apk->config_to).' for APK '.$mand['project'].': '.h::encode_php($cmd_result).' ('.h::encode_php($opt).')');

			// define benchmark time
			if($bench) $bench['config_added'] = microtime(true);
			}



		// define jarsigner command
		$jarsigner_cmd = 'jarsigner'
			.' -verbose'
			.' -sigalg MD5withRSA'
			.' -digestalg SHA1'
			.(!empty($opt['with_tsa']) ? '-tsa http://timestamp.digicert.com' : '')
			.' -keypass '.$apk->keypass
			.' -storepass '.$apk->storepass
			.' -keystore '.$target_path.'PlayStoreKeystore'
			.' '.$target_path.'source_'.$apk->download_as
			.' '.$apk->keystore_alias;

		// sign apk
		$cmd_result = shell_exec($jarsigner_cmd);

		// on error
		if(strpos($cmd_result, "jar signed") === false) return self::response(500, 'DEBUG: Failed to sign apk for APK '.$mand['project'].': '.h::encode_php($cmd_result).' ('.h::encode_php($opt).')');

		// define benchmark time
		if($bench) $bench['apk_signed'] = microtime(true);


		// optimize apk with zipalign
		$cmd_result = shell_exec('zipalign -f 4 '.$target_path.'source_'.$apk->download_as.' '.$target_path.$apk->download_as);

		// on error
		if($cmd_result) return self::response(500, 'DEBUG: Failed to optimize apk for APK '.$mand['project'].': '.h::encode_php($cmd_result).' ('.h::encode_php($opt).')');

		// delete source file
		$cmd_result = shell_exec('rm '.$target_path.'source_'.$apk->download_as);

		// define benchmark time
		if($bench) $bench['apk_optimized'] = microtime(true);


		// if compression is defined
		if(!empty($opt['compress'])){

			// compress signed and optimized apk
			$cmd_result = file_put_contents($target_path.$apk->download_as.'.gz', gzencode(file_get_contents($target_path.$apk->download_as), 6));

			// on error
			if(!$cmd_result) return self::response(500, 'DEBUG: Failed to compress apk for APK '.$mand['project'].': '.h::encode_php($cmd_result).' ('.h::encode_php($opt).')');

			// define benchmark time
			if($bench) $bench['apk_compressed'] = microtime(true);
			}


		// define result
		$result = [
			'file'		=> $apk->download_as,
			'file_gz'	=> $apk->download_as.'.gz',
			'file_path'	=> $target_path,
			];

		// if benchmark is wanted
		if($bench){

			// take and remove start time
			$timepos = $startpos = $bench['start'];
			unset($bench['start']);

			// define benchmark in result
			$result['benchmark'] = [];

			// for each entry
			foreach($bench as $mark => $time){

				// calculate time
				$result['benchmark'][$mark] = round(($time - $timepos)* 1000, 2).' ms';

				// take time as new timepos
				$timepos = $time;
				}

			// calculate complete time
			$result['benchmark']['summarized'] = round(($timepos - $startpos)* 1000, 2).' ms';
			}

		// return result
		return self::response(200, (object) $result);
		}

	public static function delete_signed_apk($req = []){

		// mandatory
		$mand = h::eX($req, [
			'savepath'		=> '~^[a-zA-Z0-9\-\_]{1,60}\/(?:[a-zA-Z0-9\-\_\/]{1,240}\/|)$',
			'marker'		=> '~1,60/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// if marker file does not exist, return not found
		if(empty($_SERVER['DATA_PATH'])) return self::response(500, 'DEBUG: delete_signed_apk has no DATA_PATH');

		// define path
		$path = $_SERVER['DATA_PATH'].'/'.$mand['savepath'];

		// if marker file does not exist, return not found
		if(!is_file($path.$mand['marker'])) return self::response(404);

		// delete generated directory
		$cmd_result = shell_exec('rm -fR '.$path);

		// return success
		return self::response(204);
		}
	}
