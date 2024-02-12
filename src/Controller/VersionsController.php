<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use EasyRdf\Graph;
use EasyRdf\Literal;
use acdhOeaw\arche\lib\RepoDb;
use acdhOeaw\arche\lib\SearchConfig;
use acdhOeaw\arche\lib\SearchTerm;
use zozlak\RdfConstants as RC;

class VersionsController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {

    private $apiHelper;

    public function __construct() {
        parent::__construct();
        $this->apiHelper = new \Drupal\arche_core_gui_api\Helper\ApiHelper();
    }

    public function versionsList(string $id, string $lang = "en") {

        $result = [];
        $schema = $this->repoDb->getSchema();

        $context = [
            $schema->label => 'title',
            $schema->isNewVersionOf => 'prevVersion',
            $schema->ontology->version => 'version',
        ];

        $result = $this->getVersions($this->repoDb, $id, $schema->isNewVersionOf, $context);

        echo "<pre>";
        var_dump($result->title[0]->value);
        var_dump($result->version[0]->value);
        foreach($result->prevVersion as $obj) {
            $this->traverseArray($obj);
        }
        
        echo "</pre>";

        die();

        $helper = new \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper();
        $result = $helper->extractRootView($pdoStmt, $scfg->resourceProperties, $properties, $lang);
        if (count((array) $result) == 0) {
            return new JsonResponse(array("There is no resource"), 404, ['Content-Type' => 'application/json']);
        }

        return new JsonResponse($result, 200, ['Content-Type' => 'application/json']);
    }

    private function traverseArray($array) {
        foreach ($array as $key => $value) {
            // Check if the current key is 'prevversion'
            if ($key === 'prevVersion') {
                if(isset($value->title)) {
                     echo "<pre>";
                var_dump($value->title[0]->value);
                echo "</pre>";
                }  elseif (is_array($value)) {
                // If the current value is an array, call the function recursively
                $this->traverseArray($value);
            }
                
               
            } elseif (is_array($value)) {
                // If the current value is an array, call the function recursively
                $this->traverseArray($value);
            }
        }
    }

    public function versionsTree(string $id, string $lang = "en") {
        
    }

    private function getVersions(\acdhOeaw\arche\lib\RepoDb $repo, int $resId, string $prevVerProp, array $context): object {
        $res = new \acdhOeaw\arche\lib\RepoResourceDb($resId, $repo);
        $pdoStmt = $res->getMetadataStatement(
                '99_99_0_0',
                $prevVerProp,
                array_keys($context),
                array_keys($context)
        );
        $tree = [];
        while ($triple = $pdoStmt->fetchObject()) {
            $id = (string) $triple->id;
            $tree[$id] ??= new \stdClass();
            $property = $context[$triple->property];
            if ($property === 'prevVersion') {
                $tree[$triple->value] ??= new \stdClass();
                $tree[$id]->{$property}[] = $tree[$triple->value];
                $tree[$triple->value]->newerVersion[] = $tree[$id];
            } else {
                $tree[$id]->$property[] = \acdhOeaw\arche\lib\TripleValue::fromDbRow($triple);
            }
        }
        return $tree[(string) $resId];
    }
}
