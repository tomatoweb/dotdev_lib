<?php
/*****
 * Version 1.2.2016-06-27
**/
namespace tools;

use \tools\error as e;

class helper {

	/* Misc */
	public static function bytesize_format($b,$r=0){
		$u = ['B','KB','MB','GB','TB'];
		for($i=0; ($b>1024 and $i<4); $b=$b/1024) $i++;
		return round($b,$r).' '.$u[$i];
		}

	public static function mb_rawurlencode($s){
		$e = '';
		$l = mb_strlen($s);
		for($i=0;$i<$l;$i++) $e .= '%'.wordwrap(bin2hex(mb_substr($s,$i,1)),2,'%',true);
		return $e;
		}

	public static function bench_ms($f, $s=2){
		return round((microtime(true) - $f)* 1000, $s).' ms';
		}

	public static function encode_php($var, $tab = '   ',  $add = ''){
		if($var === null) return 'null';
		elseif(is_int($var) or is_float($var)) return $var;
		elseif(is_string($var)) return '"'.str_replace(['\\','"'],['\\\\','\"'], $var).'"';
		elseif(is_bool($var)) return $var ? 'true' : 'false';
		elseif($x = is_array($var) or is_object($var)){
			$o = $x ? '[' : '(object) [';
			$n = true;
			$end = empty($tab) ? '' : "\n";
			$op = empty($tab) ? '=>' : ' => ';
			$sequential = self::is_sequentialArray($var);
			foreach($var as $key => $sub){
				$o .= ($n ? '' : ',').$end.$tab.$add.($sequential ? '' : self::encode_php($key).$op).self::encode_php($sub, $tab, $add.$tab);
				$n = false;
				}
			return $o.(!$n ? $end.$tab.$add : '').']';
			}
		else return 'null';
		}

	public static function replace_in_str($str, $arr){
		return str_replace(array_keys($arr), array_values($arr), $str);
		}

	public static function rand_str($strlen, $extrakeys = '', $basekeys = 'abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789'){
		$key = $basekeys.$extrakeys;
		$keylen = \mb_strlen($key);
		$pw = '';
		for($i=0;$i<$strlen;$i++) $pw .= $key[\mt_rand(0,$keylen-1)];
		return $pw;
		}

	public static function rand_bool($float, $precision = 10000){
		return (self::is($float, "~0,1/f") and $float > 0 and $precision > 0) ? ($precision * $float > mt_rand(0, $precision)) : false;
		}


	/* DateTime Abstraktion */
	public static function date($date, $modify = null, $format = null, $clone = false){
		if(is_int($date) or is_float($date)) $date = date('Y-m-d H:i:s', $date);

		if(is_string($date)){
			try{
				$date = new \DateTime($date);
				}
			catch(Exception $e){
				e::trigger('Invalid '.$date.' for creating DateTime-Object in helper::date', E_USER_ERROR, 2);
				}
			}

		if(!is_object($date)) return null;
		elseif($clone) $date = clone $date;

		if(!empty($modify)){
			if(is_string($modify)) $modify = explode(';', $modify);
			if(is_array($modify)){
				try{
					foreach($modify as $m) $date->modify($m);
					}
				catch(Exception $e){
					e::trigger('Invalid '.$m.' for modifying DateTime-Object in helper::date', E_USER_ERROR, 2);
					}
				}
			}

		if(!empty($format)){
			try{
				$date = $date->format($format);
				}
			catch(Exception $e){
				e::trigger('Invalid '.$format.' for format DateTime-Object in helper::date', E_USER_ERROR, 2);
				}
			}

		return $date;
		}

	public static function convert_time($source, $to = 'U'){
		if(is_object($source)){
			return self::date($source, null, $to);
			}
		elseif(self::is($source, '~631148400,1924988399/f')){
			if($to == 'U.u') $c = (float) $source;
			elseif($to == 'U') $c = (int) $source;
			else $c = date($to, $source);
			}
		elseif(self::is($source, '~631148400000,1924988399000/i')){
			if($to == 'U.u') $c = (float) $source/1000;
			elseif($to == 'U') $c = (int) $source/1000;
			else $c = date($to, $source/1000);
			}
		elseif(is_string($source) and ($conv = strtotime($source)) !== false) $c = date($to, $conv);
		else return false;
		return (is_string($c) and in_array($to, ['U','U.u'])) ? ($to == 'U.u' ? (float) $c : (int) $c) : $c;
		}

	public static function dtstr($source, $to = 'Y-m-d H:i:s'){
		return self::convert_time($source, $to);
		}


	/* Reverse DNS (Non-Cache-Version) */
	public static function rdns($IP){
		return gethostbyaddr($IP);
		}


