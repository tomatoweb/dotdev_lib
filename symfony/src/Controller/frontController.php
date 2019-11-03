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
     * @param Environment $twig
     * @return Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function index (Environment $twig) {

        return new Response($twig->render('pages/index.html.twig'));
    }
}