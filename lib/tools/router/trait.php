<?php
/*****
 * Version 1.3.2015-04-20
**/
namespace tools;

use \tools\helper as h;
use \tools\error as e;

trait router_trait {

	protected $router_output;
	protected $router_type = 'html'; // Default Content Type
	protected $router_xsendfile;
	protected $router_code = 404; // Default HTTP-Code
	protected $router_request = '/';
	protected $router_dispatch_history = [];
	protected $router_add_postdata_to_param = true;
	protected $router_force_download = false;

	protected function router_dispatch($url){

		$this->router_request = $url;

		$this->router_test();

		$code_nocontent = [
			201 => 'Created', 202 => 'Accepted', 204 => 'No Content',
			400 => 'Bad Request', 403 => 'Forbidden', 404 => 'Not Found',
			500 => 'Internal Server Error', 503 => 'Service Temporarily Unavailable'
			];
		$code_withcontent = [
			200 => 'OK',
			403 => 'Forbidden', 409 => 'Conflict', 423 => 'Locked'
			];
		$code_redirect = [
			301 => 'Moved Permanently', 302 => 'Found'
			];

		if($this->router_code == 0){
			// No Header, cause Header already sent
			return true;
			}
		elseif(isset($code_nocontent[$this->router_code])){
			header('HTTP/1.1 '.$this->router_code.' '.$code_nocontent[$this->router_code]);
			}
		elseif(isset($code_withcontent[$this->router_code])){
			header('HTTP/1.1 '.$this->router_code.' '.$code_withcontent[$this->router_code]);

			// Postprocessing
			if($this->router_xsendfile){
				header('Content-Type: '.http::mimetype($this->router_type).';');
				if($this->router_force_download) header('Content-Disposition: attachment;');
				header('X-Accel-Redirect: '.$this->router_xsendfile);
				}
			else{
				header('Content-Type: '.http::mimetype($this->router_type).'; charset=utf-8');

				// Ggf. JSON kodieren
				if($this->router_type == 'json' and !is_string($this->router_output)) $this->router_output = json_encode($this->router_output);
				// Oder Non-String konvertieren
				elseif($this->router_type == 'txt' and !is_string($this->router_output)) $this->router_output = h::encode_php($this->router_output);
				// Oder CSV generieren
				elseif($this->router_type == 'csv' and is_array($this->router_output) and !empty($this->router_output)){
					$csv = '';
					$kpos = array_keys(reset($this->router_output));
					if(empty($kpos)) return e::trigger('Router cannot convert output to csv');
					foreach($this->router_output as $entry){
						$line = [];
						foreach($kpos as $key){
							$n = isset($entry[$key]) ? $entry[$key].'' : '';
							$n = str_replace([';',"\n"], ' ', $n);
							$line[] = $n;
							}
						$csv .= implode(';', $line)."\n";
						}
					$this->router_output = $csv;
					}

				// Content ausgeben, wenn möglich
				if(is_string($this->router_output)) echo $this->router_output;
				}
			return true;
			}
		elseif(isset($code_redirect[$this->router_code])){
			header('HTTP/1.1 '.$this->router_code.' '.$code_redirect[$this->router_code]);
			header('Location: '.$this->router_output);
			}
		else{
			header('HTTP/1.1 500 Internal Server Error');
			}
		return true;

		}

	protected function router_test(){
		$addPostdata = $this->router_add_postdata_to_param ? in_array($_SERVER['REQUEST_METHOD'], ['POST','PUT','PATCH']) : false;
		$callFn = function($fn, $params = []) use ($addPostdata){
			if($addPostdata) array_unshift($params, postdata::get());
			return call_user_func_array([$this, $fn], $params);
			};

		foreach($this->router_definition() as $method => $list) if(substr($method,0,strlen($_SERVER['REQUEST_METHOD'])) == $_SERVER['REQUEST_METHOD'] or (is_string($method) and $method[0] == '*')){
			foreach($list as $fn => $set){
				if(!is_array($set) or (isset($set[1]) and !is_array($set[1]))) $set = [$set];
				list($rule, $allowedType) = $set + [1=>null];
				$this->router_type = 'html'; // default
				$request = $this->router_request;
				$this->router_dispatch_history[$method.' '.$fn] = 'Try';

				// Suche nach definierter Extension
				if($allowedType){
					if(is_array($allowedType)) $allowedType = implode('|', $allowedType);
					elseif(!is_string($allowedType)) return e::trigger('router cannot parse type triggered by router_definition '.$fn.' in '.get_class($this));

					if(preg_match('/^(.*)\.('.$allowedType.')$/', $this->router_request, $match)) list(,$request,$this->router_type) = $match;
					else{
						$this->router_dispatch_history[$method.' '.$fn] = 'Type of url not matching '.$allowedType;
						continue;
						}
					}

				$r = null;
				if(is_bool($rule)){
					$r = $callFn($fn);
					if($rule === $r){
						$this->router_dispatch_history[$method.' '.$fn] = 'Continue with next dispatch';
						continue;
						}
					else{
						$this->router_dispatch_history[$method.' '.$fn] = 'Continue with next method';
						continue 2;
						}
					}
				elseif(is_string($rule)){
					if(!empty($rule) and $rule[0] === '~'){
						if(preg_match('/'.substr($rule, 1).'/', $request, $params)){
							array_shift($params);
							$r = $callFn($fn, $params);
							}
						}
					elseif($rule === '*' or $rule === $request){
						$r = $callFn($fn);
						}
					else{
						$this->router_dispatch_history[$method.' '.$fn] = 'String '.$rule.' does not trigger '.$request;
						continue;
						}
					}
				elseif(is_callable($rule)){
					$p = call_user_func_array($rule, ($addPostdata ? [$request, postdata::get()] : [$request]));
					if($p === true) $r = $callFn($fn);
					elseif(!empty($p) and is_array($p)) $r = $callFn($fn, $p);
					else{
						$this->router_dispatch_history[$method.' '.$fn] = 'Function does not trigger: '.h::encode_php($p);
						continue;
						}
					}
				else return e::trigger('router cannot parse rule triggered by router_definition '.$fn.' in '.get_class($this));

				$this->router_dispatch_history[$method.' '.$fn] = 'Triggered and returns: '.h::encode_php($r);
				if(is_bool($r)) return $r;
				}
			}

		return;
		}

	protected function router_definition(){
		return [];
		}

	protected function response($code, $content = null, $forceType = null){
		$this->router_code = $code;
		if($forceType) $this->router_type = $forceType;
		$this->router_output = is_callable($content) ? call_user_func($content) : $content;
		return true;
		}

	protected function response_file($code, $file, $forceType = null, $forceDownload = null){
		$this->router_code = $code;
		$this->router_xsendfile = $file;
		if($forceType) $this->router_type = $forceType;
		if($forceDownload) $this->router_force_download = true;
		return true;
		}

	protected function response_ob($code, $content, $postFn = null, $forceType = null){
		ob_start();
		echo is_callable($content) ? call_user_func($content) : $content;
		$content = $postFn !== null ? call_user_func($postFn, ob_get_clean()) : ob_get_clean();
		return $this->response($code, $content, $forceType);
		}

	}
