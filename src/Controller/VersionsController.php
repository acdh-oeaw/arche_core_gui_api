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
    private $prev = [];
    private $newer = [];
    private $versions = [];

    public function __construct() {
        parent::__construct();
        $this->apiHelper = new \Drupal\arche_core_gui_api\Helper\ApiHelper();
    }

    public function versionsList(string $id, string $lang = "en") {

        $result = [];
        $schema = $this->repoDb->getSchema();

        $context = [
            $schema->label => 'title',
            $schema->id => 'id',
            $schema->isNewVersionOf => 'prevVersion',
            $schema->ontology->version => 'version',
        ];

        $result = $this->getVersions($this->repoDb, $id, $schema->isNewVersionOf, $context);

        /*
          "id" => $data[0]->id,
          "uri" => $data[0]->id,
          "uri_dl" => $this->repoDb->getBaseUrl() . $data[0]->id,
          "filename" => $this->getDateFromDateTime($data[0]->avdate).' - '.$data[0]->version,
          "resShortId" => $data[0]->id,
          "title" => $this->getDateFromDateTime($data[0]->avdate).' - '.$data[0]->version,
          "text" => $this->getDateFromDateTime($data[0]->avdate).' - '.$data[0]->version,
          "previd" => '',
          "userAllowedToDL" => true,
          "dir" => false,
          "accessRestriction" => 'public',
          "encodedUri" => $this->repoDb->getBaseUrl() . $data[0]->id

         */
        //$this->versions[0] = array("title" => $result->title[0]->value, 'version' => $result->version[0]->value, 'repoid' => $result->repoid);



        if (isset($result->newerVersion)) {
            echo "newest";
            $this->versions = [];
            foreach ($result->newerVersion as $obj) {
                $this->traverseObject($obj, $this->versions);
            }
        } else {
            echo "oldest";
            $prevArr = [];
            foreach ($result->prevVersion as $obj) {
                $this->traverseObject($obj, $prevArr);
            }
            $this->versions[0] = array("title" => $result->title[0]->value, 'version' => $result->version[0]->value, 'repoid' => $result->repoid, "children" => $prevArr);
            
        }







        echo "<pre>";
        var_dump($this->versions);
        echo "</pre>";

        die();

        //if we have newer content
        if (count($newerArray) > 0) {
            //$this->versions = $newerArray;
            if (count($prevArray) > 0) {
                echo "itt";
                $res = $this->findChildByRepoid($prevArray, 11255);
                echo "<pre>";
                var_dump($res);
                echo "</pre>";
            }
        } else {
            $this->versions[0] = array("title" => $result->title[0]->value, 'version' => $result->version[0]->value, 'repoid' => $result->repoid);
            $this->versions[0]['children'] = $prevArray;
        }

        echo "<pre>";
        var_dump($prevArray);

        var_dump($newerArray);
        var_dump($this->versions);
        echo "</pre>";

        die();

        $helper = new \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper();
        $result = $helper->extractRootView($pdoStmt, $scfg->resourceProperties, $properties, $lang);
        if (count((array) $result) == 0) {
            return new JsonResponse(array("There is no resource"), 404, ['Content-Type' => 'application/json']);
        }

        return new JsonResponse($result, 200, ['Content-Type' => 'application/json']);
    }

    private function findChildByRepoid($array, $repoid) {
        // Loop through each element of the array
        foreach ($array as $element) {
            // Check if the current element's repoid matches the desired repoid
            if ($element['repoid'] == $repoid) {
                // If found, return the current element
                return $element;
            }

            // If the current element has children, recursively search within its children
            if (!empty($element['children'])) {
                $result = $this->findChildByRepoid($element['children'], $repoid);
                // If a match is found within the children, return the result
                if ($result !== null) {
                    return $result;
                }
            }
        }

        // If no match is found, return null
        return null;
    }

    private function traverseObject($inputObject, &$outputArray) {

        // Extract title and repoid from the current object
        $title = $inputObject->title[0]->value;
        $version = $inputObject->version[0]->value;
        $repoid = $inputObject->repoid;

        // Create a new array with title and repoid
        $newItem = array('title' => $title, 'repoid' => $repoid, 'version' => $version);

        // If the current object has a 'prevVersion' property and it's not empty
        if (isset($inputObject->prevVersion) && !empty($inputObject->prevVersion)) {
            if (count($inputObject->prevVersion) > 0) {
                foreach ($inputObject->prevVersion as $prev) {
                    $this->traverseObject($prev, $newItem['children']);
                }
            }
        }

        // Append the new item to the output array
        $outputArray[] = $newItem;
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
        $previd = 0;
        while ($triple = $pdoStmt->fetchObject()) {
            $id = (string) $triple->id;
            $tree[$id] ??= new \stdClass();
            $property = $context[$triple->property];
            if ($property === 'prevVersion') {
                //$previd = $triple->value;

                $tree[$triple->value] ??= new \stdClass();
                $tree[$id]->{$property}[] = $tree[$triple->value];
                $tree[$triple->value]->newerVersion[] = $tree[$id];
                $tree[$id]->repoid = $triple->id;
                $tree[$id]->previd = $previd;
            } else {
                $tree[$id]->$property[] = \acdhOeaw\arche\lib\TripleValue::fromDbRow($triple);
                $tree[$id]->repoid = $triple->id;
                //$tree[$id]->previd = $resId;
            }
        }
        return $tree[(string) $resId];
    }
}
