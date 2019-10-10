<?php

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * appDevUrlMatcher.
 *
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class appDevUrlMatcher extends Symfony\Bundle\FrameworkBundle\Routing\RedirectableUrlMatcher
{
    /**
     * Constructor.
     */
    public function __construct(RequestContext $context)
    {
        $this->context = $context;
    }

    public function match($pathinfo)
    {
        $allow = array();
        $pathinfo = rawurldecode($pathinfo);
        $context = $this->context;
        $request = $this->request;

        if (0 === strpos($pathinfo, '/_')) {
            // _wdt
            if (0 === strpos($pathinfo, '/_wdt') && preg_match('#^/_wdt/(?P<token>[^/]++)$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => '_wdt')), array (  '_controller' => 'web_profiler.controller.profiler:toolbarAction',));
            }

            if (0 === strpos($pathinfo, '/_profiler')) {
                // _profiler_home
                if (rtrim($pathinfo, '/') === '/_profiler') {
                    if (substr($pathinfo, -1) !== '/') {
                        return $this->redirect($pathinfo.'/', '_profiler_home');
                    }

                    return array (  '_controller' => 'web_profiler.controller.profiler:homeAction',  '_route' => '_profiler_home',);
                }

                if (0 === strpos($pathinfo, '/_profiler/search')) {
                    // _profiler_search
                    if ($pathinfo === '/_profiler/search') {
                        return array (  '_controller' => 'web_profiler.controller.profiler:searchAction',  '_route' => '_profiler_search',);
                    }

                    // _profiler_search_bar
                    if ($pathinfo === '/_profiler/search_bar') {
                        return array (  '_controller' => 'web_profiler.controller.profiler:searchBarAction',  '_route' => '_profiler_search_bar',);
                    }

                }

                // _profiler_purge
                if ($pathinfo === '/_profiler/purge') {
                    return array (  '_controller' => 'web_profiler.controller.profiler:purgeAction',  '_route' => '_profiler_purge',);
                }

                // _profiler_info
                if (0 === strpos($pathinfo, '/_profiler/info') && preg_match('#^/_profiler/info/(?P<about>[^/]++)$#s', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($matches, array('_route' => '_profiler_info')), array (  '_controller' => 'web_profiler.controller.profiler:infoAction',));
                }

                // _profiler_phpinfo
                if ($pathinfo === '/_profiler/phpinfo') {
                    return array (  '_controller' => 'web_profiler.controller.profiler:phpinfoAction',  '_route' => '_profiler_phpinfo',);
                }

                // _profiler_search_results
                if (preg_match('#^/_profiler/(?P<token>[^/]++)/search/results$#s', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($matches, array('_route' => '_profiler_search_results')), array (  '_controller' => 'web_profiler.controller.profiler:searchResultsAction',));
                }

                // _profiler
                if (preg_match('#^/_profiler/(?P<token>[^/]++)$#s', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($matches, array('_route' => '_profiler')), array (  '_controller' => 'web_profiler.controller.profiler:panelAction',));
                }

                // _profiler_router
                if (preg_match('#^/_profiler/(?P<token>[^/]++)/router$#s', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($matches, array('_route' => '_profiler_router')), array (  '_controller' => 'web_profiler.controller.router:panelAction',));
                }

                // _profiler_exception
                if (preg_match('#^/_profiler/(?P<token>[^/]++)/exception$#s', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($matches, array('_route' => '_profiler_exception')), array (  '_controller' => 'web_profiler.controller.exception:showAction',));
                }

                // _profiler_exception_css
                if (preg_match('#^/_profiler/(?P<token>[^/]++)/exception\\.css$#s', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($matches, array('_route' => '_profiler_exception_css')), array (  '_controller' => 'web_profiler.controller.exception:cssAction',));
                }

            }

            if (0 === strpos($pathinfo, '/_configurator')) {
                // _configurator_home
                if (rtrim($pathinfo, '/') === '/_configurator') {
                    if (substr($pathinfo, -1) !== '/') {
                        return $this->redirect($pathinfo.'/', '_configurator_home');
                    }

                    return array (  '_controller' => 'Sensio\\Bundle\\DistributionBundle\\Controller\\ConfiguratorController::checkAction',  '_route' => '_configurator_home',);
                }

                // _configurator_step
                if (0 === strpos($pathinfo, '/_configurator/step') && preg_match('#^/_configurator/step/(?P<index>[^/]++)$#s', $pathinfo, $matches)) {
                    return $this->mergeDefaults(array_replace($matches, array('_route' => '_configurator_step')), array (  '_controller' => 'Sensio\\Bundle\\DistributionBundle\\Controller\\ConfiguratorController::stepAction',));
                }

                // _configurator_final
                if ($pathinfo === '/_configurator/final') {
                    return array (  '_controller' => 'Sensio\\Bundle\\DistributionBundle\\Controller\\ConfiguratorController::finalAction',  '_route' => '_configurator_final',);
                }

            }

        }

        if (0 === strpos($pathinfo, '/shop')) {
            // shop_home
            if (rtrim($pathinfo, '/') === '/shop') {
                if (substr($pathinfo, -1) !== '/') {
                    return $this->redirect($pathinfo.'/', 'shop_home');
                }

                return array (  '_controller' => 'Acme\\ShopBundle\\Controller\\DefaultController::indexAction',  '_route' => 'shop_home',);
            }

            // getproduct
            if ($pathinfo === '/shop/getproduct.php') {
                return array (  '_controller' => 'Acme\\ShopBundle\\Controller\\DefaultController::getproductAction',  '_route' => 'getproduct',);
            }

            // order
            if ($pathinfo === '/shop/order') {
                return array (  '_controller' => 'Acme\\ShopBundle\\Controller\\DefaultController::orderAction',  '_route' => 'order',);
            }

            // delete_product
            if ($pathinfo === '/shop/delete_product') {
                return array (  '_controller' => 'Acme\\ShopBundle\\Controller\\DefaultController::deleteProductAction',  '_route' => 'delete_product',);
            }

            // test
            if ($pathinfo === '/shop/test') {
                return array (  '_controller' => 'Acme\\ShopBundle\\Controller\\DefaultController::testAction',  '_route' => 'test',);
            }

        }

        if (0 === strpos($pathinfo, '/paradigm')) {
            if (0 === strpos($pathinfo, '/paradigm/categories')) {
                // categories
                if ($pathinfo === '/paradigm/categories') {
                    return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\CategoriesController::categoriesAction',  '_route' => 'categories',);
                }

                // delete_category
                if ($pathinfo === '/paradigm/categories/delete') {
                    return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\CategoriesController::deleteCategoryAction',  '_route' => 'delete_category',);
                }

                // edit_category
                if ($pathinfo === '/paradigm/categories/edit_category') {
                    return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\CategoriesController::editCategoryAction',  '_route' => 'edit_category',);
                }

            }

            // home
            if (rtrim($pathinfo, '/') === '/paradigm') {
                if (substr($pathinfo, -1) !== '/') {
                    return $this->redirect($pathinfo.'/', 'home');
                }

                return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\DefaultController::indexAction',  '_route' => 'home',);
            }

            if (0 === strpos($pathinfo, '/paradigm/index.')) {
                // acme_paradigm_default_index
                if ($pathinfo === '/paradigm/index.html') {
                    return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\DefaultController::indexAction',  '_route' => 'acme_paradigm_default_index',);
                }

                // acme_paradigm_default_index_1
                if ($pathinfo === '/paradigm/index.php') {
                    return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\DefaultController::indexAction',  '_route' => 'acme_paradigm_default_index_1',);
                }

            }

            // acme_paradigm_default_index_2
            if ($pathinfo === '/paradigm/blabla') {
                return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\DefaultController::indexAction',  '_route' => 'acme_paradigm_default_index_2',);
            }

            // cms
            if ($pathinfo === '/paradigm/cms') {
                return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\DefaultController::cmsAction',  '_route' => 'cms',);
            }

            if (0 === strpos($pathinfo, '/paradigm/log')) {
                // login
                if ($pathinfo === '/paradigm/login') {
                    return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\DefaultController::loginAction',  '_route' => 'login',);
                }

                // logout
                if ($pathinfo === '/paradigm/logout') {
                    return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\DefaultController::logoutAction',  '_route' => 'logout',);
                }

            }

            // formu
            if ($pathinfo === '/paradigm/form') {
                return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\FormulaireController::formAction',  '_route' => 'formu',);
            }

            if (0 === strpos($pathinfo, '/paradigm/works')) {
                // works
                if ($pathinfo === '/paradigm/works') {
                    return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\WorksController::worksAction',  '_route' => 'works',);
                }

                // delete_work
                if (rtrim($pathinfo, '/') === '/paradigm/works/delete') {
                    if (substr($pathinfo, -1) !== '/') {
                        return $this->redirect($pathinfo.'/', 'delete_work');
                    }

                    return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\WorksController::deleteAction',  '_route' => 'delete_work',);
                }

                // edit_work
                if ($pathinfo === '/paradigm/works/edit') {
                    return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\WorksController::editAction',  '_route' => 'edit_work',);
                }

                // delete_image
                if ($pathinfo === '/paradigm/works/delete_image') {
                    return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\WorksController::deleteImageAction',  '_route' => 'delete_image',);
                }

                // highlight_image
                if ($pathinfo === '/paradigm/works/highlight_image') {
                    return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\WorksController::highlightImageAction',  '_route' => 'highlight_image',);
                }

            }

            // admin_login
            if ($pathinfo === '/paradigm/admin_login') {
                return array (  '_controller' => 'Acme\\ParadigmBundle\\Controller\\WorksController::admin_loginAction',  '_route' => 'admin_login',);
            }

        }

        // acme_hello_homepage
        if (0 === strpos($pathinfo, '/hello') && preg_match('#^/hello/(?P<name>[^/]++)$#s', $pathinfo, $matches)) {
            return $this->mergeDefaults(array_replace($matches, array('_route' => 'acme_hello_homepage')), array (  '_controller' => 'Acme\\HelloBundle\\Controller\\DefaultController::indexAction',));
        }

        // acme_hello_homepage_without_param
        if (rtrim($pathinfo, '/') === '') {
            if (substr($pathinfo, -1) !== '/') {
                return $this->redirect($pathinfo.'/', 'acme_hello_homepage_without_param');
            }

            return array (  '_controller' => 'Acme\\HelloBundle\\Controller\\DefaultController::index0Action',  '_route' => 'acme_hello_homepage_without_param',);
        }

        if (0 === strpos($pathinfo, '/blog')) {
            // tuto_blog_homepage
            if (0 === strpos($pathinfo, '/blog/hello') && preg_match('#^/blog/hello/(?P<name>[^/]++)$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'tuto_blog_homepage')), array (  '_controller' => 'Tuto\\BlogBundle\\Controller\\DefaultController::indexAction',));
            }

            // tuto_blog_home
            if (rtrim($pathinfo, '/') === '/blog') {
                if (substr($pathinfo, -1) !== '/') {
                    return $this->redirect($pathinfo.'/', 'tuto_blog_home');
                }

                return array (  '_controller' => 'Tuto\\BlogBundle\\Controller\\PublicController::indexAction',  '_route' => 'tuto_blog_home',);
            }

            // tuto_blog_page
            if (0 === strpos($pathinfo, '/blog/page') && preg_match('#^/blog/page/(?P<id>[^/]++)$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'tuto_blog_page')), array (  '_controller' => 'Tuto\\BlogBundle\\Controller\\PublicController::pageAction',));
            }

            // tuto_blog_article
            if (0 === strpos($pathinfo, '/blog/article') && preg_match('#^/blog/article/(?P<lang>fr|en)/(?P<annee>\\d{4})/(?P<slug>[^/\\.]++)(?:\\.(?P<format>html|rss))?$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => 'tuto_blog_article')), array (  '_controller' => 'Tuto\\BlogBundle\\Controller\\PublicController::articleAction',  'format' => 'html',));
            }

        }

        if (0 === strpos($pathinfo, '/admin/blog')) {
            // blog_admin_home
            if (rtrim($pathinfo, '/') === '/admin/blog') {
                if (!in_array($this->context->getMethod(), array('GET', 'HEAD'))) {
                    $allow = array_merge($allow, array('GET', 'HEAD'));
                    goto not_blog_admin_home;
                }

                if (substr($pathinfo, -1) !== '/') {
                    return $this->redirect($pathinfo.'/', 'blog_admin_home');
                }

                return array (  '_controller' => 'Tuto\\BlogBundle\\Controller\\AdminController::indexAction',  '_route' => 'blog_admin_home',);
            }
            not_blog_admin_home:

            // blog_admin_ajouterArticle
            if ($pathinfo === '/admin/blog/ajouter') {
                if (!in_array($this->context->getMethod(), array('GET', 'POST', 'HEAD'))) {
                    $allow = array_merge($allow, array('GET', 'POST', 'HEAD'));
                    goto not_blog_admin_ajouterArticle;
                }

                return array (  '_controller' => 'Tuto\\BlogBundle\\Controller\\AdminArticleController::ajouterAction',  '_route' => 'blog_admin_ajouterArticle',);
            }
            not_blog_admin_ajouterArticle:

            // blog_admin_editerArticle
            if (0 === strpos($pathinfo, '/admin/blog/editer') && preg_match('#^/admin/blog/editer/(?P<id>\\d+)$#s', $pathinfo, $matches)) {
                if (!in_array($this->context->getMethod(), array('GET', 'POST', 'HEAD'))) {
                    $allow = array_merge($allow, array('GET', 'POST', 'HEAD'));
                    goto not_blog_admin_editerArticle;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'blog_admin_editerArticle')), array (  '_controller' => 'Tuto\\BlogBundle\\Controller\\AdminArticleController::editerAction',));
            }
            not_blog_admin_editerArticle:

            // blog_admin_supprimerArticle
            if (0 === strpos($pathinfo, '/admin/blog/supprimer') && preg_match('#^/admin/blog/supprimer/(?P<id>\\d+)$#s', $pathinfo, $matches)) {
                if ($this->context->getMethod() != 'DELETE') {
                    $allow[] = 'DELETE';
                    goto not_blog_admin_supprimerArticle;
                }

                return $this->mergeDefaults(array_replace($matches, array('_route' => 'blog_admin_supprimerArticle')), array (  '_controller' => 'Tuto\\BlogBundle\\Controller\\AdminArticleController::supprimerAction',));
            }
            not_blog_admin_supprimerArticle:

        }

        // _welcome
        if (rtrim($pathinfo, '/') === '') {
            if (substr($pathinfo, -1) !== '/') {
                return $this->redirect($pathinfo.'/', '_welcome');
            }

            return array (  '_controller' => 'Acme\\DemoBundle\\Controller\\WelcomeController::indexAction',  '_route' => '_welcome',);
        }

        if (0 === strpos($pathinfo, '/demo')) {
            if (0 === strpos($pathinfo, '/demo/secured')) {
                if (0 === strpos($pathinfo, '/demo/secured/log')) {
                    if (0 === strpos($pathinfo, '/demo/secured/login')) {
                        // _demo_login
                        if ($pathinfo === '/demo/secured/login') {
                            return array (  '_controller' => 'Acme\\DemoBundle\\Controller\\SecuredController::loginAction',  '_route' => '_demo_login',);
                        }

                        // _security_check
                        if ($pathinfo === '/demo/secured/login_check') {
                            return array (  '_controller' => 'Acme\\DemoBundle\\Controller\\SecuredController::securityCheckAction',  '_route' => '_security_check',);
                        }

                    }

                    // _demo_logout
                    if ($pathinfo === '/demo/secured/logout') {
                        return array (  '_controller' => 'Acme\\DemoBundle\\Controller\\SecuredController::logoutAction',  '_route' => '_demo_logout',);
                    }

                }

                if (0 === strpos($pathinfo, '/demo/secured/hello')) {
                    // acme_demo_secured_hello
                    if ($pathinfo === '/demo/secured/hello') {
                        return array (  'name' => 'World',  '_controller' => 'Acme\\DemoBundle\\Controller\\SecuredController::helloAction',  '_route' => 'acme_demo_secured_hello',);
                    }

                    // _demo_secured_hello
                    if (preg_match('#^/demo/secured/hello/(?P<name>[^/]++)$#s', $pathinfo, $matches)) {
                        return $this->mergeDefaults(array_replace($matches, array('_route' => '_demo_secured_hello')), array (  '_controller' => 'Acme\\DemoBundle\\Controller\\SecuredController::helloAction',));
                    }

                    // _demo_secured_hello_admin
                    if (0 === strpos($pathinfo, '/demo/secured/hello/admin') && preg_match('#^/demo/secured/hello/admin/(?P<name>[^/]++)$#s', $pathinfo, $matches)) {
                        return $this->mergeDefaults(array_replace($matches, array('_route' => '_demo_secured_hello_admin')), array (  '_controller' => 'Acme\\DemoBundle\\Controller\\SecuredController::helloadminAction',));
                    }

                }

            }

            // _demo
            if (rtrim($pathinfo, '/') === '/demo') {
                if (substr($pathinfo, -1) !== '/') {
                    return $this->redirect($pathinfo.'/', '_demo');
                }

                return array (  '_controller' => 'Acme\\DemoBundle\\Controller\\DemoController::indexAction',  '_route' => '_demo',);
            }

            // _demo_hello
            if (0 === strpos($pathinfo, '/demo/hello') && preg_match('#^/demo/hello/(?P<name>[^/]++)$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($matches, array('_route' => '_demo_hello')), array (  '_controller' => 'Acme\\DemoBundle\\Controller\\DemoController::helloAction',));
            }

            // _demo_contact
            if ($pathinfo === '/demo/contact') {
                return array (  '_controller' => 'Acme\\DemoBundle\\Controller\\DemoController::contactAction',  '_route' => '_demo_contact',);
            }

        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
