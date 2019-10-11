<?php

/* ParadigmBundle:partials:home_header.html.twig */
class __TwigTemplate_594d8dfbd0559261eea3e5c220b43519c1de0def956306db03740aa09aff9611 extends Twig_Template
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
        $__internal_64b31b4b58153ccf4506c0b655ecd6bbadb2d1aa6c6c84a2d94c9f6b3b5da447 = $this->env->getExtension("native_profiler");
        $__internal_64b31b4b58153ccf4506c0b655ecd6bbadb2d1aa6c6c84a2d94c9f6b3b5da447->enter($__internal_64b31b4b58153ccf4506c0b655ecd6bbadb2d1aa6c6c84a2d94c9f6b3b5da447_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:partials:home_header.html.twig"));

        // line 1
        echo "<!DOCTYPE html>
<html lang=\"en\" >
<head>
    <meta charset=\"utf-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <meta name=\"description\" content=\"\">
    <meta name=\"author\" content=\"\">
    <!-- local css and js for no internet access cases -->
    ";
        // line 10
        echo "    <link rel=\"stylesheet\" href=\"http://netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap.min.css\"> 
    <title>Mona Lisa Creations</title>
    <link rel=\"stylesheet\" href=\"";
        // line 12
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/paradigm/css/paradigm.css"), "html", null, true);
        echo "\" type=\"text/css\">
    <link rel=\"icon\" href=\"";
        // line 13
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/paradigm/img/works/favicon.png"), "html", null, true);
        echo "\">
</head>
<body style=\"background-image: url('";
        // line 15
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/paradigm/img/works/background-image-music.jpg"), "html", null, true);
        echo "'); background-size:cover; background-repeat:no-repeat;\">
    <!-- NAVBAR -->
    <div class=\"navbar navbar-fixed-top navbar-inverse bs-docs-nav\" role=\"navigation\" style=\"position:relative; margin: 0; letter-spacing: 2px\">
      <div class=\"container\">
        <div id=\"navbar-header\" class=\"navbar-header\" style=\"margin-right: 0;\">
            <!-- END NAVBAR RESPONSIVE TOGGLE BUTTON ---------------------------------------------------------------------------->
            <button type=\"button\" class=\"navbar-toggle\" data-toggle=\"collapse\" data-target=\"#bs-example-navbar-collapse-1\">
              <span class=\"sr-only\">Toggle navigation</span>
              <span class=\"icon-bar\"></span>
              <span class=\"icon-bar\"></span>
              <span class=\"icon-bar\"></span>
            </button>
            <ul class=\"nav navbar-nav\">
               <li style=\"display:inline-block; width:200px; margin-left: 86px;\">
                   <a class=\"glyphicon glyphicon-home\" id=\"glyphicon1\" href=\"";
        // line 29
        echo $this->env->getExtension('routing')->getPath("home");
        echo "\" ></a>
               </li>               
            </ul>                    
        </div>
        <!-- Collect the nav links, forms, and other content for toggling -->
        <div class=\"collapse navbar-collapse\" id=\"bs-example-navbar-collapse-1\">
            <ul class=\"nav navbar-nav right-50\">
                <li style=\"display:inline-block; color:#999;\">
                    <a href=\"\" class=\"slideToggle7\" style=\"padding-top:15px\">WEB DEVELOPMENT</a>                    
                </li>
            </ul>
        <ul class=\"nav navbar-nav right-zero\">          
          <li>
            <a class=\"glyphicon glyphicon-envelope glyphicon1\" href=\"mailto:info@monalisacreations.de\" ></a>
          </li>
          <li class=\"dropdown\" style=\"padding-right: 45px;\">
            <a href=\"#\" class=\"dropdown-toggle\" data-toggle=\"dropdown\">Works <b class=\"caret\"></b></a>
            <ul class=\"dropdown-menu\">            
              <li><a href=\"";
        // line 47
        echo $this->env->getExtension('routing')->getPath("cms");
        echo "\">The International Post</a></li>
              <li><a href=\"";
        // line 48
        echo $this->env->getExtension('routing')->getPath("shop_home");
        echo "\">The e-Shop</a></li>
              <li class=\"divider\"></li>
              <li><a href=\"";
        // line 50
        echo $this->env->getExtension('routing')->getPath("home");
        echo "\">Mona Lisa Creations</a></li>
            </ul>
          </li>
          <li>
            <a href=\"";
        // line 54
        echo $this->env->getExtension('routing')->getPath("logout");
        echo "\" >logout</a>
          </li>    
        </ul>
        </div>
      </div>
    </div>
    <div class=\"navbar-content\">
        <p>Web developer since 2006, based in Germany.<br>
           Specialized in rock solid web application development coupled with responsive web design.<br>
           Every web project is built primarily using open source technologies such as PHP and MySQL.<br>
           We extensively use Symfony2 MVC Framework, jQuery, HTML5, CSS and AJAX.<br>
           We are passionate about web technologies and constantly keep an eye out for new technologies.
        </p>
    </div>
    ";
        
        $__internal_64b31b4b58153ccf4506c0b655ecd6bbadb2d1aa6c6c84a2d94c9f6b3b5da447->leave($__internal_64b31b4b58153ccf4506c0b655ecd6bbadb2d1aa6c6c84a2d94c9f6b3b5da447_prof);

    }

    public function getTemplateName()
    {
        return "ParadigmBundle:partials:home_header.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  99 => 54,  92 => 50,  87 => 48,  83 => 47,  62 => 29,  45 => 15,  40 => 13,  36 => 12,  32 => 10,  22 => 1,);
    }
}
