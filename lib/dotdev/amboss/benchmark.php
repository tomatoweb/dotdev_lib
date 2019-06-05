<?php
/*****
 * Version 1.0.2019-04-11
**/
namespace dotdev\amboss;

use \tools\error as e;
use \tools\helper as h;

class benchmark {
	use \tools\libcom_trait,
		\tools\redis_trait;

	/* Redis */
	protected static function redis_config(){
		return 'mt_service';
		}

	/* set benchmark time */
	public static function set_benchmark_time($req = []){

		// mandatory
		$mand = h::eX($req, [
			'startTime'		=> '~/f',
			'key'			=> '~^(?:[a-zA-Z0-9\_]{1,32}(?:\:|)){1,10}$',
			], $error);

		// optional
		$opt = h::eX($req, [
			'endTime'		=> '~/f',
			'interval'		=> '~^(\+[0-9]{1,2} (day|hour|min)|none)$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// if endTime is not set
		if(!isset($opt['endTime'])){

			// now
			$opt['endTime'] = round(microtime(), 4);
			}

		// default
		$opt += [
			'interval'	=> ['+10 min', 'min'],
			];

		// init redis
		$redis = self::redis();

		// now
		$now = h::dtstr('now');

		// if redis is not accessable
		if(!$redis){

			// return error
			return self::response(500, 'Redis is not accessable');
			}

		// cache key
		$cache_key = 'amboss:benchmark:'.$mand['key'];

		// calc difference (microseconds)
		$diff = round($opt['endTime'] - $mand['startTime'], 4);


		// if interval none is set
		if($opt['interval'][0] == 'none'){

			// create new redis entry
			$entry = (object) [
				'key'		=> $cache_key,
				'interval'	=> 'none',
				'timeserie'	=> [
					$now	=> (object) [
						'count'		=> 1,
						'avg'		=> $diff,
						'peak_max'	=> $diff,
						'peak_min'	=> $diff,
						]
					]
				];

			// if cache key exists
			if($redis->exists($cache_key)){

				// get entry from redis
				$entry = $redis->get($cache_key);

				foreach($entry->timeserie as $obj){

					// increment count
					$obj->count++;

					// if diff bigger than peak_max
					if($diff > $obj->peak_max){

						// set peak max
						$obj->peak_max = $diff;
						}

					// if diff smaller than peak_min
					if($diff < $obj->peak_min){

						// set peak max
						$obj->peak_min = $diff;
						}

					// calc new avg
					$obj->avg = round($obj->avg + (($diff - $obj->avg) / $obj->count), 4);
					}
				}

			// save into redis
			$redis->set($cache_key, $entry, ['ex'=>604800]);	// expires 7 days after last update/set

			// process done
			return self::response(204);
			}

		// step range
		$step_range = $opt['interval'][0];

		// init times
		switch($opt['interval'][1]){
			case 'min':
				$from = h::date($now, null, 'Y-m-d H:00:00');
				$end_time = h::date($now, null, 'Y-m-d H:59:59');
				break;
			case 'hour':
				$from = h::date($now, null, 'Y-m-d H:00:00');
				$end_time = h::date($now, null, 'Y-m-d H:59:59');
				break;
			case 'day':
				$from = h::date($now, null, 'Y-m-d 00:00:00');
				$end_time = h::date($now, null, 'Y-m-d 23:59:59');
				break;
			}

		// run loop
		do{

			// calc and convert next "to" time
			$to = h::date($from.' '.$step_range.' -1 sec');
			$to = h::dtstr(h::dtstr($to) < $end_time ? $to : $end_time);

			// if current time is between
			if($now <= $to and $now >= $from){

				// create new redis entry
				$entry = (object) [
					'key'		=> $cache_key,
					'interval'	=> $step_range,
					'timeserie'	=> [
						$from	=> (object) [
							'count'		=> 1,
							'avg'		=> $diff,
							'peak_max'	=> $diff,
							'peak_min'	=> $diff,
							]
						]
					];

				// from redis and return
				if($redis->exists($cache_key)){

					// get entry from redis
					$entry = $redis->get($cache_key);

					// create new
					$create_new = true;

					// for each object in timeserie
					foreach($entry->timeserie as $k => $obj){

						// if key evals from
						if($k == $from){

							// increase count
							$obj->count++;

							// if diff bigger than peak_max
							if($diff > $obj->peak_max){

								// set peak max
								$obj->peak_max = $diff;
								}

							// if diff smaller than peak_min
							elseif($diff < $obj->peak_min){

								// set peak max
								$obj->peak_min = $diff;
								}

							// calc new avg
							$obj->avg = round($obj->avg + (($diff - $obj->avg) / $obj->count), 4);

							// create new is false
							$create_new = false;

							// break here
							break;
							}
						}

					// if we have to create a new entry
					if($create_new){

						// add new entry
						$entry->timeserie[h::dtstr($from)] = (object) [
							'count'		=> 1,
							'avg'		=> $diff,
							'peak_max'	=> $diff,
							'peak_min'	=> $diff,
							];
						}
					}

				// save in redis
				$redis->set($cache_key, $entry, ['ex'=>604800]);	// expires 7 days after last update/set

				// process done
				return self::response(204);
				}

			// take "to" as new "from" time
			$from = h::dtstr($to.' +1 sec');

			// loop as long as we do not reach end time
			} while ($to < $end_time);

		// process done
		return self::response(204);
		}

	/* get benchmark time */
	public static function get_benchmark_time($req = []){

		// mandatory
		$mand = h::eX($req, [
			'from'			=> '~Y-m-d H:i:s/d',
			'to'			=> '~Y-m-d H:i:s/d',
			], $error);

		// optional
		$opt = h::eX($req, [
			'key'			=> '~^(?:[a-zA-Z0-9\_]{1,32}(?:\:|)){1,10}$',
			'interval'		=> '~^(\+[0-9]{1,2} (day|hour|min)|none)$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// init redis
		$redis = self::redis();

		// default
		$opt += [
			'interval'	=> ['+10 min', 'min'],
			];

		// if redis is not accessable
		if(!$redis){

			// return error
			return self::response(500, 'Redis is not accessable');
			}

		// cache key
		$cache_key = 'amboss:benchmark:';

		// init times
		$from = $mand['from'];
		$end_time = h::date($mand['to']);

		// default
		$timeline = [];
		$list = null;

		// if key is set
		if(isset($opt['key'])){

			// default search
			$search = $cache_key.$opt['key'];

			// if last char is ':' get group
			if(substr($opt['key'], -1) == ':'){

				// prepare search
				$search = $cache_key.$opt['key'].'*';
				}

			// get entry
			$res = self::redis_get([
				'search'	=> $search,
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// list
			$list = $res->data;
			}

		// if list is not set from previous
		if(!$list){

			// get list of all entries
			$res = self::redis_get([
				'search'	=> $cache_key.'*',
				]);

			// on error
			if($res->status != 200) return self::response(570, $res);

			// list
			$list = $res->data;
			}

		// if interval is set as 'none', calculate sum avg for each key instead of going through timeline
		if($opt['interval'][0] == 'none'){

			// for each entry in list
			foreach($list as $key => $entry){

				// default
				$timeline_data = (object) [
					'name'		=> $key,
					'count'		=> 0,
					'avg'		=> 0,
					'peak_min'	=> 0,
					'peak_max'	=> 0,
					];

				// for each entry in timeserie
				foreach($entry->timeserie as $time => $obj){

					// increase count
					$timeline_data->count += $obj->count;

					// if diff bigger than peak_max
					if($obj->peak_max > $timeline_data->peak_max){

						// set peak max
						$timeline_data->peak_max = $obj->peak_max;
						}

					// if diff smaller than peak_min
					if($timeline_data->peak_min === 0 or $obj->peak_min < $timeline_data->peak_min){

						// set peak max
						$timeline_data->peak_min = $obj->peak_min;
						}

					// calc new avg
					$timeline_data->avg = round($timeline_data->avg + (($obj->avg - $timeline_data->avg) / $timeline_data->count), 4);
					}

				// append data
				$timeline[] = $timeline_data;
				}

			// return list/entry
			return self::response(200, $timeline);
			}

		// step range and format
		$format_list = ['day'=>'m-d', 'hour'=>'d. H\h', 'min'=>'H:i'];
		$step_range = $opt['interval'][0];
		$step_format = $format_list[$opt['interval'][1]];

		// run loop
		do{

			// calc and convert next "to" time
			$to = h::date($from.' '.$step_range.' -1 sec');
			$to = h::dtstr($to < $end_time ? $to : $end_time);

			// generate step
			$step = (object)[
				"name"	=> h::dtstr($from, $step_format),
				"time"	=> $from,
				];

			// for each entry in list
			foreach($list as $key => $entry){

				// skip entries with interval none
				if($entry->interval == 'none') continue;

				// default
				$timeline_data = (object) [
					'count'		=> 0,
					'avg'		=> 0,
					'peak_min'	=> 0,
					'peak_max'	=> 0,
					];

				// for each entry in timeserie
				foreach($entry->timeserie as $time => $obj){

					// if time is between
					if($time <= $to and $time >= $from){

						// increase count
						$timeline_data->count += $obj->count;

						// if diff bigger than peak_max
						if($obj->peak_max > $timeline_data->peak_max){

							// set peak max
							$timeline_data->peak_max = $obj->peak_max;
							}

						// if diff smaller than peak_min
						if($timeline_data->peak_min === 0 or $obj->peak_min < $timeline_data->peak_min){

							// set peak max
							$timeline_data->peak_min = $obj->peak_min;
							}

						// calc new avg
						$timeline_data->avg = round($timeline_data->avg + (($obj->avg - $timeline_data->avg) / $timeline_data->count), 4);
						}
					}

				// append data
				$step->{$key} = $timeline_data;
				}

			// add to result
			$timeline[] = $step;

			// take "to" as new "from" time
			$from = h::dtstr($to.' +1 sec');

			// loop as long as we do not reach end time
			} while (h::date($to) < $end_time);

		// return list/entry
		return self::response(200, $timeline);
		}

	/* reset benchmark time */
	public static function reset_benchmark_time($req = []){

		// optional
		$opt = h::eX($req, [
			'key'			=> '~^(?:[a-zA-Z0-9\_]{1,32}(?:\:|)){1,10}$',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// init redis
		$redis = self::redis();

		// if redis is not accessable
		if(!$redis){

			// return error
			return self::response(500, 'Redis is not accessable');
			}

		// cache key, search
		$cache_key = 'amboss:benchmark:';
		$search = $cache_key.'*';

		// if name is set
		if(isset($opt['key'])){

			// default search
			$search = $cache_key.$opt['key'];

			// if last char is ':' get group
			if(substr($opt['key'], -1) == ':'){

				// prepare search
				$search = $cache_key.$opt['key'].'*';
				}
			}

		// unset redis
		$res = self::redis_unset([
			'search'	=> $search,
			]);

		// on error
		if($res->status != 204) return self::response(570, $res);

		// process done
		return self::response(204);
		}

	}
