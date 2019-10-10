<?php

/* ShopBundle:partials:home_footer.html.twig */
class __TwigTemplate_b0a4322e82e71e43b5ec569c5e70986631d0b4c4a45d19b24dd264de37eb5614 extends Twig_Template
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
        $__internal_f829cd6004d9dacef255673e6c5cf5a40c24f3c296b93f88ae24509fadebe1c4 = $this->env->getExtension("native_profiler");
        $__internal_f829cd6004d9dacef255673e6c5cf5a40c24f3c296b93f88ae24509fadebe1c4->enter($__internal_f829cd6004d9dacef255673e6c5cf5a40c24f3c296b93f88ae24509fadebe1c4_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ShopBundle:partials:home_footer.html.twig"));

        // line 1
        echo "<div id=\"footer\">
    Development/Design by 
    <a href=\"";
        // line 3
        echo $this->env->getExtension('routing')->getPath("home");
        echo "\" id=\"footer-link\" title=\"MonaLisaCreations.de\">MONA LISA Creations</a>
     <span style=\"font-family:arial;font-size:12px\"> ©</span> ";
        // line 4
        echo twig_escape_filter($this->env, twig_date_format_filter($this->env, "now", "Y"), "html", null, true);
        echo "
</div>
<script src=\"http://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js\"></script>
<script src=\"http://netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js\"></script>
<script src=\"//code.jquery.com/ui/1.10.4/jquery-ui.js\"></script>
<script>

\$(document).ready(function(){    
    \$(\"#product\").autocomplete({
        source: function (request, response) {                        
            \$.get(\"getproduct.php?q=\" + request.term, function(data) {                
                                                                response(data);
                                                           });
        },
        minLength: 1
    });
});

       
/* With xmlhttprequest */
/*
\$(document).ready(function(){
    \$(\"#product\").keyup(function(){    
        if (window.XMLHttpRequest) {
            xmlhttp = new XMLHttpRequest();
        } else {
            xmlhttp = new ActiveXObject(\"Microsoft.XMLHTTP\");
        }
        xmlhttp.open('GET', \"getproduct.php?q=\" + \$(\"#product\").val(), true);
        xmlhttp.send();
        xmlhttp.onreadystatechange = function(){            
            if(xmlhttp.readyState == 4 && xmlhttp.status == 200){                
                result = JSON.parse(xmlhttp.responseText);                
                \$(\"#product\").autocomplete({
                    source: result,                                                
                    minLength: 1, // doesn't work...
                });                
            }            
        }
    });
});
*/
        

</script>
</body>
</html>";
        
        $__internal_f829cd6004d9dacef255673e6c5cf5a40c24f3c296b93f88ae24509fadebe1c4->leave($__internal_f829cd6004d9dacef255673e6c5cf5a40c24f3c296b93f88ae24509fadebe1c4_prof);

    }

    public function getTemplateName()
    {
        return "ShopBundle:partials:home_footer.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  30 => 4,  26 => 3,  22 => 1,);
    }
}
