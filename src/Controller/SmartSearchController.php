<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use quickRdf\DataFactory as DF;
use termTemplates\PredicateTemplate as PT;

/**
 * Description of SmartSearchController
 *
 * @author nczirjak
 */
class SmartSearchController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {

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
    }

    private function setContext() {
        $this->context = [
            (string) $this->schema->label => 'title',
            (string) $this->schema->namespaces->rdfs . 'type' => 'class',
            (string) $this->schema->modificationDate => 'availableDate',
            (string) $this->schema->accessRestriction => 'accessRestriction',
            (string) $this->schema->accessRestrictionAgg => 'accessRestrictionSummary',
            (string) $this->schema->ontology->description => 'description',
            (string) $this->schema->searchFts => 'matchHiglight',
            (string) $this->schema->searchMatch => 'matchProperty',
            (string) $this->schema->searchWeight => 'matchWeight',
            (string) $this->schema->searchOrder => 'matchOrder',
            (string) $this->schema->parent => 'parent',
        ];
    }

    private function setBasicPropertys(array $postParams) {
        $this->sConfig = $this->config->smartSearch;
        //$this->schema = new \acdhOeaw\arche\lib\Schema($this->config->schema);
        $this->baseUrl = $this->repoDb->getBaseUrl();
        $this->preferredLang = $postParams['preferredLang'] ?? $this->sConfig->prefLang ?? 'en';
        $this->searchInBinaries = $postParams['includeBinaries'] ?? false;
        $this->searchPhrase = $postParams['q'] ?? "";
        $this->reqFacets = $postParams['facets'] ?? [];
    }

    /**
     * The first load or after reset search, which provides only facets
     * @param array $postParams
     * @return Response
     */
    private function initialSearch(array $postParams): Response {

        try {
            $this->setBasicPropertys($postParams);
            $search = $this->repoDb->getSmartSearch();
            $search->setFacets((array) $this->sConfig->facets);
            $useCache = ((bool) ($postParams['noCache'] ?? false));

            $response = [
                'facets' => $search->getInitialFacets($this->preferredLang, $this->sConfig->facetsCache, $useCache),
                'results' => [],
                'totalCount' => -1,
                'page' => 0,
                'pageSize' => 0,
                'maxCount' => -1
            ];
            return new Response(json_encode($response, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE), Response::HTTP_OK, ['Content-Type' => 'application/json']);
        } catch (\Throwable $e) {
            return new Response(t("Error in search!") . $e->getMessage(), 404, ['Content-Type' => 'application/json']);
        }

        if ($object === false) {
            return new Response(t("There is no resource"), 404, ['Content-Type' => 'application/json']);
        }


        return new Response(json_encode($result));
    }

    /**
     * The main search 
     * @param array $postParams
     * @return Response
     */
    public function search(array $postParams): Response {
        //if we do the empty search or reset filters then just load the facets
        if (isset($postParams['initialFacets'])) {
            return $this->initialSearch($postParams);
        }
        //we are generating the hash for the DB request store process
        $this->requestHash = md5(print_r($postParams, true));
        $msg = [];

        try {
            $this->setBasicPropertys($postParams);
            $useCache = !((bool) ($postParams['noCache'] ?? false));

            //if api call uses cache
            if ($useCache) {
                $cached = $this->getCachedData();
                //if we have already stored cache
                if ($cached !== null) {
                    return new Response($cached->response, Response::HTTP_OK, ['x-smartsearch-cache' => $cached->created, 'Content-Type' => 'application/json']);
                }
            }

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

            if ($emptySearch) {
                $facetStats = $search->getInitialFacets($facetsLang, $this->sConfig->facetsCache);
            } else {
                $facetStats = $search->getSearchFacets($facetsLang);
            }
            // obtain one page of results

            $page = ((int) ($postParams['page'] ?? 1));
            $resourcesPerPage = (int) ($postParams['pageSize'] ?? 20);
            $cfg = new \acdhOeaw\arche\lib\SearchConfig();
            $cfg->metadataMode = '0_99_1_0';
            $cfg->metadataParentProperty = (string) $this->schema->parent;
            $cfg->resourceProperties = array_keys($this->context);
            $cfg->relativesProperties = array_keys($relContext);
            $cfg->orderBy = [$this->sConfig->fallbackOrderBy];

            /**
             * #23810 - only topcollection search order by is availabledate
             */
            if (isset($postParams['facets']) && count($postParams['facets']) === 1) {
                $rdf = $this->config->schema->namespaces->rdfs . 'type';
                if (key_exists($rdf, $postParams['facets']) &&
                        $postParams['facets'][$rdf][0] === $this->config->schema->classes->topCollection) {
                    $cfg->orderBy = ['^' . $this->config->schema->creationDate];
                }
            }

            $triplesIterator = $search->getSearchPage($page, $resourcesPerPage, $cfg, $this->sConfig->prefLang ?? 'en');
            // parse triples into objects as ordinary
            $totalCount = 0;

            $resources = $this->triples2resourceObjects($triplesIterator, $totalCount);

            $resources = array_filter($resources, fn($x) => isset($x->matchOrder));
            $order = array_map(fn($x) => (int) $x->matchOrder[0], $resources);
            array_multisort($order, $resources);

            $facets = array_combine(array_map(fn($x) => $x->property ?? $x->type, $facets), $facets);

            $this->postprocessResources($resources);
            foreach ($resources as $i) {
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

            // put facets used for search first
            // uasort is stable in PHP >=8.0
            uasort($facetStats, fn($a, $b) => in_array($b->property, $facetsInUse) <=> in_array($a->property, $facetsInUse));

            // fetch data on $searchIn resources
            if (count($searchIn) > 0) {
                $searchInQuery = "SELECT * FROM (VALUES " . substr(str_repeat(', (?::bigint)', count($searchIn)), 2) . ") t (id)";
                $searchInQuery = $this->repoDb->getPdoStatementBySqlQuery($searchInQuery, $searchIn, $cfg);
                $searchInRes   = $this->triples2resourceObjects($searchInQuery->fetchAll(\PDO::FETCH_OBJ));
                $searchInRes   = array_values(array_filter($searchInRes, fn($x) => in_array($x, $searchIn), ARRAY_FILTER_USE_KEY));
                $this->postprocessResources($searchInRes);
            }

            // if map filter is used, include also location of all places in the spatial index
            $allPins = null;
            if ($spatialSearchTerm !== null) {
                $allPins = $search->getInitialFacets($this->preferredLang, $this->sConfig->facetsCache);
                $allPins = array_filter($allPins, fn($x) => $x->type === \acdhOeaw\arche\lib\SmartSearch::FACET_MAP);
                $allPins = (reset($allPins) ?: null)?->values;
            }

            // check for corner cases user should be warned about
            $messages = [];
            foreach ($this->sConfig->warnings ?? [] as $i) {
                $dataset = new \quickRdf\Dataset(false);
                $sbj = DF::namedNode('subject');

                foreach ($this->reqFacets as $property => $values) {
                    $values = is_array($values) ? $values : [$values];
                    $dataset->add(array_map(fn($x) => DF::Quad($sbj, DF::namedNode($property), DF::literal($x)), $values));
                }
                $outerMatch = true;
                foreach ($i->match as $matchGroup) {
                    $groupMatch = false;
                    foreach ($matchGroup as $property => $value) {
                        if (str_starts_with((string) $value, '!')) {
                            $groupMatch = $groupMatch || !$dataset->copy(new PT($property))->every(new PT($property, substr($value, 1)));
                        } else {
                            $groupMatch = $groupMatch || $dataset->any(new PT($property, $value));
                        }
                    }
                    $outerMatch = $outerMatch && $groupMatch;
                }
                if ($outerMatch) {
                    $msg = (array) $i->message;
                    $messages[] = [
                        'message' => $msg[$facetsLang] ?? $msg['en'] ?? reset($msg),
                        'class' => 'bg-' . $i->severity ?? 'bg-error',
                    ];
                }
            }

            if ($emptySearch) {
                $msg = (array) $this->sConfig->emptySearchMessage;
                $messages[] = [
                    'message' => $msg[$facetsLang] ?? $msg['en'] ?? reset($msg),
                    'class' => 'bg-info',
                ];
            }
            if (!$msg) {
                $msg['en'] = "";
            }

            $fullresponse = [
                'facets' => $facetStats,
                'results' => $resources,
                'totalCount' => $emptySearch ? -1 : $totalCount,
                'maxCount' => $emptySearch ? -1 : $this->sConfig->matchesLimit,
                'page' => $page,
                'messages' => $msg[$facetsLang] ?? $msg['en'] ?? reset($msg),
                'class' => 'bg-' . $i->severity ?? 'bg-error',
                'pageSize' => $resourcesPerPage,
                'searchIn' => $searchInRes ?? null,
                'allPins' => $allPins,
            ];
            $fullresponse = json_encode($fullresponse, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
            if ($useCache) {
                $this->cacheResults($fullresponse);
            }
            return new Response($fullresponse, Response::HTTP_OK, ['x-smartsearch-cache' => 'none', 'Content-Type' => 'application/json']);
        } catch (\Throwable $e) {
            return new Response("Error in search! " . $e->getMessage(), 404, ['Content-Type' => 'application/json']);
        }

        if ($object === false) {
            return new Response("There is no resource", 404, ['Content-Type' => 'application/json']);
        }
        return new Response(json_encode($result));
    }

    private function postprocessResources(array $resources): void {
        foreach ($resources as $i) {
            $i->url = $this->baseUrl . $i->id;
            $i->matchProperty ??= [];
            $i->matchHiglight ??= array_fill(0, count($i->matchProperty), '');
        }
    }

    private function triples2resourceObjects($triplesIterator, &$totalCount = null): array {
        $resources = [];
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
                } elseif (!empty($triple->lang)) {
                    $resources[$id]->{$property}[$triple->lang] = $triple->value;
                } else {
                    $resources[$id]->{$property}[] = $triple->value;
                }
            }
        }

        return $resources;
    }

    /**
     * Cache the search results
     * @param type $result
     * @return bool
     */
    private function cacheResults($result): bool {
        try {
            $query = $this->pdo->prepare("INSERT INTO gui.search_cache VALUES (?, ?, now(), now())");
            $query->execute([$this->requestHash, $result]);
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * get the existing cache
     * @return string
     */
    private function getCachedData(): \stdClass | null {
        try {
            $query = $this->pdo->prepare("DELETE FROM gui.search_cache WHERE now() - requested > ?::interval");
            $del = $query->execute([$this->sConfig->cacheTimeout]);

            $query = $this->pdo->prepare("UPDATE gui.search_cache SET requested = now() WHERE hash = ? RETURNING response, created");
            $query->execute([$this->requestHash]);
            $result = $query->fetchObject();
            if ($result !== false) {
                error_log("CACHED:");
                error_log(print_r($result, true));
                return $result;
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    /**
     * Fetch the date facets - deprecated
     * @return Response
     */
    public function dateFacets(): Response {
        try {
            return new Response(json_encode($this->config->smartSearch->dateFacets));
        } catch (Throwable $e) {
            return new Response("", 404, ['Content-Type' => 'application/json']);
        }
    }

    /**
     * Search input autocomplete
     * @param string $str
     * @return Response
     */
    public function autocomplete(string $str): Response {
        $response = [];
        $q = $str ?? '';
        if (!empty($q)) {
            $this->sConfig = $this->config->smartSearch;
            $limit = $this->sConfig->autocomplete?->count ?? 10;
            $maxLength = $this->sConfig->autocomplete?->maxLength ?? 50;

            $weights = array_filter($this->sConfig->facets, fn($x) => $x->type === 'matchProperty');
            $weights = reset($weights) ?: new \stdClass();
            $weights->weights ??= ['_' => 0.0];
            $weights->defaultWeight ??= 1.0;

            $query = new \zozlak\queryPart\QueryPart("WITH weights (property, weight) AS (VALUES ");
            foreach ($weights->weights as $k => $v) {
                $query->query .= "(?::text, ?::float),";
                $query->param[] = $k;
                $query->param[] = $v;
            }
            $query->query = substr($query->query, 0, -1) . ")";
            $query->query .= "
            SELECT DISTINCT value FROM (
                SELECT *
                FROM metadata LEFT JOIN weights USING (property)
                WHERE value ILIKE ? AND length(value) < ?
                ORDER BY coalesce(weight, ?) DESC, value
            ) t LIMIT ?
        ";
            $query->param[] = $q . '%';
            $query->param[] = $maxLength;
            $query->param[] = $weights->defaultWeight;
            $query->param[] = $limit;
            $pdoStmnt = $this->pdo->prepare($query->query);
            $pdoStmnt->execute($query->param);
            $response = $pdoStmnt->fetchAll(\PDO::FETCH_COLUMN);

            $limit -= count($response);
            if ($limit > 0) {
                $query->param[count($query->param) - 4] = '%' . $q . '%';
                $pdoStmnt->execute($query->param);
                $response = array_merge($response, $pdoStmnt->fetchAll(\PDO::FETCH_COLUMN));
            }
        }
        return new Response(json_encode($response));
    }

    private function updateSearchHistoryCookie() {
        
    }
}
