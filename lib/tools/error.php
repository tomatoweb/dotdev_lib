<?php
/*****
 * Version 1.1.2016-02-22
**/
namespace tools;

use \tools\helper as h;

class error {

	public static $registered = false;
	public static $fileshorten = '';
	public static $strict = true;
	public static $fnArr = [];
	protected static $header_sent = false;

	public static function register($fn = null, $fileshorten = null, $strict = null){
		if($fn !== null){
			if(is_array($fn)) foreach($fn as $n => $f){
				is_string($n) ? self::on($f, $n) : self::on($f);
				}
			else self::on($fn);
			}
		if($fileshorten !== null) self::$fileshorten = $fileshorten;
		if(is_bool($strict)) self::$strict = $strict;

		if(!self::$registered){
			ob_start();
			set_error_handler(__CLASS__.'::_errorhandler');
			register_shutdown_function(__CLASS__.'::_fatalerrorhandler');
			self::$registered = true;
			}
		}

	public static function _fatalerrorhandler(){
		$last_error = error_get_last();
		if($last_error['type'] === E_ERROR) self::dispatch(E_ERROR, $last_error['message'], $last_error['file'], $last_error['line']);
		}

	public static function _errorhandler($code, $desc, $file, $line){
		return $code & error_reporting() ? self::dispatch($code, $desc, $file, $line) : false;
		}

	protected static function dispatch($code, $desc, $file, $line, $args = null){
		$m = ($code === E_USER_NOTICE) ? false : (self::$strict or $code !== E_NOTICE);
		if($m and ob_get_length() !== false) ob_end_clean();

		if(!empty(self::$fileshorten)) $file = str_replace(self::$fileshorten, '', $file);

		// Error-Funktions-Stack aufrufen
		$http500 = true;
		foreach(self::$fnArr as $name => $fn){
			if(call_user_func($fn, $code, $desc, $file, $line, $args)) $http500 = false;
			}

		if($m){ // Wenn es kein abfangbarer Fehler ist
			if($http500 and !self::$header_sent) header('HTTP/1.1 500 Internal Server Error');
			exit;
			}

		return true; // true verhindert PHP-interne Fehlerbehandlung
		}

	/* Trigger Error */
	public static function trigger($desc = 'Error', $code = E_USER_ERROR, $tracelvl = 1){
		$d = self::call_trace(0)[$tracelvl];
		self::dispatch($code, $desc, isset($d['file']) ? $d['file'] : 'n/a', isset($d['line']) ? $d['line'] : 'n/a', isset($d['args']) ? $d['args'] : null);
		return true;
		}

	/* Trigger Error für Log-Eintrag ohne Abbruch des Scripts */
	public static function logtrigger($desc = 'Error', $code = E_USER_ERROR, $tracelvl = 1){
		if(isset(self::$fnArr['log'])){
			$d = self::call_trace(0)[$tracelvl];
			call_user_func(self::$fnArr['log'], $code, $desc, isset($d['file']) ? $d['file'] : 'n/a', isset($d['line']) ? $d['line'] : 'n/a', isset($d['args']) ? $d['args'] : null);
			}
		return true;
		}

	/* Header wurde gesendet, sodass ewaitige HTTP-Codes verhindern werden müssen */
	public static function set_header_sent(){
		self::$header_sent = true;
		}

	/* On Error: Mail Funktion */
	protected static $mail_fn_addr;
	protected static $mail_fn_title;

	public static function mail_fn($addr, $title){
		self::$mail_fn_addr = $addr;
		self::$mail_fn_title = $title;
		return __CLASS__.'::mail_fn_call';
		}

	public static function mail_fn_call($code, $desc, $file, $line, $args){
		$postdata = self::postdata();
		$body  = date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']).': '.self::name($code)." in $file | $line: ".htmlspecialchars_decode(strip_tags(str_replace("<br/>","\n",$desc)))."\n\n";
		$body .= 'ARGS = '.self::encode_php($args)."\n\n";
		$body .= 'URL = '.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']."\n\n";
		$body .= 'HTTP '.$_SERVER['REQUEST_METHOD'].' '.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']."\n\n";
		$body .= 'GET = '.self::encode_php($_GET)."\n\n";
		$body .= 'POST ('.$_SERVER['HTTP_ACCEPT'].') = '.self::encode_php($postdata)."\n\n";
		\mail(self::$mail_fn_addr, self::$mail_fn_title, $body);
		}


	/* On Error: Print Funktion */
	protected static $print_fn_detailed;

	public static function print_fn($detailed = false){
		self::$print_fn_detailed = $detailed;
		return __CLASS__.'::print_fn_call';
		}

	public static function print_fn_call($code, $desc, $file, $line, $args){
		header('Content-Type:text/html; charset=utf-8');
		echo "<b>".self::name($code)."</b> in $file | <b>$line</b>: $desc<br />\n";
		if(self::$print_fn_detailed){
			echo "<br />\n";
			echo 'ARGS = '.self::encode_php($args)."<br />\n";
			echo 'URL = '.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']."<br />\n";
			echo 'HTTP '.$_SERVER['REQUEST_METHOD'].' '.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']."<br />\n";
			echo 'GET = '.self::encode_php($_GET)."<br />\n";
			echo 'POST ('.$_SERVER['HTTP_ACCEPT'].') = '.self::encode_php(self::postdata())."<br />\n";
			}
		return true; // Verhindert die HTTP 500 Anzeige, wenn durch trigger() aufgerufen
		}

