<?php

namespace Acme\ParadigmBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Acme\ParadigmBundle\Entity\works;
use Acme\ParadigmBundle\Entity\categories;

class FormulaireController extends Controller {
    
    /**
     *@Route("/form", name="formu")
     */
    public function formAction(Request $request){
        $work = new works;
        $siteKey = '6LeADwwTAAAAAEKXZgYVjNNN9N6cfkq0SLDpCmoS';  // Google recaptcha
        $secret = '6LeADwwTAAAAAEKXZgYVjNNN9N6cfkq0SLDpCmoS';   // Google recaptcha
        $lang = 'en'; // Google recaptcha
        $em = $this->getDoctrine()->getManager();
        $form = $this->createFormBuilder($work)
                ->add('name', 'text')
                ->add('slug', 'text')
                ->add('content', 'genemu_tinymce') //https://github.com/genemu/GenemuFormBundle/
                ->add('categoryId', 'text')
                ->getForm();
        $request = $this->getRequest();
        if($request->getMethod() == 'POST'){
            $form->bind($request);
            
            $recaptcha = new \ReCaptcha\ReCaptcha($secret);
            $resp = $recaptcha->verify($_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
            
            var_dump($resp);die();
            
            if ($resp->isSuccess()) {
                // verified!
                
            } else {
                $errors = $resp->getErrorCodes();
            }          
            
        }
        
        if($form->isValid()){            
            $em->persist($work); $em->flush();            
        }
        $categories = $em->getRepository('ParadigmBundle:categories')->findAll();
        return $this->render('ParadigmBundle:Formulaire:formulaire.html.twig', array('form' => $form->createView(), 'categories'=>$categories));
    }
}