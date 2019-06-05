<?php
/*****
 * Version 1.0.2017-10-09
**/
namespace dotdev\misc;

use \tools\error as e;
use \tools\helper as h;
use \tools\http;

class tools {

	use \tools\libcom_trait;

	/* phplog cleaner */
	public static function clean_log_by_string($req = []) {

		// mandatory
		$mand = h::eX($req, [
			'path'				=> '~^.*\/[^\/]*$',
			'file_name'			=> '~^[a-zA-z0-9\-\_]{1,64}\.(?:txt|log)$',
			'search_string'		=> '~/s',
			], $error);

		// on error
		if($error) return self::response(400, $error);

		// add / at the end or start of path string if missing
		if(substr($mand['path'], -1) != '/') $mand['path'] .= '/';
		if(substr($mand['path'], 1) != '/') $mand['path'] = '/'.$mand['path'];

		// get paths
		$data_path = $_SERVER['DATA_PATH'].'/';
		$file_path = $mand['path'].$mand['file_name'];

		// final (relative) path with file
		$final_path = $data_path.'..'.$file_path;

		// check if path and file exists
		if(!file_exists($final_path)) return self::response(404, 'path/file not found');

		// create the regex string for preg_match
		$regex = '~^.*(?:'.preg_quote($mand['search_string']).').*$~';

		$handle = fopen($final_path, 'r');

		$array = [];
		$count_lines = 0;
		$count_insert = 0;

		if($handle) {

			// read line for line and write into array if pregmatch dont match (exclude)
			while (($line = fgets($handle)) !== false) {
				$count_lines++;
				if(!preg_match($regex, $line)) {
					$count_insert++;
					$array[] = $line;
					}
				}

			// close
			fclose($handle);
			}
		else {

			// return on error
			return self::response(400);
			}

		// edit file
		$new_file = fopen($final_path, 'w');

		// return on error
		if(!$new_file) return self::response(400, 'unable to edit file');

		// for each line write into new file
		foreach($array as $line) {
			fwrite($new_file, $line);
			}

		$result = (object) [
			'file name'					=> $mand['file_name'],
			'total lines of file'		=> $count_lines,
			'new file inserts'			=> $count_insert,
			];

		// return result
		return self::response(200, $result);

		}

