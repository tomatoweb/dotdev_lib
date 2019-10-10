<?php

/* TwigBundle:Exception:exception_full.html.twig */
class __TwigTemplate_dfb8be4e7633bb30bb98c8f5ba2aec6a66a11f0c564b21126d971b705a954c06 extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        // line 1
        $this->parent = $this->loadTemplate("TwigBundle::layout.html.twig", "TwigBundle:Exception:exception_full.html.twig", 1);
        $this->blocks = array(
            'head' => array($this, 'block_head'),
            'title' => array($this, 'block_title'),
            'body' => array($this, 'block_body'),
        );
    }

    protected function doGetParent(array $context)
    {
        return "TwigBundle::layout.html.twig";
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        $__internal_668673b775c69e6c4ab1640769271ba2d5af0f25b3bf8fcba70328b46b045484 = $this->env->getExtension("native_profiler");
        $__internal_668673b775c69e6c4ab1640769271ba2d5af0f25b3bf8fcba70328b46b045484->enter($__internal_668673b775c69e6c4ab1640769271ba2d5af0f25b3bf8fcba70328b46b045484_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "TwigBundle:Exception:exception_full.html.twig"));

        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_668673b775c69e6c4ab1640769271ba2d5af0f25b3bf8fcba70328b46b045484->leave($__internal_668673b775c69e6c4ab1640769271ba2d5af0f25b3bf8fcba70328b46b045484_prof);

    }

    // line 3
    public function block_head($context, array $blocks = array())
    {
        $__internal_fda41f35482ebe5f65613105f1ff3f1ebf7a5c6df94a82f388fd97e7f280df7b = $this->env->getExtension("native_profiler");
        $__internal_fda41f35482ebe5f65613105f1ff3f1ebf7a5c6df94a82f388fd97e7f280df7b->enter($__internal_fda41f35482ebe5f65613105f1ff3f1ebf7a5c6df94a82f388fd97e7f280df7b_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "head"));

        // line 4
        echo "    <link href=\"";
        echo twig_escape_filter($this->env, $this->env->getExtension('request')->generateAbsoluteUrl($this->env->getExtension('asset')->getAssetUrl("bundles/framework/css/exception.css")), "html", null, true);
        echo "\" rel=\"stylesheet\" type=\"text/css\" media=\"all\" />
";
        
        $__internal_fda41f35482ebe5f65613105f1ff3f1ebf7a5c6df94a82f388fd97e7f280df7b->leave($__internal_fda41f35482ebe5f65613105f1ff3f1ebf7a5c6df94a82f388fd97e7f280df7b_prof);

    }

    // line 7
    public function block_title($context, array $blocks = array())
    {
        $__internal_4010ce0f87cbc218565ba0ecfc08c731ec1c01cad2b2f81e58181e064caa1ed4 = $this->env->getExtension("native_profiler");
        $__internal_4010ce0f87cbc218565ba0ecfc08c731ec1c01cad2b2f81e58181e064caa1ed4->enter($__internal_4010ce0f87cbc218565ba0ecfc08c731ec1c01cad2b2f81e58181e064caa1ed4_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "title"));

        // line 8
        echo "    ";
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["exception"]) ? $context["exception"] : $this->getContext($context, "exception")), "message", array()), "html", null, true);
        echo " (";
        echo twig_escape_filter($this->env, (isset($context["status_code"]) ? $context["status_code"] : $this->getContext($context, "status_code")), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, (isset($context["status_text"]) ? $context["status_text"] : $this->getContext($context, "status_text")), "html", null, true);
        echo ")
";
        
        $__internal_4010ce0f87cbc218565ba0ecfc08c731ec1c01cad2b2f81e58181e064caa1ed4->leave($__internal_4010ce0f87cbc218565ba0ecfc08c731ec1c01cad2b2f81e58181e064caa1ed4_prof);

    }

    // line 11
    public function block_body($context, array $blocks = array())
    {
        $__internal_483ddecb76896d79c6206cdb8530a81aa816e52dd8663c869be5206129f928ff = $this->env->getExtension("native_profiler");
        $__internal_483ddecb76896d79c6206cdb8530a81aa816e52dd8663c869be5206129f928ff->enter($__internal_483ddecb76896d79c6206cdb8530a81aa816e52dd8663c869be5206129f928ff_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "body"));

        // line 12
        echo "    ";
        $this->loadTemplate("TwigBundle:Exception:exception.html.twig", "TwigBundle:Exception:exception_full.html.twig", 12)->display($context);
        
        $__internal_483ddecb76896d79c6206cdb8530a81aa816e52dd8663c869be5206129f928ff->leave($__internal_483ddecb76896d79c6206cdb8530a81aa816e52dd8663c869be5206129f928ff_prof);

    }

    public function getTemplateName()
    {
        return "TwigBundle:Exception:exception_full.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  78 => 12,  72 => 11,  58 => 8,  52 => 7,  42 => 4,  36 => 3,  11 => 1,);
    }
}
