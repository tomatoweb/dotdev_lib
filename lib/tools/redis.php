<?php
/*****
 * Version 1.2.2015-09-03
**/
namespace tools;

use \tools\error as e;
use \tools\helper as h;

class redis {

	protected static $_res = [];
	protected static $_active = true;

	protected static function _load_setting($set){

		if(!isset(self::$_res[$set])){

			$setfile = $_SERVER['REDIS_PATH'].'/'.$set.'.php';
			if(!file_exists($setfile)) return e::trigger('Redis Configuration '.$set.' not found at '.$setfile);

			self::set_resource($set, include($setfile));
			}

		return self::$_res[$set];
		}

	public static function load_resource($set){

		$res = self::_load_setting($set);
		if(!$res) return false;

		if(!$res->handle or !$res->handle->isConnected()){

			$res->handle = $redis = new \Redis();

			if(!is_object($redis) or !call_user_func_array([$redis, 'connect'], $res->instance)){
				e::logtrigger('Verbindung zu Redis-Instanz '.$set.' fehlgeschlagen. ('.h::encode_php($res->instance).')');
				return false;
				}

			if(!$redis->select($res->db)){
				e::logtrigger('Selektieren der DB '.$res->db.' der Redis-Instanz '.$set.' fehlgeschlagen. ('.h::encode_php($res->instance).')');
				return false;
				}

			foreach($res->options as $k => $v){
				if(!$redis->setOption($k, $v)){
					e::logtrigger('Option '.$k.'=>'.$v.' für die Redis-Instanz '.$set.' konnte nicht gesetzt werden ('.h::encode_php($res->instance).')');
					return false;
					}
				}

			$res->handle->_setname = $set;
			}

		return $res->handle;
		}

	public static function set_resource($set, $data){
		self::$_res[$set] = $data;

		self::$_res[$set]->handle = null;
		if(!isset(self::$_res[$set]->options)) self::$_res[$set]->options = [];
		}

	public static function alive(&$redis){
		if(!$redis or !is_object($redis)) return false;
		if($redis->isConnected()) return true;
		if(empty($redis->_setname)) return false;
		$redis = self::load_resource($redis->_setname);
		return $redis ? true :false;
		}


	// Concurrent Process Functions
	public static function lock_process(&$redis, $process_key, $opt = []){

		/*
		Diese Funktion beeinflusst konkurriernde Prozesse mittels Stati
		 - 100 Fahre fort (andere Prozesse bekommen 102)
		 - 102 Warte -> ggf. Timeout 408
		 - 200 Vorgang bereits abgeschlossen
		 - 404 Kein Prozess gefunden (nur bei waitonly)
		 - 503 Redis nicht verfügbar

		Optionen:
		 - ttl: Fallback bei gescheitertem Prozess (Default: 10 Sekunden / 0 nicht erlaubt)
		 - timeout_ms: Wartezeit, nachdem mit 408 abgebrochen wird, wenn kein anderer Status als 102
		 - retry_ms: Erneut Status auslesen alle retry_ms während timeout_ms
		*/

		// Options
		$opt = $opt + ['ttl' => 10, 'waitonly' => false];

		if(empty($process_key) or empty($opt['ttl'])) return 400;
		if(!redis::alive($redis)) return 503;

		$set = $redis->_setname;
		$status = $redis->get('_process:'.$process_key.':code');

		// Wenn kein Prozess im Gang ist
		if(!$status and $opt['waitonly']) return 404;

		// Warte Algorythmus bei konkurrenten Prozess
		if($status == 102 and !empty($opt['timeout_ms'])){

			// retry_ms setzen, falls nicht gesetzt oder zu groß
			if(empty($opt['retry_ms']) or $opt['retry_ms'] > $opt['timeout_ms']) $opt['retry_ms'] = $opt['timeout_ms'];

			$slept = 0;

			// Warten und Status erneut abfragen
			do{
				$redis->close();
				usleep($opt['retry_ms'] * 1000); // ms
				$slept += $opt['retry_ms'];
				$redis = redis::load_resource($set);
				if(!$redis){
					e::logtrigger('Redis Connection '.$set.' konnte nach '.$slept.'ms nicht wieder aufgebaut werden.');
					return 503;
					}
				$status = $redis->get('_process:'.$process_key.':code');
				} while($status == 102 and $slept < $opt['timeout_ms']);
			}

		// Bei Status 200 oder 102 hier abbrechen
		if($status == 200) return 200;
		if($status == 102) return 408;

		// Erzeuge einen Wert, der möglichst nicht in konkurrenten Prozessen vorkommt, und setze ihn mit NX
		$unique_key = uniqid(rand(), true);
		$redis->setnx('_process:'.$process_key.':unique_key', $unique_key);

		// Wenn nun der Unique-Key nicht derselbe ist, war ein konkurriernder Prozess schneller, daher Lock-Funktion erneut von vorne starten
		if($redis->get('_process:'.$process_key.':unique_key') != $unique_key){
			return self::lock_process($redis, $process_key, $opt);
			}

		// Setze Status 102 und die ttl
		$redis->set('_process:'.$process_key.':code', 102);
		$redis->expire('_process:'.$process_key.':code', $opt['ttl']);
		$redis->expire('_process:'.$process_key.':unique_key', $opt['ttl']);

		// Rufe die Lock-Funktion auf
		return 100;
		}

	public static function unlock_process(&$redis, $process_key, $opt = []){

		/*
		Diese Funktion baut auf lock_process() auf

		Optionen:
		 - ttl: Freigabe des Prozesses
		*/

		// Options
		$opt = $opt + ['ttl' => 0, 'expireAt' => 0];

		// Lädt Redis und prüft $process_key
		if(empty($process_key)) return 400;
		if(!redis::alive($redis)) return 503;

		// Lösche den Unique-Key
		$redis->delete('_process:'.$process_key.':unique_key');

		// Den Status für eine Zeit erhalten (Caching Schema)
		if($opt['ttl'] or $opt['expireAt']){

			$redis->set('_process:'.$process_key.':code', 200);
			if($opt['expireAt']) $redis->expireAt('_process:'.$process_key.':code', $opt['expireAt']);
			else $redis->expire('_process:'.$process_key.':code', $opt['ttl']);
			}
		// Oder Status löschen
		else{

			$redis->delete('_process:'.$process_key.':code');
			}

		return 200;
		}

	public static function get_process_status(&$redis, $process_key){

		if(empty($process_key)) return 400;
		if(!redis::alive($redis)) return 503;

		return $redis->get('_process:'.$process_key.':code');
		}

	}