	/* img serie downloader */
	public static function img_serie_downloader($req = []){

		// mandatory
		$mand = h::eX($req, [
			'url'				=> '~1,1000/s',
			'saveto'			=> '~1,1000/s',
			'saveto_subpath'	=> '~5,1000/s',
			'inserie_start'		=> '~0,99999998/i',
			], $error);

		// optional
		$opt = h::eX($req, [
			'inserie_end'		=> '~1,99999999/i',
			'inserie_pad'		=> '~0,10/i',
			'series_start'		=> '~0,99999998/i',
			'series_end'		=> '~1,99999999/i',
			'series_pad'		=> '~0,10/i',
			'series'			=> '~/l',
			'skip_after_retry'	=> '~0,1000/i',
			'save_stop'			=> '~1,999999/i',
			'cookie'			=> '~/l',
			'useragent'			=> '~0,2000/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// check start is lower than until
		if(isset($opt['inserie_end']) and $mand['inserie_start'] > $opt['inserie_end']) return self::response(400, ['inserie_start', 'inserie_end']);

		// check saveto path
		if(!is_dir($mand['saveto'])) return self::response(400, ['saveto']);

		// check series_start and series_end
		if(isset($opt['series_start']) and isset($opt['series_end']) and ($opt['series_start'] > $opt['series_end'] or isset($opt['series']))) return self::response(400, ['series_start', 'series_end']);

		// check series array
		if(isset($opt['series'])){

			// convert to array
			if(is_object($opt['series'])) $opt['series'] = (array) $opt['series'];
			}

		// define default
		$opt += [
			'inserie_end'		=> false,
			'inserie_pad'		=> 0,
			'series'			=> [],
			'serie_pad'			=> 0,
			'skip_after_retry'	=> 0,
			'save_stop'			=> 10000,
			'cookie'			=> null,
			'useragent'			=> null,
			];

		// fill series, if needed
		if(isset($opt['series_start']) and isset($opt['series_end'])){

			// run from start to end
			for($i = $opt['series_start']; $i <= $opt['series_end']; $i++){

				// add special url
				$opt['series'][] = $opt['inserie_pad'] ? str_pad($i, $opt['series_pad'], '0', STR_PAD_LEFT) : $i;
				}
			}

		// if no serie define yet
		if(empty($opt['series'])){

			// fill single empty serie
			$opt['series'][] = '';
			}

		// define result
		$result = (object)[
			'files_saved'	=> 0,
			'files_failed'	=> 0,
			'files_404'		=> 0,
			'log'			=> [],
			];

		// first define to stop loop
		$stop_loop = false;

		// define actual serie
		$serie_key = key($opt['series']);
		$serie = array_shift($opt['series']);
		$inserie_pos = $mand['inserie_start'];
		$retries = $opt['skip_after_retry'];

		// run loop
		do{

			// decrement save stop counter
			$opt['save_stop']--;

			// define some replacement values
			$replace = [
				'{serie_key}'			=> $serie_key,
				'{serie}'				=> $serie,
				'{inserie_pos}'			=> $opt['inserie_pad'] ? str_pad($inserie_pos, $opt['inserie_pad'], '0', STR_PAD_LEFT) : $inserie_pos,
				'{inserie_pos_unpadded}'=> $inserie_pos,
				];

			// create actual url
			$request_url = h::replace_in_str($mand['url'], $replace);

			// create save_path and file
			$save_path = h::replace_in_str($mand['saveto'].$mand['saveto_subpath'], $replace);
			$save_path_struktur = explode('/', $save_path);
			$save_file = end($save_path_struktur);
			$save_path = substr($save_path, 0, (strlen($save_file) + 1) * -1);

			// send request
			$curl_obj = http::curl_obj([
				'url' 		=> $request_url,
				'method'	=> 'GET',
				'cookie'	=> $opt['cookie'],
				'useragent'	=> $opt['useragent'],
				]);

			// define file is saved
			$saved = false;

			// increment stat
			$result->files_failed++;

			// if image is found
			if($curl_obj->httpcode == 200 and strlen($curl_obj->content) > 100){

				// check for directory
				if(!is_dir($save_path)) mkdir($save_path, 0755, true);

				// save file
				$saved = file_put_contents($save_path.'/'.$save_file, $curl_obj->content);

				// if saved, increment stat
				if($saved) $result->files_saved++;

				// else, increment stat
				else $result->files_failed++;
				}

			// else
			else {

				// decrement retries
				$retries--;

				// increment stat
				$result->files_404++;
				}

			// add to result
			$result->log[] = (object)[
				'url'		=> $request_url,
				'httpcode'	=> $curl_obj->httpcode,
				'path'		=> $save_path,
				'file'		=> $save_file,
				'saved'		=> $saved,
				];

			// increment to next step
			$inserie_pos++;

			// check if we reached past series end or retries fall below 0
			if(($opt['inserie_end'] and $inserie_pos > $opt['inserie_end']) or $retries < 0){

				// check if there is a next serie
				if(!empty($opt['series'])){

					// take new serie and reset serie_pos
					$serie_key = key($opt['series']);
					$serie = array_shift($opt['series']);
					$inserie_pos = $mand['inserie_start'];
					$retries = $opt['skip_after_retry'];
					}

				// else
				else {

					// stop further processing
					$stop_loop = true;
					}
				}

			// define to stop if save_stop is reached or retries fall below 0
			if($opt['save_stop'] <= 0 or $retries < 0) $stop_loop = true;

			// define to continue loop
			} while(!$stop_loop);

		// return result
		return self::response(200, $result);
		}

	}