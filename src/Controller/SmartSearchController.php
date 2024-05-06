<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Description of SmartSearchController
 *
 * @author nczirjak
 */
class SmartSearchController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {

    private $aConfig;
    private $sConfig;
    private $context = [];
    private $schema;

    public function __construct() {
        parent::__construct();
        $this->aConfig = \acdhOeaw\arche\lib\Config::fromYaml(\Drupal::service('extension.list.module')->getPath('arche_core_gui') . '/config/config.yaml');
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

    /**
     * The first load or after reset search, which provides only facets
     * @param array $postParams
     * @return Response
     */
    private function initialSearch(array $postParams): Response {

        try {
            $this->sConfig = $this->aConfig->smartSearch;
            $this->schema = new \acdhOeaw\arche\lib\Schema($this->aConfig->schema);
            $baseUrl = $this->aConfig->rest->urlBase . $this->aConfig->rest->pathBase;
            $search = $this->repoDb->getSmartSearch();
            $search->setFacets((array) $this->sConfig->facets);

            $prefLang = $postParams['preferredLang'] ?? $this->sConfig->prefLang ?? 'en';

            return new Response(json_encode([
                        'facets' => $search->getInitialFacets($prefLang, $this->sConfig->facetsCache, false),
                        'results' => [],
                        'totalCount' => -1,
                        'page' => 0,
                        'pageSize' => 0,
                        'maxCount' => -1
                            ], \JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            return new Response("Error in search! " . $e->getMessage(), 404, ['Content-Type' => 'application/json']);
        }

        if ($object === false) {
            return new Response("There is no resource", 404, ['Content-Type' => 'application/json']);
        }


        return new Response(json_encode($result));
    }

    /**
     * The main search 
     * @param array $postParams
     * @return Response
     */
    public function search(array $postParams): Response {

        error_log("SEARCH API backend:::::");
        error_log(print_r($postParams, true));

        if (isset($postParams['initialFacets'])) {
            return $this->initialSearch($postParams);
        }
        try {
            $this->sConfig = $this->aConfig->smartSearch;
            $this->schema = new \acdhOeaw\arche\lib\Schema($this->aConfig->schema);
            $baseUrl = $this->aConfig->rest->urlBase . $this->aConfig->rest->pathBase;

            $this->setContext();
            // context needed to display search results

            $relContext = [
                (string) $this->schema->label => 'title',
                (string) $this->schema->parent => 'parent',
            ];

            // SEARCH CONFIG
            $preferredLang = $postParams['preferredLang'] ?? '';
            $searchInBinaries = $postParams['includeBinaries'] ?? false;
            $searchPhrase = $postParams['q'];
            $reqFacets = $postParams['facets'] ?? [];
            $facets = $this->sConfig->facets;

            if (!$postParams['linkNamedEntities'] ?? true) {
                $facets = array_filter($facets, fn($x) => $x->type !== 'linkProperty');
            }

            $searchTerms = [];
            $allowedProperties = [];
            foreach ($facets as $facet) {
                $fid = $facet->property ?? $facet->type;
                if (is_array($reqFacets[$fid] ?? null)) {
                    $reqFacet = $reqFacets[$fid];
                    if ($facet->type === 'linkProperty') {
                        foreach ($reqFacet as $i) {
                            $facet->weights->$i ??= 1.0;
                        }
                        foreach (array_diff(array_keys(get_object_vars($facet->weights)), $reqFacet) as $i) {
                            unset($facet->weights->$i);
                        }
                        $facet->defaultWeigth = 0.0;
                        continue;
                    } elseif ($facet->type === 'matchProperty') {
                        $allowedProperties = reset($reqFacet);
                    } elseif ($facet->type === 'continuous') {
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
                                $this->context[$i] = "|min|$fid|$n";
                            }
                            foreach ($facet->end as $n => $i) {
                                $this->context[$i] = "|max|$fid|$n";
                            }
                        }
                        $facet->distribution = (bool) ($reqFacets[$fid]['distribution'] ?? false);
                    } elseif (count($reqFacet) > 0) {
                        $type = $facet->type === 'object' ? \acdhOeaw\arche\lib\SearchTerm::TYPE_RELATION : null;
                        $searchTerms[] = new \acdhOeaw\arche\lib\SearchTerm($fid, array_values($reqFacet), type: $type);
                    }
                }
            }
            unset($facet);

            $spatialSearchTerm = null;
            if (isset($reqFacets['bbox'])) {
                $spatialSearchTerm = new \acdhOeaw\arche\lib\SearchTerm(value: $reqFacets['bbox'], operator: '&&');
            }

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
            $search->search($searchPhrase, $preferredLang, $searchInBinaries, $allowedProperties, $searchTerms, $spatialSearchTerm, $searchIn, $this->sConfig->matchesLimit);

            // display distribution of defined facets
            $facetsLang = !empty($postParams['labelsLang']) ? $postParams['labelsLang'] : (!empty($postParams['preferredLang']) ? $postParams['preferredLang'] : ($this->sConfig->prefLang ?? 'en'));
            $emptySearch = empty($searchPhrase) && count($searchTerms) === 0 && $spatialSearchTerm === null && count($searchIn) === 0;
            if ($emptySearch) {
                $facetStats = $search->getInitialFacets($facetsLang, $this->sConfig->facetsCache);
            } else {
                $facetStats = $search->getSearchFacets($facetsLang);
            }
            // obtain one page of results

            $page = ((int) ($postParams['page'] ?? 0));
            $resourcesPerPage = (int) ($postParams['pageSize'] ?? 20);
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
                    } elseif (!empty($triple->lang)) {
                        $resources[$id]->{$property}[$triple->lang] = $triple->value;
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
                $i->url = $baseUrl . $i->id;
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

            return new Response(json_encode([
                        'facets' => $facetStats,
                        'results' => $resources,
                        'totalCount' => $emptySearch ? -1 : $totalCount,
                        'maxCount' => $emptySearch ? -1 : $sConfig->matchesLimit,
                        'page' => $page,
                        'pageSize' => $resourcesPerPage
                            ], \JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {

            return new Response("Error in search! " . $e->getMessage(), 404, ['Content-Type' => 'application/json']);
        }

        if ($object === false) {
            return new Response("There is no resource", 404, ['Content-Type' => 'application/json']);
        }
        return new Response(json_encode($result));
    }

    /**
     * Fetch the date facets - deprecated
     * @return Response
     */
    public function dateFacets(): Response {
        try {
            return new Response(json_encode($this->aConfig->smartSearch->dateFacets));
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
            $this->sConfig = $this->aConfig->smartSearch;
            $limit = $this->sConfig->autocomplete?->count ?? 10;
            $maxLength = $sConfig->autocomplete?->maxLength ?? 50;
            
            $pdo = new \PDO($this->aConfig->dbConnStr->guest);

            $weights = array_filter($this->sConfig->facets, fn($x) => $x->type === 'matchProperty');
            $weights = reset($weights) ?: new stdClass();
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
            $pdoStmnt = $pdo->prepare($query->query);
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
}
