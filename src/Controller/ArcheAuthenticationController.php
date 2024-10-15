<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Description of ArcheAuthenticationController
 *
 * @author nczirjak
 */
class ArcheAuthenticationController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {

    private $actualPage;

    public function __construct() {
        parent::__construct();
    }

    public function checkUserPermission(string $resId, array $userData) {
        $result = [];
        $this->actualPage = $this->repoDb->getBaseUrl() . $resId;
        
        if (!empty($_COOKIE[$this->config->authLoginCookie])) {
                $roles = explode(',', $_COOKIE[$this->config->authLoginCookie]);
                $msg = "You are logged in as " . array_shift($roles) . " (groups: " . implode(', ', $roles) . ")<br/>";
                // While there is a way to handle SSO login logout,
                // there is no good way we can log out from the HTTP basic auth
                $msg .= "Please close your browser to log out.<br/>";
                $result['message'] = $msg;
            
        } 
      
        $response = new Response();
        $response->setContent(json_encode((array) $result, \JSON_PARTIAL_OUTPUT_ON_ERROR, 1024));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

}
