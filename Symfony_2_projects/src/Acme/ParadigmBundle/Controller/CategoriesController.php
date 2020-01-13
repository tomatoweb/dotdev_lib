<?php
namespace Acme\ParadigmBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Acme\ParadigmBundle\Entity\categories;

class CategoriesController extends Controller { 
    
    /**
     * @Route("/categories", name="categories")     
     */
    public function categoriesAction(Request $request){
        if (!$request->getSession()->get('auth')){            
                return $this->render('ParadigmBundle:Default:login.html.twig');
        }
        $categories = $this->getDoctrine()->getRepository('ParadigmBundle:categories')->findAll();
        return $this->render('ParadigmBundle:Categories:categories.html.twig', array('categories' => $categories));
    }
    
   /**
    * @Route("/categories/delete", name="delete_category")
    */
   public function deleteCategoryAction(Request $request){
       if (!$request->getSession()->get('auth')){            
                return $this->render('ParadigmBundle:Default:login.html.twig');
        }
       if($request->query->get('csrf') == $request->getSession()->get('token')){
           $id = $request->query->get('id');
           $em = $this->getDoctrine()->getManager();
           $category = $em->getRepository('ParadigmBundle:categories')->findOneBy(array('id' => $id));
           if (!$category) {
                   throw $this->createNotFoundException('Unable to find this category in database.');
           }
           $em->remove($category); $em->flush();           
           return $this->forward('ParadigmBundle:Categories:categories');
       }
       throw $this->createNotFoundException('Security issue: incorrect csrf token');
   }
   
   /**
    * @Route("/categories/edit_category", name="edit_category")
    */
   public function editCategoryAction(Request $request){
       if (!$request->getSession()->get('auth')){            
                return $this->render('ParadigmBundle:Default:login.html.twig');
       }
       if (!$request->getSession()->get('admin_auth')){            
            return $this->render('ParadigmBundle:Works:admin_login.html.twig');
        }
       
       $em = $this->getDoctrine()->getManager();
       // POST (update a work or create a new work) 
       if ($request->getMethod() == 'POST') {
            $name = $request->request->get('name');
            $slug = $request->request->get('slug');                        
            if ($request->request->get('id')) { // UPDATE                
                $em->getRepository('ParadigmBundle:categories')->find($request->request->get('id'))->setName($name)->setSlug($slug);
                $em->flush();
            } else {                            // INSERT
                $category = new categories;
                $category->setName($name)->setSlug($slug);                
                $em->persist($category);
                $em->flush();
            }
            $request->getSession()->getFlashBag()->set('flash', array('type' => 'success', 'message' => 'Update ok!'));
            return $this->forward('ParadigmBundle:Categories:categories');
        }                
       if (($request->getMethod() == 'GET') && ($request->query->get('csrf') === $request->getSession()->get('token'))){
           $category = $em->getRepository('ParadigmBundle:categories')->findOneBy(array('id' => $request->query->get('id')));
           if(!$category){
               $this->get('session')->getFlashBag()->set('flash', array('type'=>'danger', 'message'=>'<strong>Watch out !</strong> This category doesn\'t exist'));
               return $this->forward('ParadigmBundle:Categories:categories');
           }           
       } else { $category = ''; } // NEW CATEGORY       
       return $this->render('ParadigmBundle:Categories:edit_category.html.twig', array('category' => $category));
   }
}