<?php

namespace Drupal\arche_core_gui_api\Controller;

class ApiController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {
    
    
    public function topCollections(int $count) {
        
        //this should use the search API
        echo $count;
        return [];
    }
    
    public function metadata(int $id) {
        
        //METADATA API
        
        //CACHE THE METADATA
        echo $id;
        return [];
    }
    
    
    
}
