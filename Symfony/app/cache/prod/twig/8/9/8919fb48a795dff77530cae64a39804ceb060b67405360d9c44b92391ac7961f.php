<?php

/* ParadigmBundle:partials:home_header_new.html.twig */
class __TwigTemplate_8919fb48a795dff77530cae64a39804ceb060b67405360d9c44b92391ac7961f extends Twig_Template
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
        $__internal_24bc1862363e8c9f3d6884c41389da3702cbb08618eaf4ac71525ed02aff3198 = $this->env->getExtension("native_profiler");
        $__internal_24bc1862363e8c9f3d6884c41389da3702cbb08618eaf4ac71525ed02aff3198->enter($__internal_24bc1862363e8c9f3d6884c41389da3702cbb08618eaf4ac71525ed02aff3198_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:partials:home_header_new.html.twig"));

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
        
        $__internal_24bc1862363e8c9f3d6884c41389da3702cbb08618eaf4ac71525ed02aff3198->leave($__internal_24bc1862363e8c9f3d6884c41389da3702cbb08618eaf4ac71525ed02aff3198_prof);

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
