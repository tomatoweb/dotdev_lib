<?
/* Default Configuration */
$cfg['blowfish_secret']			= '';
$cfg['AllowThirdPartyFraming']	= true;
$cfg['DefaultLang']				= 'de';
$cfg['ServerDefault']			= 1;
$cfg['ShowPhpInfo']				= true;
$cfg['PropertiesIconic']		= true;
$cfg['UploadDir']				= '/var/www/srv_dev/data/pma/upload';
$cfg['SaveDir']					= '/var/www/srv_dev/data/pma/export';
$cfg['SuhosinDisableWarning']	= true;
$cfg['TitleTable']				= 'pma.ma - @VSERVER@ / @DATABASE@ / @TABLE@';
$cfg['TitleDatabase']			= 'pma.ma - @VSERVER@ / @DATABASE@';
$cfg['TitleServer']				= 'pma.ma - @VSERVER@';
$cfg['TitleDefault']			= 'pma.ma';

$default_server_cfg = [
	'socket' 						=> '',
	'connect_type' 					=> 'tcp',
	'extension' 					=> 'mysqli',
	'auth_type' 					=> 'config',
	'DisableIS' 					=> false,
	'pmadb' 						=> 'phpmyadmin',
	'bookmarktable' 				=> 'pma_bookmark',
	'relation' 						=> 'pma_relation',
	'userconfig' 					=> 'pma_userconfig',
	'table_info' 					=> 'pma_table_info',
	'column_info' 					=> 'pma_column_info',
	'history' 						=> 'pma_history',
	'tracking' 						=> 'pma_tracking',
	'recent' 						=> 'pma_recent',
	'table_uiprefs' 				=> 'pma_table_uiprefs',
	'table_coords' 					=> 'pma_table_coords',
	'pdf_pages' 					=> 'pma_pdf_pages',
	'designer_coords'				=> 'pma_designer_coords',
	'users' 						=> 'pma_users',
	'usergroups'					=> 'pma_usergroups',
	'navigationhiding' 				=> 'pma_navigationhiding',
	'savedsearches' 				=> 'pma_savedsearches',
	'tracking_version_auto_create'	=> false,
	];

/* Server: Debian Dev */
$cfg['Servers'][1] = [
	'verbose' 		=> 'Debian Dev',
	'host' 			=> '127.0.0.1',
	'port' 			=> '3306',
	//'AllowNoPasswordRoot' => true,
	'user' 			=> '',
	'password' 		=> '',	
	'controluser' 	=> '',
	'controlpass' 	=> '',
	] + $default_server_cfg;