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

class MetadataController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {

    private $apiHelper;

    public function __construct() {
        parent::__construct();
        $this->apiHelper = new \Drupal\arche_core_gui_api\Helper\ApiHelper();
    }

    public function getSearchCoordinates(string $lang = "en"): JsonResponse {
        $result = [];

        $schema = $this->repoDb->getSchema();
        $scfg = new \acdhOeaw\arche\lib\SearchConfig();
        //$scfg->orderBy = ['^' . $schema->modificationDate];
        //$scfg->limit = $count;
        $scfg->metadataMode = 'resource';

        $scfg->resourceProperties = [
            $schema->label,
            'https://vocabs.acdh.oeaw.ac.at/schema#hasLatitude',
            'https://vocabs.acdh.oeaw.ac.at/schema#hasLongitude',
            'https://vocabs.acdh.oeaw.ac.at/schema#hasWKT',
            $schema->id
        ];

        $properties = [
            $schema->label => 'title',
            'https://vocabs.acdh.oeaw.ac.at/schema#hasLatitude' => 'lat',
            'https://vocabs.acdh.oeaw.ac.at/schema#hasLongitude' => 'lon',
            'https://vocabs.acdh.oeaw.ac.at/schema#hasWKT' => 'wkt'
        ];
        $scfg->relativesProperties = [];
        $pdoStmt = $this->repoDb->getPdoStatementBySearchTerms([new \acdhOeaw\arche\lib\SearchTerm(RC::RDF_TYPE, 'https://vocabs.acdh.oeaw.ac.at/schema#Place')], $scfg);

        $helper = new \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper();
        $result = $helper->extractRootView($pdoStmt, $scfg->resourceProperties, $properties, $lang);
        if (count((array) $result) == 0) {
            return new JsonResponse(array("There is no resource"), 404, ['Content-Type' => 'application/json']);
        }

        return new JsonResponse($result, 200, ['Content-Type' => 'application/json']);
    }

