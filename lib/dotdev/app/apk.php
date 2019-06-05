<?php
/*****
 * Version 1.0.2018-04-12
**/
namespace dotdev\app;

use \tools\error as e;
use \tools\helper as h;

class apk {
	use \tools\libcom_trait;

	public static function get_config($req = []){ // DEPRECATED

		return \dotdev\apk\share::get_config($req);
		}
	}
