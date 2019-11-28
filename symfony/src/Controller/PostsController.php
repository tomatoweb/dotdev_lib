<?php

namespace App\Controller;

use App\Entity\Categories;
use App\Entity\Works;
use App\Form\WorksType;
use App\Repository\WorksRepository;
use Twig\Environment;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Annotation\Route;

// Si on extends AbstractController ou Controller
// et qu'on va à sa définition puis dans la définition de ControllerTrait (la ligne use ControllerTrait)
// on verra dans la méthode getDoctrine l'appel au service doctrine:
// $this->container->get('doctrine')

//class PostsController  extends  Controller     (obsolete, use AbstractController)
//class PostsController  extends  AbstractController

// sans extends, on injecte les dépendances (twig et doctrine) dans la méthode qui en a besoin
// voir avec bin/console debug:autowiring doctrine:
// il autowire  Symfony\Bridge\Doctrine\RegistryInterface

class  PostsController
{
    /**
     * @Route("/posts", name="posts")
     */
    public function index(Environment $twig, RegistryInterface $doctrine)      {


        $works = $doctrine->getRepository(Works::class)->findAll();

        //$works = $this->getDoctrine()->getManager();

        $categories = $doctrine->getRepository(Categories::class)->findAll();

        return new Response($twig->render('posts/index.html.twig', [
            'controller_name' => 'PostsController',
            'env'             => $_ENV,
            'works'           => $works,
            'categories'      => $categories
        ]));
    }


    // Same method but with WorksRepository dependency injection

    /**
     * @Route("/postsonly", name="postsonly")
     */
    public function indexonly(Environment $twig, WorksRepository $doctrine, FormFactoryInterface $formFactory)      {


        $works = $doctrine->findAll();


        // bin/console make:form Works
        $form = $formFactory->createBuilder(WorksType::class, $works[0])->getForm();

        return new Response($twig->render('posts/indexonly.html.twig', [
            'controller_name' => 'PostsController',
            'env'             => $_ENV,
            'works'           => $works,
            'form'            => $form->createView()
        ]));
    }


}
