<?php

/* TwigBundle:Exception:traces.txt.twig */
class __TwigTemplate_b4e6e5ce5d7b4079371c837d8fee392e743b9a7f80b5f7ccf7596b1567efa468 extends Twig_Template
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
        $__internal_35e7234fdb3d20e92bc457c2e8e039cc979bdc50249d16406cca0619647e0d8d = $this->env->getExtension("native_profiler");
        $__internal_35e7234fdb3d20e92bc457c2e8e039cc979bdc50249d16406cca0619647e0d8d->enter($__internal_35e7234fdb3d20e92bc457c2e8e039cc979bdc50249d16406cca0619647e0d8d_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "TwigBundle:Exception:traces.txt.twig"));

        // line 1
        if (twig_length_filter($this->env, $this->getAttribute((isset($context["exception"]) ? $context["exception"] : $this->getContext($context, "exception")), "trace", array()))) {
            // line 2
            $context['_parent'] = (array) $context;
            $context['_seq'] = twig_ensure_traversable($this->getAttribute((isset($context["exception"]) ? $context["exception"] : $this->getContext($context, "exception")), "trace", array()));
            foreach ($context['_seq'] as $context["_key"] => $context["trace"]) {
                // line 3
                $this->loadTemplate("TwigBundle:Exception:trace.txt.twig", "TwigBundle:Exception:traces.txt.twig", 3)->display(array("trace" => $context["trace"]));
                // line 4
                echo "
";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['trace'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
        }
        
        $__internal_35e7234fdb3d20e92bc457c2e8e039cc979bdc50249d16406cca0619647e0d8d->leave($__internal_35e7234fdb3d20e92bc457c2e8e039cc979bdc50249d16406cca0619647e0d8d_prof);

    }

    public function getTemplateName()
    {
        return "TwigBundle:Exception:traces.txt.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  30 => 4,  28 => 3,  24 => 2,  22 => 1,);
    }
}
