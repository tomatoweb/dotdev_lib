<?php
/*****
 * Version 1.0.2014-02-03
**/
namespace tools;

class log {

	public static function file($p1, $p2, $filename, $line){
		// p1 = Pfad1 -> muss bereits existieren
		// p2 = Pfad2 -> wird dynamisch erstellt
		$replace = array(
			'{mtime}' => microtime()
			);
		foreach(array('req'=>$_SERVER['REQUEST_TIME'], 'time'=>time()) as $n => $t){
			$t_date = date('Y-m-d H:i:s', $t);
			$replace = $replace + array(
				'{'.$n.'}' => $t,
				'{'.$n.'_date}' => $t_date,
				'{'.$n.'_Y-m-d}' => substr($t_date, 0, 10),
				'{'.$n.'_H:i:s}' => substr($t_date, 11),
				'{'.$n.'_Y}' => substr($t_date, 0, 4),
				'{'.$n.'_m}' => substr($t_date, 5, 2),
				'{'.$n.'_d}' => substr($t_date, 8, 2),
				'{'.$n.'_H}' => substr($t_date, 11, 2),
				'{'.$n.'_i}' => substr($t_date, 14, 2),
				'{'.$n.'_s}' => substr($t_date, 17, 2)
				);
			}

		$p1 = str_replace(array_keys($replace), array_values($replace), $p1);
		$p2 = str_replace(array_keys($replace), array_values($replace), $p2);
		$filename = str_replace(array_keys($replace), array_values($replace), $filename);

		if(!is_dir($p1)) return false;

		if(!empty($p2)){
			$ci = $p1;
			foreach(explode('/', $p2) as $sp){
				$ci .= '/'.$sp;
				if(!is_dir($ci)) mkdir($ci, 0755, true);
				}
			$d = $p1.'/'.$p2;
			}
		else $d = $p1;

		if(!is_dir($d)) return false;
		$f = $d.'/'.$filename;

		if(!file_exists($f) and file_put_contents($f, '') === false) return false;
		return (file_put_contents($f, $line."\n", FILE_APPEND) !== false);
		}

	}