    public function getTopCollectionsDT(array $searchProps, string $lang = "en"): JsonResponse {

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

        $scfg->resourceProperties = [
            $schema->label,
            $schema->modificationDate,
            $schema->creationDate,
            $schema->ontology->description,
            $schema->ontology->version,
            $schema->id
        ];

        $properties = [
            $schema->label => 'title',
            $schema->modificationDate => 'modifyDate',
            $schema->creationDate => 'avDate',
            $schema->ontology->description => 'description',
            $schema->ontology->version => 'version',
            $schema->id => 'identifier'
        ];
        $scfg->relativesProperties = [];
        $pdoStmt = $this->repoDb->getPdoStatementBySearchTerms([new \acdhOeaw\arche\lib\SearchTerm(RC::RDF_TYPE, $schema->classes->topCollection)], $scfg);

        $helper = new \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper();
        $result = $helper->extractRootDTView($pdoStmt, $scfg->resourceProperties, $properties, $lang);

        if (count((array) $result) == 0) {
            return new JsonResponse(array("There is no resource"), 404, ['Content-Type' => 'application/json']);
        }

        $sumcount = $result['sumcount'];
        unset($result['sumcount']);

        $response = new JsonResponse();
        $response->setContent(
                json_encode(
                        array(
                            "aaData" => (array) $result,
                            "iTotalRecords" => (string) $sumcount,
                            "iTotalDisplayRecords" => (string) $sumcount,
                            "draw" => intval($searchProps['draw']),
                            "cols" => array_keys((array) $result[0]),
                            "order" => 'asc',
                            "orderby" => 1,
                            "childTitle" => "title"
                        )
                )
        );
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Provide the top collections, based on the count value
     *
     * @param int $count
     * @param string $lang
     * @return JsonResponse
     */
    public function getTopCollections(int $count, string $lang = "en"): JsonResponse {
        $result = [];
        $schema = $this->repoDb->getSchema();
        $scfg = new \acdhOeaw\arche\lib\SearchConfig();
        $scfg->orderBy = ['^' . $schema->modificationDate];
        $scfg->limit = $count;
        $scfg->metadataMode = 'resource';

        $scfg->resourceProperties = [
            $schema->label,
            $schema->modificationDate,
            $schema->creationDate,
            $schema->ontology->description,
            $schema->id
        ];

        $properties = [
            $schema->label => 'title',
            $schema->modificationDate => 'modifyDate',
            $schema->creationDate => 'avDate',
            $schema->ontology->description => 'description',
            $schema->id => 'identifier'
        ];
        $scfg->relativesProperties = [];
        $pdoStmt = $this->repoDb->getPdoStatementBySearchTerms([new \acdhOeaw\arche\lib\SearchTerm(RC::RDF_TYPE, $schema->classes->topCollection)], $scfg);

        $helper = new \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper();
        $result = $helper->extractRootView($pdoStmt, $scfg->resourceProperties, $properties, $lang);
        if (count((array) $result) == 0) {
            return new JsonResponse(array("There is no resource"), 404, ['Content-Type' => 'application/json']);
        }

        return new JsonResponse($result, 200, ['Content-Type' => 'application/json']);
    }

    public function getBreadcrumb(string $id, string $lang = "en") {
        $id = \Drupal\Component\Utility\Xss::filter(preg_replace('/[^0-9]/', '', $id));

        if (empty($id)) {
            return new JsonResponse(array("Please provide an id"), 404, ['Content-Type' => 'application/json']);
        }

        $result = [];

        try {
            $res = new \acdhOeaw\arche\lib\RepoResourceDb($id, $this->repoDb);
        } catch (\Exception $ex) {
            return [];
        }

        $schema = $this->repoDb->getSchema();
        $context = [
            $schema->label => 'title',
            $schema->parent => 'parent',
        ];

        $pdoStmt = $res->getMetadataStatement(
                '0_99_1_0',
                $schema->parent,
                array_keys($context),
                array_keys($context)
        );
        $result = [];

        $helper = new \Drupal\arche_core_gui_api\Helper\ArcheBreadcrumbHelper();
        $result = $helper->extractBreadcrumbView($pdoStmt, $id, $context, $lang);

        if (count((array) $result) == 0) {
            return new JsonResponse(array("There is no resource"), 404, ['Content-Type' => 'application/json']);
        }

        return new JsonResponse($result, 200, ['Content-Type' => 'application/json']);
    }

    public function getExpertData(string $id, string $lang = "en") {

        $id = \Drupal\Component\Utility\Xss::filter(preg_replace('/[^0-9]/', '', $id));

        if (empty($id)) {
            return new JsonResponse(array("Please provide an id"), 404, ['Content-Type' => 'application/json']);
        }

        $result = [];

        try {
            $res = new \acdhOeaw\arche\lib\RepoResourceDb($id, $this->repoDb);
        } catch (\Exception $ex) {
            return [];
        }

        $schema = $this->repoDb->getSchema();
        $contextResource = [
            $schema->label => 'title',
            $schema->parent => 'parent',
            'https://vocabs.acdh.oeaw.ac.at/schema#hasAuthor' => 'author',
            'https://vocabs.acdh.oeaw.ac.at/schema#hasCurator' => 'curator',
            'https://vocabs.acdh.oeaw.ac.at/schema#hasLicense' => 'license',
            'https://vocabs.acdh.oeaw.ac.at/schema#binarySize' => 'binarySize',
            \zozlak\RdfConstants::RDF_TYPE => 'class',
        ];
        $contextRelatives = [
            $schema->label => 'title',
            \zozlak\RdfConstants::RDF_TYPE => 'class',
            $schema->parent => 'parent',
        ];

        $pdoStmt = $res->getMetadataStatement(
                '0_99_1_0',
                $schema->parent,
                [],
                array_keys($contextRelatives)
        );
        $result = [];

        $helper = new \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper();
        $result = $helper->extractExpertView($pdoStmt, $id, $contextRelatives, $lang);

        if (count((array) $result) == 0) {
            return new JsonResponse(array("There is no resource"), 404, ['Content-Type' => 'application/json']);
        }

        return new JsonResponse(array("data" => $result), 200, ['Content-Type' => 'application/json']);
    }

}
