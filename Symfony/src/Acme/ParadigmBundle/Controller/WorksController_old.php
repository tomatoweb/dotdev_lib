<?php
namespace Acme\ParadigmBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Acme\ParadigmBundle\Entity\works;
use Acme\ParadigmBundle\Entity\images;
use Acme\ParadigmBundle\Entity\users;

class WorksController extends Controller {
    
    /**
     * @Route("/works", name="works")
     */  
    public function worksAction(Request $request){
        
        /* AUTO LOGON ------------------------------------- */
        $user = new users;
        $user->setUsername('user');
        $user->setPassword('user');
        $request->getSession()->set('auth', $user);
        $request->getSession()->set('token', md5('bvjhgmbc'));
        /* END AUTO LOGON --------------------------------- */
        
        if (!$request->getSession()->get('auth')){            
                return $this->render('ParadigmBundle:Default:login.html.twig');
        }
        $works = $this->getDoctrine()->getRepository('ParadigmBundle:works')->findAll();
        return $this->render('ParadigmBundle:Works:works.html.twig', array('works' => $works));
    }
    
    /**
     * @Route("/works/delete/", name="delete_work")
     */
    public function deleteAction(Request $request){
        if (!$request->getSession()->get('auth')){            
                return $this->render('ParadigmBundle:Default:login.html.twig');
        }
        if ($request->query->get('csrf') == $request->getSession()->get('token')){
            $id = $request->query->get('id');            
            $em = $this->getDoctrine()->getManager();
            $work = $em->getRepository('ParadigmBundle:works')->findOneBy(array('id'=>$id));
            $em->remove($work); $em->flush();
            $this->get('session')->getFlashBag()->set('flash', array('type'=>'danger', 'message'=>'<strong>Work deleted !</strong>'));
            return $this->forward('ParadigmBundle:Works:works');
        }
        throw $this->createNotFoundException('Security issue : incorrect token');
    }
    
