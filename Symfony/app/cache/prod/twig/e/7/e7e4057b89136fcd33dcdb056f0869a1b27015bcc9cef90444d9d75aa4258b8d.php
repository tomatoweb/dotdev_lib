<?php

/* TwigBundle:Exception:exception_full.html.twig */
class __TwigTemplate_e7e4057b89136fcd33dcdb056f0869a1b27015bcc9cef90444d9d75aa4258b8d extends Twig_Template
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
        $__internal_c373f1963d6e3a828a29d82b131b9510341c88e9132bae66aa8f0913cfaf79ab = $this->env->getExtension("native_profiler");
        $__internal_c373f1963d6e3a828a29d82b131b9510341c88e9132bae66aa8f0913cfaf79ab->enter($__internal_c373f1963d6e3a828a29d82b131b9510341c88e9132bae66aa8f0913cfaf79ab_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "TwigBundle:Exception:exception_full.html.twig"));

        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_c373f1963d6e3a828a29d82b131b9510341c88e9132bae66aa8f0913cfaf79ab->leave($__internal_c373f1963d6e3a828a29d82b131b9510341c88e9132bae66aa8f0913cfaf79ab_prof);

    }

    // line 3
    public function block_head($context, array $blocks = array())
    {
        $__internal_0522e762113d96c4c7e241a8691cffb99dc6af7e95601dd96395a0e9351aba99 = $this->env->getExtension("native_profiler");
        $__internal_0522e762113d96c4c7e241a8691cffb99dc6af7e95601dd96395a0e9351aba99->enter($__internal_0522e762113d96c4c7e241a8691cffb99dc6af7e95601dd96395a0e9351aba99_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "head"));

        // line 4
        echo "    <link href=\"";
        echo twig_escape_filter($this->env, $this->env->getExtension('request')->generateAbsoluteUrl($this->env->getExtension('asset')->getAssetUrl("bundles/framework/css/exception.css")), "html", null, true);
        echo "\" rel=\"stylesheet\" type=\"text/css\" media=\"all\" />
";
        
        $__internal_0522e762113d96c4c7e241a8691cffb99dc6af7e95601dd96395a0e9351aba99->leave($__internal_0522e762113d96c4c7e241a8691cffb99dc6af7e95601dd96395a0e9351aba99_prof);

    }

    // line 7
    public function block_title($context, array $blocks = array())
    {
        $__internal_ce1d2a01b10feb75cd636edf11aa906f1088eafad483ae8cfbc83cfa7b7a6b6b = $this->env->getExtension("native_profiler");
        $__internal_ce1d2a01b10feb75cd636edf11aa906f1088eafad483ae8cfbc83cfa7b7a6b6b->enter($__internal_ce1d2a01b10feb75cd636edf11aa906f1088eafad483ae8cfbc83cfa7b7a6b6b_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "title"));

        // line 8
        echo "    ";
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["exception"]) ? $context["exception"] : $this->getContext($context, "exception")), "message", array()), "html", null, true);
        echo " (";
        echo twig_escape_filter($this->env, (isset($context["status_code"]) ? $context["status_code"] : $this->getContext($context, "status_code")), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, (isset($context["status_text"]) ? $context["status_text"] : $this->getContext($context, "status_text")), "html", null, true);
        echo ")
";
        
        $__internal_ce1d2a01b10feb75cd636edf11aa906f1088eafad483ae8cfbc83cfa7b7a6b6b->leave($__internal_ce1d2a01b10feb75cd636edf11aa906f1088eafad483ae8cfbc83cfa7b7a6b6b_prof);

    }

    // line 11
    public function block_body($context, array $blocks = array())
    {
        $__internal_ef5827c198e9597f71be4fded6ac37410469d16fa5a30c02d449300ca0016d01 = $this->env->getExtension("native_profiler");
        $__internal_ef5827c198e9597f71be4fded6ac37410469d16fa5a30c02d449300ca0016d01->enter($__internal_ef5827c198e9597f71be4fded6ac37410469d16fa5a30c02d449300ca0016d01_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "body"));

        // line 12
        echo "    ";
        $this->loadTemplate("TwigBundle:Exception:exception.html.twig", "TwigBundle:Exception:exception_full.html.twig", 12)->display($context);
        
        $__internal_ef5827c198e9597f71be4fded6ac37410469d16fa5a30c02d449300ca0016d01->leave($__internal_ef5827c198e9597f71be4fded6ac37410469d16fa5a30c02d449300ca0016d01_prof);

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
