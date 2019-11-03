<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class PostsController
{
    /**
     * @Route("/posts", name="posts")
     */
    public function index(Environment $twig)
    {
        //echo (__DIR__); die;

        return new Response($twig->render('posts/index.html.twig', [
            'controller_name' => 'PostsController',
            'env'             => $_ENV
        ]));
    }
}
