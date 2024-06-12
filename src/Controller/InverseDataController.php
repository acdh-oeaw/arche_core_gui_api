<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use acdhOeaw\arche\lib\RepoDb;
use acdhOeaw\arche\lib\SearchConfig;
use acdhOeaw\arche\lib\SearchTerm;
use zozlak\RdfConstants as RC;

class InverseDataController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {

    private $apiHelper;

    public function __construct() {
        parent::__construct();
        $this->apiHelper = new \Drupal\arche_core_gui_api\Helper\ApiHelper();
    }

    private function getInverse(
            int $resId,
            array $resContext, // RDF properties to object properties mapping for the inverse resources
            array $relContext = [], // RDF properties to object properties mapping for other resource
            ?\acdhOeaw\arche\lib\SearchConfig $searchCfg = null, // specify ordering and paging here
            string|array|null $properties = null, // allowed reverse property(ies); if null, all are fetched
            string $searchPhrase = '', // search phrase for narrowing the results; search is performed only in properties listed in the $context
            array $searchTerms = [] // any other search terms
    ): array {
        $properties = is_string($properties) ? [$properties] : $properties;

        $schema = $this->repoDb->getSchema();
        $totalCountProp = (string)$this->repoDb->getSchema()->searchCount;
        try {
            $res = new \acdhOeaw\arche\lib\RepoResourceDb($resId, $this->repoDb);
        } catch (\Exception $ex) {
            return [];
        }

        $resId = (string) $resId;

        $searchCfg ??= new \acdhOeaw\arche\lib\SearchConfig();
        $searchCfg->metadataMode = count($relContext) > 0 ? '0_0_1_0' : 'resource';
        if (is_array($properties)) {
            $searchCfg->resourceProperties = array_merge(array_keys($resContext), $properties);
        }
        if (count($relContext) > 0) {
            $searchCfg->relativesProperties = array_keys($relContext);
        }

        $searchTerms[] = new \acdhOeaw\arche\lib\SearchTerm($properties, $res->getUri(), type: \acdhOeaw\arche\lib\SearchTerm::TYPE_RELATION);
        if (!empty($searchPhrase)) {
            $searchTerms[] = new \acdhOeaw\arche\lib\SearchTerm(array_keys($resContext), $searchPhrase, '~');
        }
        $pdoStmt = $this->repoDb->getPdoStatementBySearchTerms($searchTerms, $searchCfg);
        $relations = [];
        $resources = [];
        $context = array_merge($relContext, $resContext);
        $context[(string)$schema->searchOrder] = 'searchOrder';
        $context[(string)$schema->searchOrderValue . '1'] = 'searchValue';
        $totalCount = null;
        
        while ($triple = $pdoStmt->fetchObject()) {
            $triple->value ??= '';
            $id = (string) $triple->id;
            $shortProperty = $context[$triple->property] ?? false;
            $property = $shortProperty ?: $triple->property;

            $resources[$id] ??= (object) ['id' => $id];
          
            if ($triple->type === 'REL') {
                if ($triple->value === $resId && ($properties === null || in_array($triple->property, $properties))) {
                    $relations[] = (object) [
                                'property' => $property,
                                'resource' => $resources[$id],
                    ];
                } elseif ($triple->value !== $resId && $shortProperty) {
                    $tid = (string) $triple->value;
                    $resources[$tid] ??= (object) ['id' => $tid];
                    $resources[$id]->{$shortProperty}[] = $resources[$tid];
                }
            } elseif ($shortProperty) {
                $tripleVal = \acdhOeaw\arche\lib\TripleValue::fromDbRow($triple);
                if ($shortProperty === "searchOrder") {
                    $resources[$id]->{$shortProperty}[] = $tripleVal;
                } else {
                    $tLang = (isset($tripleVal->lang)) ? $tripleVal->lang : $triple->lang;
                    (empty($tLang)) ? $tLang = $searchCfg->orderByLang : "";
                    $resources[$id]->{$shortProperty}[$tLang] = $tripleVal;
                }
            } elseif ($triple->property === $totalCountProp) {
                $totalCount = $triple->value;
            }
        }
        $order = array_map(fn($x) => $x->resource->searchOrder[0]->value, $relations);
        array_multisort($order, $relations);
        return [$relations, $totalCount];
    }

