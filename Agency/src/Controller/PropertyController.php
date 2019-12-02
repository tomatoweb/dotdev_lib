<?php

namespace App\Controller;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PropertyController extends AbstractController{

    /**
     * @Route("/biens", name="property.index")
     * @param PropertyRepository $repo
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function index(PropertyRepository $repo, EntityManagerInterface $em): Response{

        $property = (new Property())
            ->setName('bel-etage')
            ->setDescription("un duplex")
            ->setSurface(55)
            ->setBedrooms(2)
            ->setRooms(4);
        $em->persist($property);

        $prop = $repo->findAllCreated();
        $prop[4]->setName('appart');

        $em->flush();

        return $this->render('property/index.html.twig',
            [
                "menu_active" => "properties"
            ]);
    }
}