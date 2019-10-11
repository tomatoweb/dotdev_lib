<?php

namespace Acme\ParadigmBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * users
 */
class users {
    /**
     * @var integer
     */
    private $id;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()    {
        return $this->id;
    }

    /**
     * Set username
     *
     * @param string $username
     * @return users
     */
    public function setUsername($username)    {
        $this->username = $username;
        return $this;
    }

    /**
     * Get username
     *
     * @return string 
     */
    public function getUsername()    {
        return $this->username;
    }

    /**
     * Set password
     *
     * @param string $password
     * @return users
     */
    public function setPassword($password)    {
        $this->password = $password;
        return $this;
    }

    /**
     * Get password
     *
     * @return string 
     */
    public function getPassword()    {
        return $this->password;
    }
  
  /**
     * Get user (for dev purposes only)
     *
     * @return users 
     */
    public function get(){
        return array(
          $this->getUsername(),
          $this->getPassword()
        ) ;
    }
}
