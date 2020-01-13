<?php

use Symfony\Component\ClassLoader\ApcClassLoader;
use Symfony\Component\HttpFoundation\Request;

// load helper Class
require $_SERVER["DOCUMENT_ROOT"] . '/phplib/tools/helper.php';

// namespace
use tools\helper as h;

// list current directory 
//echo'<pre>'.h::encode_php(scandir(__DIR__)).'</pre>'; // scandir = ls
//die(); 

$loader = require_once __DIR__.'/../app/bootstrap.php.cache';

//var_dump($loader);die();

// Use APC for autoloading to improve performance.
// Change 'sf2' to a unique prefix in order to prevent cache key conflicts
// with other applications also using APC.
/*
$apcLoader = new ApcClassLoader('sf2', $loader);
$loader->unregister();
$apcLoader->register(true);
*/

require_once __DIR__.'/../app/AppKernel.php';
//require_once __DIR__.'/../app/AppCache.php';

$kernel = new AppKernel('prod', true);
$kernel->loadClassCache();
//$kernel = new AppCache($kernel);

// When using the HttpCache, you need to call the method in your front controller instead of relying on the configuration parameter
//Request::enableHttpMethodParameterOverride();
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
