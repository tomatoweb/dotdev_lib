<?php
/*****
 * Version 1.0.2016-11-11
**/
namespace tools;

use \tools\error as e;
use \tools\helper as h;

class crypt {

	public static function aes_encrypt($message, $key, $cfg = [], &$error = ''){

		// default config
		$cfg += [
			'codec' 	=> MCRYPT_RIJNDAEL_128,
			'mode'		=> MCRYPT_MODE_CBC,
			'key_is'	=> 'base64',
			'convert_to'=> 'base64',
			];

		// decode key to binary
		if($cfg['key_is'] == 'base64') $key = base64_decode($key);
		if($cfg['key_is'] == 'hex') $key = pack('H*', $key);

		// initial vector
		$iv_size = mcrypt_get_iv_size($cfg['codec'], $cfg['mode']);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_DEV_URANDOM);

		// pkcs5 pad
		$block_size = mcrypt_get_block_size($cfg['codec'], $cfg['mode']);
		$pad = $block_size - (strlen($message) % $block_size);
		$message .= str_repeat(chr($pad), $pad);

		// encrypt and prepend IV
		$message = $iv.mcrypt_encrypt($cfg['codec'], $key, $message, $cfg['mode'], $iv);

		// return data
		if($cfg['convert_to'] == 'base64') return base64_encode($message);
		if($cfg['convert_to'] == 'hex') return unpack('H*', $message)[1];
		return $message;
		}

	public static function aes_decrypt($message, $key, $cfg = [], &$error = ''){

		// default config
		$cfg += [
			'codec' 	=> MCRYPT_RIJNDAEL_128,
			'mode'		=> MCRYPT_MODE_CBC,
			'key_is'	=> 'base64',
			'message_is'=> 'base64',
			];

		// decode key to binary
		if($cfg['key_is'] == 'base64') $key = base64_decode($key);
		if($cfg['key_is'] == 'hex') $key = pack('H*', $key);

		// decode Base64
		if($cfg['message_is'] == 'base64') $message = base64_decode($message);
		if($cfg['message_is'] == 'hex') $message = pack('H*', $message);

		// get sizes
		$message_size = strlen($message);
		$block_size = mcrypt_get_block_size($cfg['codec'], $cfg['mode']);
		$iv_size = mcrypt_get_iv_size($cfg['codec'], $cfg['mode']);

		// check if message length is at least the iv_size and is multiple of blocksize
		if($message_size < $iv_size or $message_size % $block_size){

			// return error
			$error = 'message sizes wrong: '.$message_size.'/'.$iv_size.'/'.$block_size;
			return false;
			}

		// split IV from message
		$iv = substr($message, 0, $iv_size);
		$message = substr($message, $iv_size);

		// decrypt message
		$message = mcrypt_decrypt($cfg['codec'], $key, $message, $cfg['mode'], $iv);

		// pkcs5 unpad
		$mlen = strlen($message);
		$pad = ord($message[$mlen-1]);

		// on error
		if($pad > $mlen){

			// return error
			$error = 'pkcs5 unpad failure: pad '.$pad.' > mlen '.$mlen;
			return false;
			}

		// on error
		if(strspn($message, chr($pad), $mlen - $pad) != $pad){

			// return error
			$error = 'pkcs5 unpad failure: pad '.$pad.' does not compare with message ending';
			return false;
			}

		// get message
		$message = substr($message, 0, -1 * $pad);

		// return message
		return $message;
		}

	public static function openssl_public_encrypt($data, $public_file_content, &$aes_key = null, &$error = ''){

		// encode data as json
		$json_data = json_encode($data);

		// check encoding
		if($json_data === false){

			// return error
			$error = 'cannot encode data as json';
			return false;
			}

		// if aes_key exists but is not a string
		if(!empty($aes_key) and !is_string($aes_key)){

			// return error
			$error = 'given aes_key is invalid';
			return false;
			}

		// if no aes_key is given, generate random key
		if(!$aes_key) $aes_key = hash("SHA256", rand(), true);

		// encrypt data
		$encrypted_req = self::aes_encrypt($json_data, $aes_key, ['key_is'=>'binary', 'convert_to'=>'binary'], $suberror);

		// check encrypted data
		if($encrypted_req === false){

			// return error
			$error = 'encryption of data failed with: '.$suberror;
			return false;
			}

		// create hmac
		$hmac = hash_hmac("SHA256", $encrypted_req, $aes_key);

		// load public key from file
		$public_key = openssl_pkey_get_public($public_file_content);

		// if key is not loaded
		if(empty($public_key)){

			// return error
			$error = 'cannot read public key';
			return false;
			}

		// encrypt aes_key
		$encrypted_key = '';
		$encryption = openssl_public_encrypt($aes_key, $encrypted_key, $public_key, OPENSSL_ALGO_SHA1);

		// free key resource for security reasons
		openssl_pkey_free($public_key);

		// if encryption failed
		if(!$encryption){

			// return error
			$error = 'cannot encrypt aes_key';
			return false;
			}

		// return result
		return (object)[
			'data'	=> base64_encode($encrypted_req),
			'key'	=> base64_encode($encrypted_key),
			'hmac'	=> $hmac,
			];
		}

	public static function openssl_public_decrypt($data, $public_file_content, $aes_key, &$error = ''){ // TODO: Implement function

		// first check if data is encrypt-object
		$mand = h::eX($data, [
			'data'		=> '~^[a-zA-Z0-9\+\/]*[\=]{0,2}$', // base64 data
			'signature'	=> '~^[a-zA-Z0-9\+\/]*[\=]{0,2}$', // base64 data
			], $req_error);

		// on error
		if($req_error){

			$error = 'encrypt-object seems not valid ('.implode($req_error).')';
			return false;
			}

		// if aes_key seems base64
		if(h::is($aes_key, '~^[a-zA-Z0-9\+\/]*[\=]{0,2}$')){

			// convert it
			$aes_key = base64_decode($aes_key);
			}

		// if aes_key is empty or not a string
		if(empty($aes_key) or !is_string($aes_key)){

			// return error
			$error = 'aes_key is invalid';
			return false;
			}

		// convert message to binary
		$mand['data'] = base64_decode($mand['data']);

		// on error
		if($mand['data'] === false){

			// return error
			$error = 'decoding base64 encoded message failed';
			return false;
			}

		// convert signature to binary
		$mand['signature'] = base64_decode($mand['signature']);

		// on error
		if($mand['signature'] === false){

			// return error
			$error = 'decoding base64 encoded signature failed';
			return false;
			}

		// load public key from file
		$public_key = openssl_pkey_get_public($public_file_content);

		// if key is not loaded
		if(empty($public_key)){

			// return error
			$error = 'cannot read public key';
			return false;
			}

		// verify signature
		$verified = openssl_verify($mand['data'], $mand['signature'], $public_key, OPENSSL_ALGO_SHA1);

		// free key resource for security reasons
		openssl_pkey_free($public_key);

		// if verification failed
		if(!$verified){

			// return error
			$error = 'verification of signature failed';
			return false;
			}

		// decrypt message
		$message = self::aes_decrypt($mand['data'], $aes_key, ['key_is'=>'binary', 'message_is'=>'binary'], $suberror);

		// on error
		if(!$message){

			// return error
			$error = 'decryption of message failed with: '.$suberror;
			return false;
			}

		// decode message from json
		$decoded_message = json_decode($message);

		// on error
		if($decoded_message === null){

			// return error
			$error = 'decoding decrypted message from json failed';
			return false;
			}


		// return success
		return (object)[
			'data'	=> $decoded_message,
			];
		}

	public static function openssl_private_encrypt($data, $private_file_content, $aes_key, &$error = ''){

		// if aes_key is empty or not a string
		if(empty($aes_key) or !is_string($aes_key)){

			// return error
			$error = 'aes_key is invalid';
			return false;
			}

		// encode data as json
		$json_data = json_encode($data);

		// check encoding
		if($json_data === false){

			// return error
			$error = 'cannot encode data as json';
			return false;
			}

		// encrypt ressource with random key
		$encrypted_data = self::aes_encrypt($json_data, $aes_key, ['key_is'=>'binary', 'convert_to'=>'binary']);

		// load private key from file
		$private_key = openssl_pkey_get_private($private_file_content);

		// on error
		if(empty($private_key)){

			// return error
			$error = 'cannot read private key';
			return false;
			}

		// generate signature
		$signasture = '';
		$signed = openssl_sign($encrypted_data, $signature, $private_key, OPENSSL_ALGO_SHA1);

		// free key resource for security reasons
		openssl_pkey_free($private_key);

		// if generate signature failed
		if(!$signed){

			// return error
			$error = 'Cannot generate signature';
			return false;
			}

		// return result
		return (object)[
			'data'		=> base64_encode($encrypted_data),
			'signature'	=> base64_encode($signature),
			];
		}

	public static function openssl_private_decrypt($req, $private_file_content, &$error = ''){

		// first check if data is encrypt-object
		$mand = h::eX($req, [
			'data'	=> '~^[a-zA-Z0-9\+\/]*[\=]{0,2}$', // base64 data
			'key'	=> '~^[a-zA-Z0-9\+\/]*[\=]{0,2}$', // base64 data
			'hmac'	=> '~^[a-fA-F0-9]{64}$', // hex data
			], $req_error);

		// on error
		if($req_error){

			$error = 'encrypt-object seems not valid ('.implode($req_error).')';
			return false;
			}

		// decode aes_key from base64
		$mand['key'] = base64_decode($mand['key']);

		// on error
		if($mand['key'] === false){

			// return error
			$error = 'decoding base64 encoded key failed';
			return false;
			}

		// load private key from file
		$private_key = openssl_pkey_get_private($private_file_content);

		// on error
		if(empty($private_key)){

			// return error
			$error = 'cannot read private key';
			return false;
			}

		// decrypt aes_key from key
		$aes_key = '';
		$decrypted = openssl_private_decrypt($mand['key'], $aes_key, $private_key, OPENSSL_PKCS1_PADDING);

		// free key resource for security reasons
		openssl_pkey_free($private_key);

		// if decryption of aes_key failed
		if(!$decrypted){

			// return error
			$error = 'cannot decrypt key to aes_key';
			return false;
			}

		// convert message to binary
		$mand['data'] = base64_decode($mand['data']);

		// on error
		if($mand['data'] === false){

			// return error
			$error = 'decoding base64 encoded message failed';
			return false;
			}

		// generate and compare hmac (hex != hex)
		$hmac = hash_hmac("SHA256", $mand['data'], $aes_key);

		// on error
		if(strtolower($mand['hmac']) != $hmac){

			// return error
			$error = 'hmac comparison failed';
			return false;
			}

		// decrypt message
		$message = self::aes_decrypt($mand['data'], $aes_key, ['key_is'=>'binary', 'message_is'=>'binary'], $suberror);

		// on error
		if(!$message){

			// return error
			$error = 'decryption of message failed with: '.$suberror;
			return false;
			}

		// decode message from json
		$decoded_message = json_decode($message);

		// on error
		if($decoded_message === null){

			// return error
			$error = 'decoding decrypted message from json failed';
			return false;
			}

		// return success
		return (object)[
			'data'	=> $decoded_message,
			'key'	=> $aes_key,
			];
		}

	}
