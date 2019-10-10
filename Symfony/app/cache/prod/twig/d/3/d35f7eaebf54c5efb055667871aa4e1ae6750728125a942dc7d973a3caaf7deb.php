<?php

/* ParadigmBundle:partials:cms_admin_footer.html.twig */
class __TwigTemplate_d35f7eaebf54c5efb055667871aa4e1ae6750728125a942dc7d973a3caaf7deb extends Twig_Template
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
        $__internal_87c8f2139f095e2adcacff21f6c41a17cb8738eb8c2be972a4941ba5a111ab81 = $this->env->getExtension("native_profiler");
        $__internal_87c8f2139f095e2adcacff21f6c41a17cb8738eb8c2be972a4941ba5a111ab81->enter($__internal_87c8f2139f095e2adcacff21f6c41a17cb8738eb8c2be972a4941ba5a111ab81_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:partials:cms_admin_footer.html.twig"));

        // line 1
        echo "<script src=\"http://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js\"></script>
<script src=\"http://netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js\"></script>
<script src=\"";
        // line 3
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("bundles/paradigm/js/validateForms.js"), "html", null, true);
        echo "\"></script>
<div id=\"footer\">
    Development/Design by 
    <a href=\"";
        // line 6
        echo $this->env->getExtension('routing')->getPath("home");
        echo "\" id=\"footer-link\" title='MonaLisaCreations.de'>
        MONA LISA Creations <span style=\"font-family:arial;font-size:12px\"> ©</span> ";
        // line 7
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, "now", "Y"), "html", null, true);
        echo "
    </a>
</div>
</body>
</html>

";
        
        $__internal_87c8f2139f095e2adcacff21f6c41a17cb8738eb8c2be972a4941ba5a111ab81->leave($__internal_87c8f2139f095e2adcacff21f6c41a17cb8738eb8c2be972a4941ba5a111ab81_prof);

    }

    public function getTemplateName()
    {
        return "ParadigmBundle:partials:cms_admin_footer.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  36 => 7,  32 => 6,  26 => 3,  22 => 1,);
    }
}
