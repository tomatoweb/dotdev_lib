<?php

/* ParadigmBundle:partials:cms_footer.html.twig */
class __TwigTemplate_cf02dc63304d4d68380f75b7c7674d1a3ff1128ddb9bf4f3ee2bacd235ab6eef extends Twig_Template
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
        $__internal_6c6929de495a405107a0e9d94ef24d78bcce0afdb37f174f809012827e4429e2 = $this->env->getExtension("native_profiler");
        $__internal_6c6929de495a405107a0e9d94ef24d78bcce0afdb37f174f809012827e4429e2->enter($__internal_6c6929de495a405107a0e9d94ef24d78bcce0afdb37f174f809012827e4429e2_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:partials:cms_footer.html.twig"));

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
        
        $__internal_6c6929de495a405107a0e9d94ef24d78bcce0afdb37f174f809012827e4429e2->leave($__internal_6c6929de495a405107a0e9d94ef24d78bcce0afdb37f174f809012827e4429e2_prof);

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
