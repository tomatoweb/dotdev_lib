<?php
/*****
 * Version 1.0.2015-06-08
 *
 * PSR-0 Final Proposal
 * - https://gist.github.com/Thinkscape/1234504
 * - http://stackoverflow.com/questions/12082507/php-most-lightweight-psr-0-compliant-autoloader
**/
namespace tools;

class psrloader {

	protected static $try_path = [];

	public static function register($path = null){
		if(!$path) $path = $_SERVER['DOCUMENT_ROOT'];
		if($path and substr($path, -1) == '/') $path = substr($path, 0, -1);
		if(in_array($path, self::$try_path)) return true;

		if(empty(self::$try_path) and !spl_autoload_register(__CLASS__.'::load')) return false;

		self::$try_path[] = $path;
		return true;
		}

	public static function load($call){
		if(!self::loadable($call, $file, $notfoundin)){
			return self::error('Keine PHP-Datei für '.$call.' gefunden ('.implode(', ', $notfoundin).')');
			}
		include $file;
		return true;
		}

	public static function error($error, $hidedeep = 4){
		return class_exists(__NAMESPACE__.'\error', false)
			? namespace\error::trigger($error, E_USER_ERROR, $hidedeep)
			: trigger_error($error, E_USER_ERROR);
		}

	public static function loadable($call, &$file = '', &$notfoundin = []){
		$ns = explode('::', $call);
		$sub = explode('\\', $ns[0]);
		$sub[] = str_replace('_', '/', array_pop($sub));
		$sub = ($sub[0] ? '/' : '').implode('/', $sub).'.php';

		foreach(array_reverse(self::$try_path) as $path){
			$file = $path.$sub;
			if(is_file($file)) return true;
			$notfoundin[] = $file;
			}
		return false;
		}

	}
