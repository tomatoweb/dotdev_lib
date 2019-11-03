<?php
// Bugfix
if(!isset($_SERVER['HTTP_ACCEPT'])) $_SERVER['HTTP_ACCEPT'] = '';

// https://www.php.net/manual/en/features.connection-handling.php
if(!empty($_SERVER['IGNORE_USER_ABORT'])) ignore_user_abort(true);

// Allgemeine Bibliothek per PSR-0 AutoLoad verfügbar machen
if(empty($_SERVER['ENV_PATH'])){
	trigger_error('No ENV_PATH set');
	// header('HTTP/1.1 500 Internal Server Error');
	exit;
	}

// PSR-0 Autoloader
include $_SERVER['ENV_PATH'].'/lib/tools/psrloader.php';

\tools\psrloader::register($_SERVER['ENV_PATH'].'/lib');

// Error Handler laden
if(!empty($_SERVER['LOG_PATH'])){
	$error_handler_config = ['log' => \tools\error::log_fn($_SERVER['LOG_PATH'], '{req_Y}-{req_m}', '{req_d}.log')];
	if(!empty($_SERVER['PRINT_ERROR']))	$error_handler_config['print'] = \tools\error::print_fn(true);
	\tools\error::register($error_handler_config, $_SERVER['ENV_PATH']);
	unset($error_handler_config);
	}

// Weitere Bibliothek laden
if(!empty($_SERVER['APP_LIB_PATH'])) \tools\psrloader::register($_SERVER['APP_LIB_PATH']);

// Controller instanzieren
if(!empty($_SERVER['APP_CONTROLLER'])){
	$controller = new $_SERVER['APP_CONTROLLER'];

	}

// oder Script starten
elseif(!empty($_SERVER['APP_SCRIPT'])){
	include $_SERVER['APP_SCRIPT'];
	};
