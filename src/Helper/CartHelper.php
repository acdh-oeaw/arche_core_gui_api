<?php

namespace Drupal\arche_core_gui_api\Helper;

use Drupal\Core\Cache\Cache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use acdhOeaw\arche\lib\Config;
use acdhOeaw\arche\lib\RepoInterface;
use acdhOeaw\arche\lib\SearchConfig;
use acdhOeaw\arche\lib\SearchTerm;
use acdhOeaw\arche\lib\RepoDb;
use zozlak\RdfConstants;

/**
 * Description of CartHelper Static Class
 *
 * @author nczirjak
 */
class CartHelper {

    protected Config $config;
    protected \acdhOeaw\arche\lib\Schema $schema;
    protected RepoDb $repoDb;
    protected \PDO $pdo;
    private $breadcrumbs = [];
    private $resources;

    public function __construct() {
        $this->config = Config::fromYaml(\Drupal::service('extension.list.module')->getPath('arche_core_gui') . '/config/config.yaml');
        try {
            $this->pdo = new \PDO($this->config->dbConnStr);
            $baseUrl = $this->config->rest->urlBase . $this->config->rest->pathBase;
            $this->schema = new \acdhOeaw\arche\lib\Schema($this->config->schema);
            $headers = new \acdhOeaw\arche\lib\Schema($this->config->rest->headers);
            $nonRelProp = $this->config->metadataManagment->nonRelationProperties ?? [];
            $this->repoDb = new RepoDb($baseUrl, $this->schema, $headers, $this->pdo, $nonRelProp);
        } catch (\Exception $ex) {
            \Drupal::messenger()->addWarning($this->t('Error during the BaseController initialization!') . ' ' . $ex->getMessage());
            return array();
        }
    }

    public function checkCartContent(): bool {
        $items = json_decode($_COOKIE['cart_items'], true);
        //$items[333] = ['title' => 'example', 'accessres' => 'public', 'type' => 'collection', 'size' => '22222' ]; 
        //we need two cookies. one for the cart cookie. and the second is for the displaying the DT    


        $_COOKIE['cart_items'] = json_encode($items);

        $content = $this->checkCartItems();
        if (count($content) === 0) {
            return true;
        }
        $this->createNewCookiData($content, $items);
        return true;
    }

    private function createNewCookiData(array $data, array $cookie): void {
        $_COOKIE['cart_items_ordered'] = json_encode($data);
    }

    private function checkCartItems(): array {
        $items = json_decode($_COOKIE['cart_items'], true);
        if (count((array) $items) === 0) {
            return [];
        }
        $ids = array_keys($items);
        $output = [];
        $result = [];
        foreach ($ids as $id) {
            //$data[$id] = $mdC->getBreadcrumb($id);

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
            $result[$id] = $this->extractParents($pdoStmt, $id, $context, "en");
        }
        $output = $this->buildNestedNoChildrenKey($result);

        $meta = [];
        $meta = $this->addMetaInfo($output, $items);
       
        return $meta;
        /**
          $input = [
          333 => [['id' => '19641'], ['id' => '38486'], ['id' => '34665']],
          532948 => [['id' => '532966'], ['id' => '532959']],
          532954 => [['id' => '532966']],
          532966 => [],
          533417 => [['id' => '532966'], ['id' => '532959']],
          512234 => [['id' => '512221'], ['id' => '512235']],
          512236 => [['id' => '512221'], ['id' => '512235'], ['id' => '512234']]
          ];

          $resultArr = [
          [333] = [],
          [532966] => ['children' => [
          [532959] => ['children' => [533417, 532948]],
          [532954] => []
          ]
          ],
          [512234] => ['children' => [512236]]
          ];
         */
    }

    private function addMetaInfo($tree, $titles) {

        $out = [];
        foreach ($tree as $id => $children) {
            // base node: must have a title
            $node = [
                'title' => $titles[$id]['title'] ?? '(no title)',
                'accessres' => $titles[$id]['accessres'] ?? '---',
                'size' => $titles[$id]['size'] ?? '---',
                'type' => $titles[$id]['type'] ?? '---',
            ];

            // if there are children, recurse
            if (!empty($children)) {
                if(is_array($children)) {
                    $node['children'] = $this->addMetaInfo($children, $titles);
                }
            }
            $out[$id] = $node;
        }
        return $out;
    }

