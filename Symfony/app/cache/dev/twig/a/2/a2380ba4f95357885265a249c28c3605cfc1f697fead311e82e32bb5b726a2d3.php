<?php

/* TwigBundle:Exception:exception_full.html.twig */
class __TwigTemplate_a2380ba4f95357885265a249c28c3605cfc1f697fead311e82e32bb5b726a2d3 extends Twig_Template
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
        $__internal_21c6c3b09ad82e15ee5ded23952054859beb394acb1e734c5364eaff899f98ec = $this->env->getExtension("native_profiler");
        $__internal_21c6c3b09ad82e15ee5ded23952054859beb394acb1e734c5364eaff899f98ec->enter($__internal_21c6c3b09ad82e15ee5ded23952054859beb394acb1e734c5364eaff899f98ec_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "TwigBundle:Exception:exception_full.html.twig"));

        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_21c6c3b09ad82e15ee5ded23952054859beb394acb1e734c5364eaff899f98ec->leave($__internal_21c6c3b09ad82e15ee5ded23952054859beb394acb1e734c5364eaff899f98ec_prof);

    }

    // line 3
    public function block_head($context, array $blocks = array())
    {
        $__internal_55c0943538bad11046f0cdafebec164ad21f958f954c5a0b187833c25cfb6968 = $this->env->getExtension("native_profiler");
        $__internal_55c0943538bad11046f0cdafebec164ad21f958f954c5a0b187833c25cfb6968->enter($__internal_55c0943538bad11046f0cdafebec164ad21f958f954c5a0b187833c25cfb6968_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "head"));

        // line 4
        echo "    <link href=\"";
        echo twig_escape_filter($this->env, $this->env->getExtension('request')->generateAbsoluteUrl($this->env->getExtension('asset')->getAssetUrl("bundles/framework/css/exception.css")), "html", null, true);
        echo "\" rel=\"stylesheet\" type=\"text/css\" media=\"all\" />
";
        
        $__internal_55c0943538bad11046f0cdafebec164ad21f958f954c5a0b187833c25cfb6968->leave($__internal_55c0943538bad11046f0cdafebec164ad21f958f954c5a0b187833c25cfb6968_prof);

    }

    // line 7
    public function block_title($context, array $blocks = array())
    {
        $__internal_9794eada0c1b52782b587818d715a5c641f26b9e136697aa84ecc8ad0af134f0 = $this->env->getExtension("native_profiler");
        $__internal_9794eada0c1b52782b587818d715a5c641f26b9e136697aa84ecc8ad0af134f0->enter($__internal_9794eada0c1b52782b587818d715a5c641f26b9e136697aa84ecc8ad0af134f0_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "title"));

        // line 8
        echo "    ";
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["exception"]) ? $context["exception"] : $this->getContext($context, "exception")), "message", array()), "html", null, true);
        echo " (";
        echo twig_escape_filter($this->env, (isset($context["status_code"]) ? $context["status_code"] : $this->getContext($context, "status_code")), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, (isset($context["status_text"]) ? $context["status_text"] : $this->getContext($context, "status_text")), "html", null, true);
        echo ")
";
        
        $__internal_9794eada0c1b52782b587818d715a5c641f26b9e136697aa84ecc8ad0af134f0->leave($__internal_9794eada0c1b52782b587818d715a5c641f26b9e136697aa84ecc8ad0af134f0_prof);

    }

    // line 11
    public function block_body($context, array $blocks = array())
    {
        $__internal_943959de1dd4bb3365dc4c8005ab972e806d74824f4f332e3059a41a409a3594 = $this->env->getExtension("native_profiler");
        $__internal_943959de1dd4bb3365dc4c8005ab972e806d74824f4f332e3059a41a409a3594->enter($__internal_943959de1dd4bb3365dc4c8005ab972e806d74824f4f332e3059a41a409a3594_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "body"));

        // line 12
        echo "    ";
        $this->loadTemplate("TwigBundle:Exception:exception.html.twig", "TwigBundle:Exception:exception_full.html.twig", 12)->display($context);
        
        $__internal_943959de1dd4bb3365dc4c8005ab972e806d74824f4f332e3059a41a409a3594->leave($__internal_943959de1dd4bb3365dc4c8005ab972e806d74824f4f332e3059a41a409a3594_prof);

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
