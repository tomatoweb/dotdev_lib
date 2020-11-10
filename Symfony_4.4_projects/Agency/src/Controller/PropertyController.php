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

        // test create
        $property = (new Property())
            ->setName('bel-etage')
            ->setDescription("un duplex")
            ->setSurface(55)
            ->setBedrooms(2)
            ->setRooms(4)
            ->setPrice(10000);

        // test insert
        //$em->persist($property);

        // test select
        $prop = $repo->findAllCreated();

        // test update le cinquième
        $prop[4]->setName('appart');

        // test commit
        $em->flush();

        return $this->render('property/index.html.twig',
            [
                "menu_active"  => "properties",
                "properties"   => $prop
            ]);
    }

    /**
     * @Route("/show/{name}-{id}", name="property.show", requirements={"name":"^.+$",
     * "id":"^[-+]?[1-9]\d*$"})
     * @param $id
     * @param Property $property
     * @return Response
     * @internal param PropertyRepository $repo
     */
    public function show(Property $property, $name) : Response{
    //public function show($id, PropertyRepository $repo) : Response{ // alternative

        //$property = $repo->find($id);

        if($name !== $property->getName()){
            return $this->redirectToRoute(
                "property.show",
                ["id" => $property->getId(), "name" => $property->getName()],
                301
            );
        }

        return $this->render('property/show.html.twig',
        [
            "menu_active"  => "properties",
            "property"     => $property
        ]);
    }
}