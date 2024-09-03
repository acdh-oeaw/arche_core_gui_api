<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use acdhOeaw\arche\lib\RepoDb;
use acdhOeaw\arche\lib\SearchConfig;
use acdhOeaw\arche\lib\SearchTerm;
use zozlak\RdfConstants as RC;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class ChildController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {

    use StringTranslationTrait;

    private $apiHelper;

    public function __construct() {
        parent::__construct();
        $this->apiHelper = new \Drupal\arche_core_gui_api\Helper\ApiHelper();
    }

    /**
     * Child Datatable api - NOT IN USE NOW!
     * @param string $id
     * @param array $searchProps
     * @param string $lang
     * @return Response
     */
    public function getChildData(string $id, array $searchProps, string $lang): Response {
        $id = \Drupal\Component\Utility\Xss::filter(preg_replace('/[^0-9]/', '', $id));

        if (empty($id)) {
            return new JsonResponse(array($this->t("Please provide an id")), 404, ['Content-Type' => 'application/json']);
        }

        $result = [];
        $schema = $this->repoDb->getSchema();
        $property = (string) $schema->parent;

        $resContext = [
            (string) $schema->label => 'title',
            (string) \zozlak\RdfConstants::RDF_TYPE => 'rdftype',
            (string) $schema->creationDate => 'avDate',
            (string) $schema->id => 'identifier',
            (string) $schema->accessRestriction => 'accessRestriction',
            (string) $schema->binarySize => 'binarysize',
            (string) $schema->fileName => 'filename',
            (string) $schema->ingest->location => 'locationpath'
        ];

        $relContext = [
            (string) $schema->label => 'title',
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
        $searchPhrase = '';
        $t = microtime(true);

        list($result, $totalCount) = $this->getInverse($id, $resContext, $relContext, $searchCfg, $property, $searchPhrase);

        $helper = new \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper();
        $result = $helper->extractChildView($result, ['id', 'title', 'class', 'avDate'], $totalCount, $this->repoDb->getBaseUrl(), $lang);

        if (count((array) $result) == 0) {
            return new Response(json_encode($this->t("There is no content")), 200, ['Content-Type' => 'application/json']);
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
            return new JsonResponse(array($this->t("Please provide an id")), 404, ['Content-Type' => 'application/json']);
        }

        $result = [];
        $schema = $this->repoDb->getSchema();
        $property = [(string) $schema->parent, 'http://www.w3.org/2004/02/skos/core#prefLabel'];

        $resContext = [
            (string) $schema->label => 'title',
            (string) \zozlak\RdfConstants::RDF_TYPE => 'rdftype',
            (string) $schema->creationDate => 'avDate',
            (string) $schema->id => 'identifier',
            (string) $schema->accessRestriction => 'accessRestriction',
            (string) $schema->binarySize => 'binarysize',
            (string) $schema->fileName => 'filename',
            (string) $schema->ingest->location => 'locationpath'
        ];

        $relContext = [
            (string) $schema->label => 'title',
            \zozlak\RdfConstants::RDF_TYPE => 'rdftype'
        ];
       
        $searchCfg = new \acdhOeaw\arche\lib\SearchConfig();
        //$searchCfg->offset = $searchProps['offset'];
        //$searchCfg->limit = $searchProps['limit'];
        $orderby = "asc";
        if ($searchProps['order'] === 'desc') {
            $orderby = '^';
        }
        //$searchCfg->orderBy = [$orderby . (string)\zozlak\RdfConstants::RDF_TYPE => 'rdftype'];
        $searchCfg->orderBy = [(string) \zozlak\RdfConstants::RDF_TYPE => 'rdftype'];
        $searchCfg->orderByLang = $lang;
        //$searchPhrase = '170308';
        $searchPhrase = '';
        //list($result, $totalCount) = $this->getInverse($id, $resContext, $relContext, $searchCfg, $property, $searchPhrase);
        //list($result, $totalCount) = $this->getChildren($id, $relContext, $orderby, $lang );
   $result = $this->getChildren($id, $resContext, $orderby, $lang );
       
        $helper = new \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper();
        $result = $helper->extractChildTreeView((array)$result, $this->repoDb->getBaseUrl(), $lang);

        if (count((array) $result) == 0) {
            return new Response(json_encode(t("There is no content")), 200, ['Content-Type' => 'application/json']);
        }

        $response = new Response();
        $response->setContent(json_encode((array) $result));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Get the inverse data of a resource
     * @param int $resId
     * @param array $resContext
     * @param array $relContext
     * @param \acdhOeaw\arche\lib\SearchConfig|null $searchCfg
     * @param string|array|null $properties
     * @param string $searchPhrase
     * @param array $searchTerms
     * @return array
     */
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
        $totalCountProp = (string) $this->repoDb->getSchema()->searchCount;
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
        $context[(string) $schema->searchOrder] = 'searchOrder';
        $context[(string) $schema->searchOrderValue . '1'] = 'searchValue';
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

    function getChildren(int $resId, array $context, string $orderBy, string $orderByLang): array {
        $schema = $this->repoDb->getSchema();
        // add context required to resolve It should cover all of the next item and put collections first 
        $resContext = [
            (string) $schema->nextItem => 'nextItem',
        ];
        $context[\zozlak\RdfConstants::RDF_TYPE] = 'class';
        $context[(string) $schema->nextItem] = 'nextItem';

        // search for acdh:hasNextItem 
        $searchCfg = new \acdhOeaw\arche\lib\SearchConfig();
        $searchCfg->metadataMode = '999999_999999_0_0';
        $searchCfg->metadataParentProperty = $schema->nextItem;
        $searchCfg->resourceProperties = array_keys($resContext);
        $searchCfg->relativesProperties = array_keys($context);
        $searchTerm = new \acdhOeaw\arche\lib\SearchTerm($schema->id, $resId, type: \acdhOeaw\arche\lib\SearchTerm::TYPE_ID);
        $pdoStmt = $this->repoDb->getPdoStatementBySearchTerms([$searchTerm], $searchCfg);
        $resources = [];
        while ($triple = $pdoStmt->fetchObject()) {
            $triple->value ??= '';
            $id = (string) $triple->id;
            $shortProperty = $context[$triple->property] ?? false;
            if (!$shortProperty) {
                continue;
            }

            $resources[$id] ??= (object) ['id' => $id];
            if ($triple->type === 'REL') {
                $tid = (string) $triple->value;
                $resources[$tid] ??= (object) ['id' => $tid];
                $resources[$id]->{$shortProperty}[] = $resources[$tid];
            } elseif ($shortProperty === 'class') {
                $resources[$id]->class = $triple->value;
            } else {
                $resources[$id]->{$shortProperty}[$triple->lang] = $triple->value;
            }
        }
        // if the resource has the acdh:hasNextItem, return children based on it 
        if (count($resources[(string) $resId]->nextItem ?? []) > 0) {
            $children = [];
            $queue = new SplQueue();
            array_map(fn($x) => $queue->push($x), $resources[(string) $resId]->nextItem);
            while (count($queue) > 0) {
                $next = $queue->shift();
                $children[] = $next;
                array_map(fn($x) => $queue->push($x), $next->nextItem ?? []);
                unset($next->nextItem); // optional, assures printing $children is safe 
            }
            return $children;
        }
        // if the resource has no acdh:hasNextItem, fallback to acdh:isPartOf 
        unset($context[(string) $schema->nextItem]);
        $searchCfg = new SearchConfig();
        $searchCfg->metadataMode = '0_0_0_0';
        $searchCfg->resourceProperties = array_keys($context);
        $searchTerm = new \acdhOeaw\arche\lib\SearchTerm($schema->parent, $this->repoDb->getBaseUrl() . $resId, type: \acdhOeaw\arche\lib\SearchTerm::TYPE_RELATION);
        $pdoStmt = $this->repoDb->getPdoStatementBySearchTerms([$searchTerm], $searchCfg);
        $resources = [];
        while ($triple = $pdoStmt->fetchObject()) {
            $triple->value ??= '';
            $id = (string) $triple->id;
            $shortProperty = $context[$triple->property] ?? false;
            if (!$shortProperty) {
                continue;
            }
            $resources[$id] ??= (object) ['id' => $id];
            if ($shortProperty === 'class') {
                $resources[$id]->class = $triple->value;
            } else {
                $resources[$id]->{$shortProperty}[$triple->lang] = $triple->value;
            }
        }
        $sortFn = function ($a, $b) use ($orderByLang): int {
            if ($a->class !== $b->class) {
                return $a->class <=> $b->class;
            }
            return ($a->title[$orderByLang] ?? reset($a->title)) <=> ($b->title[$orderByLang] ?? reset($b->title));
        };
        usort($resources, $sortFn);
        return $resources;
    }
}
