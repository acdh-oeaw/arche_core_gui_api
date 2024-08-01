<?php

namespace Drupal\arche_core_gui_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use acdhOeaw\arche\lib\Config;
use acdhOeaw\arche\lib\RepoDb;
use acdhOeaw\arche\lib\schema\Ontology;

/**
 * Description of ArcheBaseController
 *
 * @author nczirjak
 */
class ArcheBaseController extends ControllerBase {
    protected Config $config;
    protected RepoDb $repoDb;
    protected Ontology $ontology;
    protected $siteLang;
    protected $helper;
    protected $model;

    public function __construct() {

        (isset($_SESSION['language'])) ? $this->siteLang = strtolower($_SESSION['language']) : $this->siteLang = "en";
        if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
            $this->config = Config::fromYaml(\Drupal::service('extension.list.module')->getPath('arche_core_gui') . '/config/config-gui.yaml');
        } else {
            $this->config = Config::fromYaml(\Drupal::service('extension.list.module')->getPath('arche_core_gui') . '/config/config.yaml');
        }

        try {
            $this->pdo = new \PDO($this->config->dbConnStr->guest);
            $baseUrl = $this->config->rest->urlBase . $this->config->rest->pathBase;
            $schema = new \acdhOeaw\arche\lib\Schema($this->config->schema);
            $headers = new \acdhOeaw\arche\lib\Schema($this->config->rest->headers);
            $nonRelProp = $this->config->metadataManagment->nonRelationProperties ?? [];
            $this->repoDb = new RepoDb($baseUrl, $schema, $headers, $this->pdo, $nonRelProp);
            $this->ontology = new Ontology($pdo, $schema, $config->ontologyCacheFile, $config->ontologyCacheTtl);
        } catch (\Exception $ex) {
            \Drupal::messenger()->addWarning($this->t('Error during the BaseController initialization!') . ' ' . $ex->getMessage());
            return array();
        }
    }
}
