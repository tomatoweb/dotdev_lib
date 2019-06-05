<?php
/*****
 * Version 1.0.2015-07-14
 *
 * DEPRECATED PLACEHOLDER
 *
**/
namespace dotdev\mobile;

class service {


	/* Object: service */
	public static function get_service($req = []){

		return \dotdev\nexus\service::get_service($req);
		}

	public static function get_service_fn($req){

		return \dotdev\nexus\service::get_service_fn($req);
		}

	public static function create_service($req = []){

		return \dotdev\nexus\service::create_service($req);
		}

	public static function update_service($req = []){

		return \dotdev\nexus\service::update_service($req);
		}


	/* Object: product */
	public static function get_product($req = []){

		return \dotdev\nexus\service::get_product($req);
		}

	public static function get_product_fn($req){

		return \dotdev\nexus\service::get_product_fn($req);
		}

	public static function create_product($req = []){

		return \dotdev\nexus\service::create_product($req);
		}

	public static function update_product($req = []){

		return \dotdev\nexus\service::update_product($req);
		}

	}
