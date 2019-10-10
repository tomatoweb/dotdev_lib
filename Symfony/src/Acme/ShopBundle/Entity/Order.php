<?php

namespace Acme\ShopBundle\Entity;

class Order {
    
    private $products = array();
    
    public function __construct() {
        
    }
    
    public function add($product) {
        array_push($this->products, $product); 
    }
    
    public function remove($productId){
        unset($this->products[$productId]);
        $this->products = array_values($this->products); // re-index the array        
    }
    
    public function getProducts() {
        return $this->products;
    }
    
}