	/* Router Funktion */
	public static function route_map($str, $map, $notfound = null){
		if(!is_array($map) or empty($map)) return e::trigger('routemap is empty', E_USER_ERROR, 2);
		foreach($map as $c => $f){
			$p = [];
			if(is_array($f)){
				$p = !is_array($f[1]) ? [$f[1]] : $f[1];
				$f = $f[0];
				}
			$callable = (is_callable($f) or (is_array($f) and is_object($f[0]) and is_string($f[1])));
			if(is_string($c) and $c[0] == '~'){
				if(preg_match('/'.substr($c, 1).'/', $str, $match)){
					array_shift($match);
					return $callable ? call_user_func_array($f, $match+$p) : $f;
					}
				}
			elseif($c === $str){
				return $callable ? call_user_func_array($f, $p) : $f;
				}
			}
		$callable = (is_callable($notfound) or (is_array($notfound) and is_object($notfound[0]) and is_string($notfound[1])));
		return $callable ? call_user_func($notfound) : $notfound;
		}


	/* Verzeichnis Mapping */
	public static function rmap_files($map, $callbackFn){ // EXPERIMENTAL, in Benutzung!
		if(!is_array($map)) $map = [$map];
		foreach($map as $link){
			if(is_dir($link)){
				foreach(scandir($link) as $file){
					if($file[0] == '.') continue;
					elseif(is_file($link.'/'.$file)) $callbackFn($link.'/'.$file);
					else self::rmap_files($link.'/'.$file, $callbackFn);
					}
				}
			else $callbackFn($link);
			}
		}


	/* Kombifunktion der Einzeltests (wird u.A. in cX() genutzt) */
	public static function is($val, $c = true){

		// special checks
		if(is_string($c) and strlen($c) > 1 and $c[0] === '~'){

			$opt = substr($c, -2);
			$check = substr($c, 1, -2);

			// check string
			if($opt == '/s'){
				return $check ? self::is_betweenStrlen($val, $check) : is_string($val);
				}

			// check integer
			if($opt == '/i'){
				if(is_array($val) or is_object($val)) return false;
				return $check ? self::is_betweenInt($val, $check) : (bool) preg_match('/^(?:-|)(?:[0-9]|[1-9][0-9]*)$/', $val);
				}

			// check datetime
			if($opt == '/d'){
				if(is_array($val)) return false;
				return self::is_datetime($val);
				}

			// check lists (means any array or object)
			if($opt == '/l'){
				return (is_array($val) or is_object($val));
				}

			// check array (or collection as associative array or object)
			if($opt == '/a' or $opt == '/c'){

				// if not array (if /a or if it is also !object)
				if(!is_array($val) and ($opt == '/a' or !is_object($val))) return false;

				// if it cannot be empty
				if(strpos($check, '!empty') !== false and empty($val)) return false;

				// if array must be a sequentialArray
				if($opt == '/a' and strpos($check, 'sequential') !== false) return self::is_sequentialArray($val);

				// if it must be a assocArray (or a collection)
				if(is_array($val) and ($opt == '/c' or strpos($check, 'assoc') !== false)) return self::is_assocArray($val);

				return true;
				}

			// check object
			if($opt == '/o') return is_object($val);

			// check boolean-like
			if($opt == '/b') return (is_scalar($val) or $val === null);

			// check float
			if($opt == '/f'){
				if(is_array($val) or is_object($val)) return false;
				return $check ? self::is_betweenFloat($val, $check) : (bool) preg_match('/^(?:-|)(?:[0-9]|[1-9][0-9]*)(?:\.[0-9]*|)$/', $val);
				}

			// check regular (non scalar values are uncheckable and results in false)
			return (is_scalar($val) or $val === null) ? preg_match('/'.substr($c, 1).'/s', $val) : false;
			}

		// base check
		if($c===true and $val !== '') return true;

		// or direct compare test
		return $val === $c;
		}


	/* is() single tests */
	public static function is_datetime($val){

		if(self::is_betweenFloat($val, '631148400,1924988399')) return true;
		if(self::is_betweenInt($val, '631148400000,1924988399000')) return true;
		if(is_string($val) and strtotime($val) !== false) return true;

		return false;
		}

	public static function is_betweenInt($val, $def){

		return !(is_int($def) or is_string($def) && preg_match('/^\-?[0-9]+$/', $val)) ? false : self::is_betweenFloat((float) $val, $def);
		}

	public static function is_betweenFloat($val, $def){

		if(!(is_float($val) or is_string($def) && preg_match('/^\-?[0-9]+(?:\.[0-9]*|)$/', $val))) return false;

		foreach(explode('|', $def) as $r){
			$r = explode(',', $r);
			if(!isset($r[1])) $r[1] = $r[0];
			if($val >= $r[0] and $val <= $r[1]) return true;
			}

		return false;
		}

