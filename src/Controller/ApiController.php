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

    public function breadcrumbData(string $id, string $lang = "en") {
        $controller = new \Drupal\arche_core_gui_api\Controller\MetadataController();
        return $controller->getBreadcrumb($id, $lang);
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
