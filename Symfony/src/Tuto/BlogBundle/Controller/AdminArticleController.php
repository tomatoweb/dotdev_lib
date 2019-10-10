<?php
namespace Tuto\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class AdminArticleController extends Controller{
    public function AjouterAction(Request $request){        
        if ($request->getMethod() == 'POST'){                
                $this->get('session')->getFlashBag()->add('info', "L'article a été ajouté");                
                return $this->redirect($this->generateUrl('blog_admin_home'));
        }
        return $this->render("TutoBlogBundle:AdminArticle:ajouter.html.twig");
    }
}

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

