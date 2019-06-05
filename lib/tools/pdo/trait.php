<?php
/*****
 * Version 1.2.2016-06-27
**/
namespace tools;

use \tools\helper as h;
use \tools\error as e;
use \tools\pdo_cache;

trait pdo_trait {

	/* PDO functions */
	protected static function pdo_connection($reinit = false){

		static $connection;

		// if already initialized
		if($connection !== null and !$reinit){
			return $connection;
			}

		// get class config
		list($setname, $list) = self::pdo_config();

		// get connection
		$connection = pdo_cache::connection($setname);

		// if connection failes, return it here
		if(!$connection) return $connection;

		// run query list
		foreach($list as $key => $query){

			// if query is an array, process it
			if(is_array($query)){

				// check if invalid
				if(!is_string($query[0]) or !is_array($query[1]) or !is_string($list[$query[0]])){
					e::trigger('Invalid fork for query '.$key);
					unset($list[$key]);
					continue;
					}

				// directly replace query in list
				$list[$key] = str_replace(array_keys($query[1]), $query[1], $list[$query[0]]);
				}

			// append statement
			$connection->statement[$key] = pdo_cache::generate_statement($list[$key], true);
			}

		// success
		return $connection;
		}

	protected static function pdo($query, $param = [], $opt = []){

		// DEPRECATED
		if($opt === true) $opt = ['return_stmt'=>true];

		// define default
		$opt += ['return_stmt'=>false, 'no_cache'=>false];

		// get connection
		$connection = self::pdo_connection();
		if(!$connection) return false;

		// query
		return pdo_cache::query($connection->name, $query, $param, $opt['return_stmt'], $opt['no_cache']);
		}

	protected static function pdo_extract($query, $replace = []){

		// get connection
		$connection = self::pdo_connection();
		if(!$connection) return false;

		// extract
		$query = pdo_cache::extract_querystring($connection->name, $query);
		return !empty($replace) ? h::replace_in_str($query, $replace) : $query;
		}


	/* PDO config dummy */
	protected static function pdo_config(){

		e::trigger('No Configuration for PDO Abstraction Trait found');
		return [null,null];
		}


	/* LibCom functions */
	public static function pdo_instance(){

		// get connection
		$connection = self::pdo_connection();
		if(!$connection) return self::response(560);

		// get instance
		$instance = pdo_cache::get_instance($connection->name);
		if(!$instance) return self::response(560);

		// return instance
		return self::response(200, $instance);
		}

	public static function pdo_statement($req = []){

		// mandatory
		$mand = h::eX($req, [
			'query'			=> '~/s'
			], $error);

		// optional
		$opt = h::eX($req, [
			'close_cursor'	=> '~/b',
			'no_cache'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default
		$opt += ['no_cache'=>false, 'close_cursor'=>true];

		// get connection
		$connection = self::pdo_connection();
		if(!$connection) return self::response(560);

		// get statement
		$statement = pdo_cache::get_statement($connection->name, $mand['query'], $opt['no_cache']);
		if(!$statement) return self::response(560);

		// this will prevent throwing a \PDOException on $statement->resource destruction, if $statment->resource->execute() is never called
		if($opt['close_cursor']) $statement->resource->closeCursor();

		// return statement
		return self::response(200, $statement);
		}

	public static function pdo_query($req = []){

		// mandatory
		$mand = h::eX($req, [
			'query'			=> '~/s'
			], $error);

		// optional
		$opt = h::eX($req, [
			'param'			=> '~/l',
			'return_stmt'	=> '~/b',
			'no_cache'		=> '~/b',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);

		// default
		$opt += ['param'=>[], 'error_if'=>false, 'return_stmt'=>false, 'no_cache'=>false];

		// get connection
		$connection = self::pdo_connection();
		if(!$connection) return self::response(560);

		// run query
		$result = pdo_cache::query($connection->name, $mand['query'], $opt['param'], $opt['return_stmt'], $opt['no_cache']);

		// check error
		if($result === false) return self::response(560);

		// return result
		return self::response(200, $result);
		}

	}
