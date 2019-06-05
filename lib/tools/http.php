<?php
/*****
 * Version 1.3.2015-08-04
**/
namespace tools;

use \tools\error as e;
use \dmolsen\useragent;

class http {

	public static function flat_post_array($v, $k = null){
		if(is_object($v) or is_array($v)){

			$newv = [];
			foreach($v as $ik => $iv){
				$newk = $k !== null ? $k.'['.$ik.']' : $ik;
				$innerv = self::flat_post_array($iv, $newk);
				if(is_array($innerv)){
					foreach($innerv as $sk => $sv){
						$newv[$sk] = $sv;
						}
					}
				else $newv[$newk] = $innerv;
				}
			return !empty($newv) ? $newv : null;
			}
		return $v;
		}

	public static function curl_obj($obj){
		if(is_array($obj)) $obj = (object) $obj;
		elseif(!is_object($obj)) return e::trigger('Parameter is no object or array');

		$default = [
			'url'		=> null,
			'get'		=> null,
			'post' 		=> null,
			'sslVerify'	=> null,
			'follow'	=> true,
			'referer'	=> null,
			'ipv4only'	=> null,
			'useragent'	=> null,
			'method'	=> 'GET',
			'urlencode'	=> null,
			'charset'	=> null,
			'httpcode'	=> null,
			'contenttype'=>null,
			'content'	=> null,
			'log_header'=> false,
			];

		foreach($default as $k=>$v){
			if(!isset($obj->{$k})) $obj->{$k} = $v;
			}

		if($obj->urlencode === null and $obj->method === 'GET'){
			$obj->urlencode = true;
			}


		if(is_array($obj->get)){
			$n = [];
			foreach($obj->get as $k => $v){
				$n[] = $obj->urlencode ? urlencode($k).'='.urlencode($v) : $k.'='.$v;
				}
			$obj->get = '?'.implode('&', $n);
			}

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $obj->url.(is_string($obj->get) ? $obj->get : ''));

