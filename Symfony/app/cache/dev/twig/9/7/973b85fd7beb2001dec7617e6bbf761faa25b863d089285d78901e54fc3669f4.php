<?php

/* ParadigmBundle:partials:home_footer.html.twig */
class __TwigTemplate_973b85fd7beb2001dec7617e6bbf761faa25b863d089285d78901e54fc3669f4 extends Twig_Template
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
        $__internal_dade6a81dec7701ad6a22776522679f84903a5880b767d2ad37753d22549c308 = $this->env->getExtension("native_profiler");
        $__internal_dade6a81dec7701ad6a22776522679f84903a5880b767d2ad37753d22549c308->enter($__internal_dade6a81dec7701ad6a22776522679f84903a5880b767d2ad37753d22549c308_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:partials:home_footer.html.twig"));

        // line 1
        echo "<div id=\"footer\" style=\"position: fixed\">Development/Design by MONA LISA Creations <span style=\"font-family:arial;font-size:12px\"> ©</span> ";
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, "now", "Y"), "html", null, true);
        echo "</div>
<script src=\"http://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js\"></script>
<script src=\"http://netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js\"></script>
";
        // line 4
        $this->displayBlock('javascripts', $context, $blocks);
        // line 34
        echo "</body>
</html>

";
        
        $__internal_dade6a81dec7701ad6a22776522679f84903a5880b767d2ad37753d22549c308->leave($__internal_dade6a81dec7701ad6a22776522679f84903a5880b767d2ad37753d22549c308_prof);

    }

    // line 4
    public function block_javascripts($context, array $blocks = array())
    {
        $__internal_73717e38466429cd34d14dde50769e4e63c121e7d4422ddd23eb1aeaf714a7f9 = $this->env->getExtension("native_profiler");
        $__internal_73717e38466429cd34d14dde50769e4e63c121e7d4422ddd23eb1aeaf714a7f9->enter($__internal_73717e38466429cd34d14dde50769e4e63c121e7d4422ddd23eb1aeaf714a7f9_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "javascripts"));

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
      
      \$('div.alert.alert-danger').delay(7000).slideUp(300);
    });
</script>
";
        
        $__internal_73717e38466429cd34d14dde50769e4e63c121e7d4422ddd23eb1aeaf714a7f9->leave($__internal_73717e38466429cd34d14dde50769e4e63c121e7d4422ddd23eb1aeaf714a7f9_prof);

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
        return array (  48 => 5,  42 => 4,  32 => 34,  30 => 4,  23 => 1,);
    }
}
