<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Description of SmartSearchController
 *
 * @author nczirjak
 */
class SmartSearchController extends \Drupal\Core\Controller\ControllerBase {

    private $config;
    private $sConfig;
    private $context = [];
    private $schema;

    public function __construct() {
        $this->config = \acdhOeaw\arche\lib\Config::fromYaml(\Drupal::service('extension.list.module')->getPath('arche_core_gui') . '/config/config.yaml');
    }

    private function setContext() {
        $this->context = [
            $this->schema->label => 'title',
            $this->config->schema->namespaces->rdfs . 'type' => 'class',
            $this->schema->modificationDate => 'availableDate',
            $this->schema->searchFts => 'matchHiglight',
            $this->schema->searchMatch => 'matchProperty',
            $this->schema->searchWeight => 'matchWeight',
            $this->schema->searchOrder => 'matchOrder',
            $this->schema->parent => 'parent',
        ];
    }

    public function search(array $post): Response {

        error_log(print_r($post, true));
        $postParams = $post;
        
        try {
            $this->sConfig = $this->config->smartSearch;
            $this->schema = new \acdhOeaw\arche\lib\Schema($this->config->schema);
            $baseUrl = $this->config->rest->urlBase . $this->config->rest->pathBase;

            $this->setContext();
            // context needed to display search results

            $relContext = [
                $this->schema->label => 'title',
                $this->schema->parent => 'parent',
            ];
   
            // SEARCH CONFIG
            $namedEntityWeights = [];
            $namedEntityClasses = [];
            $namedEntityWeightDefault = 1.0;
            if ($postParams['linkNamedEntities'] ?? true) {
                $namedEntityWeights = (array) $this->sConfig->namedEntities->weights;
                $namedEntityClasses = $this->sConfig->namedEntities->classes;
                $namedEntityWeightDefault = $this->sConfig->namedEntities->defaultWeight;
            }
            $preferredLang = $postParams['preferredLang'] ?? '';
            $searchInBinaries = $postParams['includeBinaries'] ?? false;
            $searchPhrase = $postParams['q'];

            $reqFacets = $postParams['facets'] ?? [];
            $allowedProperties = $reqFacets['property'] ?? [];
            if (is_array($reqFacets['linkProperty'] ?? false)) {
                foreach ($reqFacets['linkProperty'] as $i) {
                    $namedEntityWeights[$i] ??= 1.0;
                }
                foreach (array_diff(array_keys($namedEntityWeights), $reqFacets['linkProperty']) as $i) {
                    unset($namedEntityWeights[$i]);
                }
                $namedEntityWeightDefault = 0.0;
            }

            $searchTerms = [];

            foreach ($this->sConfig->facets as $facet) {
                $fid = $facet->property;
                if (is_array($reqFacets[$fid] ?? null)) {
                    if (!empty($reqFacets[$fid]['min'])) {
                        $searchTerms[] = new \acdhOeaw\arche\lib\SearchTerm($fid, $reqFacets[$fid]['min'], '>=');
                    }
                    if (!empty($reqFacets[$fid]['max'])) {
                        $value = $reqFacets[$fid]['max'];
                        if ($facet->type === 'date') {
                            $value = substr($value, 0, 10) . 'T23:59:59';
                        }
                        $searchTerms[] = new \acdhOeaw\arche\lib\SearchTerm($fid, $value, '<=');
                    }
                    if (is_array($reqFacets[$fid] ?? false) && isset($reqFacets[$fid][0])) {
                        $type = $facet->type === 'object' ? \acdhOeaw\arche\lib\SearchTerm::TYPE_RELATION : null;
                        $searchTerms[] = new \acdhOeaw\arche\lib\SearchTerm($fid, $reqFacets[$fid], type: $type);
                    }
                }
            }

            
            $facets = $this->sConfig->facets;
            $dateFacets = $this->sConfig->dateFacets;
            foreach ($dateFacets as $fid => &$facet) {
                if (is_array($reqFacets[$fid] ?? null)) {
                    // Rounding to full years!
                    if (!empty($reqFacets[$fid]['min'])) {
                        $facet->min = (int) $reqFacets[$fid]['min'];
                        $searchTerms[] = new \acdhOeaw\arche\lib\SearchTerm($facet->end, $facet->min, '>=', type: \acdhOeaw\arche\lib\SearchTerm::TYPE_NUMBER);
                    }
                    if (!empty($reqFacets[$fid]['max'])) {
                        $facet->max = (int) $reqFacets[$fid]['max'];
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
                }
                $facet->distribution ??= false;
                $facet->precision = 0;
                $facet->start = is_array($facet->start) ? $facet->start : [$facet->start];
                $facet->end = is_array($facet->end) ? $facet->end : [$facet->end];
            }
            
            
            
            unset($facet);
            $dateFacets = array_filter((array) $dateFacets, fn($x) => $x->distribution);
            $spatialSearchTerm = null;
            if (isset($reqFacets['bbox'])) {
                $spatialSearchTerm = new \acdhOeaw\arche\lib\SearchTerm(value: $reqFacets['bbox'], operator: '&&');
            }

            $pswd = "";
            if (file_exists("/home/www-data/.pgpass")) {
                foreach (explode("\n", file_get_contents("/home/www-data/.pgpass")) as $i) {
                    $i = explode(':', $i);
                    if (isset($i[3]) && $i[3] == 'gui' && isset($i[4])) {
                        $pswd = $i[4];
                        break;
                    }
                }
            }
            
            //model !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            $pdo = new \PDO('pgsql: host=127.0.0.1 dbname=www-data user=gui password=' . $pswd);
            // Put everything together
            $search = new \acdhOeaw\arche\lib\SmartSearch($pdo, $this->schema, $baseUrl);
            $search->setPropertyWeights((array) $this->sConfig->property->weights);
            $search->setWeightedFacets($facets);
            $search->setRangeFacets($dateFacets);
            $search->setNamedEntityWeights($namedEntityWeights, $namedEntityWeightDefault);
            $search->setNamedEntityFilter($namedEntityClasses);
           
            $search->search($searchPhrase, $preferredLang, $searchInBinaries, $allowedProperties, $searchTerms, $spatialSearchTerm, $postParams['searchIn'] ?? []);

            // display distribution of defined facets
            $facetLabels = array_combine(
                    array_map(fn($x) => $x->property, $this->sConfig->facets),
                    array_map(fn($x) => $x->label, $this->sConfig->facets)
            );
            $facetLabels['property'] = $this->sConfig->property->label;
            $facetsLang = !empty($postParams['labelsLang']) ? $postParams['labelsLang'] : (!empty($postParams['preferredLang']) ? $postParams['preferredLang'] : ($this->sConfig->prefLang ?? 'en'));
            $facets = [];
    
            foreach ($search->getSearchFacets($facetsLang) as $prop => $i) {
                $i['property'] = $prop;
                $i['label'] = $facetLabels[$prop] ?? $prop;
                $facets[] = $i;
            }
         
            // obtain one page of results
            $page = (int) ($postParams['page'] ?? 0);
            $resourcesPerPage = (int) ($postParams['pageSize'] ?? 20);
            $cfg = new \acdhOeaw\arche\lib\SearchConfig();
            $cfg->metadataMode = '0_99_0_0';
            $cfg->metadataParentProperty = $this->schema->parent;
            $cfg->resourceProperties = array_keys($this->context);
            $cfg->relativesProperties = array_keys($relContext);
            $triplesIterator = $search->getSearchPage($page, $resourcesPerPage, $cfg);
            // parse triples into objects as ordinary
            $resources = [];
            $totalCount = 0;
            foreach ($triplesIterator as $triple) {
                if ($triple->property === $this->schema->searchCount) {
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
            
        
            foreach ($resources as $i) {
                $i->url = $baseUrl . $i->id;
                $i->matchProperty ??= [];
                $i->matchHiglight ??= array_fill(0, count($i->matchProperty), '');

                // turn date properties context into matchProperty values
                foreach (get_object_vars($i) as $p => $v) {
                    if (!str_starts_with($p, '|')) {
                        continue;
                    }
                    $v = array_map(fn($x) => (int) $x, $v);
                    list(, $vp, $fid, $n) = explode('|', $p);
                    $facet = $dateFacets[$fid];
                    if ($vp === 'min' && min($v) >= $facet->min) {
                        $i->matchProperty[] = $facet->start[$n];
                        $i->matchHiglight[] = min($v);
                    } elseif ($vp === 'max' && max($v) <= $facet->max) {
                        $i->matchProperty[] = $facet->end[$n];
                        $i->matchHiglight[] = max($v);
                    }
                    unset($i->$p);
                }
            }

            return new Response(json_encode([
                        'facets' => $facets,
                        'results' => $resources,
                        'totalCount' => $totalCount,
                        'page' => $page,
                        'pageSize' => $resourcesPerPage,
                                    ], \JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            return new Response(array("Error in search! " . $e->getMessage()), 404, ['Content-Type' => 'application/json']);
        }

     
        if ($object === false) {
            return new Response(array("There is no resource"), 404, ['Content-Type' => 'application/json']);
        }
        return new Response(json_encode($result));
    }

    public function dateFacets(): Response {
        try {
            return new Response(json_encode($this->config->smartSearch->dateFacets));
        } catch (Throwable $e) {
            return new Response(array(), 404, ['Content-Type' => 'application/json']);
        }
    }

}
