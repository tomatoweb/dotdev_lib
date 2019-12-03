<?php

namespace App\Controller\Admin;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Form\PropertyType;

class AdminController extends AbstractController{


    private $repo;
    private $em;

    /**
     * @param PropertyRepository $repo
     * @param EntityManagerInterface $em
     */
    public function __construct(PropertyRepository $repo, EntityManagerInterface $em){

        $this->repo = $repo;
        $this->em = $em;
    }

    /**
     * @Route("/admin", name="admin.property.index")
     * @return Response
     */
    public function index(): Response{

        $properties = $this->repo->findAll();

        return $this->render('admin/index.html.twig', [
            'properties' => $properties,
            'menu_active' => 'admin'
        ]);
    }

    // Attention: la route /admin/{id} va catcher toutes les routes qui commencent par /admin.
    // y compris /admin/new. Donc on aura une erreur object not found by the @ParamConverter annotation
    // ==> il faut placer la fonction new() avant la fonction edit()
    /**
     * @Route("/admin/new", name="admin.property.new")"
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response{

        $property = new Property();

        $form = $this->createForm(PropertyType::class, $property);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            $this->em->persist($property);
            $this->em->flush();

            $this->addFlash('success', "update ok");

            return $this->redirectToRoute('admin.property.index');
        }

        return $this->render('admin/new.html.twig', [
            "property"  => $property,
            "form"      => $form->createView()
        ]);
    }


    // Attention: la route /admin/{id} va catcher toutes les routes qui commencent par /admin.
    // y compris /admin/new. Donc on aura une erreur object not found by the @ParamConverter annotation
    // ==> il faut placer la fonction new() avant la fonction edit()
    /**
     * @Route("/admin/{id}", name="admin.property.edit", methods="GET|POST")
     * @param Property $property
     * @return Response
     */
    public function edit(Property $property, Request $request): Response{

        $form = $this->createForm(PropertyType::class, $property);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){

            $this->em->flush();

            $this->addFlash('success', "update ok");

            return $this->redirectToRoute("admin.property.index");
        }

        return $this->render('admin/edit.html.twig', [
            "property"  => $property,
            "form"      => $form->createView()
        ]);
    }

    /**
     * @Route("/admin/{id}", name="admin.property.delete", methods="DELETE")
     * @param Property $property
     */
    public function delete(Property $property, Request $request){

        if ($this->isCsrfTokenValid('delete' . $property->getId(), $request->get('_token'))){

        $this->em->remove($property);
        $this->em->flush();

        $this->addFlash('success', "update ok");
        return $this->redirectToRoute('admin.property.index');
        }



        return new Response('csrf error');
    }
}