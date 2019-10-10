<?php

/* ParadigmBundle:Works:admin_login.html.twig */
class __TwigTemplate_e2f3bec684365afd6f0c5734a2d674ab99e6433ff0ac5d584c4de8567f7b11dc extends Twig_Template
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
        $__internal_61586f33b968887282b075c41f50a24b85dc14533bcf25cfac9e9a13e71e17e4 = $this->env->getExtension("native_profiler");
        $__internal_61586f33b968887282b075c41f50a24b85dc14533bcf25cfac9e9a13e71e17e4->enter($__internal_61586f33b968887282b075c41f50a24b85dc14533bcf25cfac9e9a13e71e17e4_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:Works:admin_login.html.twig"));

        // line 1
        $this->loadTemplate("ParadigmBundle:partials:home_header.html.twig", "ParadigmBundle:Works:admin_login.html.twig", 1)->display($context);
        // line 2
        echo "<!-- FLASH -->
    ";
        // line 3
        $context["flash"] = $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "session", array()), "flashbag", array()), "get", array(0 => "flash"), "method");
        // line 4
        echo "    ";
        if ($this->getAttribute((isset($context["flash"]) ? $context["flash"] : null), "type", array(), "any", true, true)) {
            // line 5
            echo "        <div class=\"alert alert-";
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["flash"]) ? $context["flash"] : $this->getContext($context, "flash")), "type", array()), "html", null, true);
            echo "\">";
            echo $this->getAttribute((isset($context["flash"]) ? $context["flash"] : $this->getContext($context, "flash")), "message", array());
            echo "</div>    
    ";
        }
        // line 7
        echo "       
<div class=\"container margin-top-3\" style=\"min-height:597px;\">
    <form action=\"";
        // line 9
        echo $this->env->getExtension('routing')->getPath("admin_login");
        echo "\" method=\"post\">
        <div class=\"form-group\">
            <label for=\"username\" style=\"color:white\">Username</label>
            <input type=\"text\" class=\"form-control\" name=\"username\" value=\"user\" autofocus >
        </div>
        <div class=\"form-group\">
            <label for=\"password\" style=\"color:white\">Password</label>
            <input type=\"password\" class=\"form-control\" name=\"password\" style=\"font-family:none;\">
        </div>             
        <button type=\"submit\" class=\"btn btn-default\">Connect me</button>   
    </form>
</div>    
";
        // line 21
        $this->loadTemplate("ParadigmBundle:partials:home_footer.html.twig", "ParadigmBundle:Works:admin_login.html.twig", 21)->display($context);
        // line 22
        echo "
";
        
        $__internal_61586f33b968887282b075c41f50a24b85dc14533bcf25cfac9e9a13e71e17e4->leave($__internal_61586f33b968887282b075c41f50a24b85dc14533bcf25cfac9e9a13e71e17e4_prof);

    }

    public function getTemplateName()
    {
        return "ParadigmBundle:Works:admin_login.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  61 => 22,  59 => 21,  44 => 9,  40 => 7,  32 => 5,  29 => 4,  27 => 3,  24 => 2,  22 => 1,);
    }
}
