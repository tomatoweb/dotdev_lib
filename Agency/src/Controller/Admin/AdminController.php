<?php

namespace App\Controller\Admin;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Form\PropertyType;

class AdminController extends AbstractController{


    private $repo;

    /**
     * @param PropertyRepository $repo
     */
    public function __construct(PropertyRepository $repo){

        $this->repo = $repo;
    }

    /**
     * @Route("/admin", name="admin.property.index")
     * @return Response
     */
    public function index(): Response{

        $properties = $this->repo->findAll();

        return $this->render('admin/index.html.twig', compact('properties'));
    }

    /**
     * @Route("/admin/{id}", name="admin.property.edit")
     * @param Property $property
     * @return Response
     */
    public function edit(Property $property): Response{

        $form = $this->createForm(PropertyType::class, $property);

        return $this->render('admin/edit.html.twig', [
            "property"  => $property,
            "form"      => $form->createView()
        ]);

    }
}