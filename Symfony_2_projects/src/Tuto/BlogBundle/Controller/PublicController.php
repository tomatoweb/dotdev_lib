<?php

namespace Tuto\BlogBundle\Controller;

Class PublicController extends \Symfony\Bundle\FrameworkBundle\Controller\Controller {
    public function indexAction(){
        return new \Symfony\Component\HttpFoundation\Response("Accueil du blog");
    }
    
    public function pageAction($id){
        return $this->render("TutoBlogBundle:Public:page.html.twig", array('id' => $id));
    }
    
    public function articleAction($lang, $annee, $slug, $format){        
        $auteur = $this->getRequest()->cookies->set('auteur', 'Toto');
        $session = new \Symfony\Component\HttpFoundation\Session\Session();
        $session->start();
        $session->set('name', 'maSession');
        $article = array(
                    'lang' => $lang,
                    'annee' => $annee,
                    'slug' => $slug,
                    'format' => $format,                    
                    'token' => $this->getRequest()->query->get('token'), // $_GET['token'],
                    'date' => new \DateTime("now"),
                    'request' => $this->get('request'),
                    'cookie' => $this->getRequest()->cookies->get('PHPSESSID'),
                    'auteur' => $this->getRequest()->cookies->get('auteur'),
                    'cookies' => $this->getRequest()->cookies->all(),
                    'session' => $session
                   );
        return $this->render('TutoBlogBundle:Public:article.html.twig', array('article' => $article));
    }
}




