Symfony 2 tips
==============

Swich between DEV and PROD environment
--------------------------------------
in web/app.php line 32
    $kernel = new AppKernel('prod', true)
    $kernel = new AppKernel('dev', true)
DEV mode displays the Symfony DEBUG TOOLBAR on the bottom (will NOT display without <html> tag in html page code)

Own PHP encode tool (from library /lib/tools/helper.php
-------------------------------------------------------
echo'<pre>'.h::encode_php(scandir(__DIR__));die;

Will display any variable of any type in a human friendly readable format

