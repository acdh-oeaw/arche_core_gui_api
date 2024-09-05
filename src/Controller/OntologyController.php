<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use acdhOeaw\arche\lib\Schema;
use acdhOeaw\arche\lib\schema\Ontology;
use acdhOeaw\arche\lib\schema\PropertyDesc;

class OntologyController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {

    private $rootTableHelper;
    private $rootTableData = [];
    
    public function __construct() {
        parent::__construct();
        $this->rootTableHelper = new \Drupal\arche_core_gui_api\Helper\RootTableHelper();
    }

    public function getRootTable(string $lang): Response {
        $this->getRootTableData($lang);
        /*
        echo "<pre>";
        var_dump($this->rootTableData[0]);
        echo "</pre>";
        */
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
            $row['range'] = reset($p->range);
            $row['vocabulary'] = $p->vocabs;
            $row['automatedFill'] = $p->automatedFill;
            $row['defaultValue'] = $p->defaultValue;
            $row['langTag'] = $p->langTag;
            $row['comment'] = $p->comment;
            $this->rootTableData[] = $row;
        }
    }
}
