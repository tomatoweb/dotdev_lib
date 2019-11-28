<?php


namespace App\Controller;


use ApiPlatform\Core\Action\NotFoundAction;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;


class frontController
{

    /**
     * @Route("/", name="home")
     */
    public function index (Environment $twig){

        return new Response($twig->render('pages/index.html.twig'));
    }


    /**
     * @Route("/news/{slug}")
     */
    public function show ($slug){

        return new Response(sprintf(
            'Future page to show the article: %s',
            $slug
            ));
    }
}