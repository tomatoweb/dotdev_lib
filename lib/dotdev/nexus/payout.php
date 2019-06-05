<?php
/*****
 * Version 1.0.2018-02-28
**/
namespace dotdev\nexus;

use \tools\error as e;
use \tools\helper as h;
use \tools\redis;
use \dotdev\nexus\service;

class payout {
	use \tools\libcom_trait;


	/* Object: service_payout */
	public static function get_service_payout($req = []){ // DEPRECATED

		return service::get_service_payout($req);
		}

	public static function set_service_payout($req = []){ // DEPRECATED

		return service::set_service_payout($req);
		}

	public static function unset_service_payout($req = []){ // DEPRECATED

		return service::unset_service_payout($req);
		}

	}
