<?php

/* ParadigmBundle:partials:cms_header.html.twig */
class __TwigTemplate_e61099b7aa076d399cf65d2e8b6ec1ec576312cccfd65b091f0de11c84224efe extends Twig_Template
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
        $__internal_e0b9f5bd00f4b542f5f81bfb908cdba91ce68029f20be3a7541fdc6a7a9ec328 = $this->env->getExtension("native_profiler");
        $__internal_e0b9f5bd00f4b542f5f81bfb908cdba91ce68029f20be3a7541fdc6a7a9ec328->enter($__internal_e0b9f5bd00f4b542f5f81bfb908cdba91ce68029f20be3a7541fdc6a7a9ec328_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:partials:cms_header.html.twig"));

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
<link href='https://fonts.googleapis.com/css?family=Roboto' rel='stylesheet' type='text/css'>
<link rel=\"icon\" href=\"";
        // line 13
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/paradigm/img/works/favicon.png"), "html", null, true);
        echo "\">
<script src=\"https://www.google.com/recaptcha/api.js?hl=en\"></script>
</head>
<body style=\"font-family: 'Roboto', sans-serif;\">
    <div id=\"cms-container\">
    <div class=\"navbar bs-docs-nav\" id=\"cms_navbar\" role=\"navigation\">
        <div class=\"container\" id=\"cms_navbar_container\">
            <div class=\"navbar-header\" style=\"float:none\">
                <button type=\"button\" class=\"navbar-toggle\" data-toggle=\"collapse\" data-target=\"#bs-example-navbar-collapse-1\" style=\"border: 1px solid grey;\">
                    <span class=\"sr-only\">Toggle navigation</span>
                    <span class=\"icon-bar\" style=\"background-color: grey;\"></span>
                    <span class=\"icon-bar\" style=\"background-color: grey;\"></span>
                    <span class=\"icon-bar\" style=\"background-color: grey;\"></span>
                </button>
                <a class=\"glyphicon glyphicon-home\" id=\"glyphicon2\" href=\"";
        // line 27
        echo $this->env->getExtension('routing')->getPath("home");
        echo "\" title=\"MonaLisaCreations.de\" ></a>

                <!-- Collect the nav links, forms, and other content for toggling -->
                <div class=\"collapse navbar-collapse\" id=\"bs-example-navbar-collapse-1\" style=\"border-color:none\">
                    <ul class=\"nav navbar-nav\" style=\"margin-left: 32%;\">
                        <li class=\"dropdown\">
                            <a href=\"#\" data-toggle=\"dropdown\" class=\"dropdown-toggle\" >ARTICLES<span class=\"caret\"></span></a>
                            <ul class=\"dropdown-menu\">
                                <li><a href=\"";
        // line 35
        echo $this->env->getExtension('routing')->getPath("works");
        echo "\">Edit an Article</a></li>
                                <li><a href=\"";
        // line 36
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
                    <div>
                        <div id=\"masterHead\" >
                            <div id=\"brand\">
                                <span id=\"masterHeadLogo\">
                                    <img src=\"";
        // line 57
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/paradigm/img/works/internationalPost.png"), "html", null, true);
        echo "\" alt=\"International Post\" width=\"700\" height=\"66\" style=\"height: 100%;width: 100%;max-width: 700px;\" >
                                </span>
                            </div>
                            <ul id=\"infobar\">
                                <li class=\"infobaritem\">";
        // line 61
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, "now", "l, F d. Y"), "html", null, true);
        echo "</li>
                            </ul>
                        </div>
                        <ul id=\"cat-list\" class=\"list-inline text-center\">
            ";
        // line 65
        $context['_parent'] = (array) $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["categories"]) ? $context["categories"] : $this->getContext($context, "categories")));
        foreach ($context['_seq'] as $context["_key"] => $context["category"]) {
            // line 66
            echo "                            <li><a href=\"";
            echo twig_escape_filter($this->env, $this->env->getExtension('routing')->getPath("cms", array("category" => $this->getAttribute($context["category"], "id", array()))), "html", null, true);
            echo "\">";
            echo twig_escape_filter($this->env, $this->getAttribute($context["category"], "name", array()), "html", null, true);
            echo "</a></li>
            ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['category'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 68
        echo "                            <li><a href=\"";
        echo $this->env->getExtension('routing')->getPath("cms");
        echo "\" class=\"\">All categories</a></li>
                        </ul>
                    </div>
            </div>
        </div>
    </div>";
        
        $__internal_e0b9f5bd00f4b542f5f81bfb908cdba91ce68029f20be3a7541fdc6a7a9ec328->leave($__internal_e0b9f5bd00f4b542f5f81bfb908cdba91ce68029f20be3a7541fdc6a7a9ec328_prof);

    }

    public function getTemplateName()
    {
        return "ParadigmBundle:partials:cms_header.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  134 => 68,  123 => 66,  119 => 65,  112 => 61,  105 => 57,  94 => 49,  85 => 43,  81 => 42,  72 => 36,  68 => 35,  57 => 27,  40 => 13,  35 => 11,  32 => 10,  22 => 1,);
    }
}
