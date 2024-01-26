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

    
    
    public function topCollections__(int $count, string $lang = "en"): JsonResponse {
        $schema = $this->repoDb->getSchema();

        $scfg = new \acdhOeaw\arche\lib\SearchConfig();
        $scfg->orderBy = ['^' . $schema->modificationDate];
        $scfg->limit = 10;
//<-- adjust according to what you need to display
//    see https://acdh-oeaw.github.io/arche-docs/aux/metadata_api_for_programmers.html
        $scfg->metadataMode = 'resource';
        $scfg->resourceProperties = [
            $schema->title,
            $schema->modificationDate,
        ];
        $scfg->relativesProperties = [];

        $pdoStmt = $this->repoDb->getPdoStatementBySearchTerms([new \acdhOeaw\arche\lib\SearchTerm(RC::RDF_TYPE, $schema->classes->topCollection)], $scfg);

        while ($triple = $pdoStmt->fetchObject()) {
            $id = (string) $triple->id;

            echo "<pre>";
            var_dump($triple);
            echo "</pre>";

            if (!isset($context[$triple->property])) {
                continue;
            }
            $property = $context[$triple->property];
            $resources[$id] ??= (object) ['id' => $id];
            if ($triple->type === 'REL') {
                $tid = $triple->value;
                $resources[$tid] ??= (object) ['id' => $tid];
                $resources[$id]->$property[] = $resources[$tid];
            } else {
                $resources[$id]->$property[] = \acdhOeaw\arche\lib\TripleValue::fromDbRow($triple);
            }
        }



//        $resources = $this->repoDb->getResourcesBySearchTerms([new \acdhOeaw\arche\lib\SearchTerm(RC::RDF_TYPE, $schema->classes->topCollection)], $scfg);
        echo "<pre>";
        var_dump($pdoStmt);
        echo "</pre>";

        die();

        /*
          use acdhOeaw\arche\lib\SearchConfig;
          use acdhOeaw\arche\lib\SearchTerm;

         */
        $cfgPath = '/home/www-data/config/yaml/config-gui.yaml';
        $repo = RepoDb::factory($cfgPath);

//-->
// the fastest way but with more postprocessing required you get a PDO statement with each row being a metadata triple
// and you need to map it to resource objects, see code samples e.g. in https://redmine.acdh.oeaw.ac.at/issues/21475
// or
// here you get a collection of RepoResourceDb objects and from each you can get its EasyRdf metadata object and read
// data from it
    }
}
