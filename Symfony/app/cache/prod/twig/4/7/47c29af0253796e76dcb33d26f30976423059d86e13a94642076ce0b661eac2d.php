<?php

/* ParadigmBundle:Works:works.html.twig */
class __TwigTemplate_47c29af0253796e76dcb33d26f30976423059d86e13a94642076ce0b661eac2d extends Twig_Template
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
        $__internal_1c5429ac1f2c83ec773d1f65ba99f160a240046dc3071bb9c5e4c4bfcd3a228b = $this->env->getExtension("native_profiler");
        $__internal_1c5429ac1f2c83ec773d1f65ba99f160a240046dc3071bb9c5e4c4bfcd3a228b->enter($__internal_1c5429ac1f2c83ec773d1f65ba99f160a240046dc3071bb9c5e4c4bfcd3a228b_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:Works:works.html.twig"));

        // line 1
        $this->loadTemplate("ParadigmBundle:partials:cms_admin_header.html.twig", "ParadigmBundle:Works:works.html.twig", 1)->display($context);
        // line 2
        echo "
<div class=\"container margin-top-3\" style=\"min-height:597px; letter-spacing: 2px\">
    <h3>Works</h3>

    <!-- FLASH -->
    ";
        // line 7
        $context["flash"] = $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "session", array()), "flashbag", array()), "get", array(0 => "flash"), "method");
        // line 8
        echo "    ";
        if ($this->getAttribute((isset($context["flash"]) ? $context["flash"] : null), "type", array(), "any", true, true)) {
            // line 9
            echo "        <div class=\"alert alert-";
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["flash"]) ? $context["flash"] : $this->getContext($context, "flash")), "type", array()), "html", null, true);
            echo "\">";
            echo $this->getAttribute((isset($context["flash"]) ? $context["flash"] : $this->getContext($context, "flash")), "message", array());
            echo "</div>    
    ";
        }
        // line 11
        echo "
    <p><a href=\"";
        // line 12
        echo $this->env->getExtension('routing')->getPath("edit_work");
        echo "\" class=\"btn btn-success\">Create a new Work</a></p>
    <table class=\"table table-striped\">
        <thead>
            <tr>
                <th>Id</th><th>Name</th><th>slug</th>
            </tr>
        </thead>
        <tbody>
        ";
        // line 20
        $context['_parent'] = (array) $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["works"]) ? $context["works"] : $this->getContext($context, "works")));
        foreach ($context['_seq'] as $context["_key"] => $context["work"]) {
            // line 21
            echo "            <tr>
                <td>";
            // line 22
            echo twig_escape_filter($this->env, $this->getAttribute($context["work"], "id", array()), "html", null, true);
            echo "</td><td>";
            echo twig_escape_filter($this->env, $this->getAttribute($context["work"], "name", array()), "html", null, true);
            echo "</td><td>";
            echo twig_escape_filter($this->env, $this->getAttribute($context["work"], "slug", array()), "html", null, true);
            echo "</td>
                <td>
                    <a href=\"";
            // line 24
            echo twig_escape_filter($this->env, $this->env->getExtension('routing')->getPath("edit_work", array("id" => $this->getAttribute($context["work"], "id", array()), "csrf" => $this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "session", array()), "get", array(0 => "token"), "method"))), "html", null, true);
            echo "\" class=\"btn btn-success\">edit</a>
                    <a href=\"";
            // line 25
            echo twig_escape_filter($this->env, $this->env->getExtension('routing')->getPath("delete_work", array("id" => $this->getAttribute($context["work"], "id", array()), "csrf" => $this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "session", array()), "get", array(0 => "token"), "method"))), "html", null, true);
            echo "\" 
                       class=\"btn btn-danger\" onclick=\"return confirm('are you sure?')\">delete
                    </a>
                </td>
            </tr>        
        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['work'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 31
        echo "        </tbody>
    </table>
</div>
";
        // line 34
        $this->loadTemplate("ParadigmBundle:partials:cms_admin_footer.html.twig", "ParadigmBundle:Works:works.html.twig", 34)->display($context);
        
        $__internal_1c5429ac1f2c83ec773d1f65ba99f160a240046dc3071bb9c5e4c4bfcd3a228b->leave($__internal_1c5429ac1f2c83ec773d1f65ba99f160a240046dc3071bb9c5e4c4bfcd3a228b_prof);

    }

    public function getTemplateName()
    {
        return "ParadigmBundle:Works:works.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  95 => 34,  90 => 31,  78 => 25,  74 => 24,  65 => 22,  62 => 21,  58 => 20,  47 => 12,  44 => 11,  36 => 9,  33 => 8,  31 => 7,  24 => 2,  22 => 1,);
    }
}
