<?php

/* AcmeHelloBundle:Default:index.html.twig */
class __TwigTemplate_459d73849c4ae35b1c2e14604e4b85a3ca4c47123e83306c76f61ef0d47e02c2 extends Twig_Template
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
        $__internal_d5192c0179a94facde645e8843bcb6a8fca4e5784dc94316321cbb7b924d6483 = $this->env->getExtension("native_profiler");
        $__internal_d5192c0179a94facde645e8843bcb6a8fca4e5784dc94316321cbb7b924d6483->enter($__internal_d5192c0179a94facde645e8843bcb6a8fca4e5784dc94316321cbb7b924d6483_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "AcmeHelloBundle:Default:index.html.twig"));

        // line 1
        echo "<!-- after installing EMMET you need to press exclamation mark(!) and then tab key to generate basic structure of html -->
<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <title>Document</title>
</head>
<body>
    Hello (from HelloBundle) ";
        // line 9
        echo twig_escape_filter($this->env, (isset($context["name"]) ? $context["name"] : $this->getContext($context, "name")), "html", null, true);
        echo "!
</body>
</html>

";
        
        $__internal_d5192c0179a94facde645e8843bcb6a8fca4e5784dc94316321cbb7b924d6483->leave($__internal_d5192c0179a94facde645e8843bcb6a8fca4e5784dc94316321cbb7b924d6483_prof);

    }

    public function getTemplateName()
    {
        return "AcmeHelloBundle:Default:index.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  32 => 9,  22 => 1,);
    }
}
