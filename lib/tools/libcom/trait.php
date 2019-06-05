<?php
/*****
 * Version 1.0.2016-06-27
**/
namespace tools;

use \tools\helper as h;
use \tools\error as e;
use \tools\pdo_cache;

trait libcom_trait {

	protected static function response($status, $data = null){

		// LibCom 400
		if($status == 400){
			if(!(is_string($data) and substr($data, 0, 28) !== 'Missing or invalid parameter')){
				$data = is_array($data) ? 'Missing or invalid parameter '.h::encode_php($data) : h::encode_php($data);
				}
			e::logtrigger('LibCom '.$status.': '.$data, E_USER_NOTICE, 2);

			return (object) ["status"=>400, "error"=>$data];
			}

		// LibCom 500-504
		if(in_array($status, [500,501,502,503,504])){
			if(is_string($data)){
				e::logtrigger('LibCom '.$status.': '.$data, E_USER_ERROR, 2);
				}
			return (object) ["status"=>$status, "error"=>$data];
			}

		// LibCom 500/560
		if($status == 560){
			$error = pdo_cache::error() ?: 'Error triggered, but PDO returns none';
			e::logtrigger('LibCom 500/560: '.$error, E_USER_ERROR, 2);

			return (object) ["status"=>500, "error"=>'DB Error (see log)'];
			}

		// LibCom 500/570
		if($status == 570){
			if(is_object($data)){
				if(isset($data->error) and substr($data->error, 0, 16) === 'LibCom 500/570: ') $data->error = substr($data->error, 16);
				$data = (isset($data->status) ? $data->status : '0').' '.(isset($data->error) ? $data->error : 'undefined');
				}
			elseif(is_string($data) and substr($data, 0, 16) === 'LibCom 500/570: '){
				$data = substr($data, 16);
				}
			e::logtrigger('LibCom 500/570: '.$data, E_USER_NOTICE, 2);

			return (object) ["status"=>500, "error"=>$data];
			}

		// LibCom every other statuscode
		return $data !== null ? (object) ["status"=>$status, "data"=>$data] : (object) ["status"=>$status];
		}

	}