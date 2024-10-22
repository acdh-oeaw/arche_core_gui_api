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

    private $roles = [];
    private $actualPage;

    public function __construct() {
        parent::__construct();
    }

    /**
     * check the logged or not logged user rights and the actual resource rights
     * @param string $resId
     * @param string $aclRead
     * @return Response
     */
    public function checkUserPermission(string $resId, string $aclRead = "") {
        $result = [];
        $this->actualPage = $this->repoDb->getBaseUrl() . $resId;

        $result = $this->checkHttpLogin($aclRead);

        if (count($result) === 0) {
            $result = $this->checkShibbolethLogin($aclRead);
        }

        if (count($result) === 0) {
            //the user is not logged in
            $result['access'] = "login";
        }

        $response = new Response();
        $response->setContent(json_encode((array) $result, \JSON_PARTIAL_OUTPUT_ON_ERROR, 1024));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Check if the user logged in with httpd
     * @param string $aclRead
     * @return array
     */
    private function checkHttpLogin(string $aclRead): array {
        //check if the user is logged in
        $result = [];
        if (!empty($_COOKIE[$this->config->authLoginCookie])) {

            $username = "";
            $loginCookie = explode(',', $_COOKIE[$this->config->authLoginCookie]);
            $username = $loginCookie[0];
            unset($loginCookie[0]);
            $this->roles = $loginCookie;
            //we passed acl rights
            $result['username'] = $username;
            $result['roles'] = implode(', ', $this->roles);
            $result['loginMethod'] = 'httpd';
            if (!empty($aclRead)) {
                $aclRead = explode(',', $aclRead);
                //the user has the right aclread group
                if (array_intersect($aclRead, $this->roles)) {
                    $result['username'] = $username;
                    $result['access'] = "authorized";
                } elseif (in_array('admin', $this->roles)) {
                    $result['username'] = $username;
                    $result['access'] = "authorized";
                } else {
                    //the user doesnt have the right group to access the resource
                    $result['access'] = "not authorized";
                }
            } else {
                //the user doesnt have the right group to access the resource
                $result['access'] = "not authorized";
            }
        }
        return $result;
    }

    /**
     * Check if the user logged in with Shibboleth
     * @param string $aclRead
     * @return array
     */
    private function checkShibbolethLogin(string $aclRead): array {
        $result = [];
        if (!empty($_SERVER[$this->config->authSsoHeader])) {
            $result['username'] = $_SERVER[$this->config->authSsoHeader];
            $this->roles[] = 'academic';
            $this->roles[] = 'public';
            $result['roles'] = 'academic, public';
            $result['loginMethod'] = 'shibboleth';
            if (!empty($aclRead)) {
                $aclRead = explode(',', $aclRead);
                //the user has the right aclread group
                if (array_intersect($aclRead, $this->roles)) {
                    $result['access'] = "authorized";
                } elseif (in_array('admin', $this->roles)) {
                    $result['access'] = "authorized";
                } else {
                    //the user doesnt have the right group to access the resource
                    $result['access'] = "not authorized";
                }
            } else {
                //the user doesnt have the right group to access the resource
                $result['access'] = "not authorized";
            }
        }
        return $result;
    }
}
