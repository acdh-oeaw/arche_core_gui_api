<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use quickRdf\DataFactory as DF;
use termTemplates\PredicateTemplate as PT;

/**
 * Description of VCRController
 *
 * @author nczirjak
 */
class VCRController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {

    private $aConfig;
    private $sConfig;
    private $context = [];
    protected \acdhOeaw\arche\lib\Schema $schema;
    private $baseUrl;
    private $preferredLang;
    private $searchInBinaries;
    private $searchPhrase;
    private $reqFacets;
    private $requestHash;

    public function __construct() {
        parent::__construct();
        $this->aConfig = \acdhOeaw\arche\lib\Config::fromYaml(\Drupal::service('extension.list.module')->getPath('arche_core_gui') . '/config/config.yaml');
    }

    private function setContext() {
        $this->context = [
            (string) $this->schema->id => 'uri',
            (string) $this->schema->label => 'title',
            (string) $this->schema->ontology->description => 'description',
            (string) $this->schema->searchFts => 'matchHiglight',
            (string) $this->schema->searchMatch => 'matchProperty',
            (string) $this->schema->searchWeight => 'matchWeight',
            (string) $this->schema->searchOrder => 'matchOrder',
            (string) $this->schema->parent => 'parent',
        ];
    }

    private function setBasicPropertys(array $postParams) {
        $this->sConfig = $this->aConfig->smartSearch;
        $this->schema = new \acdhOeaw\arche\lib\Schema($this->aConfig->schema);
        $this->baseUrl = $this->aConfig->rest->urlBase . $this->aConfig->rest->pathBase;
        $this->preferredLang = $postParams['preferredLang'] ?? $this->sConfig->prefLang ?? 'en';
        $this->searchInBinaries = $postParams['includeBinaries'] ?? false;
        $this->searchPhrase = $postParams['q'] ?? "";
        $this->reqFacets = $postParams['facets'] ?? [];
    }

