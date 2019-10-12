<?php

/* ParadigmBundle:partials:cms_admin_header.html.twig */
class __TwigTemplate_9024b2cb747dd87519839ea23ecdf6aadc675fd92baf1b10263f60449f5bcb0a extends Twig_Template
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
        $__internal_82a7fe16feb97bf007c5f853c7ba4346b33b723d7c66d2d11d3993d5adaa70ee = $this->env->getExtension("native_profiler");
        $__internal_82a7fe16feb97bf007c5f853c7ba4346b33b723d7c66d2d11d3993d5adaa70ee->enter($__internal_82a7fe16feb97bf007c5f853c7ba4346b33b723d7c66d2d11d3993d5adaa70ee_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:partials:cms_admin_header.html.twig"));

        // line 1
        echo "<!DOCTYPE html>
<html>
<head>
<meta charset=\"utf-8\">
<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
<meta name=\"description\" content=\"\">
<meta name=\"author\" content=\"\">
<title>The International Post</title>
";
        // line 10
        echo "<link rel=\"stylesheet\" href=\"http://netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap.min.css\">
<link rel=\"stylesheet\" href=\"";
        // line 11
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/paradigm/css/paradigm.css"), "html", null, true);
        echo "\" type=\"text/css\" />
<link rel=\"icon\" href=\"";
        // line 12
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/paradigm/img/works/favicon.png"), "html", null, true);
        echo "\">
</head>
  <body style=\"background-image :url(";
        // line 14
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/paradigm/img/works/bg.jpg"), "html", null, true);
        echo "); \">    
    <div class=\"navbar navbar-fixed-top navbar-inverse bs-docs-nav\" role=\"navigation\" style=\"position:relative; margin: 0; letter-spacing: 2px\">      
          <div class=\"navbar-header\">
              <button type=\"button\" class=\"navbar-toggle\" data-toggle=\"collapse\" data-target=\"#bs-example-navbar-collapse-1\" style=\"border: 1px solid grey;\">
                  <span class=\"sr-only\">Toggle navigation</span>
                  <span class=\"icon-bar\" style=\"background-color: grey;\"></span>
                  <span class=\"icon-bar\" style=\"background-color: grey;\"></span>
                  <span class=\"icon-bar\" style=\"background-color: grey;\"></span>
              </button>
              <a class=\"glyphicon glyphicon-home\" id=\"glyphicon2\" href=\"";
        // line 23
        echo $this->env->getExtension('routing')->getPath("home");
        echo "\" ></a>          
          </div>
          <!-- Collect the nav links, forms, and other content for toggling -->
          <div class=\"collapse navbar-collapse\" id=\"bs-example-navbar-collapse-1\" style=\"border-color:none\">
          <ul class=\"nav navbar-nav right-50\">
              <li >
                  <a href=\"";
        // line 29
        echo $this->env->getExtension('routing')->getPath("cms");
        echo "\" >THE INTERNATIONAL POST</a>              
              </li>
              <li class=\"dropdown\">
                  <a href=\"#\" data-toggle=\"dropdown\" class=\"dropdown-toggle\">ARTICLES<span class=\"caret\"></span></a>
                  <ul class=\"dropdown-menu\">
                      <li><a href=\"";
        // line 34
        echo $this->env->getExtension('routing')->getPath("works");
        echo "\">Edit an Article</a></li>
                      <li><a href=\"";
        // line 35
        echo $this->env->getExtension('routing')->getPath("edit_work");
        echo "\">Create a new Article</a></li>                 
                      
                  </ul>
              </li>
              <li class=\"dropdown\">
                  <a href=\"#\" data-toggle=\"dropdown\" class=\"dropdown-toggle\">CATEGORIES<span class=\"caret\"></span></a>
                  <ul class=\"dropdown-menu\">
                      <li><a href=\"";
        // line 42
        echo $this->env->getExtension('routing')->getPath("categories");
        echo "\">Edit a Category</a></li>
                      <li><a href=\"";
        // line 43
        echo $this->env->getExtension('routing')->getPath("edit_category");
        echo "\">Create a new Category</a></li>                      
                  </ul>
              </li>          
          </ul>
          <ul class=\"nav navbar-nav right-zero\">
              <li>
                  <a href=\"";
        // line 49
        echo $this->env->getExtension('routing')->getPath("logout");
        echo "\" >logout</a>
              </li>    
          </ul>
        </div>      
    </div><!-- /.navbar -->";
        
        $__internal_82a7fe16feb97bf007c5f853c7ba4346b33b723d7c66d2d11d3993d5adaa70ee->leave($__internal_82a7fe16feb97bf007c5f853c7ba4346b33b723d7c66d2d11d3993d5adaa70ee_prof);

    }

    public function getTemplateName()
    {
        return "ParadigmBundle:partials:cms_admin_header.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  100 => 49,  91 => 43,  87 => 42,  77 => 35,  73 => 34,  65 => 29,  56 => 23,  44 => 14,  39 => 12,  35 => 11,  32 => 10,  22 => 1,);
    }
}
