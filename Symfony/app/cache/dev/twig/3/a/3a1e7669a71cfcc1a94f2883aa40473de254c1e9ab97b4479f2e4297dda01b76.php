<?php

/* ParadigmBundle:Categories:categories.html.twig */
class __TwigTemplate_3a1e7669a71cfcc1a94f2883aa40473de254c1e9ab97b4479f2e4297dda01b76 extends Twig_Template
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
        $__internal_96835810251467211b7b0c4c8c2bf8ab3e07fe0ab263a3d0358e6f26152927e5 = $this->env->getExtension("native_profiler");
        $__internal_96835810251467211b7b0c4c8c2bf8ab3e07fe0ab263a3d0358e6f26152927e5->enter($__internal_96835810251467211b7b0c4c8c2bf8ab3e07fe0ab263a3d0358e6f26152927e5_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:Categories:categories.html.twig"));

        // line 1
        $this->loadTemplate("ParadigmBundle:partials:cms_admin_header.html.twig", "ParadigmBundle:Categories:categories.html.twig", 1)->display($context);
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
<div class=\"container margin-top-3\" style=\"min-height:687px; letter-spacing: 2px\">
    <h3>Catégories</h3>
    <p><a href=\"";
        // line 11
        echo $this->env->getExtension('routing')->getPath("edit_category");
        echo "\" class=\"btn btn-success\">Create a new Category</a></p>
    <table class=\"table table-responsive\">
        <thead>
            <tr>
                <th>Id</th>
                <th>Nom</th>
                <th>Slug</th>
            </tr>
        </thead>
        <tbody>
    ";
        // line 21
        $context['_parent'] = (array) $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["categories"]) ? $context["categories"] : $this->getContext($context, "categories")));
        foreach ($context['_seq'] as $context["_key"] => $context["category"]) {
            echo " ";
            // line 22
            echo "            <tr>
                <td>";
            // line 23
            echo twig_escape_filter($this->env, $this->getAttribute($context["category"], "id", array()), "html", null, true);
            echo "</td>
                <td>";
            // line 24
            echo twig_escape_filter($this->env, $this->getAttribute($context["category"], "name", array()), "html", null, true);
            echo "</td>
                <td>";
            // line 25
            echo twig_escape_filter($this->env, $this->getAttribute($context["category"], "slug", array()), "html", null, true);
            echo "</td>
                <td>
                    <a href=\"";
            // line 27
            echo twig_escape_filter($this->env, $this->env->getExtension('routing')->getPath("edit_category", array("id" => $this->getAttribute($context["category"], "id", array()), "csrf" => $this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "session", array()), "get", array(0 => "token"), "method"))), "html", null, true);
            echo "\" class=\"btn btn-success\">edit</a>

                    <a href=\"";
            // line 29
            echo twig_escape_filter($this->env, $this->env->getExtension('routing')->getPath("delete_category", array("id" => $this->getAttribute($context["category"], "id", array()), "csrf" => $this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "session", array()), "get", array(0 => "token"), "method"))), "html", null, true);
            echo "\"
                       class=\"btn btn-danger\" onclick=\"return confirm('Are you sure?')\">delete
                    </a>
                </td>
            </tr>    
    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['category'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 35
        echo "        </tbody>
    </table>
</div>
";
        // line 38
        $this->loadTemplate("ParadigmBundle:partials:cms_admin_footer.html.twig", "ParadigmBundle:Categories:categories.html.twig", 38)->display($context);
        
        $__internal_96835810251467211b7b0c4c8c2bf8ab3e07fe0ab263a3d0358e6f26152927e5->leave($__internal_96835810251467211b7b0c4c8c2bf8ab3e07fe0ab263a3d0358e6f26152927e5_prof);

    }

    public function getTemplateName()
    {
        return "ParadigmBundle:Categories:categories.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  101 => 38,  96 => 35,  84 => 29,  79 => 27,  74 => 25,  70 => 24,  66 => 23,  63 => 22,  58 => 21,  45 => 11,  40 => 8,  32 => 6,  30 => 5,  28 => 4,  24 => 2,  22 => 1,);
    }
}
