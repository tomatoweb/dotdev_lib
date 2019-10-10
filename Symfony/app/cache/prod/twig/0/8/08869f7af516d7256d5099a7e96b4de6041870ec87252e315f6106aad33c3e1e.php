<?php

/* ShopBundle:Default:index.html.twig */
class __TwigTemplate_08869f7af516d7256d5099a7e96b4de6041870ec87252e315f6106aad33c3e1e extends Twig_Template
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
        $__internal_c2cde2c40c5f2d30a35399a8543b6d11919d430978af592a140ce06ff9593219 = $this->env->getExtension("native_profiler");
        $__internal_c2cde2c40c5f2d30a35399a8543b6d11919d430978af592a140ce06ff9593219->enter($__internal_c2cde2c40c5f2d30a35399a8543b6d11919d430978af592a140ce06ff9593219_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ShopBundle:Default:index.html.twig"));

        // line 1
        $this->loadTemplate("ShopBundle:partials:home_header.html.twig", "ShopBundle:Default:index.html.twig", 1)->display($context);
        // line 2
        echo "<div class=\"container\" >
    ";
        // line 3
        if (($this->getAttribute($this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "request", array()), "query", array()), "get", array(0 => "cart"), "method") == "view")) {
            // line 4
            echo "    <h2>My Order</h2>
    ";
        }
        // line 5
        echo "    
    <div class=\"row\">        
    ";
        // line 7
        $context['_parent'] = (array) $context;
        $context['_seq'] = twig_ensure_traversable((isset($context["products"]) ? $context["products"] : $this->getContext($context, "products")));
        $context['loop'] = array(
          'parent' => $context['_parent'],
          'index0' => 0,
          'index'  => 1,
          'first'  => true,
        );
        if (is_array($context['_seq']) || (is_object($context['_seq']) && $context['_seq'] instanceof Countable)) {
            $length = count($context['_seq']);
            $context['loop']['revindex0'] = $length - 1;
            $context['loop']['revindex'] = $length;
            $context['loop']['length'] = $length;
            $context['loop']['last'] = 1 === $length;
        }
        foreach ($context['_seq'] as $context["key"] => $context["product"]) {
            // line 8
            echo "        <div class=\"col-sm-3\" >
            <div class=\"shop-article\">
                <a href=\"\"  data-toggle=\"modal\" data-target=\"#myModal";
            // line 10
            echo twig_escape_filter($this->env, $this->getAttribute($context["loop"], "index", array()), "html", null, true);
            echo "\">
                    <img src=\"";
            // line 11
            echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl((("bundles/shop/images/" . "small_") . $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["images"]) ? $context["images"] : $this->getContext($context, "images")), $this->getAttribute($context["loop"], "index0", array()), array(), "array"), 0, array(), "array"), "name", array()))), "html", null, true);
            echo "\" class=\"\" alt=\"\" width=\"\" style=\"max-width: 100%;\" >
                </a>
                <p id=\"product_name\">";
            // line 13
            echo twig_escape_filter($this->env, $this->getAttribute($context["product"], "name", array()), "html", null, true);
            echo "</p>
                <p id=\"product_price\"><b>\$";
            // line 14
            echo twig_escape_filter($this->env, twig_random($this->env, 200), "html", null, true);
            echo "</b></p>
                ";
            // line 15
            if (($this->getAttribute($this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "request", array()), "query", array()), "get", array(0 => "cart"), "method") == "view")) {
                echo " ";
                echo "                
                <a href=\"";
                // line 16
                echo twig_escape_filter($this->env, $this->env->getExtension('routing')->getPath("delete_product", array("product_id" => $context["key"])), "html", null, true);
                echo "\">
                    <button type=\"button\" class=\"close\" title=\"remove from my order\" style=\"float:none; margin-right: -88%\">&times;</button>                    
                </a>
                ";
            }
            // line 20
            echo "            </div>
            <div class=\"modal fade\" id=\"myModal";
            // line 21
            echo twig_escape_filter($this->env, $this->getAttribute($context["loop"], "index", array()), "html", null, true);
            echo "\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"myModalLabel\" aria-hidden=\"true\">
                <div class=\"modal-dialog\">
                    <div class=\"modal-content\">
                        <div class=\"modal-header\">
                            <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-hidden=\"true\">&times;</button>
                            <h4 class=\"modal-title\" id=\"myModalLabel\">";
            // line 26
            echo twig_escape_filter($this->env, $this->getAttribute($context["product"], "name", array()), "html", null, true);
            echo "</h4>
                        </div>
                        <div class=\"modal-body\">
                            <img src=\"";
            // line 29
            echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl(("bundles/shop/images/" . $this->getAttribute($this->getAttribute($this->getAttribute((isset($context["images"]) ? $context["images"] : $this->getContext($context, "images")), $this->getAttribute($context["loop"], "index0", array()), array(), "array"), 0, array(), "array"), "name", array()))), "html", null, true);
            echo "\" alt=\"\" height=\"320\">
                            <p>";
            // line 30
            echo twig_escape_filter($this->env, $this->getAttribute($context["product"], "description", array()), "html", null, true);
            echo "</p>
                        </div>
                        <div class=\"modal-footer\">
                            <button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\" style=\"float:left\">Close</button>
                            <a href=\"";
            // line 34
            echo twig_escape_filter($this->env, $this->env->getExtension('routing')->getPath("order", array("product_id" => $this->getAttribute($context["product"], "id", array()))), "html", null, true);
            echo "\"><button type=\"button\" class=\"add-to-cart-btn\"></button></a>

                        </div>
                    </div>
                </div>
            </div>
        </div>        
    ";
            // line 41
            if (((($this->getAttribute($context["loop"], "index", array()) % 4) == 0) || $this->getAttribute($context["loop"], "last", array()))) {
                echo " ";
                // line 42
                echo "    </div> 
        ";
                // line 43
                if (($this->getAttribute($context["loop"], "last", array()) == false)) {
                    echo " ";
                    // line 44
                    echo "    <div class=\"row\">
        ";
                }
                // line 45
                echo "    
    ";
            }
            // line 47
            echo "    ";
            ++$context['loop']['index0'];
            ++$context['loop']['index'];
            $context['loop']['first'] = false;
            if (isset($context['loop']['length'])) {
                --$context['loop']['revindex0'];
                --$context['loop']['revindex'];
                $context['loop']['last'] = 0 === $context['loop']['revindex0'];
            }
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['key'], $context['product'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        echo "    
</div>    
";
        // line 49
        $this->loadTemplate("ShopBundle:partials:home_footer.html.twig", "ShopBundle:Default:index.html.twig", 49)->display($context);
        
        $__internal_c2cde2c40c5f2d30a35399a8543b6d11919d430978af592a140ce06ff9593219->leave($__internal_c2cde2c40c5f2d30a35399a8543b6d11919d430978af592a140ce06ff9593219_prof);

    }

    public function getTemplateName()
    {
        return "ShopBundle:Default:index.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  159 => 49,  142 => 47,  138 => 45,  134 => 44,  131 => 43,  128 => 42,  125 => 41,  115 => 34,  108 => 30,  104 => 29,  98 => 26,  90 => 21,  87 => 20,  80 => 16,  75 => 15,  71 => 14,  67 => 13,  62 => 11,  58 => 10,  54 => 8,  37 => 7,  33 => 5,  29 => 4,  27 => 3,  24 => 2,  22 => 1,);
    }
}
