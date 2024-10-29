<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Session\AccountInterface;

class ApiController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {

    private function setProps(): array {
        $offset = (empty($_POST['start'])) ? 0 : $_POST['start'];
        $limit = (empty($_POST['length'])) ? 10 : $_POST['length'];
        $draw = (empty($_POST['draw'])) ? 0 : $_POST['draw'];
        $search = (empty($_POST['search']['value'])) ? "" : $_POST['search']['value'];
        //datatable start columns from 0 but in db we have to start it from 1
        $orderby = (empty($_POST['order'][0]['column'])) ? 1 : (int) $_POST['order'][0]['column'];
        $order = (empty($_POST['order'][0]['dir'])) ? 'asc' : $_POST['order'][0]['dir'];
        return [
            'offset' => $offset, 'limit' => $limit, 'draw' => $draw, 'search' => $search,
            'orderby' => $orderby, 'order' => $order
        ];
    }
    
    /**
     * Home page topcollections slider endpoint
     * @param int $count
     * @param string $lang
     * @return JsonResponse
     */
    public function topCollections(int $count, string $lang = "en"): JsonResponse {
        $controller = new \Drupal\arche_core_gui_api\Controller\MetadataController();
        return $controller->getTopCollections($count, $lang);
    }

    /**
     * Top collections datatable view - not in use anymore?
     * @param string $lang
     * @return JsonResponse
     */
    public function topCollectionsDT(string $lang = "en"): JsonResponse {
        $controller = new \Drupal\arche_core_gui_api\Controller\MetadataController();
        return $controller->getTopCollectionsDT($this->setProps(), $lang);
    }

    /**
     * Get all metadata for the given resource
     * @param string $id
     * @param string $lang
     * @return JsonResponse
     */
    public function expertData(string $id, string $lang = "en") {
        $controller = new \Drupal\arche_core_gui_api\Controller\MetadataController();
        return $controller->getExpertData($id, $lang);
    }

    /**
     * Smartsearch MAP coordinates - not in use anymore?
     * @param string $lang
     * @return type
     */
    public function searchCoordinates(string $lang = "en") {
        $controller = new \Drupal\arche_core_gui_api\Controller\MetadataController();
        return $controller->getSearchCoordinates();
    }

    /**
     * Breadcrumb endpoint
     * @param string $id
     * @param string $lang
     * @return type
     */
    public function breadcrumbData(string $id, string $lang = "en") {
        $controller = new \Drupal\arche_core_gui_api\Controller\MetadataController();
        return $controller->getBreadcrumb($id, $lang);
    }

    /**
     * Resource versions data endpoint
     * @param string $identifier
     * @param string $lang
     * @return type
     */
    public function versionsList(string $identifier, string $lang = "en") {
        $controller = new \Drupal\arche_core_gui_api\Controller\VersionsController();
        return $controller->versionsList($identifier, $lang);
    }


    /**
     * The Child DataTable api endpoint
     * @param string $identifier
     * @param string $lang
     * @return type
     */
    public function childData(string $identifier, string $lang) {
        $controller = new \Drupal\arche_core_gui_api\Controller\ChildController();
        return $controller->getChildData($identifier, $this->setProps(), $lang);
    }

    /**
     * Related resources and publications endpoint
     * @param string $identifier
     * @param string $lang
     * @return type
     */
    public function rprDT(string $identifier, string $lang) {
        $controller = new \Drupal\arche_core_gui_api\Controller\InverseDataController();
        return $controller->getRprDT($identifier, $this->setProps(), $lang);
    }

    /**
     * Publications datatable endpoint
     * @param string $identifier
     * @param string $lang
     * @return type
     */
    public function publicationsDT(string $identifier, string $lang) {
        $controller = new \Drupal\arche_core_gui_api\Controller\InverseDataController();
        return $controller->getPublicationsDT($identifier, $this->setProps(), $lang);
    }
    
    /**
     * Place spatial DT
     * @param string $identifier
     * @param string $lang
     * @return type
     */
    public function spatialDT(string $identifier, string $lang) {
        $controller = new \Drupal\arche_core_gui_api\Controller\InverseDataController();
        return $controller->getSpatialDT($identifier, $this->setProps(), $lang);
    }
    
    /**
     * Person contributed DT
     * @param string $identifier
     * @param string $lang
     * @return type
     */
    public function contributedDT(string $identifier, string $lang) {
        $controller = new \Drupal\arche_core_gui_api\Controller\InverseDataController();
        return $controller->contributedDT($identifier, $this->setProps(), $lang);
    }
    
    /**
     * Organisation invvoled DT
     * @param string $identifier
     * @param string $lang
     * @return type
     */
    public function involvedDT(string $identifier, string $lang) {
        $controller = new \Drupal\arche_core_gui_api\Controller\InverseDataController();
        return $controller->involvedDT($identifier, $this->setProps(), $lang);
    }
    
    public function relatedDT(string $identifier, string $lang) {
        $controller = new \Drupal\arche_core_gui_api\Controller\InverseDataController();
        return $controller->relatedDT($identifier, $this->setProps(), $lang);
    }
    

    public function smartSearch(): Response {
        $controller = new \Drupal\arche_core_gui_api\Controller\SmartSearchController();
        return $controller->search($_GET);
    }
    
    /**
     * CLARIN VCR
     * @return Response
     */
    public function vcr(): Response {
        $controller = new \Drupal\arche_core_gui_api\Controller\VCRController();
        return $controller->search($_GET);
    }
    
    public function smartSearchAutoComplete(string $str): Response {
        $controller = new \Drupal\arche_core_gui_api\Controller\SmartSearchController();
        return $controller->autocomplete($str);
    }

    public function smartSearchDateFacets(): Response {
        $controller = new \Drupal\arche_core_gui_api\Controller\SearchBlockController();
        return $controller->dateFacets();
    }

    /**
     * The Child tree view api endpoint
     * @param string $identifier
     * @param string $lang
     * @return type
     */
    public function childTreeData(string $identifier, string $lang) {
        $controller = new \Drupal\arche_core_gui_api\Controller\ChildController();
        return $controller->getChildTreeData($identifier, $this->setProps(), $lang);
    }

    /**
     * Change language session variable API
     * Because of the special path handling, the basic language selector is not working
     *
     * @param string $lng
     * @return Response
     */
    public function archeChangeLanguage(string $lng = 'en'): Response {
       
        $_SESSION['_sf2_attributes']['language'] = strtolower($lng);
        $_SESSION['language'] = strtolower($lng);
       
        $response = new Response();
        $response->setContent(json_encode("language changed to: " . $lng));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    
    /**
     * Root table api endpoint: /browser/api/rootTable/en
     * @param string $lang
     * @return type
     */
    public function rootTable(string $lang) {
        $controller = new \Drupal\arche_core_gui_api\Controller\OntologyController();
        return $controller->getRootTable($lang);
    }
    
    /**
     * Create the Ontology html table for the CKEDITOR 
     * @param string $lang
     * @return type
     */
    public function ontologyJs(string $lang) {
        $controller = new \Drupal\arche_core_gui_api\Controller\OntologyController();
        return $controller->getOntologyJs($lang);
    }
    
    public function nextPrevItem(string $rootId, string $resourceId, string $lang) {
        $controller = new \Drupal\arche_core_gui_api\Controller\ChildController();
        return $controller->getNextPrevItem($rootId, $resourceId, $lang);
    }
    
    public function checkUserPersmission(string $identifier, string $aclRead) {
        $controller = new \Drupal\arche_core_gui_api\Controller\ArcheAuthenticationController();
        return $controller->checkUserPermission($identifier, $aclRead );
    }
   
}
