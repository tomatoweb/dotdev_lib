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






[1]: https://symfony.com
[2]: https://symfony.com/projects
[3]: https://symfony.com/doc/current/reference/requirements.html
[4]: https://symfony.com/doc/current/setup.html
[5]: http://semver.org
[6]: https://symfony.com/doc/current/contributing/community/releases.html
[7]: https://symfony.com/doc/current/page_creation.html
[8]: https://symfony.com/doc/current/index.html
[9]: https://symfony.com/doc/current/components/index.html
[10]: https://symfony.com/doc/current/best_practices/index.html
[11]: https://symfony.com/community
[12]: https://symfony.com/events/
[13]: https://symfony.com/support
[14]: https://github.com/symfony
[15]: https://twitter.com/symfony
[16]: https://www.facebook.com/SymfonyFramework/
[17]: https://symfony.com/doc/current/contributing/code/index.html
[18]: https://symfony.com/doc/current/contributing/documentation/index.html
[19]: https://symfony.com/contributors
[20]: https://symfony.com/security
[21]: https://sensiolabs.com
[22]: https://symfony.com/doc/current/contributing/code/core_team.html
[23]: https://github.com/symfony/symfony-demo
[24]: https://symfony.com/coc
[25]: https://symfony.com/doc/current/contributing/code_of_conduct/care_team.html