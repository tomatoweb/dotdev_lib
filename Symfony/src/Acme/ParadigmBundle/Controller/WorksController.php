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
            // $this->get('session') OR $request->getSession()
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
        if (!$request->getSession()->get('admin_auth')){            
            return $this->render('ParadigmBundle:Works:admin_login.html.twig');
        }
        
        $work = new works;
        $em = $this->getDoctrine()->getManager();
        //POST
        if ($request->getMethod() == 'POST'){            
            if($request->request->get('id')){ //UPDATE                              
                $work = $em->getRepository('ParadigmBundle:works')->find($request->request->get('id'));
                $work->setName($request->request->get('name'))
                     ->setSlug($request->request->get('slug'))
                     ->setContent($request->request->get('content'))
                     ->setCategoryId($request->request->get('category_id'));
                $em->flush();                
            } else {                                //INSERT                
                $work->setName($request->request->get('name'))
                     ->setSlug($request->request->get('slug'))
                     ->setContent($request->request->get('content'))
                     ->setCategoryId($request->request->get('category_id')); 
                $em->persist($work); $em->flush();
                //$work = $em->getRepository('ParadigmBundle:works')->findOneBy(array('id' => $work->getId())); //last insert Id
            } 
            // GESTION DES IMAGES
            // var_dump($_FILES);die();
            require_once 'resize_image.php'; // function smart_resize_image()
            foreach ($_FILES['images']['name'] as $k => $v){              
                $image = new images;
                // Change image name to be unique, sous la forme image_id.ext (autoincremental id from DB + jpg/jpeg/png extension)
                $image_name = $v;
                $image_tmp_name = $_FILES['images']['tmp_name'][$k];
                $image_ext = pathinfo($image_name, PATHINFO_EXTENSION);                                
                if (in_array($image_ext, array('jpeg', 'jpg', 'png'))){                    
                    $image->setWorkId($work->getId()); $em->persist($image); $em->flush();
                    $image_name = $image->getId().'.'.$image_ext;                
                    $image->setName($image_name); $em->persist($image); $em->flush();
                    
                    // PRODUCTION (D-TELEKOM hosting)________________________________________________________________________________________________
                    /*
                    // UPLOAD IMAGES ON SERVER
                    $ftp = ftp_connect("monalisacreations.homepage.t-online.de", 21);                    
                    ftp_login($ftp, "admin@monalisacreations.homepage.t-online.de", "");
                    if(ftp_pasv($ftp, true)) {
                      ftp_put($ftp, "web/bundles/paradigm/img/works/".$image_name, $image_tmp_name, FTP_BINARY); // upload image
                       
                    }
                    else {
                       echo('passiv mode not on');die();
                    }
                    ftp_close($ftp);
                    // Make a minified copy (270x180) of the image
                    $file = "/home/www/web/bundles/paradigm/img/works/".$image_name;
                    $resizedFile = "/home/www/web/bundles/paradigm/img/works/"."min_".$image_name;
                    smart_resize_image($file , null, 270 , 180 , false , $resizedFile , false , false ,100 );
                    */
                    // END PRODUCTION____________________________________________________________________________________________
                    
                    // DEVELOPMENT  (localhost)______________________________________________________________________________________________
                    // php app/console assets:install --symlink (run command as Windows administrator) To synchronise symfony resources public folder AND on web/bundles folder
                    
                    // copy image on symfony sources public folder AND on web/bundles folder (this is normally done by app/console assets:install
                    $file = $_SERVER['DOCUMENT_ROOT'].'\Symfony\src\Acme\ParadigmBundle/Resources/public/img/works/'.$image_name;
                    move_uploaded_file($image_tmp_name, $_SERVER['DOCUMENT_ROOT'].'\Symfony\src\Acme\ParadigmBundle/Resources/public/img/works/'.$image_name);                    
                    copy($file, $_SERVER['DOCUMENT_ROOT'].'\Symfony/web/bundles/paradigm/img/works/'.$image_name);
                    // Make a minified copy (270x180) of the image in public folder (C:\wamp\www\Symfony\src\Acme\ParadigmBundle\Resources\public\img\works)
                    $resizedFile = $_SERVER['DOCUMENT_ROOT'].'\Symfony\src\Acme\ParadigmBundle/Resources/public/img/works/'."min_".$image_name;
                    smart_resize_image($file , null, 270 , 180 , false , $resizedFile , false , false ,100 );
                    // copy this minified copy from public folder to web folder
                    copy($resizedFile, $_SERVER['DOCUMENT_ROOT'].'\Symfony/web/bundles/paradigm/img/works/'."min_".$image_name);                    
                     // END DEVELOPMENT ________________________________________________________________________________________                    
                }                   
            }
            $this->get('session')->getFlashBag()->set('flash', array('type'=>'success', 'message'=>'<strong>Work saved !</strong>'));
            return $this->forward('ParadigmBundle:Works:works');        
        }
        // EDIT A WORK (GET)
        if($request->query->get('id')) {            
            if ( ! ($request->query->get('csrf') == $request->getSession()->get('token'))){
                $this->get('session')->getFlashBag()->set('flash', array('type'=>'danger', 'message'=>'csrf token error'));
                return $this->forward('ParadigmBundle:Works:works');
            }                        
            $work = $em->getRepository('ParadigmBundle:works')->findOneBy(array('id' => $request->query->get('id')));
            if(!$work){
                $this->get('session')->getFlashBag()->set('flash', array('type'=>'danger','message'=>'No Work for this Id'));
                return $this->forward('ParadigmBundle:Works:works');
            }            
        }
        $categories = $em->getRepository('ParadigmBundle:categories')->findAll();
        $images = $em->getRepository('ParadigmBundle:images')->findBy(array('workId' => $work->getId()));        
        return $this->render('ParadigmBundle:Works:edit_work.html.twig', array('work' => $work, 'categories' => $categories, 'images' => $images)); //NEW WORK
    }
    
    /**
     * @Route("/works/delete_image", name="delete_image")
     */
    public function deleteImageAction(Request $request){
        $em = $this->getDoctrine()->getManager();
        $image = $em->getRepository('ParadigmBundle:images')->find($request->query->get('id'));
        // DELETE IN FOLDERS (public and web) !!!! CAREFULL, this is the devlpmt version for LOCALHOST(not for prod on monalisacreations.de, foders paths are different)
        unlink($_SERVER['DOCUMENT_ROOT'].'/Symfony/web/bundles/paradigm/img/works/'.$image->getName());
        unlink($_SERVER['DOCUMENT_ROOT'].'/Symfony/web/bundles/paradigm/img/works/min_'.$image->getName());
        unlink($_SERVER['DOCUMENT_ROOT'].'\Symfony\src\Acme\ParadigmBundle/Resources/public/img/works/'.$image->getName());
        unlink($_SERVER['DOCUMENT_ROOT'].'\Symfony\src\Acme\ParadigmBundle/Resources/public/img/works/min_'.$image->getName());
        // DELETE IN DB
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
    
    /**
     * @Route("/admin_login", name="admin_login")
     */
    public function admin_loginAction(Request $request){
        
        if( !$request->request->get("username")){       // PHP brut: if ( ! isset($_POST['username']))
            return $this->render('ParadigmBundle:Works:admin_login.html.twig');
        }
        $username = $request->request->get("username");
        $password = sha1($request->request->get("password"));        
        $em = $this->getDoctrine()->getManager();
        $user = $em->getRepository('ParadigmBundle:users')->findBy(array('username' => $username, 'password' => $password));        
        if( !$user){
            $request->getSession() // ou $this->get('session')
                    ->getFlashBag()
                    ->set("flash", array('type' => 'danger', 'message' => '<strong>access denied, wrong username or password</strong>'));            
            return $this->render('ParadigmBundle:Works:admin_login.html.twig');
        }
        $request->getSession()->set('admin_auth', $user);
        
        return $this->forward('ParadigmBundle:Works:works');
    }
}