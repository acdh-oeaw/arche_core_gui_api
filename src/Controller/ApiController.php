<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Component\Utility\Xss;
use zozlak\RdfConstants as RC;

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

    public function getExpertData(string $id, string $lang = "en") {
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        ##############################################################
        $id = \Drupal\Component\Utility\Xss::filter(preg_replace('/[^0-9]/', '', $id));

        if (empty($id)) {
            return new JsonResponse(array("Please provide an id"), 404, ['Content-Type' => 'application/json']);
        }

        $result = [];
        //try {

            $res = new \acdhOeaw\arche\lib\RepoResourceDb($id, $this->repoDb);
            
            $contextRelatives = [
                'https://vocabs.acdh.oeaw.ac.at/schema#hasTitle' => 'title',
                'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' => 'class'
            ];

            $pdoStmt = $res->getMetadataStatement(
                    '0_0_1_0',
                    '',
                    $contextRelatives,
                    $contextRelatives
            );
            echo "<pre>";
            var_dump($res);
            echo "</pre>";

            die();
             while ($triple = $pdoStmt->fetchObject()) {
              
                 echo "<pre>";
                 var_dump($triple);
                 echo "</pre>";

                
             }
 die();
            //$result = $this->helper->extractDataFromCoreApiWithId($pdoStmt, $id);
       // } catch (\Exception $ex) {
        //    return new JsonResponse(array("Error during data processing: " . $ex->getMessage()), 404, ['Content-Type' => 'application/json']);
        //x}

        if (count($result) == 0) {
            return new JsonResponse(array("There is no resource"), 404, ['Content-Type' => 'application/json']);
        }

        return new JsonResponse(array("data" => $result), 200, ['Content-Type' => 'application/json']);
        
        
        //$data = $this->helper->fetchApiEndpoint('https://arche-dev.acdh-dev.oeaw.ac.at/browser/api/core/expert/' . $identifier . '/en');
        echo "expert data";
        return [];
    }

    public function getTopCollections(int $count, string $lang = "en"): JsonResponse {
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
