<?php
return (object)[
	'ID'			=> 'flirddy_de',
	'name'			=> 'Flirddy CherryChat Chat-Fever',
	'lang'			=> 'de',
	'path_app'		=> $_SERVER['DOCUMENT_ROOT'],
	'path_pages'	=> $_SERVER['DOCUMENT_ROOT'].'/pages/cherry/de',
	'build' 		=> [
		'path_source'	=> $_SERVER['DOCUMENT_ROOT'].'/build',
		'path_result'	=> $_SERVER['DATA_PATH'].'/bragiportal/build',
		'path_cachefile'=> $_SERVER['DATA_PATH'].'/bragiportal',
		'url_prefix'	=> '/build',
		'version'		=> 'flirddy_1',
		'updatekey'		=> '2019-06-13_01',
		'less_file'		=> [
			'/css/flirddy.less',
			'/css/tooltipster.bundle.less'
		],
		'less_import_dir'=> '/css',
		'js_file'		=> [
			'/js/bragiportal.js',
			'/js/tooltipster.bundle.js'
		],
		'js_use_closure'=> false,
		'copy'			=> ['/img', '/fonts', '/resources'],
		],
	'tan_sms_str'	=> 'Die TAN für Ihre {title} Chats lautet: {tan}',
	'routes'		=> [
		'/'				=> ['page'=>'index'],
		'/sms'			=> ['page'=>'index'],
		],
	'payment'		=> [
		'bgimg'			=> '/img/bg.png',
		'google_banner'	=> '/img/flirddy/de/google-play-badge.png',
		'slogan'		=> 'Flirten. Chatten. Neue Leute kennenlernen!',
		'cherry_apk_url'	=> 'https://play.google.com/store/apps',
		'flirddy_apk_url'	=> 'https://play.google.com/store/apps',
		'apk_url_de'	=> 'https://app.adjust.com/nrefcs',
		'apk_url_at'	=> 'https://app.adjust.com/nrefcs',
		'apk_url_ch'	=> 'https://app.adjust.com/nrefcs',
		'apk_url_hu'	=> 'https://app.adjust.com/nrefcs',
		],
	'scheme'		=> [
		'flirddy'		=> 'flirddy',
		'cherry'		=> 'cherry',
		'fever'			=> 'flirddy',
		],
	'title'		=> [
		'flirddy'		=> 'Flirddy',
		'cherry'		=> 'SimplyChat',
		'fever'			=> 'Chat-Fever',
		],
	];
