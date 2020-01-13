<?php

namespace Acme\ShopBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Acme\ShopBundle\Entity\department;
use Acme\ShopBundle\Entity\shopImage;
use Acme\ShopBundle\Entity\Order;

class DefaultController extends Controller { 
    /**
     * @Route("/",name="shop_home") 
     */
    public function indexAction(Request $request){   
         
        $em = $this->getDoctrine()->getManager();        
        $products = array();
        if($request->getMethod() == 'POST'){
            $products = $em->getRepository('ShopBundle:product')->findBy(array('name' => $request->request->get('product_name')));            
        }
        else {
            $order = new Order();
            if($request->query->get('cart') == 'view'){ //see my cart                
                if($request->getSession()->get('order') != NULL){                    
                    $order = $request->getSession()->get('order');                    
                    $products = $order->getProducts();
                }
            } elseif($request->query->get('department') != '') { // 
            $products = $em->getRepository('ShopBundle:product')->findBy(array('departmentId' => $request->query->get('department')));            
           } else {
            $products = $em->getRepository('ShopBundle:product')->findAll();
           } 
        }
        
        $images = array();     
        foreach ($products as $product) {
            $image = $em->getRepository('ShopBundle:shopImage')->findBy(array('productId' => $product->getId()));
            if($image == NULL ) {
                array_push($images,NULL);
            }  else {
                array_push($images,$image);
            }
        }
        $departments = $em->getRepository('ShopBundle:department')->findAll();
        return $this->render('ShopBundle:Default:index.html.twig', array('departments'=>$departments, 'products'=>$products, 'images'=>$images));
    }
    /*
     * __________________________ SEARCH A PRODUCT ___________________________________________________
     */
    /**
     * @Route("/getproduct.php", name="getproduct")
     */
    public function getproductAction(Request $request){        
        $products = $this->getDoctrine()->getRepository('ShopBundle:product')->findAll();
        $q = $request->query->get('q');        
        $names[] = "";
        $hint = array();
        foreach ($products as $product ){
            $names[] = $product->getName();            
        }
        for($i=0; $i<count($names); $i++){                               
            if(strstr(strtolower($names[$i]), strtolower($q))) {
                array_push($hint, $names[$i]);
            }
        }        
        if(empty($hint)){
            array_push($hint, "no suggestion");
            $response = $hint;
        }else{
            $response = $hint;
        }        
        return new JsonResponse($response);
    }
    
    /*
     * ________________________________ ADD A PRODUCT TO ORDER _____________________________________
     */
    /**
     * @Route("/order", name="order")
     */
    public function orderAction(Request $request){
        
        $session = $request->getSession();
        $order = new Order();
        
        //syncronize ??
        
        if($session->get("order") == NULL){
            $session->set('order',$order);
        }
        $order = $session->get('order');
        
        $productId = $request->query->get('product_id');
        $products = $this->getDoctrine()->getRepository('ShopBundle:product')->findAll();
        foreach($products as $product){
            if($product->getId() == $productId){
                $order->add($product);
            }
        }        
        return $this->redirect($this->generateUrl('shop_home'));
    }
    /*
     * ____________________________ REMOVE A PRODUCT FROM ORDER ________________________________________
     */
    /**
     * @Route("/delete_product", name="delete_product")
     */
    public function deleteProductAction(Request $request){
        
        $order = $request->getSession()->get('order');        
        $productId = $request->query->get('product_id');                
        $order->remove($productId);                
        return $this->redirect($this->generateUrl('shop_home', array('cart'=>'view')));
    }
    
    /*
     * _________________ TEST ____________________________________________________________
     */
    /**
     * @Route("/test", name="test")
     */
    public function testAction(Request $request){
        $order = new Order();
        $products = $this->getDoctrine()->getRepository('ShopBundle:product')->findAll();
        foreach ($products as $product ){
            $order->add($product);            
        }        
        return $this->render("ShopBundle:Default:test.html.twig", array("order"=>$order->getProducts()));
    }
}
