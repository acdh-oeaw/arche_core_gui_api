<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use EasyRdf\Graph;
use EasyRdf\Literal;

class MetadataController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {

    private $apiHelper;
    
    public function __construct() {
        $this->apiHelper = new \Drupal\arche_core_gui_api\Helper\ApiHelper();
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
                if($i === $count) {
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
