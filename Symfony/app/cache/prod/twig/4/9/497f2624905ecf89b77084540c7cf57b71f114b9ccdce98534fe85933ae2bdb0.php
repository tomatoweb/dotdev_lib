<?php

/* ParadigmBundle:partials:home_header_new.html.twig */
class __TwigTemplate_497f2624905ecf89b77084540c7cf57b71f114b9ccdce98534fe85933ae2bdb0 extends Twig_Template
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
        $__internal_20baf82fff6ac23f6d01f2222db59a9c098e0f28d0c3aa5d07c0bc83929bb100 = $this->env->getExtension("native_profiler");
        $__internal_20baf82fff6ac23f6d01f2222db59a9c098e0f28d0c3aa5d07c0bc83929bb100->enter($__internal_20baf82fff6ac23f6d01f2222db59a9c098e0f28d0c3aa5d07c0bc83929bb100_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:partials:home_header_new.html.twig"));

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
        
        $__internal_20baf82fff6ac23f6d01f2222db59a9c098e0f28d0c3aa5d07c0bc83929bb100->leave($__internal_20baf82fff6ac23f6d01f2222db59a9c098e0f28d0c3aa5d07c0bc83929bb100_prof);

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
