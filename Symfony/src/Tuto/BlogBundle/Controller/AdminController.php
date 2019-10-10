<?php

namespace Tuto\BlogBundle\Controller;

Class AdminController extends \Symfony\Bundle\FrameworkBundle\Controller\Controller {
    public function indexAction(){
        return $this->render("TutoBlogBundle:Admin:index.html.twig");
    }

}




