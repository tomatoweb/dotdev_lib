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
load helper Class  
`require $_SERVER["DOCUMENT_ROOT"] . '../lib/tools/helper.php';`

namespace  
`use tools\helper as h;`
`echo "<pre>".h::encode_php(scandir(__DIR__));die;`

Will display any variable of any type in a human friendly readable format

Line breaks in markdown (.md files)
-----------------------------------
Hello <-two spaces  
World