    /**
     * The main search 
     * @param array $postParams
     * @return Response
     */
    public function search(array $postParams): Response {

        //we are generating the hash for the DB request store process
        $this->requestHash = md5(print_r($postParams, true));
        $msg = [];
        try {
            $this->setBasicPropertys($postParams);
            $useCache = !((bool) ($postParams['noCache'] ?? false));

            $this->setContext();
            // context needed to display search results

            $relContext = [
                (string) $this->schema->label => 'title',
                (string) $this->schema->parent => 'parent',
            ];

            // SEARCH CONFIG
            $facets = $this->sConfig->facets;

            if (!$postParams['linkNamedEntities'] ?? true) {
                $facets = array_filter($facets, fn($x) => $x->type !== 'linkProperty');
            }

            $specialFacets = [\acdhOeaw\arche\lib\SmartSearch::FACET_MAP, \acdhOeaw\arche\lib\SmartSearch::FACET_LINK, \acdhOeaw\arche\lib\SmartSearch::FACET_MATCH];
            $searchTerms = [];
            $spatialSearchTerms = null;
            $spatialSearchTerm = null;
            $allowedProperties = [];
            $facetsInUse = [];

            foreach ($facets as $facet) {
                $fid = in_array($facet->type, $specialFacets) ? $facet->type : $facet->property;
                if (is_array($this->reqFacets[$fid] ?? null) || isset($this->reqFacets[$fid]) && $fid === \acdhOeaw\arche\lib\SmartSearch::FACET_MAP) {
                    $facetsInUse[] = $fid;
                    $reqFacet = $this->reqFacets[$fid];
                    if ($facet->type === \acdhOeaw\arche\lib\SmartSearch::FACET_LINK) {
                        foreach ($reqFacet as $i) {
                            $facet->weights->$i ??= 1.0;
                        }
                        foreach (array_diff(array_keys(get_object_vars($facet->weights)), $reqFacet) as $i) {
                            unset($facet->weights->$i);
                        }
                        $facet->defaultWeigth = 0.0;
                        continue;
                    } elseif ($facet->type === \acdhOeaw\arche\lib\SmartSearch::FACET_MATCH) {
                        $allowedProperties = $reqFacet;
                    } elseif ($facet->type === \acdhOeaw\arche\lib\SmartSearch::FACET_CONTINUOUS) {
                        if (!empty($reqFacet['min'])) {
                            $facet->min = (int) $reqFacet['min'];
                            $searchTerms[] = new \acdhOeaw\arche\lib\SearchTerm($facet->end, $facet->min, '>=', type: \acdhOeaw\arche\lib\SearchTerm::TYPE_NUMBER);
                        }
                        if (!empty($reqFacet['max'])) {
                            $facet->max = (int) $reqFacet['max'];
                            $searchTerms[] = new \acdhOeaw\arche\lib\SearchTerm($facet->start, $facet->max, '<=', type: \acdhOeaw\arche\lib\SearchTerm::TYPE_NUMBER);
                        }
                        if (isset($facet->min) || isset($facet->max)) {
                            foreach ($facet->start as $n => $i) {
                                $context[$i] = "|min|$fid|$n";
                            }
                            foreach ($facet->end as $n => $i) {
                                $context[$i] = "|max|$fid|$n";
                            }
                        }
                        $facet->distribution = (bool) ($this->reqFacets[$fid]['distribution'] ?? false);
                    } elseif ($facet->type === \acdhOeaw\arche\lib\SmartSearch::FACET_MAP) {
                        $spatialSearchTerm = new \acdhOeaw\arche\lib\SearchTerm(value: $reqFacet, operator: '&&');
                    } elseif (count($reqFacet) > 0) {
                        $type = $facet->type === \acdhOeaw\arche\lib\SmartSearch::FACET_OBJECT ? \acdhOeaw\arche\lib\SearchTerm::TYPE_RELATION : null;
                        $searchTerms[] = new \acdhOeaw\arche\lib\SearchTerm($fid, array_values($reqFacet), type: $type);
                    }
                }
            }
            unset($facet);

            $search = $this->repoDb->getSmartSearch();
            if (file_exists($this->sConfig->log)) {
                unlink($this->sConfig->log);
            }
            $log = new \zozlak\logging\Log($this->sConfig->log);
            $search->setQueryLog($log);
            $search->setExactWeight($this->sConfig->exactMatchWeight);
            $search->setLangWeight($this->sConfig->langMatchWeight);
            $search->setFacets($facets);
            $searchIn = $postParams['searchIn'] ?? [];
            $search->search($this->searchPhrase, $this->preferredLang, $this->searchInBinaries, $allowedProperties, $searchTerms, $spatialSearchTerm, $searchIn, $this->sConfig->matchesLimit);

            // display distribution of defined facets
            $facetsLang = !empty($postParams['labelsLang']) ? $postParams['labelsLang'] : (!empty($postParams['preferredLang']) ? $postParams['preferredLang'] : ($this->sConfig->prefLang ?? 'en'));
            $emptySearch = empty($this->searchPhrase) && count($searchTerms) === 0 && $spatialSearchTerm === null && count($searchIn) === 0;

            // obtain one page of results

            $page = ((int) ($postParams['page'] ?? 0));
            $resourcesPerPage = (int) 9999;
            $cfg = new \acdhOeaw\arche\lib\SearchConfig();
            $cfg->metadataMode = '0_99_1_0';
            $cfg->metadataParentProperty = (string) $this->schema->parent;
            $cfg->resourceProperties = array_keys($this->context);
            $cfg->relativesProperties = array_keys($relContext);
            $cfg->orderBy = [$this->sConfig->fallbackOrderBy];
            $triplesIterator = $search->getSearchPage($page, $resourcesPerPage, $cfg, $this->sConfig->prefLang ?? 'en');
            // parse triples into objects as ordinary
            $resources = [];
            $totalCount = 0;

            foreach ($triplesIterator as $triple) {
                if ($triple->property === (string) $this->schema->searchCount) {
                    $totalCount = (int) $triple->value;
                    continue;
                }
                $property = $this->context[$triple->property] ?? false;
                if ($property) {
                    $id = (string) $triple->id;
                    $resources[$id] ??= (object) ['id' => $triple->id];
                    if ($triple->type === 'REL') {
                        $tid = (string) $triple->value;
                        $resources[$tid] ??= (object) ['id' => (int) $tid];
                        $resources[$id]->{$property}[] = $resources[$tid];
                    } elseif ($triple->type === 'ID') {
                        if (strpos($triple->value, 'https://id.acdh.oeaw.ac.at/') !== false) {                            
                            $resources[$id]->{$property} = $triple->value;
                        }
                    } elseif (!empty($triple->lang)) {
                        $resources[$id]->{$property} = $triple->value;
                    } else {
                        $resources[$id]->{$property}[] = $triple->value;
                    }
                }
            }
            $resources = array_filter($resources, fn($x) => isset($x->matchOrder));
            $order = array_map(fn($x) => (int) $x->matchOrder[0], $resources);
            array_multisort($order, $resources);

            $facets = array_combine(array_map(fn($x) => $x->property ?? $x->type, $facets), $facets);
            foreach ($resources as $i) {
                $i->url = $this->baseUrl . $i->id;
                $i->matchProperty ??= [];
                $i->matchHiglight ??= array_fill(0, count($i->matchProperty), '');

                // turn continuous properties context into matchProperty values
                foreach (get_object_vars($i) as $p => $v) {
                    if (!str_starts_with($p, '|')) {
                        continue;
                    }
                    $v = array_map(fn($x) => (int) $x, $v); // extract year
                    list(, $vp, $fid, $n) = explode('|', $p);
                    $agg = $vp === 'min' ? min($v) : max($v);
                    $propProp = $vp === 'min' ? 'start' : 'end';
                    $rangeProp = $vp === 'min' ? 'min' : 'max';
                    $facet = $facets[$fid];
                    if (isset($facet->$rangeProp)) {
                        $i->matchProperty[] = $facet->$propProp[$n];
                        $i->matchHiglight[] = min($v);
                    }
                    unset($i->$p);
                }
            }

            $fullresponse = json_encode(
                $resources
                    , \JSON_UNESCAPED_SLASHES);

            return new Response($fullresponse);
        } catch (\Throwable $e) {
            return new Response("Error in search! " . $e->getMessage(), 404, ['Content-Type' => 'application/json']);
        }

        if ($object === false) {
            return new Response("There is no resource", 404, ['Content-Type' => 'application/json']);
        }
        return new Response(json_encode($result));
    }
}
