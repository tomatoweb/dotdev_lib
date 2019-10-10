<?php

/* ShopBundle:partials:home_header.html.twig */
class __TwigTemplate_0e9bbd90e5aabb8c47828d3edd7a42c49f4dd0c92c33b40b1053060423ffd58e extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        $this->parent = false;

        $this->blocks = array(
        );
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        $__internal_529e213e4a39bd851ee08a8450bcdbeafd07d2dcf9e101b850885def9937291c = $this->env->getExtension("native_profiler");
        $__internal_529e213e4a39bd851ee08a8450bcdbeafd07d2dcf9e101b850885def9937291c->enter($__internal_529e213e4a39bd851ee08a8450bcdbeafd07d2dcf9e101b850885def9937291c_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ShopBundle:partials:home_header.html.twig"));

        // line 1
        echo "<!doctype html>
<html>
<head>
    <meta charset=\"utf-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <meta name=\"description\" content=\"\">
    <meta name=\"author\" content=\"\">
    <title>Shopping</title>
    <link rel=\"stylesheet\" href=\"http://netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap.min.css\">
    ";
        // line 11
        echo "    <link rel=\"stylesheet\" href=\"";
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/shop/css/shop.css"), "html", null, true);
        echo "\" type=\"text/css\" />
    <link rel=\"icon\" href=\"";
        // line 12
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/shop/images/favicon.png"), "html", null, true);
        echo "\">
</head>    
<body style=\"background-image : url(";
        // line 14
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/shop/images/bg-body.jpg"), "html", null, true);
        echo ");background-repeat: no-repeat;background-color: #ecf2f2;\">

  <nav class=\"navbar navbar-default\" role=\"navigation\" style=\"background-color: rgb(236, 242, 242);\">
  <div class=\"container-fluid\">
    <!-- Brand and toggle get grouped for better mobile display -->
    <div class=\"navbar-header\">        
      <!-- END NAVBAR RESPONSIVE TOGGLE BUTTON -->
      <button type=\"button\" class=\"navbar-toggle\" data-toggle=\"collapse\" data-target=\"#bs-example-navbar-collapse-1\">
        <span class=\"sr-only\">Toggle navigation</span>
        <span class=\"icon-bar\"></span>
        <span class=\"icon-bar\"></span>
        <span class=\"icon-bar\"></span>
      </button>
      
      <a href=\"";
        // line 28
        echo $this->env->getExtension('routing')->getPath("shop_home");
        echo "\">
        <img src=\"";
        // line 29
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/shop/images/shop5.png"), "html", null, true);
        echo "\" alt=\"shop\">
        </a>
    </div>

    <!-- Collect the nav links, forms, and other content for toggling -->
    <div class=\"collapse navbar-collapse\" id=\"bs-example-navbar-collapse-1\" style=\"\">
      <ul class=\"nav navbar-nav\">
        <li class=\"active\">
            <!-- BEST-SELLER BUTTON - - - - -->
            <form action=\"";
        // line 38
        echo $this->env->getExtension('routing')->getPath("shop_home");
        echo "\" method=\"post\">
                <input type=\"hidden\" name=\"product_name\" value=\"The Wolf of Wall Street 2013\">            
                <input type=\"submit\" value=\"Best-seller\" id=\"best-seller-btn\">
            </form>
        </li>        
        
        <!-- DROPDOWN DEPARTMENTS -->
        <li>
          <a href=\"#\" data-toggle=\"dropdown\" id=\"navbar-btn-2lines\">
              <span>Shop by<br></span>
              <span><b>Department</b></span>
              <b class=\"caret\"></b>
          </a>
          <ul class=\"dropdown-menu\">
              ";
        // line 52
        $context["i"] = 0;
        // line 53
        echo "              ";
        $context['_parent'] = (array) $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["departments"]) ? $context["departments"] : $this->getContext($context, "departments")));
        foreach ($context['_seq'] as $context["_key"] => $context["department"]) {
            echo "                
              <li><a href=\"";
            // line 54
            echo twig_escape_filter($this->env, $this->env->getExtension('routing')->getPath("shop_home", array("department" => $this->getAttribute($context["department"], "id", array()))), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, $this->getAttribute($context["department"], "name", array()), "html", null, true);
            echo "</a></li>
                ";
            // line 55
            $context["i"] = ((isset($context["i"]) ? $context["i"] : $this->getContext($context, "i")) + 1);
            // line 56
            echo "              ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['department'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 57
        echo "              <li class=\"divider\"></li>
              <li><a href=\"";
        // line 58
        echo $this->env->getExtension('routing')->getPath("shop_home");
        echo "\">All departments</a>
          </ul>
        </li>
      </ul>
        
      <!-- NAVBAR FORM -->
      <form  action=\"";
        // line 64
        echo $this->env->getExtension('routing')->getPath("shop_home");
        echo "\" method=\"post\" class=\"navbar-form\" role=\"search\" style=\"display:inline; border-top: none; border-bottom: none;\">
        <div class=\"form-group\" style=\"display: inline;\">
          <input type=\"text\"
                 id=\"product\" 
                 name=\"product_name\"                 
                 class=\"form-control\" 
                 autocomplete=\"off\"
                 autofocus
                 style=\"max-width:29%;margin-top:9px; display: inline\" 
                 placeholder=\"Search a product\" >  
          <button type=\"submit\" 
                  class=\"btn btn-default\" 
                  style=\"margin-top:9px;display:inline;\" >
                    Go
          </button>
        </div>
      </form>
      
      <!-- DROPDOWN MENU -->      
        <ul class=\"nav navbar-nav navbar-right\">
        <li>
            <a href=\"";
        // line 85
        echo $this->env->getExtension('routing')->getPath("shop_home", array("cart" => "view"));
        echo "\"  style=\"padding:0;position: absolute;margin-left: -75px;\">
                <span id=\"cart-number\">
                    ";
        // line 87
        if ( !(null === $this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "session", array()), "get", array(0 => "order"), "method"))) {
            // line 88
            echo "                        ";
            echo twig_escape_filter($this->env, twig_length_filter($this->env, $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "session", array()), "get", array(0 => "order"), "method"), "getProducts", array(), "method")), "html", null, true);
            echo "
                    ";
        }
        // line 90
        echo "                </span>
                <img src=\"";
        // line 91
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/shop/images/cart.png"), "html", null, true);
        echo "\" title=\"See my cart\" style=\"margin-top: 3%;\">
            </a>
        </li>
        <li class=\"dropdown\" style=\"border-left: 1px solid lightgray;\">
          <a href=\"#\" class=\"dropdown-toggle\" data-toggle=\"dropdown\">Works <b class=\"caret\"></b></a>
          <ul class=\"dropdown-menu\">            
            <li><a href=\"";
        // line 97
        echo $this->env->getExtension('routing')->getPath("cms");
        echo "\">The International Post</a></li>
            <li><a href=\"";
        // line 98
        echo $this->env->getExtension('routing')->getPath("shop_home");
        echo "\">The e-Shop</a></li>
            <li class=\"divider\"></li>
            <li><a href=\"";
        // line 100
        echo $this->env->getExtension('routing')->getPath("home");
        echo "\">Mona Lisa Creations</a></li>
          </ul>
        </li>
      </ul>      
    </div><!-- /.navbar-collapse -->
  </div><!-- /.container-fluid -->
</nav>
<div id=\"txtHint\"></div>
";
        
        $__internal_529e213e4a39bd851ee08a8450bcdbeafd07d2dcf9e101b850885def9937291c->leave($__internal_529e213e4a39bd851ee08a8450bcdbeafd07d2dcf9e101b850885def9937291c_prof);

    }

    public function getTemplateName()
    {
        return "ShopBundle:partials:home_header.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  186 => 100,  181 => 98,  177 => 97,  168 => 91,  165 => 90,  159 => 88,  157 => 87,  152 => 85,  128 => 64,  119 => 58,  116 => 57,  110 => 56,  108 => 55,  102 => 54,  95 => 53,  93 => 52,  76 => 38,  64 => 29,  60 => 28,  43 => 14,  38 => 12,  33 => 11,  22 => 1,);
    }
}
