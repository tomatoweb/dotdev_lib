<?php
/*****
 * Local Test function for catlop (Controlled access to library over Public-key cryptography)
 * Version	 	1.0.2016-04-11
 * Local use only
 * Author: Mathias Appelmans
**/

use \tools\error as e;
use \tools\helper as h;
use \tools\http;

class testcatlop {
	use \tools\libcom_trait;

	public static function generate(){

		$message = ['fn'=>'bragi_getchat_1.0', 'param'=>['mobileID' => '12348266']];
		//$message = ['fn'=>'bragi_trackevent_1.0', 'param'=>['event' => 'FullscreenGallery','imsi' => '262026045457418','project' => 'CherryChat_Android','data' => 'FullscreenGallery']];
		//$message = ['fn'=>'bragi_getevent_1.0', 'param'=>['project' => 'CherryChat_Android']];
		$message = json_encode($message);

		$key = "azerty123kjhgfds"; // Only keys of sizes 16, 24 or 32 supported

		$encrypte = \dotdev\app\catlop::aes_encrypt($message,$key, ['key_is'=>'binary', 'convert_to'=>'binary']);

		$private_key = openssl_pkey_get_private(file_get_contents($_SERVER['ENV_PATH'].'/config/catlop/catlop.private.pem')); // inutile dans cette classe-ci
		$public_key = openssl_pkey_get_public(file_get_contents($_SERVER['ENV_PATH'].'/config/catlop/catlop.public.pem')); // (NE correspond PAS avec catlop.private.pem)
		//$public_key = openssl_pkey_get_public(file_get_contents($_SERVER['ENV_PATH'].'/config/catlop/catlop_local.public.pem')); // Local (correspond avec catlop.private.pem)
		if(empty($private_key) or empty($public_key)){
			return self::response(500, 'Cannot read certificate');
			}

		openssl_public_encrypt($key, $encryptedKey, $public_key);

		$param['d'] = base64_encode($encrypte);
		$param['k'] = base64_encode($encryptedKey);
		$param['h'] = hash_hmac("SHA256", $encrypte, $key);

		$curl_obj = http::curl_obj([
			'url' 		=> 'http://service.cct4.net/catlop/v1.json',
			//'url' 		=> 'http://mtservice.ma/catlop/v1.json', // Local
			'method'	=> 'POST',
			'post' 		=> $param,
			'urlencode'	=> true,
			]);

		$base64 = json_decode($curl_obj->content);

		$aes = base64_decode($base64->data);

		$decrypte = \dotdev\app\catlop::aes_decrypt($aes,$key, ['key_is'=>'binary', 'message_is'=>'binary']);

		return json_decode($decrypte);

		}



	}
