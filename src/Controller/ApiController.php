<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Component\Utility\Xss;

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

    public function topCollections(int $count, string $lang = "en"): JsonResponse {
        $controller = new \Drupal\arche_core_gui_api\Controller\MetadataController();
        return $controller->getTopCollections($count, $lang);
    }
    
    public function topCollectionsDT(string $lang = "en"): JsonResponse {
        $controller = new \Drupal\arche_core_gui_api\Controller\MetadataController();
        return $controller->getTopCollectionsDT($this->setProps(), $lang);
    }

    public function metadata(string $identifier) {
        //METADATA API
        //CACHE THE METADATA
        echo $identifier;
        return [];
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
    
    public function searchCoordinates(string $lang = "en") {
        $controller = new \Drupal\arche_core_gui_api\Controller\MetadataController();
        return $controller->getSearchCoordinates();
    }

    public function breadcrumbData(string $id, string $lang = "en") {
        $controller = new \Drupal\arche_core_gui_api\Controller\MetadataController();
        return $controller->getBreadcrumb($id, $lang);
    }
    
    public function versionsList(string $identifier, string $lang = "en") {
        $controller = new \Drupal\arche_core_gui_api\Controller\VersionsController();
        return $controller->versionsList($identifier, $lang);
    }
    
    public function versionsTree(string $identifier, string $lang = "en") {
        $controller = new \Drupal\arche_core_gui_api\Controller\VersionsController();
        return $controller->versionsTree($identifier, $lang);
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
    
    public function rprDT(string $identifier, string $lang) {
        $controller = new \Drupal\arche_core_gui_api\Controller\InverseDataController();
        return $controller->getRprDT($identifier, $this->setProps(), $lang);
    }
    
    public function publicationsDT(string $identifier, string $lang) {
        $controller = new \Drupal\arche_core_gui_api\Controller\InverseDataController();
        return $controller->getPublicationsDT($identifier, $this->setProps(), $lang);
    }
    
    public function smartSearch(): Response
    {
        $controller = new \Drupal\arche_core_gui_api\Controller\SmartSearchController();
        return $controller->search($_GET);
    }
    
    public function smartSearchDateFacets(): Response
    {
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

}