		if((is_array($obj->post) or is_object($obj->post)) and !empty($obj->post)){
			$obj->post = self::flat_post_array($obj->post);
			if($obj->urlencode){
				$n = [];
				foreach($obj->post as $k => $v){
					$n[] = urlencode($k).'='.urlencode($v);
					}
				$obj->post = implode('&', $n);
				}
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $obj->post);
			if(in_array($obj->method, ['GET','HEAD','DELETE'])) e::logtrigger("POST-Daten funktionieren nicht mit: ".$obj->method.' '.$obj->url);
			}

		if(is_int($obj->sslVerify)) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $obj->sslVerify);
		elseif(substr($obj->url, 0, 6) === 'https:') curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

		if($obj->follow) curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		if($obj->ipv4only) curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);


		if($obj->log_header){
			curl_setopt($ch, CURLINFO_HEADER_OUT, true);
			curl_setopt($ch, CURLOPT_HEADER, true);
			}

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		if($obj->charset){
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['charset:'.$obj->charset.'']);
			}

		if($obj->referer !== null) curl_setopt($ch, CURLOPT_REFERER, $obj->referer);
		if($obj->useragent !== null) curl_setopt($ch, CURLOPT_USERAGENT, $obj->useragent);

		if(in_array($obj->method, ['GET','POST','PUT','PATCH','HEAD','DELETE'])) curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $obj->method);

		$obj->content = curl_exec($ch);

		$obj->httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$obj->contenttype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

		if($obj->log_header){
			$obj->header_out = trim(curl_getinfo($ch, CURLINFO_HEADER_OUT));
			$obj->header_in = '';
			while(substr(ltrim($obj->content), 0, 4) === 'HTTP'){
				list($one_header, $obj->content) = explode("\r\n\r\n", $obj->content, 2) + ["",""];
				$obj->header_in .= "\n\n".$one_header;
				}
			$obj->header_in = trim($obj->header_in);
			}

		curl_close($ch);

		return $obj;
		}

	public static function curl_redirect($url, $opt = [], $mode = null){
		$curl_obj = self::curl_obj($opt + [
			'url'		=> $url,
			'get'		=> $_GET,
			'post'		=> $_POST,
			'referer'	=> isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
			'useragent'	=> isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
			'method'	=> $_SERVER['REQUEST_METHOD']
			]);

		if($mode === 'reflect'){
			$code_nocontent = [
				201 => 'Created', 202 => 'Accepted', 204 => 'No Content',
				304 => 'Not Modified',
				400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 409 => 'Conflict', 410 => 'Gone', 423 => 'Unprocessable Entity',
				500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway', 503 => 'Service Temporarily Unavailable', 505 => 'HTTP Version not supported'
				];
			$code_withcontent = [
				200 => 'OK',
				403 => 'Forbidden', 409 => 'Conflict'
				];
			$code_redirect = [
				301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other', 307 => 'Temporary Redirect'
				];

			if(isset($code_nocontent[$curl_obj->httpcode])){
				header('HTTP/1.1 '.$curl_obj->httpcode.' '.$code_nocontent[$curl_obj->httpcode]);
				}
			elseif(isset($code_withcontent[$curl_obj->httpcode])){
				header('HTTP/1.1 '.$curl_obj->httpcode.' '.$code_withcontent[$curl_obj->httpcode]);
				header('Content-Type: '.$curl_obj->contenttype.'; charset=utf-8');
				echo $curl_obj->content;
				}
			elseif(isset($code_redirect[$curl_obj->httpcode])){
				header('HTTP/1.1 '.$curl_obj->httpcode.' '.$code_redirect[$curl_obj->httpcode]);
				header('Location: '.$curl_obj->content);
				}
			else{
				header('HTTP/1.1 500 Internal Server Error');
				echo $curl_obj->content;
				}

			return;
			}

		return $curl_obj;
		}

	public static function mimearray(){
		return [
			'txt'	=> 'text/plain',
			'htm' 	=> 'text/html',
			'html'	=> 'text/html',
			'php' 	=> 'text/html',
			'css' 	=> 'text/css',
			'js' 	=> 'text/javascript',
			'json' 	=> 'text/json',
			'xml' 	=> 'application/xml',
			'swf' 	=> 'application/x-shockwave-flash',
			'flv' 	=> 'video/x-flv',
			'png' 	=> 'image/png',
			'jpe' 	=> 'image/jpeg',
			'jpeg' 	=> 'image/jpeg',
			'jpg' 	=> 'image/jpeg',
			'gif' 	=> 'image/gif',
			'bmp' 	=> 'image/bmp',
			'ico' 	=> 'image/vnd.microsoft.icon',
			'tiff' 	=> 'image/tiff',
			'tif' 	=> 'image/tiff',
			'svg' 	=> 'image/svg+xml',
			'svgz' 	=> 'image/svg+xml',
			'zip' 	=> 'application/zip',
			'rar' 	=> 'application/x-rar-compressed',
			'exe' 	=> 'application/x-msdownload',
			'msi' 	=> 'application/x-msdownload',
			'cab' 	=> 'application/vnd.ms-cab-compressed',
			'mp3' 	=> 'audio/mpeg',
			'mp4'	=> 'video/mp4',
			'qt' 	=> 'video/quicktime',
			'mov' 	=> 'video/quicktime',
			'pdf' 	=> 'application/pdf',
			'psd' 	=> 'image/vnd.adobe.photoshop',
			'ai' 	=> 'application/postscript',
			'eps' 	=> 'application/postscript',
			'ps'	=> 'application/postscript',
			'doc' 	=> 'application/msword',
			'rtf' 	=> 'application/rtf',
			'xls' 	=> 'application/vnd.ms-excel',
			'ppt' 	=> 'application/vnd.ms-powerpoint',
			'odt' 	=> 'application/vnd.oasis.opendocument.text',
			'ods' 	=> 'application/vnd.oasis.opendocument.spreadsheet',
			'apk'	=> 'application/vnd.android.package-archive',
			];
		}

	public static function mimetype($f){
		$m = self::mimearray();
		if(!is_string($f)) return e::trigger('Invalid argument-type '.gettype($f).'');
		return (preg_match('/(?:\.|^)([a-z0-9]{1,4})$/', $f, $e) and isset($m[$e[1]])) ? $m[$e[1]] : 'text/plain';
		}

	// HTTP Request Header checker und getter
	public static $httpheaderCache;

	public static function cHeader($n, $c = true){
		if(self::$httpheaderCache === null) self::$httpheaderCache = http_get_request_headers();
		return h::cX(self::$httpheaderCache, $n, $c);
		}

	public static function gHeader($n = null){
		if(self::$httpheaderCache === null) self::$httpheaderCache = http_get_request_headers();
		return ($n === null) ? self::$httpheaderCache : h::gX(self::$httpheaderCache, $n);
		}

	public static function useragent($str = null){
		$ua = new useragent();
		return $ua->parse($str);
		}

	}