	public static function is_betweenStrlen($val, $def){

		if(!is_string($val) or !is_string($def)) return false;

		$len = mb_strlen($val);

		foreach(explode('|', $def) as $r){
			$r = explode(',', $r);
			if(!isset($r[1])) $r[1] = $r[0];
			if($len >= $r[0] and $len <= $r[1]) return true;
			}

		return false;
		}

	public static function is_sequentialArray($val){

		// Wenn kein Array
		if(!is_array($val)) return false;

		// Prüfe, ob alle Schlüssel in Reihenfolge als numerische Keys existieren
		$i = 0;
		foreach($val as $k => $v){
			if($k !== $i) return false;
			$i++;
			}
		return true;
		}

	public static function is_assocArray($val){

		// Prüfe, ob alle Schlüssel vom Type String ist
		return is_array($val) ? (bool) count(array_filter(array_keys($val), 'is_string')) : false;
		}

	public static function is_in_daytime($time, $from, $to){ // yet not available in is()

		if(!self::is($from, '~^(?:[01]{1}[0-9]{1}|20|21|22|23)\:[012345]{1}[0-9]{1}$') or !self::is($to, '~^(?:[01]{1}[0-9]{1}|20|21|22|23)\:[012345]{1}[0-9]{1}$')) return false;

		$now = self::date($time);
		$day = self::date($now, null, 'Y-m-d');
		$from = self::date($day.' '.$from);
		$to = self::date($day.' '.$to);

		// from: 05:00, to: 18:00
		if($from < $to) return ($now >= $from and $now <= $to);
		// from: 18:00, to: 05:00
		else return ($now <= $to or $now >= $from);
		}


	/* Check, Get, Extract -> Array, Object */
	public static function cX($val, $key, $c = true){

		// multiply combined checks (should be DEPRECATED, if unused)
		if(is_array($key)){
			$result = true;
			foreach($key as $sk => $sn){
				if(!$result) break;
				$result = is_string($sk) ? self::cX($val, $sk, $sn) : self::cX($val, $sn, $c);
				}
			return $result;
			}

		// search in deeper levels of array or object
		foreach(explode(':',$key) as $s){

			// take deeper array value
			if(is_array($val) and isset($val[$s])) $val = $val[$s];

			// take deeper object value
			elseif(is_object($val) and isset($val->{$s})) $val = $val->{$s};

			// or return null
			else return false;
			}

		// return is()
		return ($c === null) ? true : self::is($val, $c);
		}

	public static function gX($val, $key, $extract = null, $cX = true){

		// if cX is needed first
		if($cX and !self::cX($val, $key, null)) return;

		// search in deeper levels of array or object
		foreach(explode(':', $key) as $s){

			// take deeper array value
			if(is_array($val)) $val = isset($val[$s]) ? $val[$s] : null;

			// take deeper object value
			elseif(is_object($val)) $val = isset($val->{$s}) ? $val->{$s} : null;

			// or return null
			else return null;
			}

		// extract rule
		if(is_string($extract) and strlen($extract) > 1 and $extract[0] === '~'){

			$opt = substr($extract, -2);
			$opt2 = substr($extract, 1, -2);

			// return string, array or object unchanged
			if(in_array($opt, ['/s', '/l', '/a', '/o'])) return $val;

			// return integer
			if($opt == '/i') return (int) $val;

			// return converted datetime
			if($opt == '/d') return $opt2 ? self::dtstr($val, $opt2) : self::dtstr($val);

			// return collection as array
			if($opt == '/c') return (array) $val;

			// return boolean
			if($opt == '/b') return (boolean) $val;

			// return float
			if($opt == '/f') return (float) $val;

			// preg_match rule
			if((is_string($val) or is_int($val) or is_float($val)) and preg_match('/'.substr($extract, 1).'/s', $val, $match)){

				// return sub matches, if exists
				if(count($match) > 1){
					array_shift($match);
					return $match;
					}

				// or return base match
				return $match[0];
				}

			return null;
			}

		// HIGHLY DEPRECATED
		if(is_callable($extract)){
			return call_user_func($extract, $val);
			}

		// return value
		return $val;
		}

