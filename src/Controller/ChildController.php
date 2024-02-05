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

class ChildController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {

    private $apiHelper;

    public function __construct() {
        parent::__construct();
        $this->apiHelper = new \Drupal\arche_core_gui_api\Helper\ApiHelper();
    }

    /**
     * Child Datatable api
     * @param string $id
     * @param array $searchProps
     * @param string $lang
     * @return Response
     */
    public function getChildData(string $id, array $searchProps, string $lang): Response {
        $id = \Drupal\Component\Utility\Xss::filter(preg_replace('/[^0-9]/', '', $id));

        if (empty($id)) {
            return new JsonResponse(array("Please provide an id"), 404, ['Content-Type' => 'application/json']);
        }

        $result = [];
        $schema = $this->repoDb->getSchema();
        $property = $schema->parent;

        $context = [
            $schema->label => 'title',
            \zozlak\RdfConstants::RDF_TYPE => 'class',
            $schema->creationDate => 'avDate',
            $schema->id => 'identifier',
        ];
        $contextRelatives = [
            $schema->label => 'title'
        ];
        $searchCfg = new \acdhOeaw\arche\lib\SearchConfig();
        $searchCfg->offset = $searchProps['offset'];
        $searchCfg->limit = $searchProps['limit'];
        $orderby = "";
        if ($searchProps['order'] === 'desc') {
            $orderby = '^';
        }
        $searchCfg->orderBy = [$orderby . $schema->label];
        $searchCfg->orderByLang = $lang;
        //$searchPhrase = '170308';
        $searchPhrase = '';
        $t = microtime(true);

        list($result, $totalCount) = $this->getInverse($id, $context, $searchCfg, $property, $searchPhrase);

        $helper = new \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper();
        $result = $helper->extractChildView($result, ['id', 'title', 'class', 'avDate'], $totalCount, $this->repoDb->getBaseUrl());

        if (count((array) $result) == 0) {
            return new Response(json_encode("There is no resource"), 404, ['Content-Type' => 'application/json']);
        }

        $response = new Response();
        $response->setContent(
                json_encode(
                        array(
                            "aaData" => (array) $result,
                            "iTotalRecords" => (string) $result[0]['sumcount'],
                            "iTotalDisplayRecords" => (string) $result[0]['sumcount'],
                            "draw" => intval($searchProps['draw']),
                            "cols" => array_keys((array) $result[0]),
                            "order" => 'asc',
                            "orderby" => 1,
                            "childTitle" => "title",
                            "rootType" => "https://vocabs.acdh.oeaw.ac.at/schema#TopCollection"
                        )
                )
        );
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Child tree view API
     * @param string $id
     * @param array $searchProps
     * @param string $lang
     * @return Response
     */
    public function getChildTreeData(string $id, array $searchProps, string $lang): Response {
        $id = \Drupal\Component\Utility\Xss::filter(preg_replace('/[^0-9]/', '', $id));

        if (empty($id)) {
            return new JsonResponse(array("Please provide an id"), 404, ['Content-Type' => 'application/json']);
        }

        $result = [];
        $schema = $this->repoDb->getSchema();
        $property = [$schema->parent, 'http://www.w3.org/2004/02/skos/core#prefLabel'];
        $context = [
            $schema->label => 'title',
            \zozlak\RdfConstants::RDF_TYPE => 'rdftype',
            $schema->creationDate => 'avDate',
            $schema->id => 'identifier',
            $schema->accessRestriction => 'accessRestriction',
            $schema->binarySize => 'binarysize',
            $schema->fileName => 'filename',
            $schema->ingest->location => 'locationpath'
        ];

        $searchCfg = new \acdhOeaw\arche\lib\SearchConfig();
        //$searchCfg->offset = $searchProps['offset'];
        //$searchCfg->limit = $searchProps['limit'];
        $orderby = "";
        if ($searchProps['order'] === 'desc') {
            $orderby = '^';
        }
        $searchCfg->orderBy = [$orderby . $schema->label];
        $searchCfg->orderByLang = $lang;
        //$searchPhrase = '170308';
        $searchPhrase = '';
        list($result, $totalCount) = $this->getInverse($id, $context, $searchCfg, $property, $searchPhrase);
        
        $helper = new \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper();
        $result = $helper->extractChildTreeView($result, $totalCount, $this->repoDb->getBaseUrl());

        if (count((array) $result) == 0) {
            return new Response(json_encode("There is no resource"), 404, ['Content-Type' => 'application/json']);
        }

        $response = new Response();
        $response->setContent(
                json_encode(
                        (array) $result
                )
        );
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    private function getInverse(
            int $resId,
            array $context, // RDF properties to object properties mapping
            ?\acdhOeaw\arche\lib\SearchConfig $searchCfg = null, // specify ordering and paging here
            string|array|null $properties = null, // allowed reverse property(ies); if null, all are fetched
            string $searchPhrase = '', // search phrase for narrowing the results; search is performed only in properties listed in the $context
            array $searchTerms = [] // any other search terms
    ): array {
        $properties = is_string($properties) ? [$properties] : $properties;

        $schema = $this->repoDb->getSchema();
        $totalCountProp = $this->repoDb->getSchema()->searchCount;
        try {
            $res = new \acdhOeaw\arche\lib\RepoResourceDb($resId, $this->repoDb);
        } catch (\Exception $ex) {
            return [];
        }

        $resId = (string) $resId;

        $searchCfg ??= new \acdhOeaw\arche\lib\SearchConfig();
        $searchCfg->metadataMode = 'resource';
        if (is_array($properties)) {
            $searchCfg->resourceProperties = array_merge(array_keys($context), $properties);
        }

        $searchTerms[] = new \acdhOeaw\arche\lib\SearchTerm($properties, $res->getUri(), type: \acdhOeaw\arche\lib\SearchTerm::TYPE_RELATION);
        if (!empty($searchPhrase)) {
            $searchTerms[] = new \acdhOeaw\arche\lib\SearchTerm(array_keys($context), $searchPhrase, '~');
        }
        $pdoStmt = $this->repoDb->getPdoStatementBySearchTerms($searchTerms, $searchCfg);
        $relations = [];
        $resources = [];
        $context[$schema->searchOrder] = 'searchOrder';
        $context[$schema->searchOrderValue . '1'] = 'searchValue';
        $totalCount = null;
        while ($triple = $pdoStmt->fetchObject()) {
            $triple->value ??= '';
            $id = (string) $triple->id;
            
            $shortProperty = $context[$triple->property] ?? false;
            $property = $shortProperty ?: $triple->property;

           
            
            $resources[$id] ??= (object) ['id' => $id];
          

            if ($triple->type === 'REL' && $triple->value === $resId && ($properties === null || in_array($triple->property, $properties))) {
                $relations[] = (object) [
                            'property' => $property,
                            'resource' => $resources[$id],
                ];
            } elseif ($shortProperty) {
                $resources[$id]->{$shortProperty}[] = \acdhOeaw\arche\lib\TripleValue::fromDbRow($triple);
            } elseif ($triple->property === $totalCountProp) {
                $totalCount = $triple->value;
            } 
        }
       
        $order = array_map(fn($x) => $x->resource->searchOrder[0]->value, $relations);
        array_multisort($order, $relations);
        return [$relations, $totalCount];
    }
}