    /**
     * @Route("/works/edit", name="edit_work")
     */
    public function editAction(Request $request){
        if (!$request->getSession()->get('auth')){ 
            return $this->render('ParadigmBundle:Default:login.html.twig');
        }        
        $work = new works; //entity
        $em = $this->getDoctrine()->getManager();
        
        if ($request->getMethod() == 'POST'){            
            if(!preg_match("#^[a-z\-0-9]+$#", $request->request->get('slug'))){                
                $this->get('session')->getFlashBag()->set('flash', array('type'=>'danger', 'message'=>'<strong>Validation error !</strong> Slug should only contain small letters and/or dashes(-)'));                
                $categories = $em->getRepository('ParadigmBundle:categories')->findAll();
                return $this->render('ParadigmBundle:Works:edit_work.html.twig', array('categories' => $categories));                                
            }
            if($request->request->get('id') != ''){ //UPDATE                              
                $work = $em->getRepository('ParadigmBundle:works')->find($request->request->get('id'));
                $work->setName($request->request->get('name'))
                     ->setSlug($request->request->get('slug'))
                     ->setContent($request->request->get('content'))
                     ->setCategoryId($request->request->get('category_id'));
                $em->flush();                
            } else { //INSERT                
                $work->setName($request->request->get('name'))
                     ->setSlug($request->request->get('slug'))
                     ->setContent($request->request->get('content'))
                     ->setCategoryId($request->request->get('category_id')); 
                $em->persist($work); $em->flush();
                //$work = $this->getDoctrine()->getRepository('ParadigmBundle:works')->findOneBy(array('id' => $work->getId())); //last insert Id
            } 
            // GESTION DES IMAGES
            foreach ($_FILES['images']['name'] as $k => $v){                
                $image = new images;
                $image_name = $v;
                $image_tmp_name = $_FILES['images']['tmp_name'][$k];
                $image_ext = pathinfo($image_name, PATHINFO_EXTENSION);
                require_once 'resize_image.php';
                if (in_array($image_ext, array('jpeg', 'jpg', 'png'))){                    
                    $image->setWorkId($work->getId()); $em->persist($image); $em->flush();
                    $image_name = $image->getId().'.'.$image_ext;                
                    $image->setName($image_name); $em->persist($image); $em->flush();
                    
                    // PRODUCTION (D-TELEKOM hosting)________________________________________________________________________________________________
                    /*
                    // UPLOAD IMAGES ON SERVER
                    $ftp = ftp_connect("monalisacreations.homepage.t-online.de", 21);                    
                    ftp_login($ftp, "admin@monalisacreations.homepage.t-online.de", "
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    ");
                    if(ftp_pasv($ftp, true)) {
                      ftp_put($ftp, "web/bundles/paradigm/img/works/".$image_name, $image_tmp_name, FTP_BINARY);
                       
                    }
                    else {
                       echo('passiv mode not on');die();
                    }
                    ftp_close($ftp);
                    // MINIFIED COPY OF IMAGE
                    $file = "/home/www/web/bundles/paradigm/img/works/".$image_name;
                    $resizedFile = "/home/www/web/bundles/paradigm/img/works/"."min_".$image_name;
                    smart_resize_image($file , null, 270 , 180 , false , $resizedFile , false , false ,100 );
                    */
                    // END PRODUCTION____________________________________________________________________________________________
                    
                    // DEVELOPMENT  (localhost)______________________________________________________________________________________________
                    // php app/console assets:install --symlink (run command as Windows administrator)                    
                    move_uploaded_file($image_tmp_name, $_SERVER['DOCUMENT_ROOT'].'\Symfony\src\Acme\ParadigmBundle/Resources/public/img/works/'.$image_name);                    
                    copy($_SERVER['DOCUMENT_ROOT'].'\Symfony\src\Acme\ParadigmBundle/Resources/public/img/works/'.$image_name,
                         $_SERVER['DOCUMENT_ROOT'].'\Symfony/web/bundles/paradigm/img/works/'.$image_name);
                    $file = $_SERVER['DOCUMENT_ROOT'].'\Symfony\src\Acme\ParadigmBundle/Resources/public/img/works/'.$image_name;
                    $resizedFile = $_SERVER['DOCUMENT_ROOT'].'\Symfony\src\Acme\ParadigmBundle/Resources/public/img/works/'."min_".$image_name;
                    smart_resize_image($file , null, 270 , 180 , false , $resizedFile , false , false ,100 );
                    copy($_SERVER['DOCUMENT_ROOT'].'\Symfony\src\Acme\ParadigmBundle/Resources/public/img/works/'."min_".$image_name,
                         $_SERVER['DOCUMENT_ROOT'].'\Symfony/web/bundles/paradigm/img/works/'."min_".$image_name);                    
                     // END DEVELOPMENT ________________________________________________________________________________________                    
                }                   
            }
            $this->get('session')->getFlashBag()->set('flash', array('type'=>'success', 'message'=>'<strong>Work saved !</strong>'));
            return $this->forward('ParadigmBundle:Works:works');        
        }
        
        //GET
        $get = $request->query;
        if(count($get) != 0){            
            if (!($get->get('csrf') == $request->getSession()->get('token'))){
                $this->get('session')->getFlashBag()->set('flash', array('type'=>'danger', 'message'=>'csrf token error'));
                return $this->forward('ParadigmBundle:Works:works');
            }
            if($get->get('id') != ''){            
                $work = $this->getDoctrine()->getRepository('ParadigmBundle:works')->findOneBy(array('id' => $get->get('id')));
                if(!$work){
                    $this->get('session')->getFlashBag()->set('flash', array('type'=>'danger','message'=>'No Work for this Id'));
                    return $this->forward('ParadigmBundle:Works:works');
                }            
            }
        }
        $categories = $this->getDoctrine()->getRepository('ParadigmBundle:categories')->findAll();
        $images = $this->getDoctrine()->getRepository('ParadigmBundle:images')->findBy(array('workId' => $work->getId()));        
        return $this->render('ParadigmBundle:Works:edit_work.html.twig', array('work' => $work, 'categories' => $categories, 'images' => $images)); //NEW WORK
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    ...}
    
    /**
     * @Route("/works/delete_image", name="delete_image")
     */
    public function deleteImageAction(Request $request){
        $em = $this->getDoctrine()->getManager();
        $image = $em->getRepository('ParadigmBundle:images')->find($request->query->get('id'));
        unlink($_SERVER['DOCUMENT_ROOT'].'/web/bundles/paradigm/img/works/'.$image->getName());
        unlink($_SERVER['DOCUMENT_ROOT'].'/web/bundles/paradigm/img/works/min_'.$image->getName());
        $em->remove($image); $em->flush(); 
        return $this->forward('ParadigmBundle:Works:works');
    }
    
    /**
     * @Route("/works/highlight_image", name="highlight_image")
     */
    public function highlightImageAction(Request $request){
        $em = $this->getDoctrine()->getManager();        
        $work = $em->getRepository('ParadigmBundle:works')->find($request->query->get('work_id'));
        $work->setImageId($request->query->get('id')); $em->flush();                
        return $this->forward('ParadigmBundle:Works:works');
    }
}