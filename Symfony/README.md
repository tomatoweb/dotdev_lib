<p align="center"><a href="https://symfony.com" target="_blank">
    <img src="https://symfony.com/logos/symfony_black_02.svg">
</a></p>

[Symfony][1] is a **PHP framework** for web applications and a set of reusable
**PHP components**. Symfony is used by thousands of web applications (including
BlaBlaCar.com and Spotify.com) and most of the [popular PHP projects][2] (including
Drupal and Magento).


Symfony tips
============

Swich between DEV and PROD environment
--------------------------------------
in web/app.php line 32  
    `$kernel = new AppKernel('prod', true)`    
    `$kernel = new AppKernel('dev', true)`    
DEV mode displays the Symfony DEBUG TOOLBAR on the bottom (will NOT display without `<html>` tag in html page code)

Own PHP encode tool (from library /lib/tools/helper.php
-------------------------------------------------------
load helper Class  
`require $_SERVER["DOCUMENT_ROOT"] . '../lib/tools/helper.php';`

namespace  
`use tools\helper as h;`  

use with pre tag  
`echo "<pre>".h::encode_php($anything);die;`

Will display any variable of any type in a human friendly readable format

Line breaks in markdown (.md files)
-----------------------------------
Hello <-two spaces  
World  
or  
Hello\\\
World