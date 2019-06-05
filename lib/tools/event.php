<?php
/*****
 * Version 1.0.2014-03-02
**/
namespace tools;

class event {

	protected static $data = [];

	public static function on($name, $fn){
		if(!isset(self::$data[$name])) self::$data[$name] = [$fn];
		else self::$data[$name][] = $fn;
		}

	public static function trigger($name){
		if(!empty(self::$data[$name])){
			foreach(self::$data[$name] as $fn) \call_user_func($fn);
			}
		}

	public static function off($name){
		if(!empty(self::$data[$name])) unset(self::$data[$name]);
		}

	public static function active(){
		return array_keys(self::$data);
		}

	}
