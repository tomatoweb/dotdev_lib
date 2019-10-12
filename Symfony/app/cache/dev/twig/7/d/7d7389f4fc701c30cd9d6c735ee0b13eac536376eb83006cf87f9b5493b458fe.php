<?php

/* ParadigmBundle:partials:cms_admin_footer.html.twig */
class __TwigTemplate_7d7389f4fc701c30cd9d6c735ee0b13eac536376eb83006cf87f9b5493b458fe extends Twig_Template
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
        $__internal_549b6942a3044be2d743a80c2b6a4dc5b73becee0e5cc22e08780e36be43486e = $this->env->getExtension("native_profiler");
        $__internal_549b6942a3044be2d743a80c2b6a4dc5b73becee0e5cc22e08780e36be43486e->enter($__internal_549b6942a3044be2d743a80c2b6a4dc5b73becee0e5cc22e08780e36be43486e_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:partials:cms_admin_footer.html.twig"));

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
        
        $__internal_549b6942a3044be2d743a80c2b6a4dc5b73becee0e5cc22e08780e36be43486e->leave($__internal_549b6942a3044be2d743a80c2b6a4dc5b73becee0e5cc22e08780e36be43486e_prof);

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