	public static function eX($obj, $keyrules, &$error = [], $optional = false){

		// Error Array erstellen
		$error || $error = [];

		// Prüfen ob die Parameter okay sind
		if(!is_array($keyrules) or empty($keyrules) or (!is_array($obj) and !is_object($obj))){
			$error[] = 'invalid eX call';
			e::trigger('Ungültiger Aufruf: eX('.gettype($obj).', '.gettype($keyrules).', '.gettype($error).', '.gettype($optional).')', E_USER_ERROR, 2);
			return null;
			}

		// Ergebnisarray erstellen
		$result = [];

		// Jede Key/Rule Angabe durchgenen
		foreach($keyrules as $key => $rule){

			// Wenn nur Key angeben ist, einfachste Regel (isset) definieren
			if(!is_string($key)){
				list($key, $rule) = [$rule, null];
				}

			// Key für Suche und Rückgabe definieren
			if(strpos($key, '|') !== false){
				$k2 = explode('|', $key);
				$key_search = $k2[1] ? implode(':', $k2) : $k2[0];
				$key_return = $k2[1];
				}
			else{
				$key_search = $key_return = $key;
				}

			// Regel für Suche und Rückgabe definieren (is_callable should be DEPRECATED, maybe spliting rules too)
			list($search_rule, $return_rule) = is_array($rule) && !is_callable($rule) ? $rule+[null,null] : [$rule, $rule];

			// Prüfen ob Key der Regel entspricht
			if(self::cX($obj, $key_search, $search_rule)){

				// Wenn der Rückgabe Key einen Wert hat diesen verwenden
				if($key_return){
					$result[$key_return] = self::gX($obj, $key_search, $return_rule, false);
					}
				// Ansonsten einfach anfügen
				else{
					$result[] = self::gX($obj, $key_search, $return_rule, false);
					}
				}

			// Ansonsten, wenn Key nicht optional ist oder trotz fehlgeschlagener Regel existiert Fehler loggen
			elseif(!$optional or self::cX($obj, $key_search, null)){
				$error[] = $key;
				}

			// Ansonsten continue
			}

		// Rückgabe des Ergebnisses
		return $result;
		}

	public static function lX($obj, $keyrules, $result = []){
		if(!is_array($keyrules) or empty($keyrules)) return $result;
		foreach($keyrules as $k => $v){
			if(!is_string($k)) list($k, $v) = [$v, null];
			if(!isset($result[$k])) $result[$k] = $v;
			}
		if(!is_array($obj) and !is_object($obj)) return $result;
		foreach(self::eX($obj, $keyrules) as $k => $v){
			$result[$k] = $v;
			}
		return $result;
		}


	/* Check, Get, Extract Shortcuts für globale Variablen */
	// $_POST
	public static function cP($n, $c = true){
		return self::cX($_POST,$n,$c);
		}

	public static function gP($n, $e = null){
		return self::gX($_POST,$n);
		}

	public static function eP($n, &$e = [], $x = false){
		return self::eX($_POST, $n, $e, $x);
		}

	public static function lP($n, $r = []){
		return self::lX($_POST, $n, $r);
		}

	// $_GET
	public static function cG($n, $c = true){
		return self::cX($_GET,$n,$c);
		}

	public static function gG($n, $e = null){
		return self::gX($_GET,$n);
		}

	public static function eG($n, &$e = [], $x = false){
		return self::eX($_GET, $n, $e, $x);
		}

	public static function lG($n, $r = []){
		return self::lX($_GET, $n, $r);
		}

	// $_REQUEST
	public static function cR($n, $c = true){
		return self::cX($_REQUEST,$n,$c);
		}

	public static function gR($n, $e = null){
		return self::gX($_REQUEST,$n);
		}

	public static function eR($n, &$e = [], $x = false){
		return self::eX($_REQUEST, $n, $e, $x);
		}

	public static function lR($n, $r = []){
		return self::lX($_REQUEST, $n, $r);
		}

	// $_SESSION
	public static function cS($n, $c = true){
		if(!isset($_SESSION)) return null;
		return self::cX($_SESSION,$n,$c);
		}

	public static function gS($n, $e = null){
		if(!isset($_SESSION)) return null;
		return self::gX($_SESSION,$n);
		}

	public static function eS($n, &$e = [], $x = false){
		return self::eX((isset($_SESSION) ? $_SESSION : []), $n, $e, $x);
		}

	public static function lS($n, $r = []){
		return self::lX($_SESSION, $n, $r);
		}

	// $_SERVER
	public static function cE($n, $c = true){
		return self::cX($_SERVER,$n,$c);
		}

	public static function gE($n, $e = null){
		return self::gX($_SERVER,$n);
		}

	public static function eE($n, &$e = [], $x = false){
		return self::eX($_SERVER, $n, $e, $x);
		}

	public static function lE($n, $r = []){
		return self::lX($_SERVER, $n, $r);
		}

	// $_COOKIE
	public static function cC($n, $c = true){
		return self::cX($_COOKIE,$n,$c);
		}

	public static function gC($n, $e = null){
		return self::gX($_COOKIE,$n);
		}

	public static function eC($n, &$e = [], $x = false){
		return self::eX($_COOKIE, $n, $e, $x);
		}

	public static function lC($n, $r = []){
		return self::lX($_COOKIE, $n, $r);
		}

	}
