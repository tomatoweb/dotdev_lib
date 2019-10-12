<?php

/* ParadigmBundle:Categories:edit_category.html.twig */
class __TwigTemplate_7dd68f3e3ce0b2c34642e11bef08e1a1a5b8d8a82acda61059715c1a3d8af534 extends Twig_Template
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
        $__internal_9434241d54755ec5152b4a307818c7fa5db920d023e6ef991077a5423df83292 = $this->env->getExtension("native_profiler");
        $__internal_9434241d54755ec5152b4a307818c7fa5db920d023e6ef991077a5423df83292->enter($__internal_9434241d54755ec5152b4a307818c7fa5db920d023e6ef991077a5423df83292_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:Categories:edit_category.html.twig"));

        // line 1
        $this->loadTemplate("ParadigmBundle:partials:cms_admin_header.html.twig", "ParadigmBundle:Categories:edit_category.html.twig", 1)->display($context);
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
        // line 7
        echo " 

";
        // line 10
        if ($this->getAttribute((isset($context["category"]) ? $context["category"] : null), "id", array(), "any", true, true)) {
            // line 11
            echo "    ";
            $context["cat"] = array("id" => $this->getAttribute((isset($context["category"]) ? $context["category"] : $this->getContext($context, "category")), "id", array()), "name" => $this->getAttribute((isset($context["category"]) ? $context["category"] : $this->getContext($context, "category")), "name", array()), "slug" => $this->getAttribute((isset($context["category"]) ? $context["category"] : $this->getContext($context, "category")), "slug", array()));
            echo "    
    ";
            // line 12
            echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "request", array()), "request", array()), "replace", array(0 => (isset($context["cat"]) ? $context["cat"] : $this->getContext($context, "cat"))), "method"), "html", null, true);
            echo "    
";
        }
        // line 14
        echo "    
    
<div class=\"container margin-top-3\" style=\"min-height:687px; letter-spacing: 2px\">
    <h2>Edit a category</h2>
    <!-- FORM -->
    <form action=\"#\" method=\"post\" onsubmit=\"return validateFormEditCategory()\">
        <div class=\"form-group\">        
            <input type=\"hidden\" class=\"form-control\" name=\"id\" value=\"";
        // line 21
        echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "request", array()), "request", array()), "get", array(0 => "id"), "method"), "html", null, true);
        echo "\"> 
        </div>
        <div class=\"form-group\">
            <label for=\"name\">name</label><span id=\"myErrorSpan3\"></span>
            <input type=\"text\" class=\"form-control\" id=\"name\" name=\"name\" value=\"";
        // line 25
        echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "request", array()), "request", array()), "get", array(0 => "name"), "method"), "html", null, true);
        echo "\">
        </div>
        <div class=\"form-group\">
            <label for=\"slug\">slug</label><span id=\"myErrorSpan4\"></span>
            <input type=\"text\" class=\"form-control\" id=\"slug\" name=\"slug\" value=\"";
        // line 29
        echo twig_escape_filter($this->env, $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "request", array()), "request", array()), "get", array(0 => "slug"), "method"), "html", null, true);
        echo "\">
        </div>
        <button type=\"submit\" class=\"btn btn-success\">submit</button>
    </form>
</div>
";
        // line 36
        echo "
";
        // line 37
        $this->loadTemplate("ParadigmBundle:partials:cms_admin_footer.html.twig", "ParadigmBundle:Categories:edit_category.html.twig", 37)->display($context);
        
        $__internal_9434241d54755ec5152b4a307818c7fa5db920d023e6ef991077a5423df83292->leave($__internal_9434241d54755ec5152b4a307818c7fa5db920d023e6ef991077a5423df83292_prof);

    }

    public function getTemplateName()
    {
        return "ParadigmBundle:Categories:edit_category.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  90 => 37,  87 => 36,  79 => 29,  72 => 25,  65 => 21,  56 => 14,  51 => 12,  46 => 11,  44 => 10,  40 => 7,  32 => 6,  30 => 5,  28 => 4,  24 => 2,  22 => 1,);
    }
}
