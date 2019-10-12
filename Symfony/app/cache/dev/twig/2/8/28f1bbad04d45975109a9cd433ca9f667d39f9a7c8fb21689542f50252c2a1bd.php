<?php

/* ParadigmBundle:Works:edit_work.html.twig */
class __TwigTemplate_28f1bbad04d45975109a9cd433ca9f667d39f9a7c8fb21689542f50252c2a1bd extends Twig_Template
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
        $__internal_de1eb428b70a1535238be9bba947f356673f5ae79001e41bb934b17ccbb372fb = $this->env->getExtension("native_profiler");
        $__internal_de1eb428b70a1535238be9bba947f356673f5ae79001e41bb934b17ccbb372fb->enter($__internal_de1eb428b70a1535238be9bba947f356673f5ae79001e41bb934b17ccbb372fb_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:Works:edit_work.html.twig"));

        // line 1
        $this->loadTemplate("ParadigmBundle:partials:cms_admin_header.html.twig", "ParadigmBundle:Works:edit_work.html.twig", 1)->display($context);
        // line 2
        echo "
<!-- FLASH -->
";
        // line 4
        $context["flash"] = $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "session", array()), "flashbag", array()), "get", array(0 => "flash"), "method");
        // line 5
        if ($this->getAttribute((isset($context["flash"]) ? $context["flash"] : null), "type", array(), "any", true, true)) {
            // line 6
            echo "    <div class=\"alert alert-";
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["flash"]) ? $context["flash"] : $this->getContext($context, "flash")), "type", array()), "html", null, true);
            echo "\">";
            echo $this->getAttribute((isset($context["flash"]) ? $context["flash"] : $this->getContext($context, "flash")), "message", array());
            echo "</div>    
";
        }
        // line 8
        echo "    
