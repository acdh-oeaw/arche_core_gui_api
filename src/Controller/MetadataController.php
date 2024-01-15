<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use EasyRdf\Graph;
use EasyRdf\Literal;

use acdhOeaw\arche\lib\RepoDb;
use acdhOeaw\arche\lib\SearchConfig;
use acdhOeaw\arche\lib\SearchTerm;

class MetadataController extends \Drupal\arche_core_gui_api\Controller\ArcheBaseController {
    
    private $apiHelper;

    public function __construct() {
        $this->apiHelper = new \Drupal\arche_core_gui_api\Helper\ApiHelper();
    }

    public function getTopCollections_(int $count, string $lang = "en"): JsonResponse {
  
        $repo = RepoDb::factory(\Drupal::service('extension.list.module')->getPath('arche_core_gui') . '/config/config-gui.yaml');
       
$schema = $repo->getSchema();
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
//-->

// the fastest way but with more postprocessing required you get a PDO statement with each row being a metadata triple
// and you need to map it to resource objects, see code samples e.g. in https://redmine.acdh.oeaw.ac.at/issues/21475
$pdoStmt = $repo->getPdoStatementBySearchTerms([new \acdhOeaw\arche\lib\SearchTerm(\zozlak\RdfConstants::RDF_TYPE, $schema->classes->topCollection)], $scfg);
// or
// here you get a collection of RepoResourceDb objects and from each you can get its EasyRdf metadata object and read
// data from it
//$resources = $repo->getResourcesBySearchTerms([new \acdhOeaw\arche\lib\SearchTerm(\zozlak\RdfConstants::RDF_TYPE, $schema->classes->topCollection)], $scfg);


while ($triple = $pdoStmt->fetchObject()) {
         
        $id = (string)$triple->id;
        $property = $triple->property;
        
        echo "<pre>";
        var_dump($triple);
        echo "</pre>";

     
        if($property === "search://match") {
            echo $id;
            echo "<br>";
        }
        
        $context = $id === $resId ? $contextResource : $contextRelatives;
        $shortProperty = $context[$triple->property] ?? false;
        $resources[$id] ??= (object) ['id' => $id];
        $tid = null;
        if ($triple->type === 'REL') {
            $tid = $triple->value;
            $resources[$tid] ??= (object) ['id' => $tid];
        }

        if ($triple->type !== 'REL') {
            if ($shortProperty) {
                // ordinary property existing in the context
                $resources[$id]->{$shortProperty}[] = \acdhOeaw\arche\lib\TripleValue::fromDbRow($triple);
            } elseif ($id === $resId) {
                // expert property - out of context but belongs to the main resource 
                $resource->expert[$property][] = \acdhOeaw\arche\lib\TripleValue::fromDbRow($triple);
            }
        } elseif ($shortProperty) {
            $resources[$id]->{$shortProperty}[$tid] = $resources[$tid];
        } elseif ($id === $resId) {
            $resource->expert[$property][$tid] = $resources[$tid];
        }
}
echo "<pre>";
var_dump($resources);
echo "</pre>";

die();

die();
        
       
    }

    /**
     * Provide the top collections, based on the count value
     *
     * @param int $count
     * @param string $lang
     * @return JsonResponse
     */
    public function getTopCollections(int $count, string $lang = "en"): JsonResponse {
        $build = "";
        // PHP
        $searchParam = [
            'property' => ['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'],
            'value' => ['https://vocabs.acdh.oeaw.ac.at/schema#TopCollection'],
            'readMode' => 'resource',
            'format' => 'text/turtle'
        ];

        // just with file_get_contents() - only GET possible
        $response = file_get_contents('https://arche-dev.acdh-dev.oeaw.ac.at/api/search?' . http_build_query($searchParam));

        // PSR-7 & PSR-18 way provided by Guzzle - both GET and POST possible
        $client = new \GuzzleHttp\Client();
        $getRequest = new \GuzzleHttp\Psr7\Request(
                'GET',
                'https://arche-dev.acdh-dev.oeaw.ac.at/api/search?' . http_build_query($searchParam)
        );
        $getResponse = $client->sendRequest($getRequest);
        $getResponse->getBody();
        $postRequest = new \GuzzleHttp\Psr7\Request(
                'POST',
                'https://arche-dev.acdh-dev.oeaw.ac.at/api/search',
                ['Content-Type' => 'application/x-www-form-urlencoded'],
                http_build_query($searchParam)
        );
        $postResponse = $client->sendRequest($postRequest);
        $postResponse->getBody();

        $graph = new \EasyRdf\Graph();
        $graph->parse($postResponse->getBody());
        $json = $graph->serialise('json');
        $php = json_decode($json, true);

        $results = [];
        $i = 0;
        foreach ($php as $k => $v) {
            if ($v['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'][0]['value'] == 'https://vocabs.acdh.oeaw.ac.at/schema#TopCollection') {
                if ($i === $count) {
                    break;
                }
                $id = str_replace('https://arche-dev.acdh-dev.oeaw.ac.at/api/', '', $k);
                $results[$id]['title'] = $this->apiHelper->getLangValue($v['https://vocabs.acdh.oeaw.ac.at/schema#hasTitle'], $lang);
                $results[$id]['description'] = $this->apiHelper->getLangValue($v['https://vocabs.acdh.oeaw.ac.at/schema#hasDescription'], $lang);
                $results[$id]['acdhid'] = $this->apiHelper->getAcdhIdValue($v['https://vocabs.acdh.oeaw.ac.at/schema#hasIdentifier']);

                $i++;
            }
        }
        return new JsonResponse($results);
    }
}
