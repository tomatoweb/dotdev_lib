<?php

/* TwigBundle:Exception:exception_full.html.twig */
class __TwigTemplate_78e2163e4eb9af336c95ce2a789be6e43f389c9f010afae1d18ca864d90e194d extends Twig_Template
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
        $__internal_6c7b753d0b5c4f8dddcf248a61344729f60f250d8b8914aac53c1cf31694bd4a = $this->env->getExtension("native_profiler");
        $__internal_6c7b753d0b5c4f8dddcf248a61344729f60f250d8b8914aac53c1cf31694bd4a->enter($__internal_6c7b753d0b5c4f8dddcf248a61344729f60f250d8b8914aac53c1cf31694bd4a_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "TwigBundle:Exception:exception_full.html.twig"));

        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_6c7b753d0b5c4f8dddcf248a61344729f60f250d8b8914aac53c1cf31694bd4a->leave($__internal_6c7b753d0b5c4f8dddcf248a61344729f60f250d8b8914aac53c1cf31694bd4a_prof);

    }

    // line 3
    public function block_head($context, array $blocks = array())
    {
        $__internal_96346be8b342aec4cbcac7e4699fadd5d4de7e1bfbe9a52f575c9e3f7234cd3e = $this->env->getExtension("native_profiler");
        $__internal_96346be8b342aec4cbcac7e4699fadd5d4de7e1bfbe9a52f575c9e3f7234cd3e->enter($__internal_96346be8b342aec4cbcac7e4699fadd5d4de7e1bfbe9a52f575c9e3f7234cd3e_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "head"));

        // line 4
        echo "    <link href=\"";
        echo twig_escape_filter($this->env, $this->env->getExtension('request')->generateAbsoluteUrl($this->env->getExtension('asset')->getAssetUrl("bundles/framework/css/exception.css")), "html", null, true);
        echo "\" rel=\"stylesheet\" type=\"text/css\" media=\"all\" />
";
        
        $__internal_96346be8b342aec4cbcac7e4699fadd5d4de7e1bfbe9a52f575c9e3f7234cd3e->leave($__internal_96346be8b342aec4cbcac7e4699fadd5d4de7e1bfbe9a52f575c9e3f7234cd3e_prof);

    }

    // line 7
    public function block_title($context, array $blocks = array())
    {
        $__internal_75a2f8de5f5e0d0e26a5586de0dc8a6abb4ae2d7bee8cbb5953e042bd0b26be5 = $this->env->getExtension("native_profiler");
        $__internal_75a2f8de5f5e0d0e26a5586de0dc8a6abb4ae2d7bee8cbb5953e042bd0b26be5->enter($__internal_75a2f8de5f5e0d0e26a5586de0dc8a6abb4ae2d7bee8cbb5953e042bd0b26be5_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "title"));

        // line 8
        echo "    ";
        echo twig_escape_filter($this->env, $this->getAttribute((isset($context["exception"]) ? $context["exception"] : $this->getContext($context, "exception")), "message", array()), "html", null, true);
        echo " (";
        echo twig_escape_filter($this->env, (isset($context["status_code"]) ? $context["status_code"] : $this->getContext($context, "status_code")), "html", null, true);
        echo " ";
        echo twig_escape_filter($this->env, (isset($context["status_text"]) ? $context["status_text"] : $this->getContext($context, "status_text")), "html", null, true);
        echo ")
";
        
        $__internal_75a2f8de5f5e0d0e26a5586de0dc8a6abb4ae2d7bee8cbb5953e042bd0b26be5->leave($__internal_75a2f8de5f5e0d0e26a5586de0dc8a6abb4ae2d7bee8cbb5953e042bd0b26be5_prof);

    }

    // line 11
    public function block_body($context, array $blocks = array())
    {
        $__internal_28ad29db76c51be81a8e71147d20f0e17327fff4911f5971fb0b4a1a2cd5b5e4 = $this->env->getExtension("native_profiler");
        $__internal_28ad29db76c51be81a8e71147d20f0e17327fff4911f5971fb0b4a1a2cd5b5e4->enter($__internal_28ad29db76c51be81a8e71147d20f0e17327fff4911f5971fb0b4a1a2cd5b5e4_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "body"));

        // line 12
        echo "    ";
        $this->loadTemplate("TwigBundle:Exception:exception.html.twig", "TwigBundle:Exception:exception_full.html.twig", 12)->display($context);
        
        $__internal_28ad29db76c51be81a8e71147d20f0e17327fff4911f5971fb0b4a1a2cd5b5e4->leave($__internal_28ad29db76c51be81a8e71147d20f0e17327fff4911f5971fb0b4a1a2cd5b5e4_prof);

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
