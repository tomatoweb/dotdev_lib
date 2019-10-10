<?php

use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Psr\Log\LoggerInterface;

/**
 * appProdUrlGenerator
 *
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class appProdUrlGenerator extends Symfony\Component\Routing\Generator\UrlGenerator
{
    private static $declaredRoutes = array(
        'shop_home' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ShopBundle\\Controller\\DefaultController::indexAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/shop/',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'getproduct' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ShopBundle\\Controller\\DefaultController::getproductAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/shop/getproduct.php',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'order' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ShopBundle\\Controller\\DefaultController::orderAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/shop/order',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'delete_product' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ShopBundle\\Controller\\DefaultController::deleteProductAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/shop/delete_product',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'test' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ShopBundle\\Controller\\DefaultController::testAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/shop/test',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'categories' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\CategoriesController::categoriesAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/categories',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'delete_category' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\CategoriesController::deleteCategoryAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/categories/delete',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'edit_category' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\CategoriesController::editCategoryAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/categories/edit_category',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'home' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\DefaultController::indexAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'acme_paradigm_default_index' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\DefaultController::indexAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/index.html',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'acme_paradigm_default_index_1' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\DefaultController::indexAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/index.php',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'cms' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\DefaultController::cmsAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/cms',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'login' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\DefaultController::loginAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/login',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'logout' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\DefaultController::logoutAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/logout',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'formu' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\FormulaireController::formAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/form',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'works' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\WorksController::worksAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/works',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'delete_work' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\WorksController::deleteAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/works/delete/',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'edit_work' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\WorksController::editAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/works/edit',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'delete_image' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\WorksController::deleteImageAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/works/delete_image',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'highlight_image' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\WorksController::highlightImageAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/works/highlight_image',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'admin_login' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\ParadigmBundle\\Controller\\WorksController::admin_loginAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/paradigm/admin_login',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'acme_hello_homepage' => array (  0 =>   array (    0 => 'name',  ),  1 =>   array (    '_controller' => 'Acme\\HelloBundle\\Controller\\DefaultController::indexAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'variable',      1 => '/',      2 => '[^/]++',      3 => 'name',    ),    1 =>     array (      0 => 'text',      1 => '/hello',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'acme_hello_homepage_without_param' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Acme\\HelloBundle\\Controller\\DefaultController::index0Action',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'tuto_blog_homepage' => array (  0 =>   array (    0 => 'name',  ),  1 =>   array (    '_controller' => 'Tuto\\BlogBundle\\Controller\\DefaultController::indexAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'variable',      1 => '/',      2 => '[^/]++',      3 => 'name',    ),    1 =>     array (      0 => 'text',      1 => '/blog/hello',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'tuto_blog_home' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Tuto\\BlogBundle\\Controller\\PublicController::indexAction',  ),  2 =>   array (  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/blog/',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'tuto_blog_page' => array (  0 =>   array (    0 => 'id',  ),  1 =>   array (    '_controller' => 'Tuto\\BlogBundle\\Controller\\PublicController::pageAction',  ),  2 =>   array (    'page' => '\\d+',  ),  3 =>   array (    0 =>     array (      0 => 'variable',      1 => '/',      2 => '[^/]++',      3 => 'id',    ),    1 =>     array (      0 => 'text',      1 => '/blog/page',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'tuto_blog_article' => array (  0 =>   array (    0 => 'lang',    1 => 'annee',    2 => 'slug',    3 => 'format',  ),  1 =>   array (    '_controller' => 'Tuto\\BlogBundle\\Controller\\PublicController::articleAction',    'format' => 'html',  ),  2 =>   array (    'lang' => 'fr|en',    'annee' => '\\d{4}',    'format' => 'html|rss',  ),  3 =>   array (    0 =>     array (      0 => 'variable',      1 => '.',      2 => 'html|rss',      3 => 'format',    ),    1 =>     array (      0 => 'variable',      1 => '/',      2 => '[^/\\.]++',      3 => 'slug',    ),    2 =>     array (      0 => 'variable',      1 => '/',      2 => '\\d{4}',      3 => 'annee',    ),    3 =>     array (      0 => 'variable',      1 => '/',      2 => 'fr|en',      3 => 'lang',    ),    4 =>     array (      0 => 'text',      1 => '/blog/article',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'blog_admin_home' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Tuto\\BlogBundle\\Controller\\AdminController::indexAction',  ),  2 =>   array (    '_method' => 'GET',  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/admin/blog/',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'blog_admin_ajouterArticle' => array (  0 =>   array (  ),  1 =>   array (    '_controller' => 'Tuto\\BlogBundle\\Controller\\AdminArticleController::ajouterAction',  ),  2 =>   array (    '_method' => 'GET|POST',  ),  3 =>   array (    0 =>     array (      0 => 'text',      1 => '/admin/blog/ajouter',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'blog_admin_editerArticle' => array (  0 =>   array (    0 => 'id',  ),  1 =>   array (    '_controller' => 'Tuto\\BlogBundle\\Controller\\AdminArticleController::editerAction',  ),  2 =>   array (    'id' => '\\d+',    '_method' => 'GET|POST',  ),  3 =>   array (    0 =>     array (      0 => 'variable',      1 => '/',      2 => '\\d+',      3 => 'id',    ),    1 =>     array (      0 => 'text',      1 => '/admin/blog/editer',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
        'blog_admin_supprimerArticle' => array (  0 =>   array (    0 => 'id',  ),  1 =>   array (    '_controller' => 'Tuto\\BlogBundle\\Controller\\AdminArticleController::supprimerAction',  ),  2 =>   array (    'id' => '\\d+',    '_method' => 'DELETE',  ),  3 =>   array (    0 =>     array (      0 => 'variable',      1 => '/',      2 => '\\d+',      3 => 'id',    ),    1 =>     array (      0 => 'text',      1 => '/admin/blog/supprimer',    ),  ),  4 =>   array (  ),  5 =>   array (  ),),
    );

    /**
     * Constructor.
     */
    public function __construct(RequestContext $context, LoggerInterface $logger = null)
    {
        $this->context = $context;
        $this->logger = $logger;
    }

    public function generate($name, $parameters = array(), $referenceType = self::ABSOLUTE_PATH)
    {
        if (!isset(self::$declaredRoutes[$name])) {
            throw new RouteNotFoundException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $name));
        }

        list($variables, $defaults, $requirements, $tokens, $hostTokens, $requiredSchemes) = self::$declaredRoutes[$name];

        return $this->doGenerate($variables, $defaults, $requirements, $tokens, $parameters, $name, $referenceType, $hostTokens, $requiredSchemes);
    }
}