    /**
     * Get Related Collections and Resources
     * @param string $id
     * @param array $searchProps
     * @param string $lang
     * @return Response
     */
    public function getRprDT(string $id, array $searchProps, string $lang): Response {
        $id = \Drupal\Component\Utility\Xss::filter(preg_replace('/[^0-9]/', '', $id));

        if (empty($id)) {
            return new JsonResponse(array("Please provide an id"), 404, ['Content-Type' => 'application/json']);
        }

        $result = [];
        $schema = $this->repoDb->getSchema();
        $scfg = new \acdhOeaw\arche\lib\SearchConfig();
        $scfg->metadataMode = 'resource';
        $scfg->offset = $searchProps['offset'];
        $scfg->limit = $searchProps['limit'];
        $orderby = "";
        if ($searchProps['order'] === 'desc') {
            $orderby = '^';
        }
        $scfg->orderBy = [$orderby . $schema->label];
        $scfg->orderByLang = $lang;

        $property = [
            (string)'https://vocabs.acdh.oeaw.ac.at/schema#relation',
            (string)'https://vocabs.acdh.oeaw.ac.at/schema#continues',
            (string)'https://vocabs.acdh.oeaw.ac.at/schema#isContinuedBy',
            (string)'https://vocabs.acdh.oeaw.ac.at/schema#documents',
            (string)'https://vocabs.acdh.oeaw.ac.at/schema#isDocumentedBy'
        ];

        $resContext = [
            (string)$schema->label => 'title',
            (string)\zozlak\RdfConstants::RDF_TYPE => 'rdftype',
            (string)$schema->creationDate => 'avDate',
            (string)$schema->id => 'identifier',
            (string)$schema->accessRestriction => 'accessRestriction'
        ];

        $relContext = [
            (string) $schema->label => 'title',
        ];

        $searchPhrase = '';
        list($result, $totalCount) = $this->getInverse($id, $resContext, $relContext, $scfg, $property, $searchPhrase, [new \acdhOeaw\arche\lib\SearchTerm(\zozlak\RdfConstants::RDF_TYPE, [$schema->classes->resource, $schema->classes->collection])]);

        $helper = new \Drupal\arche_core_gui_api\Helper\InverseTableHelper();
        $result = $helper->extractinverseTableView($result, $lang);
   
        if (count((array) $result) == 0) {
            return new Response(json_encode("There is no resource"), 404, ['Content-Type' => 'application/json']);
        }

        $response = new Response();
        $response->setContent(
                json_encode(
                        array(
                            "aaData" => (array) $result,
                            "iTotalRecords" => (string) $totalCount,
                            "iTotalDisplayRecords" => (string) $totalCount,
                            "draw" => intval($searchProps['draw']),
                            "cols" => array_keys((array) $result[0]),
                            "order" => 'asc',
                            "orderby" => 1
                        )
                )
        );
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    
    
    public function getPublicationsDT(string $id, array $searchProps, string $lang): Response {
        $id = \Drupal\Component\Utility\Xss::filter(preg_replace('/[^0-9]/', '', $id));

        if (empty($id)) {
            return new JsonResponse(array("Please provide an id"), 404, ['Content-Type' => 'application/json']);
        }
        
        $result = [];
        $schema = $this->repoDb->getSchema();
        $scfg = new \acdhOeaw\arche\lib\SearchConfig();
        $scfg->metadataMode = 'resource';
        $scfg->offset = $searchProps['offset'];
        $scfg->limit = $searchProps['limit'];
        $orderby = "";
        if ($searchProps['order'] === 'desc') {
            $orderby = '^';
        }
        $scfg->orderBy = [$orderby . $schema->label];
        $scfg->orderByLang = $lang;

        $property = [
            //(string)'https://vocabs.acdh.oeaw.ac.at/schema#isDerivedPublicationOf',
            (string)'https://vocabs.acdh.oeaw.ac.at/schema#hasDerivedPublication',
            (string)'https://vocabs.acdh.oeaw.ac.at/schema#isSourceOf',
            (string)'https://vocabs.acdh.oeaw.ac.at/schema#hasSource',
            (string)'https://vocabs.acdh.oeaw.ac.at/schema#documents',
            (string)'https://vocabs.acdh.oeaw.ac.at/schema#isDocumentedBy'
        ];

        $resContext = [
            (string)$schema->label => 'title',
            (string)\zozlak\RdfConstants::RDF_TYPE => 'rdftype',
            (string)'https://vocabs.acdh.oeaw.ac.at/schema#hasCustomCitation' => 'customCitation',
            (string)$schema->id => 'identifier',
           (string)$schema->accessRestriction => 'accessRestriction'
        ];

        $relContext = [
            (string) $schema->label => 'title',
        ];

        $searchPhrase = (isset($searchProps['search'])) ? $searchProps['search'] : "" ;
        
        list($result, $totalCount) = $this->getInverse($id, $resContext, $relContext, $scfg, $property, $searchPhrase);
        $helper = new \Drupal\arche_core_gui_api\Helper\InverseTableHelper();
        $result = $helper->extractinverseTableView($result, $lang);
        
        if (count((array) $result) == 0) {
            return new Response(json_encode("There is no resource"), 404, ['Content-Type' => 'application/json']);
        }

        $response = new Response();
        $response->setContent(
                json_encode(
                        array(
                            "aaData" => (array) $result,
                            "iTotalRecords" => (string) $totalCount,
                            "iTotalDisplayRecords" => (string) $totalCount,
                            "draw" => intval($searchProps['draw']),
                            "cols" => array_keys((array) $result[0]),
                            "order" => 'asc',
                            "orderby" => 1
                        )
                )
        );
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    
}
