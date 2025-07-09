<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use acdhOeaw\arche\lib\RepoDb;
use acdhOeaw\arche\lib\SearchConfig;
use acdhOeaw\arche\lib\SearchTerm;
use zozlak\RdfConstants as RC;

class CartController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {

    protected $helper;
    
    public function __construct() {
        parent::__construct();
        $this->helper = new \Drupal\arche_core_gui_api\Helper\CartHelper();
    }
    
    public function execute() {
        $this->helper->checkCartContent();
        //$_COOKIE['cart_items'] = [];
        error_log("after update_:::");
        error_log(print_r($_COOKIE['cart_items'], true));
        error_log("after update_ ORDEREDD:::");
        error_log(print_r($_COOKIE['cart_items_ordered'], true));
       
        $response = new Response();
        $response->setContent(json_encode(array('cart_items' => $_COOKIE['cart_items'], "cart_items_ordered" => $_COOKIE['cart_items_ordered']), \JSON_PARTIAL_OUTPUT_ON_ERROR, 1024));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }
    
}