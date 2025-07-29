<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use acdhOeaw\arche\lib\RepoDb;
use acdhOeaw\arche\lib\SearchConfig;
use acdhOeaw\arche\lib\SearchTerm;
use zozlak\RdfConstants as RC;

class MetadataController extends \Drupal\arche_core_gui\Controller\ArcheBaseController {

    protected $helper;
    private $acceptedFormatsHelper;

    public function __construct() {
        parent::__construct();
        $this->helper = new \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper();
        $this->acceptedFormatsHelper = new \Drupal\arche_core_gui_api\Helper\AcceptedFormatsHelper();
    }

    /**
     * MAP search coorindates
     * @param string $lang
     * @return JsonResponse
     */
    public function getSearchCoordinates(string $lang = "en"): JsonResponse {
        $result = [];

        $schema = $this->repoDb->getSchema();
        $scfg = new \acdhOeaw\arche\lib\SearchConfig();
        //$scfg->orderBy = ['^' . $schema->modificationDate];
        //$scfg->limit = $count;
        $scfg->metadataMode = 'resource';

        $scfg->resourceProperties = [
            (string) $schema->label,
            (string) 'https://vocabs.acdh.oeaw.ac.at/schema#hasLatitude',
            (string) 'https://vocabs.acdh.oeaw.ac.at/schema#hasLongitude',
            (string) 'https://vocabs.acdh.oeaw.ac.at/schema#hasWKT',
            (string) $schema->id
        ];

        $properties = [
            (string) $schema->label => 'title',
            (string) 'https://vocabs.acdh.oeaw.ac.at/schema#hasLatitude' => 'lat',
            (string) 'https://vocabs.acdh.oeaw.ac.at/schema#hasLongitude' => 'lon',
            (string) 'https://vocabs.acdh.oeaw.ac.at/schema#hasWKT' => 'wkt'
        ];
        $scfg->relativesProperties = [];
        $pdoStmt = $this->repoDb->getPdoStatementBySearchTerms([new \acdhOeaw\arche\lib\SearchTerm(RC::RDF_TYPE, 'https://vocabs.acdh.oeaw.ac.at/schema#Place')], $scfg);

        $result = $this->helper->extractRootView($pdoStmt, $scfg->resourceProperties, $properties, $lang);
        if (count((array) $result) == 0) {
            return new JsonResponse(array(t("There is no resource")), 404, ['Content-Type' => 'application/json']);
        }

        return new JsonResponse($result, 200, ['Content-Type' => 'application/json']);
    }

