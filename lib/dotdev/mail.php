<?php
/*****
 * Version 1.0.2017-10-23
**/
namespace dotdev;

use \tools\error as e;
use \tools\helper as h;
use \synchro\phpmailer;

class mail {
	use \tools\libcom_trait;

	public static $email_regex = '^[a-z0-9\!\#\$\%\&\'\*\+\/\=\?\^\_\`\{\|\}\~\-]+(?:\.[a-z0-9\!\#\$\%\&\'\*\+\/\=\?\^\_\`\{\|\}\~\-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$';

	public static function smtp_send($req){

		// mandatory
		$mand = h::eX($req, [
			'from'		=> '~'.self::$email_regex,
			'fromname'	=> '~2,30/s',
			'to'		=> '~'.self::$email_regex,
			'subject'	=> '~1,60/s',
			'host'		=> '~^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$',
			'username'	=> '~1,60/s',
			'password'	=> '~1,60/s',
			], $error);

		// alternativ
		$alt = h::eX($req, [
			'body'		=> '~/s',
			'htmlbody'	=> '~/s',
			], $error, true);

		// on error
		if($error) return self::response(400, $error);
		elseif(empty($alt)) return self::response(400, 'Need at least body or htmlbody param');

		// create phpmailer instance
		$mail = phpmailer::get_instance();

		// config SMTP access
		$mail->IsSMTP();
		$mail->Host = $mand['host'];
		$mail->Port = 25;
		$mail->SMTPAuth = true;
		$mail->Username = $mand['username'];
		$mail->Password = $mand['password'];
		// $mail->SMTPSecure = 'tls';

		// config mail
		$mail->CharSet = 'utf8';
		$mail->From = $mand['from'];
		$mail->FromName = $mand['fromname'];
		$mail->AddAddress($mand['to']);
		$mail->Subject = $mand['subject'];

		// for html mails
		if(!empty($alt['htmlbody'])){
			$mail->IsHTML(true);
			$mail->Body = $alt['htmlbody'];
			$mail->AltBody = !empty($alt['body']) ? $alt['body'] : strip_tags($alt['htmlbody']);
			}

		// or for text mails
		else{
			$mail->Body = $alt['body'];
			}

		// send mail
		$sent = $mail->Send();

		// on error
		if(!$sent){

			// return error
			return self::response(500, 'PHPMailer error: '.h::encode_php($mail->ErrorInfo));
			}

		// return success
		return self::response(204);
		}

	}
