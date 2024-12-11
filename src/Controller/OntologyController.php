<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use acdhOeaw\arche\lib\Schema;
use acdhOeaw\arche\lib\schema\Ontology;
use acdhOeaw\arche\lib\schema\PropertyDesc;

class OntologyController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {

    private $rootTableHelper;
    private $ontologyJsHelper;
    private $rootTableData = [];

    private static $actors_involved = array(
        'hasPrincipalInvestigator', 'hasContact',
        'hasCreator', 'hasAuthor',
        'hasEditor', 'hasContributor',
        'hasFunder', 'hasLicensor',
        'hasMetadataCreator', 'hasDigitisingAgent'
    );
    private static $coverage = array(
        'hasRelatedDiscipline', 'hasCoverage',
        'hasActor', 'hasSpatialCoverage',
        'hasSubject', 'hasTemporalCoverage',
        'hasTemporalCoverageIdentifier', 'hasCoverageEndDate',
        'hasCoverageStartDate'
    );
    private static $right_access = array(
        'hasOwner', 'hasRightsHolder',
        'hasLicense', 'hasAccessRestriction',
        'hasRestrictionRole', 'hasLicenseSummary',
        'hasAccessRestrictionSummary'
    );
    private static $dates = array(
        'hasDate', 'hasStartDate',
        'hasEndDate', 'hasCreatedDate',
        'hasCreatedStartDate', 'hasCreatedEndDate',
        'hasCollectedStartDate', 'hasCollectedEndDate',
        'hasCreatedStartDateOriginal', 'hasCreatedEndDateOriginal'
    );
    private static $relations_other_projects = array(
        'relation', 'hasRelatedProject',
        'hasRelatedCollection', 'continues',
        'isContinuedBy', 'documents',
        'isDocumentedBy', 'hasDerivedPublication',
        'hasMetadata', 'isMetadataFor',
        'hasSource', 'isSourceOf',
        'isNewVersionOf', 'isPreviousVersionOf',
        'hasPart', 'isPartOf',
        'hasTitleImage', 'isTitleImageOf',
        'hasVersionInfo'
    );
    private static $curation = array(
        'hasDepositor', 'hasAvailableDate',
        'hasPid', 'hasNumberOfItems',
        'hasBinarySize', 'hasFormat',
        'hasLocationPath', 'hasLandingPage',
        'hasCurator', 'hasHosting',
        'hasSubmissionDate', 'hasAcceptedDate',
        'hasTransferDate', 'hasTransferMethod',
        'hasUpdateDate'
    );
    
    public function __construct() {
        parent::__construct();
        $this->rootTableHelper = new \Drupal\arche_core_gui_api\Helper\RootTableHelper();
        $this->ontologyJsHelper = new \Drupal\arche_core_gui_api\Helper\OntologyJsHelper();
    }
    
    private function isCustomClass(string $type): string
    {
        if (in_array($type, self::$actors_involved)) {
            return 'actors_involved';
        }
        if (in_array($type, self::$coverage)) {
            return 'coverage';
        }
        if (in_array($type, self::$right_access)) {
            return 'right_access';
        }
        if (in_array($type, self::$dates)) {
            return 'dates';
        }
        if (in_array($type, self::$relations_other_projects)) {
            return 'relations_other_projects';
        }
        if (in_array($type, self::$curation)) {
            return 'curation';
        }
        return'basic';
    }

    /**
     * Root table view function
     * @param string $lang
     * @return Response
     */
    public function getRootTable(string $lang): Response {
        $this->getRootTableData($lang);
        $html = "";
        $html = $this->rootTableHelper->createHtml($this->rootTableData, $lang);

        if (empty($html)) {
            return new \Symfony\Component\HttpFoundation\Response("There is no resource", 404, ['Content-Type' => 'application/json']);
        }
        $response = new \Symfony\Component\HttpFoundation\Response();
        $response->setContent($html);
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'text/html');
        return $response;
    }

    /**
     * Generate the root table data
     * @param string $lang
     * @param PropertyDesc $a
     * @param PropertyDesc $b
     * @return void
     */
    private function getRootTableData(string $lang): void {
        $classes = ['Project', 'TopCollection', 'Collection', 'Resource', 'Metadata', 'Publication', 'Place', 'Organisation', 'Person'];
        $classes = array_combine($classes, array_map(fn($x) => $this->ontology->getClass($this->schema->namespaces->ontology . $x, $classes), $classes));
        $properties = $this->ontology->getProperties();
        usort($properties, fn(PropertyDesc $a, PropertyDesc $b) => $a->ordering <=> $b->ordering);
        $this->rootTableData = [];
        foreach ($properties as $p) {
            $row = ['property' => $p->uri];
            foreach ($classes as $class => $classDef) {
                $pc = (array) ($classDef->properties[$p->uri] ?? ['min' => 'x', 'max' => 'x', 'recommendedClass' => false]);
                $pc['min'] ??= $pc['recommendedClass'] ? '0R' : '0';
                $pc['max'] ??= 'n';
                $row[$class] = $pc['min'] === $pc['max'] ? $pc['min'] : $pc['min'] . '-' . $pc['max'];
            }
            $row['order'] = $p->ordering;
            $row['range'] = $p->range;
            $row['vocabulary'] = $p->vocabs;
            $row['automatedFill'] = $p->automatedFill;
            $row['defaultValue'] = $p->defaultValue;
            $row['langTag'] = $p->langTag;
            $row['comment'] = $p->comment;
            $this->rootTableData[] = $row;
        }
    }

    public function getOntologyJs(string $lang): Response {
        $data = $this->getOntologyJsData($lang);
        
        if(count($data) === 0) {
            return new \Symfony\Component\HttpFoundation\Response("There is no resource", 404, ['Content-Type' => 'application/json']);
        }
        
        $html = "";
        $html = $this->ontologyJsHelper->createHtml($data, $lang);

        if (empty($html)) {
            return new \Symfony\Component\HttpFoundation\Response("There is no resource", 404, ['Content-Type' => 'application/json']);
        }
        $response = new \Symfony\Component\HttpFoundation\Response();
        $response->setContent($html);
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'text/html');
        return $response;
    }

    private function getOntologyJsData(string $lang): array {
        $result = [];

        $classes = ['Project', 'Collection', 'Resource'];
        $classes = array_combine($classes, array_map(fn($x) => $this->ontology->getClass($this->schema->namespaces->ontology . $x, $classes), $classes));
        $properties = $this->ontology->getProperties();
        usort($properties, fn(PropertyDesc $a, PropertyDesc $b) => $a->ordering <=> $b->ordering);
        $this->rootTableData = [];
        foreach ($properties as $p) {
            
            $propClass = $this->isCustomClass(str_replace("https://vocabs.acdh.oeaw.ac.at/schema#", "", $p->uri));
           
            $row = ['property' => $p->uri];
            foreach ($classes as $class => $classDef) {
                $pc = (array) ($classDef->properties[$p->uri] ?? ['min' => 'x', 'max' => 'x', 'recommendedClass' => false]);
                $pc['min'] ??= $pc['recommendedClass'] ? '0R' : '0';
                $pc['max'] ??= 'n';
                $row[$class] = $pc['min'] === $pc['max'] ? $pc['min'] : $pc['min'] . '-' . $pc['max'];
            }
            $row['order'] = $p->ordering;
            if(isset($p->label[$lang])) {
                $row['label'] = $p->label[$lang];
            } else {
                $row['label'] = reset($p->label);
            }
            $result[$propClass][] = $row;
        }
        return $result;
    }
}