    /**
     * Datatable top collections api endpoint
     * @param array $searchProps
     * @param string $lang
     * @return JsonResponse
     */
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
            (string) $schema->label,
            (string) $schema->modificationDate,
            (string) $schema->creationDate,
            (string) $schema->ontology->description,
            (string) $schema->ontology->version,
            (string) $schema->id
        ];

        $properties = [
            (string) $schema->label => 'title',
            (string) $schema->modificationDate => 'modifyDate',
            (string) $schema->creationDate => 'avDate',
            (string) $schema->ontology->description => 'description',
            (string) $schema->ontology->version => 'version',
            (string) $schema->id => 'identifier'
        ];
        $scfg->relativesProperties = [];
        $pdoStmt = $this->repoDb->getPdoStatementBySearchTerms([new \acdhOeaw\arche\lib\SearchTerm(RC::RDF_TYPE, $schema->classes->topCollection)], $scfg);

        $result = $this->helper->extractRootDTView($pdoStmt, $scfg->resourceProperties, $properties, $lang);

        if (count((array) $result) == 0) {
            return new JsonResponse(array(t("There is no resource")), 404, ['Content-Type' => 'application/json']);
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
            (string) $schema->label,
            (string) $schema->modificationDate,
            (string) $schema->creationDate,
            (string) $schema->ontology->description,
            (string) $schema->id
        ];

        $properties = [
            (string) $schema->label => 'title',
            (string) $schema->modificationDate => 'modifyDate',
            (string) $schema->creationDate => 'avDate',
            (string) $schema->ontology->description => 'description',
            (string) $schema->id => 'identifier'
        ];
        $scfg->relativesProperties = [];
        $pdoStmt = $this->repoDb->getPdoStatementBySearchTerms([new \acdhOeaw\arche\lib\SearchTerm(RC::RDF_TYPE, $schema->classes->topCollection)], $scfg);

        $result = $this->helper->extractRootView($pdoStmt, $scfg->resourceProperties, $properties, $lang);
        if (count((array) $result) == 0) {
            return new JsonResponse(array(t("There is no resource")), 404, ['Content-Type' => 'application/json']);
        }

        return new JsonResponse($result, 200, ['Content-Type' => 'application/json']);
    }

    /**
     * Get the actual resource breadcrumb
     * @param string $id
     * @param string $lang
     * @return JsonResponse
     */
    public function getBreadcrumb(string $id, string $lang = "en") {
        $id = \Drupal\Component\Utility\Xss::filter(preg_replace('/[^0-9]/', '', $id));

        if (empty($id)) {
            return new JsonResponse(array(t("Please provide an id")), 404, ['Content-Type' => 'application/json']);
        }

        $result = [];
        try {
            $res = new \acdhOeaw\arche\lib\RepoResourceDb($id, $this->repoDb);
        } catch (\Exception $ex) {
            return [];
        }

        $schema = $this->repoDb->getSchema();
        $context = [
            (string) $schema->label => 'title',
            (string) $schema->parent => 'parent',
        ];

        $pdoStmt = $res->getMetadataStatement(
                '0_99_1_0',
                $schema->parent,
                array_keys($context),
                array_keys($context)
        );

        $breadcrumbHelper = new \Drupal\arche_core_gui_api\Helper\ArcheBreadcrumbHelper();
        $result = $breadcrumbHelper->extractBreadcrumbView($pdoStmt, $id, $context, $lang);

        if (count((array) $result) == 0) {
            return new JsonResponse(array(t("There is no resource")), 404, ['Content-Type' => 'application/json']);
        }

        return new JsonResponse($result, 200, ['Content-Type' => 'application/json']);
    }

    /**
     * Metadata view expert DT content
     * @param string $id
     * @param string $lang
     * @return JsonResponse
     */
    public function getExpertData(string $id, string $lang = "en") {

        $id = \Drupal\Component\Utility\Xss::filter(preg_replace('/[^0-9]/', '', $id));

        if (empty($id)) {
            return new JsonResponse(array(t("Please provide an id")), 404, ['Content-Type' => 'application/json']);
        }

        $result = [];

        try {
            $res = new \acdhOeaw\arche\lib\RepoResourceDb($id, $this->repoDb);
        } catch (\Exception $ex) {
            return [];
        }

        $schema = $this->repoDb->getSchema();
        $contextResource = [
            (string) $schema->label => 'title',
            (string) $schema->parent => 'parent',
            (string) 'https://vocabs.acdh.oeaw.ac.at/schema#hasAuthor' => 'author',
            (string) 'https://vocabs.acdh.oeaw.ac.at/schema#hasCurator' => 'curator',
            (string) 'https://vocabs.acdh.oeaw.ac.at/schema#hasLicense' => 'license',
            (string) 'https://vocabs.acdh.oeaw.ac.at/schema#binarySize' => 'binarySize',
            (string) 'https://vocabs.acdh.oeaw.ac.at/schema#hasIdentifier' => 'identifiers',
            (string) \zozlak\RdfConstants::RDF_TYPE => 'class',
        ];
        $contextRelatives = [
            (string) $schema->label => 'title',
            (string) \zozlak\RdfConstants::RDF_TYPE => 'class',
            (string) $schema->parent => 'parent',
            (string) 'https://vocabs.acdh.oeaw.ac.at/schema#hasIdentifier' => 'identifiers',
        ];

        $pdoStmt = $res->getMetadataStatement(
                '0_99_1_0',
                $schema->parent,
                [],
                array_keys($contextRelatives)
        );
        $result = [];
        $result = $this->helper->extractExpertView($pdoStmt, $id, $contextRelatives, $lang);

        if (count((array) $result) == 0) {
            return new JsonResponse(array(t("There is no resource")), 404, ['Content-Type' => 'application/json']);
        }

        return new JsonResponse(array("data" => $result), 200, ['Content-Type' => 'application/json']);
    }

    /**
     * DOwnload the 3d Object file and return the url.
     * @param string $identifier
     * @return JsonResponse
     */
    public function get3dUrl(string $identifier): JsonResponse {
        $tmpDir = \Drupal::service('file_system')->realpath(\Drupal::config('system.file')->get('default_scheme') . "://");

        //download the file
        $identifier = $this->repoDb->getBaseUrl() . $identifier;
        //$identifier = 'https://arche-curation.acdh-dev.oeaw.ac.at/api/3110280';
        $obj = new \Drupal\arche_core_gui\Object\ThreeDObject();
        $fileObj = $obj->downloadFile($identifier, $tmpDir);
        $fileUrl = ($fileObj['result']) ? $fileObj['result'] : "";

        if (empty($fileUrl)) {
            return new JsonResponse("No binary", 404, ['Content-Type' => 'application/json']);
        }
        return new JsonResponse($fileUrl, 200, ['Content-Type' => 'application/json']);
    }

    /**
     * Get just the coordinates for the detail view map box
     * @param string $identifier
     * @return JsonResponse
     */
    public function getCoordinates(string $identifier): JsonResponse {

        $id = \Drupal\Component\Utility\Xss::filter(preg_replace('/[^0-9]/', '', $identifier));
        $lang = "en";
        if (empty($id)) {
            return new JsonResponse(array(t("Please provide an id")), 404, ['Content-Type' => 'application/json']);
        }

        $result = [];

        try {
            $res = new \acdhOeaw\arche\lib\RepoResourceDb($id, $this->repoDb);
        } catch (\Exception $ex) {
            return [];
        }

        $schema = $this->repoDb->getSchema();

        $contextResource = [
            (string) $schema->label => 'title',
            (string) 'https://vocabs.acdh.oeaw.ac.at/schema#hasLatitude' => 'lat',
            (string) 'https://vocabs.acdh.oeaw.ac.at/schema#hasLongitude' => 'lon',
            (string) 'https://vocabs.acdh.oeaw.ac.at/schema#hasWKT' => 'wkt'
        ];
        $contextRelatives = [
            (string) $schema->label => 'title'
        ];

        $pdoStmt = $res->getMetadataStatement(
                'resource',
                $schema->parent,
                array_keys($contextResource),
                array_keys($contextRelatives)
        );

        $result = [];
        $result = $this->helper->extractExpertView($pdoStmt, $id, $contextRelatives, $lang);

        if (count((array) $result) == 0) {
            return new JsonResponse(array(t("There is no resource")), 404, ['Content-Type' => 'application/json']);
        }

        return new JsonResponse(array($result), 200, ['Content-Type' => 'application/json']);
    }

    public function getAcceptedFormats(string $lang): Response {
        $formats = \acdhOeaw\ArcheFileFormats::getAll();
        $table = [];
        foreach ($formats as $i) {
            if (empty($i->ARCHE_conformance ?? '') || empty($i->ARCHE_category ?? '')) {
                continue;
            }
            $group = explode('/', $i->ARCHE_category);
            $group = end($group);
            $table[$group][] = [implode(', ', $i->extensions), $i->name, $i->ARCHE_conformance];
        }
        foreach ($table as &$v) {
            usort($v, fn($x, $y) => $x[0] <=> $y[0]);
        }
        unset($v);
      
        if (count($table) === 0) {
            return new \Symfony\Component\HttpFoundation\Response("There is no data", 404, ['Content-Type' => 'application/json']);
        }

        $html = "";
        $html = $this->acceptedFormatsHelper->createHtml($table, $lang);

        if (empty($html)) {
            return new \Symfony\Component\HttpFoundation\Response("There is no data", 404, ['Content-Type' => 'application/json']);
        }
        $response = new \Symfony\Component\HttpFoundation\Response();
        $response->setContent($html);
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'text/html');
        return $response;
    }
}
