<?php

/* ParadigmBundle:partials:cms_footer.html.twig */
class __TwigTemplate_86ca4ade698a9788b845599b1ee3a4787469e44c016a5d5e0d081d590f351402 extends Twig_Template
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
        $__internal_f44ec50fa1e2414f90b7c1200ceb57d2cb0bdee5f68448c2e8f94a2be2061262 = $this->env->getExtension("native_profiler");
        $__internal_f44ec50fa1e2414f90b7c1200ceb57d2cb0bdee5f68448c2e8f94a2be2061262->enter($__internal_f44ec50fa1e2414f90b7c1200ceb57d2cb0bdee5f68448c2e8f94a2be2061262_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:partials:cms_footer.html.twig"));

        // line 1
        echo "</div>
<script src=\"http://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js\"></script>
<script src=\"http://netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js\"></script>
<script src=\"";
        // line 4
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("js/jquery.dotdotdot-1.4.0.js"), "html", null, true);
        echo "\"></script>
<script>
\$(document).ready(function() {
    \$(\".dot-dot-dot\").dotdotdot({height : 380});
});
</script>
<div id=\"footer\" style=\"position: fixed;\">
    Development/Design by
    <a href=\"";
        // line 12
        echo $this->env->getExtension('routing')->getPath("home");
        echo "\" id=\"footer-link\" title='MonaLisaCreations.de'>
        MONA LISA Creations <span style=\"font-family:arial;font-size:12px\"> ©</span> ";
        // line 13
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, "now", "Y"), "html", null, true);
        echo "
    </a>
</div>
</div>
</body>
</html>

";
        
        $__internal_f44ec50fa1e2414f90b7c1200ceb57d2cb0bdee5f68448c2e8f94a2be2061262->leave($__internal_f44ec50fa1e2414f90b7c1200ceb57d2cb0bdee5f68448c2e8f94a2be2061262_prof);

    }

    public function getTemplateName()
    {
        return "ParadigmBundle:partials:cms_footer.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  42 => 13,  38 => 12,  27 => 4,  22 => 1,);
    }
}
