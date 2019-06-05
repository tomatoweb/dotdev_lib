<?php
/*****
 * Version 1.0.2014-02-03
**/
namespace tools;

class postdata {

	public static $postdata;
	public static $postdataReaded = false;

	public static function get(){
		/****TODO***
		 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html
		 * Hier muss noch ein konsistentes Verhalten her. Im Grunde sollte jedes HTTP_ACCEPT
		 * irgendwie verarbeitet werden. Wenn $_POST verfügbar, ist ja alles super, ansonsten
		 * wenn die POST-Daten nicht verarbeitet werden können, sollte ein "501 - Not implemented"
		 * ausgegeben werden.
		 */
		if(!self::$postdataReaded){
			if(isset($_SERVER['HTTP_ACCEPT']) and strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) self::$postdata = json_decode(file_get_contents('php://input'));
			// elseif(strpos($_SERVER['HTTP_ACCEPT'], 'text/plain') !== false) self::$postdata = file_get_contents('php://input');
			else self::$postdata = $_POST;
			self::$postdataReaded = true;
			}
		return self::$postdata;
		}

	}
