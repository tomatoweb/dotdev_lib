<?php

/* ParadigmBundle:partials:home_header_new.html.twig */
class __TwigTemplate_bcaba7fe629a004c87284321df5122c0683c60ba6074129bc19206c2a8df96c9 extends Twig_Template
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
        $__internal_2922476baa395989ed1fd7d67d95f40d181d5aa0754cbcf393dce6d08603184e = $this->env->getExtension("native_profiler");
        $__internal_2922476baa395989ed1fd7d67d95f40d181d5aa0754cbcf393dce6d08603184e->enter($__internal_2922476baa395989ed1fd7d67d95f40d181d5aa0754cbcf393dce6d08603184e_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:partials:home_header_new.html.twig"));

        // line 1
        echo "<!DOCTYPE html>
<html lang=\"en\" >
<head>
    <meta charset=\"utf-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <meta name=\"description\" content=\"\">
    <meta name=\"author\" content=\"\">
    <title>Tomatoweb Development</title>
    <link rel=\"stylesheet\" href=\"";
        // line 9
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/paradigm/css/paradigm2.css"), "html", null, true);
        echo "\" type=\"text/css\">
    <link href=\"//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css\" rel=\"stylesheet\">
    <link rel=\"icon\" href=\"";
        // line 11
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/paradigm/img/works/favicon.png"), "html", null, true);
        echo "\">
</head>
<body>";
        
        $__internal_2922476baa395989ed1fd7d67d95f40d181d5aa0754cbcf393dce6d08603184e->leave($__internal_2922476baa395989ed1fd7d67d95f40d181d5aa0754cbcf393dce6d08603184e_prof);

    }

    public function getTemplateName()
    {
        return "ParadigmBundle:partials:home_header_new.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  37 => 11,  32 => 9,  22 => 1,);
    }
}
