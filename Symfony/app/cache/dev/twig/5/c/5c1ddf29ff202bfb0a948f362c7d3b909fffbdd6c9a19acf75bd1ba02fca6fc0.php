<?php

/* AcmeHelloBundle:Default:index0.html.twig */
class __TwigTemplate_5c1ddf29ff202bfb0a948f362c7d3b909fffbdd6c9a19acf75bd1ba02fca6fc0 extends Twig_Template
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
        $__internal_bc596993d619cbfe501fca7a3574ce391f494504c2dccd224b9f5bc9722ca8e6 = $this->env->getExtension("native_profiler");
        $__internal_bc596993d619cbfe501fca7a3574ce391f494504c2dccd224b9f5bc9722ca8e6->enter($__internal_bc596993d619cbfe501fca7a3574ce391f494504c2dccd224b9f5bc9722ca8e6_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "AcmeHelloBundle:Default:index0.html.twig"));

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
        
        $__internal_bc596993d619cbfe501fca7a3574ce391f494504c2dccd224b9f5bc9722ca8e6->leave($__internal_bc596993d619cbfe501fca7a3574ce391f494504c2dccd224b9f5bc9722ca8e6_prof);

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
