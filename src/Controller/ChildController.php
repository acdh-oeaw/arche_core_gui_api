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
         //   (string) \zozlak\RdfConstants::RDF_TYPE => 'rdftype',
            //(string) $schema->creationDate => 'avDate',
            (string) $schema->id => 'identifier',
            (string) $schema->accessRestriction => 'accessRestriction',
            (string) $schema->binarySize => 'binarysize',
            (string) $schema->fileName => 'filename',
            (string) $schema->ingest->location => 'locationpath'
        ];
        /*
        $relContext = [
            (string) $schema->label => 'title',
            \zozlak\RdfConstants::RDF_TYPE => 'rdftype'
        ];
*/
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
        $result = $this->getChildren($id, $resContext, $orderby, $lang);
        
        $helper = new \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper();
        $result = $helper->extractChildTreeView((array) $result, $this->repoDb->getBaseUrl(), $lang);
       
        if (count((array) $result) == 0) {
            return new Response(json_encode([]), 200, ['Content-Type' => 'application/json']);
        }

        $response = new Response();
        $response->setContent(json_encode((array) $result));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Fetch the children data
     * @param int $resId
     * @param array $context
     * @param string $orderBy
     * @param string $orderByLang
     * @return array
     */
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
        $searchCfg->metadataMode = '999999_999999_1_0';
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
        $resources = array_filter($resources, fn($x) => isset($x->nextItem));
        // if the resource has the acdh:hasNextItem, return children based on it 
        if (count($resources[(string) $resId]->nextItem ?? []) > 0) {
            $children = [];
            $queue = new \SplQueue();
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
        $context[(string) $schema->searchMatch] = 'match';
        $searchCfg = new \acdhOeaw\arche\lib\SearchConfig();
        $searchCfg->metadataMode = '0_0_1_0';
        $searchCfg->resourceProperties = array_keys($context);
        $searchCfg->relativesProperties = [(string) $schema->label];
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
        $resources = array_filter($resources, fn($x) => isset($x->match));
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