	/* On Error: Log Funktion */
	protected static $log_fn_p1;
	protected static $log_fn_p2;
	protected static $log_fn_logfile;

	public static function log_fn($p1, $p2, $logfile){
		self::$log_fn_p1 = $p1;
		self::$log_fn_p2 = $p2;
		self::$log_fn_logfile = $logfile;
		return __CLASS__.'::log_fn_call';
		}

	public static function log_fn_call($code, $desc, $file, $line, $args){
		if(class_exists(__NAMESPACE__.'\\log')){
			$obj = (object) [
				'time'			=> $_SERVER['REQUEST_TIME'],
				'code'			=> self::name($code),
				'desc'			=> htmlspecialchars_decode(strip_tags(str_replace("<br/>","\n",$desc))),
				'file'			=> $file,
				'line'			=> $line,
				'args'			=> $args,
				'ip'			=> $_SERVER['REMOTE_ADDR'],
				'domain'		=> $_SERVER['SERVER_NAME'],
				'uri'			=> $_SERVER['REQUEST_URI'],
				'http_accept'	=> (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : ''),
				'method'		=> $_SERVER['REQUEST_METHOD'],
				'get'			=> $_GET,
				'post'			=> self::postdata(),
				'useragent'		=> (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''),
				'referer'		=> (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''),
				'debug_backtrace'=>self::call_trace(DEBUG_BACKTRACE_IGNORE_ARGS)
				];
			$json = json_encode($obj);
			if(!$json){
				if(!json_encode($obj->desc)) $obj->desc = 'LOGERROR -> failed to encode error description';
				if(!json_encode($obj->args)) $obj->args = 'encoding failed';
				if(!json_encode($obj->get)) $obj->get = 'encoding failed';
				if(!json_encode($obj->post)) $obj->post = 'encoding failed';
				$json = json_encode($obj);
				}
			call_user_func(__NAMESPACE__.'\\log::file', self::$log_fn_p1, self::$log_fn_p2, self::$log_fn_logfile, $json);
			}
		return false; // Erlaubt die HTTP 500 Anzeige, wenn durch trigger() aufgerufen
		}

	/* Hilfe Funktionen */
	public static function name($t){
		$e = [
			E_ERROR => 'E_ERROR', // 1
			E_WARNING => 'E_WARNING', // 2
			E_PARSE => 'E_PARSE', // 4
			E_NOTICE => 'E_NOTICE', // 8
			E_CORE_ERROR => 'E_CORE_ERROR', // 16
			E_CORE_WARNING => 'E_CORE_WARNING', // 32
			E_COMPILE_ERROR => 'E_COMPILE_ERROR', // 64
			E_COMPILE_WARNING => 'E_COMPILE_WARNING', // 128
			E_USER_ERROR => 'E_USER_ERROR', // 256
			E_USER_WARNING => 'E_USER_WARNING', // 512
			E_USER_NOTICE => 'E_USER_NOTICE', // 1024
			E_STRICT => 'E_STRICT', // 2048
			E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR', // 4096
			E_DEPRECATED => 'E_DEPRECATED', // 8192
			E_USER_DEPRECATED => 'E_USER_DEPRECATED' // 16384
			];
		return isset($e[$t]) ? $e[$t] : $t;
		}

	public static function on($fn, $name = null){
		if(is_callable($fn)){
			$name ? self::$fnArr[$name] = $fn : self::$fnArr[] = $fn;
			return;
			}
		else self::trigger('Cannot set error function '.$name);
		}

	public static function off($name = null){
		if($name === null) self::$fnArr = [];
		elseif(isset(self::$fnArr[$name])) unset(self::$fnArr[$name]);
		return true;
		}

	public static function encode_php($var, $tab = '   ', $add = ''){
		if(class_exists(__NAMESPACE__.'\\helper', false)){
			return call_user_func(__NAMESPACE__.'\\helper::encode_php', $var, $tab, $add);
			}
		else return var_export($var, true);
		}

	public static function call_trace($options = 0){
		$trace = debug_backtrace($options);
		foreach($trace as $k => $v){
			if((isset($v['file']) and strpos($v['file'], "lib/amboss/error") !== false) or (isset($v['class']) and $v['class'] === "amboss\\error")) unset($trace[$k]);
			elseif(!empty(self::$fileshorten) and isset($v['file'])) $trace[$k]['file'] = str_replace(self::$fileshorten, '', $v['file']);
			}
		if(empty($trace)){
			$trace[] = ["file"=>"n/a", "line"=>"n/a", "function"=>"n/a"];
			}
		return array_values($trace);
		}

	public static function postdata(){
		return class_exists(__NAMESPACE__.'\\postdata', false) ? call_user_func(__NAMESPACE__.'\\postdata::get') : $_POST;
		}

	}