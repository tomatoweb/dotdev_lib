<?php

/* AcmeHelloBundle:Default:index0.html.twig */
class __TwigTemplate_65aae4a0ad0de7b12a72525037b18e0c78fd85cb353a7542f2970131f42e4ce2 extends Twig_Template
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
        $__internal_4e623da2eb6e787b110c146c33a6207317c12feb260bf23433bacc76e54ae1cf = $this->env->getExtension("native_profiler");
        $__internal_4e623da2eb6e787b110c146c33a6207317c12feb260bf23433bacc76e54ae1cf->enter($__internal_4e623da2eb6e787b110c146c33a6207317c12feb260bf23433bacc76e54ae1cf_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "AcmeHelloBundle:Default:index0.html.twig"));

        // line 1
        echo "<!doctype html>
<html>
<head>
<meta charset=\"utf-8\">
<title>hello</title>
</head>
<body>
    Hello (from HelloBundle) no param!
</body>
</html>

";
        
        $__internal_4e623da2eb6e787b110c146c33a6207317c12feb260bf23433bacc76e54ae1cf->leave($__internal_4e623da2eb6e787b110c146c33a6207317c12feb260bf23433bacc76e54ae1cf_prof);

    }

    public function getTemplateName()
    {
        return "AcmeHelloBundle:Default:index0.html.twig";
    }

    public function getDebugInfo()
    {
        return array (  22 => 1,);
    }
}
