<?php

/* @WebProfiler/Collector/ajax.html.twig */
class __TwigTemplate_d87a754dfd3ee18ecc3805aac8a4f878c27177c3e43fe706d43e789c7aa97ed6 extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        // line 1
        $this->parent = $this->loadTemplate("@WebProfiler/Profiler/layout.html.twig", "@WebProfiler/Collector/ajax.html.twig", 1);
        $this->blocks = array(
            'toolbar' => array($this, 'block_toolbar'),
        );
    }

    protected function doGetParent(array $context)
    {
        return "@WebProfiler/Profiler/layout.html.twig";
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        $__internal_4f2c8edbd557bf1225fd86f0f54c5b1097abfc3b3f0eb70d63bafe4fb5db52c0 = $this->env->getExtension("native_profiler");
        $__internal_4f2c8edbd557bf1225fd86f0f54c5b1097abfc3b3f0eb70d63bafe4fb5db52c0->enter($__internal_4f2c8edbd557bf1225fd86f0f54c5b1097abfc3b3f0eb70d63bafe4fb5db52c0_prof = new Twig_Profiler_Profile($this->getTemplateName(), "template", "@WebProfiler/Collector/ajax.html.twig"));

        $this->parent->display($context, array_merge($this->blocks, $blocks));
        
        $__internal_4f2c8edbd557bf1225fd86f0f54c5b1097abfc3b3f0eb70d63bafe4fb5db52c0->leave($__internal_4f2c8edbd557bf1225fd86f0f54c5b1097abfc3b3f0eb70d63bafe4fb5db52c0_prof);

    }

    // line 3
    public function block_toolbar($context, array $blocks = array())
    {
        $__internal_5b741c12a6512ab76b54e2c9d58645ad055d51a812c23a68a5e67cb1cbdfef87 = $this->env->getExtension("native_profiler");
        $__internal_5b741c12a6512ab76b54e2c9d58645ad055d51a812c23a68a5e67cb1cbdfef87->enter($__internal_5b741c12a6512ab76b54e2c9d58645ad055d51a812c23a68a5e67cb1cbdfef87_prof = new Twig_Profiler_Profile($this->getTemplateName(), "block", "toolbar"));

        // line 4
        echo "    ";
        $context["icon"] = ('' === $tmp = "        <span>
            <svg width=\"24\" height=\"28\" xmlns=\"http://www.w3.org/2000/svg\" version=\"1.1\" x=\"0px\" y=\"0px\" viewBox=\"0 0 24 28\" enable-background=\"new 0 0 24 28\" xml:space=\"preserve\"><polygon fill=\"#3F3F3F\" points=\"18.4,3.8 12.8,9.4 16.3,9.4 16.3,21.1 14.1,21.1 9.9,25.3 16.3,25.3 20.5,25.3 20.5,21.1 20.5,9.4 23.9,9.4\"/><polygon fill=\"#3F3F3F\" points=\"5.6,25.3 11.2,19.7 7.7,19.7 7.7,8 9.9,8 14.1,3.8 7.7,3.8 3.5,3.8 3.5,8 3.5,19.7 0.1,19.7\"/></svg>
            <span class=\"sf-toolbar-ajax-requests\">0</span>
        </span>
    ") ? '' : new Twig_Markup($tmp, $this->env->getCharset());
        // line 10
        echo "    ";
        $context["text"] = ('' === $tmp = "        <div class=\"sf-toolbar-info-piece\">
            <b>AJAX requests</b>
            <span class=\"sf-toolbar-ajax-info\"></span>
        </div>
        <div class=\"sf-toolbar-info-piece\">
            <table class=\"sf-toolbar-ajax-requests\">
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>URL</th>
                        <th>Time</th>
                        <th>Profile</th>
                    </tr>
                </thead>
                <tbody class=\"sf-toolbar-ajax-request-list\"></tbody>
            </table>
        </div>
    ") ? '' : new Twig_Markup($tmp, $this->env->getCharset());
        // line 29
        echo "    ";
        $this->loadTemplate("@WebProfiler/Profiler/toolbar_item.html.twig", "@WebProfiler/Collector/ajax.html.twig", 29)->display(array_merge($context, array("link" => false)));
        
        $__internal_5b741c12a6512ab76b54e2c9d58645ad055d51a812c23a68a5e67cb1cbdfef87->leave($__internal_5b741c12a6512ab76b54e2c9d58645ad055d51a812c23a68a5e67cb1cbdfef87_prof);

    }

    public function getTemplateName()
    {
        return "@WebProfiler/Collector/ajax.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  67 => 29,  47 => 10,  40 => 4,  34 => 3,  11 => 1,);
    }
}
