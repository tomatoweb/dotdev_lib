<?php

namespace Acme\HelloBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller {
    
    public function indexAction($name){
        $this->get('mailer')->send(\Swift_Message::newInstance()
                                    ->setSubject('test symfony HelloBundle')
                                    ->setFrom('t301020@gmail.com')
                                    ->setTo('t301020@gmail.com')
                                    ->setBody('This message was send from Symfony Acme/HelloBundle')
                                );
        return $this->render('AcmeHelloBundle:Default:index.html.twig', array('name' => $name));
    }
    
    public function index0Action(){        
        return $this->render('AcmeHelloBundle:Default:index0.html.twig');
    }
}
