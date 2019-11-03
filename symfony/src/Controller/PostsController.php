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
        return new Response($twig->render('posts/index.html.twig', [
            'controller_name' => 'PostsController',
        ]));
    }
}
