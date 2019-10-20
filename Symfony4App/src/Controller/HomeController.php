<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

Class HomeController{

    /**
     * @Route("/")
     */
    public function index(Environment $twig){

        return new Response($twig->render('pages/home.html.twig'));

    }

}