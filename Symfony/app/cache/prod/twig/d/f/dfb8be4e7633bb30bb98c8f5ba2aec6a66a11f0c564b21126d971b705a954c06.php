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
        $__internal_870f9c8a495e36b5fd3e17a3aa12b0cf9e731a55e381317c8d10e62181921883 = $this->env->getExtension("native_profiler");
        $__internal_870f9c8a495e36b5fd3e17a3aa12b0cf9e731a55e381317c8d10e62181921883->enter($__internal_870f9c8a495e36b5fd3e17a3aa12b0cf9e731a55e381317c8d10e62181921883_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "TwigBundle:Exception:exception_full.html.twig"));

        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_870f9c8a495e36b5fd3e17a3aa12b0cf9e731a55e381317c8d10e62181921883->leave($__internal_870f9c8a495e36b5fd3e17a3aa12b0cf9e731a55e381317c8d10e62181921883_prof);

    }

    // line 3
    public function block_head($context, array $blocks = array())
    {
        $__internal_0b7c2766ce6ca18b0a7f5869edf3f58a27b587bf886a18704f6ca9f84093f746 = $this->env->getExtension("native_profiler");
        $__internal_0b7c2766ce6ca18b0a7f5869edf3f58a27b587bf886a18704f6ca9f84093f746->enter($__internal_0b7c2766ce6ca18b0a7f5869edf3f58a27b587bf886a18704f6ca9f84093f746_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "head"));

        // line 4
        echo "    <link href=\"";
        echo twig_escape_filter($this->env, $this->env->getExtension('request')->generateAbsoluteUrl($this->env->getExtension('asset')->getAssetUrl("bundles/framework/css/exception.css")), "html", null, true);
        echo "\" rel=\"stylesheet\" type=\"text/css\" media=\"all\" />
";
        
        $__internal_0b7c2766ce6ca18b0a7f5869edf3f58a27b587bf886a18704f6ca9f84093f746->leave($__internal_0b7c2766ce6ca18b0a7f5869edf3f58a27b587bf886a18704f6ca9f84093f746_prof);

    }

    // line 7
    public function block_title($context, array $blocks = array())
    {
        $__internal_3a32990ad59fcf52bc3818e66febe792d06825bd20b4638762ba19e84d2f4827 = $this->env->getExtension("native_profiler");
        $__internal_3a32990ad59fcf52bc3818e66febe792d06825bd20b4638762ba19e84d2f4827->enter($__internal_3a32990ad59fcf52bc3818e66febe792d06825bd20b4638762ba19e84d2f4827_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "title"));

        // line 8
        echo "    ";
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["exception"]) ? $context["exception"] : $this->getContext($context, "exception")), "message", array()), "html", null, true);
        echo " (";
        echo twig_escape_filter($this->env, (isset($context["status_code"]) ? $context["status_code"] : $this->getContext($context, "status_code")), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, (isset($context["status_text"]) ? $context["status_text"] : $this->getContext($context, "status_text")), "html", null, true);
        echo ")
";
        
        $__internal_3a32990ad59fcf52bc3818e66febe792d06825bd20b4638762ba19e84d2f4827->leave($__internal_3a32990ad59fcf52bc3818e66febe792d06825bd20b4638762ba19e84d2f4827_prof);

    }

    // line 11
    public function block_body($context, array $blocks = array())
    {
        $__internal_c0cb0a57f3e8327c5296dde0f27b5b07e26e10225df91268971c0567aaab9eef = $this->env->getExtension("native_profiler");
        $__internal_c0cb0a57f3e8327c5296dde0f27b5b07e26e10225df91268971c0567aaab9eef->enter($__internal_c0cb0a57f3e8327c5296dde0f27b5b07e26e10225df91268971c0567aaab9eef_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "body"));

        // line 12
        echo "    ";
        $this->loadTemplate("TwigBundle:Exception:exception.html.twig", "TwigBundle:Exception:exception_full.html.twig", 12)->display($context);
        
        $__internal_c0cb0a57f3e8327c5296dde0f27b5b07e26e10225df91268971c0567aaab9eef->leave($__internal_c0cb0a57f3e8327c5296dde0f27b5b07e26e10225df91268971c0567aaab9eef_prof);

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
