<?php
namespace Acme\ParadigmBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Acme\ParadigmBundle\Entity\works;
use Acme\ParadigmBundle\Entity\images;
use Acme\ParadigmBundle\Entity\users;
use tools\helper as h; // Own helper

class DefaultController extends Controller {
    
    /**
     * @Route("/", name="home")
     * @Route("/index.html")
     * @Route("/index.php")
     * @Route("/blabla")
     */
    public function indexAction(Request $request ){
        
        // DEBUG
        echo "<pre>".h::encode_php('ici');die;
        
        // AUTO LOGON ------------------------------------- 
        $user = new users;
        $user->setUsername('user')->setPassword(sha1('user'));        
        $request->getSession()->set('auth', $user);             // PHP native: $_SESSION['auth'] = $user
        $request->getSession()->set('token', md5('bvjhgmbc'));
        // END AUTO LOGON --------------------------------- */
        
        /* IS LOGGED? ------------------------------------- */
        if ( !$request->getSession()->get('auth')){   // ou $this->get('session')->get('auth')        
                return $this->forward('ParadigmBundle:Default:login');                                    
        }
        /* END IS_LOGGED --------------------------------- */
        
        return $this->render('ParadigmBundle:Default:index.html.twig');
    }        
    
    /**
     *  @Route("/cms", name="cms")
     */
    public function cmsAction(Request $request){
        
        /* AUTO LOGON ------------------------------------- 
        $user = new users;
        $user->setUsername('user')->setPassword(sha1('user'));        
        $request->getSession()->set('auth', $user); // PHP brut: $_SESSION['auth'] = $user
        $request->getSession()->set('token', md5('bvjhgmbc'));
         END AUTO LOGON --------------------------------- */
        
        /* IS LOGGED? ------------------------------------- */
        if ( !$request->getSession()->get('auth')){            
                return $this->forward('ParadigmBundle:Default:login');                                    
        }
        /* END IS_LOGGED --------------------------------- */
        
        $em = $this->getDoctrine()->getManager();
        
        if ( ! $request->query->get('category')){     // PHP native: if (isset($_GET['category']))
            $works = $em->getRepository('ParadigmBundle:works')->findAll();                        
        }  
        else {
            $works = $em->getRepository('ParadigmBundle:works')->findBy(array('categoryId' => $request->query->get('category')));
        }
        
        $categories = $em->getRepository('ParadigmBundle:categories')->findBy(array(), array('id' => 'ASC'));
        
        $images = array();
        
        foreach ($works as $work){
            $imageId = $work->getImageId();
            if( $imageId == NULL) {
                array_push($images, NULL);
            } elseif ( $em->getRepository('ParadigmBundle:images')->find($imageId) == NULL) {
                array_push($images, NULL);
            } else array_push($images, $em->getRepository('ParadigmBundle:images')->find($imageId));
        }
        
        return $this->render('ParadigmBundle:Default:cms.html.twig', array('categories' => $categories, 'works' => $works, 'images' => $images));
    }
    
    /**
     * @Route("/login", name="login")
     */
    public function loginAction(Request $request){
        if( !$request->request->get("username")){       // PHP brut: if ( ! isset($_POST['username']))
            return $this->render('ParadigmBundle:Default:login.html.twig');
        }
        $username = $request->request->get("username");
        $password = sha1($request->request->get("password"));        
        $em = $this->getDoctrine()->getManager();
        $user = $em->getRepository('ParadigmBundle:users')->findBy(array('username' => $username, 'password' => $password));        
        if( !$user){
            $request->getSession() // ou $this->get('session')
                    ->getFlashBag()
                    ->set("flash", array('type' => 'danger', 'message' => '<strong>access denied, wrong username or password</strong>'));            
            return $this->render('ParadigmBundle:Default:login.html.twig');
        }
        $request->getSession()->set('auth', $user);
        $request->getSession()->set('token', md5('bvjhgmbc'));        
        return $this->forward('ParadigmBundle:Default:index');
    }
    
    
    /**
    * @Route("/logout", name="logout")
    */    
    public function logoutAction(Request $request){
        $request->getSession()->clear();
        return $this->render('ParadigmBundle:Default:login.html.twig');
    }
}
