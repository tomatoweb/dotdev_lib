<?php
/*****
 * Version 1.0.2015-06-08
 *
 * PSR-0 Final Proposal
 * - https://gist.github.com/Thinkscape/1234504
 * - http://stackoverflow.com/questions/12082507/php-most-lightweight-psr-0-compliant-autoloader
 *
 * voir aussi dans E:\Backup_USB_16-04-2019\www\phplib\tools pour ma version de class loader
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
		include $file; // value received from loadable(&$file)
		return true;
		}

	public static function error($error, $hidedeep = 4){
		return class_exists(__NAMESPACE__.'\error', false)
			? namespace\error::trigger($error, E_USER_ERROR, $hidedeep)
			: trigger_error($error, E_USER_ERROR);
		}


	/* https://dobsondev.com/2015/05/22/pass-by-reference-vs-pass-by-value/
	 * function can modify (i.e. assign to) the variable ($file and $notfoundin) used as argument—something that will be seen by its caller (self::loadable($file, $notfoundin)).
	 * Call-by-reference can therefore be used to provide an additional channel of communication between the called function and the calling function.
	 * A call-by-reference language makes it more difficult for a programmer to track the effects of a function call, and may introduce subtle bugs.
	 */
	public static function loadable($call, &$file = '', &$notfoundin = []){

		// ns: namespace
		$ns = explode('::', $call); // suppress leading double colon
		$sub = explode('\\', $ns[0]); // arrayify the class path
		$sub[] = str_replace('_', '/', array_pop($sub)); // dépile et slashify the underscored class name, p.e. pdo_trait --> pdo/trait, pdo_cache --> pdo/cache, and add it to path array
		$sub = ($sub[0] ? '/' : '').implode('/', $sub).'.php'; // rewrite all path.

		// try to find the file in all registered paths with self::register($path)
		foreach(array_reverse(self::$try_path) as $path){
			$file = $path.$sub;
			if(is_file($file)) return true;
			$notfoundin[] = $file;
			}
		return false;
		}

	}
