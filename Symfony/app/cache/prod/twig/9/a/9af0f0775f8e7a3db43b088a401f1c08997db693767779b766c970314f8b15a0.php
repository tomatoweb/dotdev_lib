<?php

/* ParadigmBundle:Default:cms.html.twig */
class __TwigTemplate_9af0f0775f8e7a3db43b088a401f1c08997db693767779b766c970314f8b15a0 extends Twig_Template
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
        $__internal_66ae8698d0e00b495d0436c5475e4f4a5d78b74052dbd9e19b07bc7f3a2fbafa = $this->env->getExtension("native_profiler");
        $__internal_66ae8698d0e00b495d0436c5475e4f4a5d78b74052dbd9e19b07bc7f3a2fbafa->enter($__internal_66ae8698d0e00b495d0436c5475e4f4a5d78b74052dbd9e19b07bc7f3a2fbafa_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "ParadigmBundle:Default:cms.html.twig"));

        // line 1
        $this->loadTemplate("ParadigmBundle:partials:cms_header.html.twig", "ParadigmBundle:Default:cms.html.twig", 1)->display($context);
        // line 2
        echo "<div class=\"container-fluid\" style=\"width: 80%; margin: auto;\">
    <div class=\"row\" style=\"\">
    ";
        // line 4
        $context["n"] = array(0 => 2, 1 => 5, 2 => 2, 3 => 3, 4 => 13);
        // line 5
        echo "    ";
        $context["i"] = 0;
        // line 6
        echo "    ";
        $context["key"] = (twig_length_filter($this->env, (isset($context["works"]) ? $context["works"] : $this->getContext($context, "works"))) - 1);
        // line 7
        echo "    ";
        $context['_parent'] = (array) $context;
        $context['_seq'] = twig_ensure_traversable(twig_reverse_filter($this->env, (isset($context["works"]) ? $context["works"] : $this->getContext($context, "works"))));
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
        foreach ($context['_seq'] as $context["_key"] => $context["work"]) {
            // line 8
            echo "        ";
            if (($this->getAttribute((isset($context["n"]) ? $context["n"] : $this->getContext($context, "n")), (isset($context["i"]) ? $context["i"] : $this->getContext($context, "i")), array(), "array") == 13)) {
                // line 9
                echo "            </div> <!-- row  -->
            <div class=\"row\">
            ";
                // line 11
                $context["i"] = 0;
                // line 12
                echo "        ";
            }
            // line 13
            echo "            <div class=\"col-sm-";
            echo twig_escape_filter($this->env, $this->getAttribute((isset($context["n"]) ? $context["n"] : $this->getContext($context, "n")), (isset($context["i"]) ? $context["i"] : $this->getContext($context, "i")), array(), "array"), "html", null, true);
            echo " dot-dot-dot\" style=\"overflow: hidden; padding: 10px\">
                <a href=\"\" data-toggle=\"modal\" data-target=\"#myModal";
            // line 14
            echo twig_escape_filter($this->env, $this->getAttribute($context["loop"], "index", array()), "html", null, true);
            echo "\">
                    <h3 style=\"margin: 0px 0px 10px 0px; color: black;\">";
            // line 15
            echo twig_escape_filter($this->env, $this->getAttribute($context["work"], "name", array()), "html", null, true);
            echo "</h3>
                    ";
            // line 16
            if (((isset($context["i"]) ? $context["i"] : $this->getContext($context, "i")) == 1)) {
                // line 17
                echo "                        ";
                if (($this->getAttribute((isset($context["images"]) ? $context["images"] : $this->getContext($context, "images")), (isset($context["key"]) ? $context["key"] : $this->getContext($context, "key")), array(), "array") != null)) {
                    // line 18
                    echo "                            <p><img src=\"";
                    echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl(("bundles/paradigm/img/works/min_" . $this->getAttribute($this->getAttribute((isset($context["images"]) ? $context["images"] : $this->getContext($context, "images")), (isset($context["key"]) ? $context["key"] : $this->getContext($context, "key")), array(), "array"), "name", array()))), "html", null, true);
                    echo "\" style=\"float: left;max-width: 100%; height: auto; margin:2%\"></p>
                        ";
                }
                // line 20
                echo "                    ";
            }
            // line 21
            echo "                    ";
            echo $this->getAttribute($context["work"], "content", array());
            echo "
                </a>
            </div> <!-- class=\"col-sm- -->
            <!-- MODAL VIEW -->
            <div class=\"modal fade\" id=\"myModal";
            // line 25
            echo twig_escape_filter($this->env, $this->getAttribute($context["loop"], "index", array()), "html", null, true);
            echo "\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"myModalLabel\" aria-hidden=\"true\">
                <div class=\"modal-lg modal-dialog\">
                    <div class=\"modal-content\">
                        <div class=\"modal-header\">
                            <button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-hidden=\"true\">&times;</button>
                            <h1 class=\"modal-title\" style=\"display:inline; margin-right:126px\">";
            // line 30
            echo twig_escape_filter($this->env, $this->getAttribute($context["work"], "name", array()), "html", null, true);
            echo "</h1>
                            <p class=\"infobaritem\">";
            // line 31
            echo twig_escape_filter($this->env, twig_date_format_filter($this->env, "now", "l, F d. Y H:i"), "html", null, true);
            echo "</p>
                        </div>
                        <div class=\"modal-body\">
                            <img src=\"";
            // line 34
            if (($this->getAttribute((isset($context["images"]) ? $context["images"] : $this->getContext($context, "images")), (isset($context["key"]) ? $context["key"] : $this->getContext($context, "key")), array(), "array") != null)) {
                // line 35
                echo "                                        ";
                echo twig_escape_filter($this->env, $this->env->getExtension('asset')->getAssetUrl(("bundles/paradigm/img/works/" . $this->getAttribute($this->getAttribute((isset($context["images"]) ? $context["images"] : $this->getContext($context, "images")), (isset($context["key"]) ? $context["key"] : $this->getContext($context, "key")), array(), "array"), "name", array()))), "html", null, true);
                echo "
                                      ";
            }
            // line 36
            echo "\"
                                 style=\"float:left; margin:2%; max-width: 100%; height: auto;\" alt=\"\" width=\"475\" height=\"317\"
                            >
                            <p style=\"color:black\">";
            // line 39
            echo $this->getAttribute($context["work"], "content", array());
            echo "</p>
                        </div>
                        <div class=\"modal-footer\">
                            <button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\" style=\"float:left\">Close</button>
                        </div>
                    </div>
                </div>
            </div><!-- modal -->
            ";
            // line 47
            $context["i"] = ((isset($context["i"]) ? $context["i"] : $this->getContext($context, "i")) + 1);
            // line 48
            echo "            ";
            $context["key"] = ((isset($context["key"]) ? $context["key"] : $this->getContext($context, "key")) - 1);
            // line 49
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
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['work'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 50
        echo "    </div>
";
        // line 51
        $this->loadTemplate("ParadigmBundle:partials:cms_footer.html.twig", "ParadigmBundle:Default:cms.html.twig", 51)->display($context);
        
        $__internal_66ae8698d0e00b495d0436c5475e4f4a5d78b74052dbd9e19b07bc7f3a2fbafa->leave($__internal_66ae8698d0e00b495d0436c5475e4f4a5d78b74052dbd9e19b07bc7f3a2fbafa_prof);

    }

    public function getTemplateName()
    {
        return "ParadigmBundle:Default:cms.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  165 => 51,  162 => 50,  148 => 49,  145 => 48,  143 => 47,  132 => 39,  127 => 36,  121 => 35,  119 => 34,  113 => 31,  109 => 30,  101 => 25,  93 => 21,  90 => 20,  84 => 18,  81 => 17,  79 => 16,  75 => 15,  71 => 14,  66 => 13,  63 => 12,  61 => 11,  57 => 9,  54 => 8,  36 => 7,  33 => 6,  30 => 5,  28 => 4,  24 => 2,  22 => 1,);
    }
}