<div class=\"container margin-top-3\" style=\"min-height:687px; letter-spacing: 2px;\">
    <h2>Edit a work</h2>

    <form action=\"";
        // line 12
        echo $this->env->getExtension('routing')->getPath("edit_work");
        echo "\" method=\"post\" enctype=\"multipart/form-data\" onsubmit=\"return validateFormEditWork()\" >
        <div class='row'>
            <div class=\"col-sm-8\">    
                <div class=\"form-group\">
                    <input type=\"hidden\" name=\"id\" class=\"form-control\" value=\"";
        // line 16
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["work"]) ? $context["work"] : $this->getContext($context, "work")), "id", array()), "html", null, true);
        echo "\">        
                </div>
                <div class=\"form-group\">
                    <label for=\"name\">Name</label><span id=\"myErrorSpan1\"></span>
                    <input type=\"text\" id=\"name\" name=\"name\" class=\"form-control\" value=\"";
        // line 20
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["work"]) ? $context["work"] : $this->getContext($context, "work")), "name", array()), "html", null, true);
        echo "\">
                </div>
                <div class=\"form-group\">
                    <label for=\"slug\">slug</label><span id=\"myErrorSpan2\"></span>
                    <input type=\"text\" id=\"slug\" name=\"slug\" class=\"form-control\" value=\"";
        // line 24
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["work"]) ? $context["work"] : $this->getContext($context, "work")), "slug", array()), "html", null, true);
        echo "\">
                </div>
                <div>
                    <label for=\"content\">Content</label>
                    <textarea name=\"content\" class=\"form-control\">";
        // line 28
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["work"]) ? $context["work"] : $this->getContext($context, "work")), "content", array()), "html", null, true);
        echo "</textarea>
                </div>
                <div><br>
                    <label for=\"category_id\">Category</label>
                    <select class=\"form-control\" name=\"category_id\">
                    ";
        // line 33
        $context['_parent'] = (array) $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["categories"]) ? $context["categories"] : $this->getContext($context, "categories")));
        foreach ($context['_seq'] as $context["_key"] => $context["category"]) {
            // line 34
            echo "                        ";
            $context["selected"] = "";
            // line 35
            echo "                        ";
            if (($this->getAttribute($context["category"], "id", array()) == $this->getAttribute((isset($context["work"]) ? $context["work"] : $this->getContext($context, "work")), "categoryId", array()))) {
                // line 36
                echo "                            ";
                $context["selected"] = "selected = \"selected\"";
                // line 37
                echo "                        ";
            }
            // line 38
            echo "                        <option value=\"";
            echo twig_escape_filter($this->env, $this->getAttribute($context["category"], "id", array()), "html", null, true);
            echo "\" ";
            echo twig_escape_filter($this->env, (isset($context["selected"]) ? $context["selected"] : $this->getContext($context, "selected")), "html", null, true);
            echo ">";
            echo twig_escape_filter($this->env, $this->getAttribute($context["category"], "name", array()), "html", null, true);
            echo "</option>
                    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['category'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 40
        echo "                    </select>
                </div> 
            </div>
            <div class=\"col-sm-4\">                
            ";
        // line 44
        if (array_key_exists("images", $context)) {
            // line 45
            echo "                ";
            $context['_parent'] = (array) $context;
            $context['_seq'] = twig_ensure_traversable((isset($context["images"]) ? $context["images"] : $this->getContext($context, "images")));
            foreach ($context['_seq'] as $context["_key"] => $context["image"]) {
                echo "<!-- php app/console assets:install   (public folder) -->
                    <img src=\"";
                // line 46
                echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl(("bundles/paradigm/img/works/min_" . $this->getAttribute($context["image"], "name", array()))), "html", null, true);
                echo "\" ><br>
                    <a href=\"";
                // line 47
                echo twig_escape_filter($this->env, $this->env->getExtension('routing')->getPath("delete_image", array("id" => $this->getAttribute($context["image"], "id", array()), "work_id" => $this->getAttribute((isset($context["work"]) ? $context["work"] : $this->getContext($context, "work")), "id", array()))), "html", null, true);
                echo "\" >delete</a><br>
                    <a href=\"";
                // line 48
                echo twig_escape_filter($this->env, $this->env->getExtension('routing')->getPath("highlight_image", array("id" => $this->getAttribute($context["image"], "id", array()), "work_id" => $this->getAttribute((isset($context["work"]) ? $context["work"] : $this->getContext($context, "work")), "id", array()))), "html", null, true);
                echo "\" >highlight</a><br><br>
                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['image'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 50
            echo "            ";
        }
        // line 51
        echo "                <div class=\"form-group\">
                    <input type=\"file\" name=\"images[]\">
                    <input type=\"file\" name=\"images[]\" class=\"hidden\" id=\"hidden_btn\">
                </div>
                <a href=\"#\" class=\"btn btn-success\" id=\"add_btn\" >add an image (475x317)</a>
                <div><br><button type=\"submit\" class=\"btn btn-success\" >submit</button></div>
            </div>            
        </div>
    </form>
</div>
<script src=\"";
        // line 61
        echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl("js/tinymce/tinymce.min.js"), "html", null, true);
        echo "\"></script>
<script> tinymce.init({selector:'textarea'}); </script>
<script src=\"http://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js\"></script>
<script>
// add upload_a_file button
(function(\$){
    \$('#add_btn').click(function(){
        var \$clone = \$('#hidden_btn').clone().attr('id','').removeClass('hidden');
        \$('#hidden_btn').before(\$clone);
    })
})(jQuery);
/*
OR
    \$(document).ready(function(){
    });
OR
    \$(function(){
    });
 */
</script>
";
        // line 81
        $this->loadTemplate("ParadigmBundle:partials:cms_admin_footer.html.twig", "ParadigmBundle:Works:edit_work.html.twig", 81)->display($context);
        
        $__internal_de1eb428b70a1535238be9bba947f356673f5ae79001e41bb934b17ccbb372fb->leave($__internal_de1eb428b70a1535238be9bba947f356673f5ae79001e41bb934b17ccbb372fb_prof);

    }

    public function getTemplateName()
    {
        return "ParadigmBundle:Works:edit_work.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  180 => 81,  157 => 61,  145 => 51,  142 => 50,  134 => 48,  130 => 47,  126 => 46,  119 => 45,  117 => 44,  111 => 40,  98 => 38,  95 => 37,  92 => 36,  89 => 35,  86 => 34,  82 => 33,  74 => 28,  67 => 24,  60 => 20,  53 => 16,  46 => 12,  40 => 8,  32 => 6,  30 => 5,  28 => 4,  24 => 2,  22 => 1,);
    }
}
