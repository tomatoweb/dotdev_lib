<?php
/*****
 * Version 1.2.2015-12-22
**/
namespace tools;

use \tools\helper as h;
use \tools\error as e;

class pdo_cache {

	// connection functions
	public static function dsn_subpath($new_subpath = null){

		static $subpath;

		// set custom subpath
		if(is_string($new_subpath) and $new_subpath){

			if(substr($new_subpath, 0, 1) === '/') $new_subpath = substr($new_subpath, 1);

			$subpath = $_SERVER['ENV_PATH'].'/config/php/pdo/'.$new_subpath;
			}

		// set default subpath
		elseif($new_subpath === true or $subpath === null){

			$subpath = $_SERVER['PDO_PATH'];
			}

		return $subpath;
		}

	public static function get_file_config($setname){

		static $cache = [];

		if(!isset($cache[$setname])){

			$setfile = self::dsn_subpath().'/'.$setname.'.php';

			if(!file_exists($setfile)){
				return self::error('PDO '.$setname.' - Config file not found. ('.$setfile.')');
				}

			$data = include($setfile);
			$new = (object)[
				'dsn'		=> null,
				'user'		=> null,
				'password'	=> null,
				'attributes'=> [],
				];

			// if data is a sequential array
			if(h::is($data, '~sequential/a')){
				list($new->dsn, $new->user, $new->password) = $data + [null, null, null];
				}

			// if data is a collection
			elseif(h::is($data, '~/c')){
				$new->dsn = h::gX($data, 'dsn');
				$new->user = h::gX($data, 'user');
				$new->password = h::gX($data, 'password');
				$new->attributes = h::gX($data, 'attributes');
				if(!is_array($new->attributes)) $new->attributes = [];
				}

			// check if everything is given
			if(empty($new->dsn) or empty($new->user) or empty($new->password)){
				return self::error('PDO '.$setname.' - Config file seems invalid. ('.$setfile.')');
				}

			// cache it
			$cache[$setname] = $new;
			}

		return $cache[$setname];
		}

	public static function connection($setname, $reset = false){

		static $cache = [];

		// reset instance
		if(isset($cache[$setname]) and $reset){
			$cache[$setname]->instance = null;
			}

		// create new set
		elseif(!isset($cache[$setname])){

			// get config from dsn file
			$config = self::get_file_config(strpos($setname, ':') ? explode(':', $setname)[0] : $setname);
			if(!$config) return false;

			// create set object
			$new = (object) [
				'name'			=> $setname,
				'dsn'			=> $config->dsn,
				'user'			=> $config->user,
				'password'		=> $config->password,
				'statement'		=> [],
				'instance'		=> null,
				'attributes'	=> $config->attributes + [
				//	\PDO::ATTR_PERSISTENT				=> true,
					\PDO::ATTR_ERRMODE					=> \PDO::ERRMODE_EXCEPTION, // \PDO::ERRMODE_SILENT
					\PDO::ATTR_EMULATE_PREPARES			=> false,
					\PDO::ATTR_ORACLE_NULLS				=> \PDO::NULL_NATURAL,
					\PDO::ATTR_DEFAULT_FETCH_MODE		=> \PDO::FETCH_OBJ,
					\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY	=> false,
				//	\PDO::MYSQL_ATTR_INIT_COMMAND		=> "SET NAMES ".$charset
					]
				];

			// append extra config
			if(!empty($_SERVER['PDO_CONNECTION_TIMEOUT'])){
				$new->attributes[\PDO::MYSQL_ATTR_INIT_COMMAND] = "SET `wait_timeout` = ".(int) $_SERVER['PDO_CONNECTION_TIMEOUT'];
				}

			// cache set
			$cache[$setname] = $new;
			}

		// return set
		return $cache[$setname];
		}


	// error functions
	public static function error($str = null, $q = null){
		static $e_str = '';
		static $e_query = '';

		if(is_string($str)){
			$e_str = $str;
			$e_query = $q;
			return false;
			}

		return $e_str.($e_query ? ' ("'.$e_query.'")' : '');
		}

	public static function stmt_error($connection, $error, $query){

		if(is_array($error)){
			return self::error('PDO '.$connection->name.' - '.implode(' | ', $error), $query); //  (isset($error[2]) ? $error[2].' ('.$error[0].' / '.$error[1].')' : $error[0])
			}

		elseif(is_object($error)){
			return self::error('PDO '.$connection->name.' - '.$error->getMessage(), $query);
			}

		return self::error('PDO '.$connection->name.' - '.$query, $q);
		}


	// query statement functions
	public static function generate_statement($query, $trim = false){

		// trim
		if($trim){
			$query = trim(str_replace("\n"," ",str_replace(" \n"," ",str_replace('	','',$query))));
			}

		// identify type
		$str6 = strtolower(substr($query, 0, 6));
		if($str6 == 'select') $type = preg_match('/limit\s1$/i', $query) ? 'selectOne' : 'selectAll';
		elseif($str6 == 'insert') $type = 'insert';
		else $type = 'other';

		// append
		return (object)[
			'query' 	=> $query,
			'resource'	=> null,
			'type'		=> $type,
			];
		}

	public static function extract_querystring($setname, $query){

		// get connection
		$connection = self::connection($setname);
		if(!$connection) return false;

		// if not found
		if(!isset($connection->statement[$query])){
			return e::trigger('PDO '.$connection->name.' - Query '.$query.' not found in statement');
			}

		// return statement query
		return $connection->statement[$query]->query;
		}


