<?php

namespace Drupal\arche_core_gui_api\Helper;

/**
 * Description of ArcheHelper Static Class
 *
 * @author nczirjak
 */
class ArcheBreadcrumbHelper extends \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper {

    private $breadcrumbs = [];
    private $resources;

    /**
     * Generate the breadcrumb data
     * @param object $pdoStmt
     * @param int $resId
     * @param array $context
     * @param string $lang
     * @return object
     */
    public function extractBreadcrumbView(object $pdoStmt, int $resId, array $context, string $lang = "en"): array {
        $this->resources = [(string) $resId => (object) ['id' => $resId, 'language' => $lang]];
        while ($triple = $pdoStmt->fetchObject()) {

            $id = (string) $triple->id;
            if (!isset($context[$triple->property])) {
                continue;
            }

            $property = $context[$triple->property];
            $this->resources[$id] ??= (object) ['id' => $id];
            if ($triple->type === 'REL') {
                $tid = $triple->value;

                $this->resources[$tid] ??= (object) ['id' => $tid];
                $this->resources[$id]->$property[] = $this->resources[$tid];
            } else {
                $this->resources[$id]->$property[$triple->lang] = \acdhOeaw\arche\lib\TripleValue::fromDbRow($triple);
            }
        }
        $this->fetchBreadcrumbValues((array) [$this->resources[$resId]], $lang);

        if (count($this->breadcrumbs) > 0) {
            $this->breadcrumbs = array_reverse($this->breadcrumbs);
            if (count($this->breadcrumbs) > 1) {
                for ($i = 1; $i < count($this->breadcrumbs) - 1; $i++) {
                    $this->breadcrumbs[$i]['title'] = '...';
                }
            }
            return $this->breadcrumbs;
        }
        return [];
    }

    private function fetchBreadcrumbValues(array $data, string $lang = "en") {
        $item = [];

        for ($i = 0; $i < count($data); $i++) {

            $item['id'] = $data[$i]->id;
            $title = reset($data[$i]->title)->value;

            if (isset($data[$i]->title[$lang]->value)) {
                $title = $data[$i]->title[$lang]->value;
            }
            if (strlen($title) > 50) {
                $truncated = substr($title, 0, 50);
                $lastSpace = strrpos($truncated, ' ');
                if ($lastSpace !== false) {
                    $truncated = substr($truncated, 0, $lastSpace); // Cut up to the last full word
                }
                $title = $truncated . '...';
            }
            $item['title'] = $title;
            $item['placeholder'] = $title;
            $this->breadcrumbs[] = $item;
            if (isset($data[$i]->parent)) {
                $this->fetchBreadcrumbValues((array) $data[$i]->parent);
            }
        }
    }
}
