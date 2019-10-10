<?php

/* ParadigmBundle:partials:home_footer.html.twig */
class __TwigTemplate_a0f03b5a3d90b087b3bd07393f8ba52cf9fb5bd3353a01e04f412daa3fb1d14b extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        $this->parent = false;

        $this->blocks = array(
            'javascripts' => array($this, 'block_javascripts'),
        );
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        $__internal_d2ea96ccfd46cd294a3595d42208ece5a93047804a8452abc61b2bd452521bdd = $this->env->getExtension("native_profiler");
        $__internal_d2ea96ccfd46cd294a3595d42208ece5a93047804a8452abc61b2bd452521bdd->enter($__internal_d2ea96ccfd46cd294a3595d42208ece5a93047804a8452abc61b2bd452521bdd_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:partials:home_footer.html.twig"));

        // line 1
        echo "<div id=\"footer\" style=\"position: fixed\">Development/Design by MONA LISA Creations <span style=\"font-family:arial;font-size:12px\"> ©</span> ";
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, "now", "Y"), "html", null, true);
        echo "</div>
<script src=\"http://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js\"></script>
<script src=\"http://netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js\"></script>
";
        // line 4
        $this->displayBlock('javascripts', $context, $blocks);
        // line 32
        echo "</body>
</html>

";
        
        $__internal_d2ea96ccfd46cd294a3595d42208ece5a93047804a8452abc61b2bd452521bdd->leave($__internal_d2ea96ccfd46cd294a3595d42208ece5a93047804a8452abc61b2bd452521bdd_prof);

    }

    // line 4
    public function block_javascripts($context, array $blocks = array())
    {
        $__internal_9be8a75deebadf63f2fd58c07f563089715ad83427bc888c567743c057a56bc2 = $this->env->getExtension("native_profiler");
        $__internal_9be8a75deebadf63f2fd58c07f563089715ad83427bc888c567743c057a56bc2->enter($__internal_9be8a75deebadf63f2fd58c07f563089715ad83427bc888c567743c057a56bc2_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "javascripts"));

        // line 5
        echo "<script>
    \$(document).ready(function(){
       \$( \".slideToggle\" ).click(function(){
            \$( \".sub-menu\" ).fadeToggle(300);
            if(\$(\".sub-menu\").is(\":visible\") === \$(\".sub-menu1\").is(\":visible\")){
                    \$(\".sub-menu1\").fadeToggle(300);
            }
       });
       \$(\".slideToggle1\").click(function(){
            \$(\".sub-menu1\").fadeToggle(300);
            if (\$(\".sub-menu\").is(\":visible\") === \$(\".sub-menu1\").is(\":visible\")){
                \$(\".sub-menu\").fadeToggle(300);
            }
       });
       \$(\".slideToggle3\").mouseover(function() {\$(\".end-content\").fadeToggle(300);})
                         .mouseout(function()  {\$(\".end-content\").fadeToggle(300);});
       \$(\".slideToggle4\").mouseover(function() {\$(\".end-content1\").fadeToggle(300);})
                         .mouseout(function()  {\$(\".end-content1\").fadeToggle(300);});
       \$(\".slideToggle5\").mouseover(function() {\$(\".end-content2\").fadeToggle(300);})
                         .mouseout(function()  {\$(\".end-content2\").fadeToggle(300);});
       \$(\".slideToggle6\").mouseover(function() {\$(\".end-content3\").fadeToggle(300);})
                         .mouseout(function()  {\$(\".end-content3\").fadeToggle(300);});
       \$(\".slideToggle7\").mouseenter(function() {\$(\".navbar-content\").fadeIn(300);})
                         .mouseleave(function()  {\$(\".navbar-content\").fadeOut(300);});
    });
</script>
";
        
        $__internal_9be8a75deebadf63f2fd58c07f563089715ad83427bc888c567743c057a56bc2->leave($__internal_9be8a75deebadf63f2fd58c07f563089715ad83427bc888c567743c057a56bc2_prof);

    }

    public function getTemplateName()
    {
        return "ParadigmBundle:partials:home_footer.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  48 => 5,  42 => 4,  32 => 32,  30 => 4,  23 => 1,);
    }
}