	// main function
	public static function get_instance($setname){

		// get connection
		$connection = self::connection($setname);
		if(!$connection) return false;

		// if connection is not prepared
		if($connection->instance === null){

			// connect
			try{
				$connection->instance = new \PDO($connection->dsn, $connection->user, $connection->password, $connection->attributes);
				}

			// catch pdo errors
			catch(\PDOException $e){
				return self::error('PDO '.$connection->name.' - '.$e->getMessage());
				}

			// catch standard error
			catch(\Exception $e){
				return self::error('PDO '.$connection->name.' - '.$e->getMessage());
				}
			}

		return $connection->instance;
		}

	public static function get_statement($setname, $query, $nocache = false){

		// get connection
		$connection = self::connection($setname);
		if(!$connection) return false;

		// if temporary query
		if(!isset($connection->statement[$query])){

			// generade statement
			$statement = self::generate_statement($query, true);

			// cache it, if wanted
			if(!$nocache){
				$connection->statement[$query] = $statement;
				}
			}

		// or this is a predefined query (which means a cacheable query)
		else{
			$statement = $connection->statement[$query];
			}

		// if resource is not prepared
		if(!isset($statement->resource)){

			// if connection is not prepared
			if($connection->instance === null){

				// connect
				try{
					$connection->instance = new \PDO($connection->dsn, $connection->user, $connection->password, $connection->attributes);
					}

				// catch pdo errors
				catch(\PDOException $e){
					return self::error('PDO '.$connection->name.' - '.$e->getMessage(), $statement->query);
					}

				// catch standard error
				catch(\Exception $e){
					return self::error('PDO '.$connection->name.' - '.$e->getMessage(), $statement->query);
					}
				}

			// prepare statement
			try{
				$statement->resource = $connection->instance->prepare($statement->query);
				}

			// catch pdo error
			catch(\PDOException $e){
				return self::stmt_error($connection, $e, $statement->query);
				}

			// catch standard error
			catch(\Exception $e){

				// if server is lost
				if(strpos($e->getMessage(), 'server has gone away') !== false){

					// create a new connection
					try{
						$connection->instance = new \PDO($connection->dsn, $connection->user, $connection->password, $connection->attributes);
						}

					// catch pdo error
					catch(\PDOException $e){
						return self::error('PDO '.$connection->name.' - '.$e->getMessage(), $statement->query);
						}

					// catch standard error
					catch(\Exception $e){
						return self::error('PDO '.$connection->name.' - '.$e->getMessage(), $statement->query);
						}

					// retry preparing statement
					try{
						$statement->resource = $connection->instance->prepare($statement->query);
						}

					// catch pdo error
					catch(\PDOException $e){
						return self::stmt_error($connection, $e, $statement->query);
						}

					// catch standard error
					catch(\Exception $e){
						return self::error('PDO '.$connection->name.' - '.$e->getMessage(), $statement->query);
						}

					}

				// or simply log error
				else{
					return self::error('PDO '.$connection->name.' - '.$e->getMessage(), $statement->query);
					}
				}
			}

		return $statement;
		}

	public static function query($setname, $query, $param = [], $return_stmt = false, $nocache = false){

		// get connection
		$connection = self::connection($setname);
		if(!$connection) return false;

		// get statement
		$statement = self::get_statement($setname, $query, $nocache);
		if(!$statement) return false;

		// convert object to array
		if(is_object($param)){
			$param = (array) $param;
			}

		// convert everything other to the first value in array
		if(!is_array($param) and $param !== null){
			$param = [$param];
			}

		// basically check every key/val
		if(is_array($param)){
			foreach($param as $key => $val){
				if(is_object($val) or is_array($val)){
					return self::error('PDO '.$connection->name.' - Invalid parameter '.$key.' with value '.h::encode_php($val), $statement->query);
					}
				}
			}

		// try to execute statement
		try{
			$result = ($param === null) ? $statement->resource->execute() : $statement->resource->execute($param);
			if(!$result){
				return self::stmt_error($connection, $statement->resource->errorInfo(), $statement->resource->queryString);
				}
			}

		// catch pdo error
		catch(\PDOException $e){
			return self::stmt_error($connection, $e, $statement->resource->queryString);
			}

		// catch standard error
		catch(\Exception $e){
			return self::error('PDO '.$connection->name.' - '.$e->getMessage(), $statement->query);
			}

		// return statement directly, if wanted
		if($return_stmt){
			return $statement->resource;
			}

		// return result for all rows (array of objects)
		if($statement->type == 'selectAll'){
			return $statement->resource->fetchAll();
			}

		// return result for one row (object only)
		elseif($statement->type == 'selectOne'){
			$result = $statement->resource->fetch();
			$statement->resource->closeCursor();
			return $result ? $result : null;
			}

		// return result for insert (primary key or null)
		elseif($statement->type == 'insert'){
			return $connection->instance->lastInsertId();
			}

		// return result for delete and update (affected row count)
		elseif($statement->type == 'delete' or $statement->type == 'update'){
			return $statement->resource->rowCount();
			}

		// for everything else, return true
		return true;
		}


	// DEPRECATED
	public static function change_dsn_subpath($subpath){
		return self::dsn_subpath($subpath);
		}
	}