    /**
     * 
     * @param array $input
     * @return array
     */
    private function buildNestedNoChildrenKey(array $input): array {
        // 1) Raw chains: id => [ parentId, … ] as ints
        $chains = [];
        foreach ($input as $id => $parents) {
            $iid = (int) $id;
            $chains[$iid] = array_map(fn($p) => (int) $p['id'], $parents);
        }

        // 2) Determine your true roots: those with empty chain
        //    or whose chain has no intersection with any other id
        $allIds = array_keys($chains);
        $roots = [];
        foreach ($chains as $id => $chain) {
            if (empty($chain) || empty(array_intersect($chain, $allIds))) {
                $roots[] = $id;
            }
        }

        // 3) Init the result map with each root => empty array
        $result = array_fill_keys($roots, []);

        // 4) Now place every non‐root under its *first* root‐ancestor
        foreach ($chains as $id => $chain) {
            if (in_array($id, $roots, true)) {
                continue;
            }

            // Find the first element in $chain that is one of the roots
            $root = null;
            $pos = null;
            foreach ($chain as $i => $pid) {
                if (in_array($pid, $roots, true)) {
                    $root = $pid;
                    $pos = $i;
                    break;
                }
            }
            if ($root === null) {
                // no known root in its chain → skip
                continue;
            }

            // Build the “tail”: what comes *after* that root ancestor, plus this node
            $tail = array_slice($chain, $pos + 1);
            $tail[] = $id;

            // Walk into $result[$root] by reference, creating nested arrays
            $cursor = &$result[$root];
            $n = count($tail);

            if ($n === 1) {
                // direct child
                $child = $tail[0];
                if (!array_key_exists($child, $cursor)) {
                    $cursor[$child] = [];
                }
            } else {
                // deeper nesting: [ level1, level2, …, leaf ]
                for ($i = 0; $i < $n - 1; $i++) {
                    $seg = $tail[$i];
                    if (!isset($cursor[$seg]) || !is_array($cursor[$seg])) {
                        $cursor[$seg] = [];
                    }
                    $cursor = &$cursor[$seg];
                }
                // final leaf goes as a numeric element under the deepest array
                $leaf = $tail[$n - 1];
                if (!in_array($leaf, $cursor, true)) {
                    $cursor[$leaf] = $leaf;
                }
            }
            unset($cursor);
        }

        return $result;
    }

    /**
     * Generate the parent data
     * @param object $pdoStmt
     * @param int $resId
     * @param array $context
     * @param string $lang
     * @return object
     */
    public function extractParents(object $pdoStmt, int $resId, array $context, string $lang = "en"): array {
        $this->resources = [(int) $resId => (object) ['id' => (int) $resId, 'language' => $lang]];
        $this->breadcrumbs = [];
        while ($triple = $pdoStmt->fetchObject()) {
            $id = (int) $triple->id;
            if (!isset($context[$triple->property])) {
                continue;
            }

            $property = $context[$triple->property];
            $this->resources[(int) $id] ??= (object) ['id' => (int) $id];
            if ($triple->type === 'REL') {
                $tid = $triple->value;
                $this->resources[(int) $tid] ??= (object) ['id' => (int) $tid];
                $this->resources[(int) $id]->$property[] = $this->resources[(int) $tid];
            }
        }

        $this->fetchParentValues((array) [$this->resources[(int) $resId]], $lang);

        if ($this->breadcrumbs[0]['id'] === (int) $resId) {
            unset($this->breadcrumbs[0]);
        }

        if (count($this->breadcrumbs) > 0) {
            $this->breadcrumbs = array_reverse($this->breadcrumbs);

            return $this->breadcrumbs;
        }
        return [];
    }

    private function fetchParentValues(array $data, string $lang = "en") {
        $item = [];

        for ($i = 0; $i < count($data); $i++) {

            $item['id'] = $data[$i]->id;

            $this->breadcrumbs[] = $item;
            if (isset($data[$i]->parent)) {
                $this->fetchParentValues((array) $data[$i]->parent);
            }
        }
    }
}
