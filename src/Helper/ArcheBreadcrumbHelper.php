<?php

namespace Drupal\arche_core_gui_api\Helper;

/**
 * Description of ArcheHelper Static Class
 *
 * @author nczirjak
 */
class ArcheBreadcrumbHelper extends \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper {

    private $breadcrumbs = [];
    
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
                $this->resources[$id]->$property[] = \acdhOeaw\arche\lib\TripleValue::fromDbRow($triple);
            }
        }

        $this->fetchBreadcrumbValues((array)[$this->resources[$resId]]);
        
        if(count($this->breadcrumbs) > 0) {
            return array_reverse($this->breadcrumbs);
        }
        
        return [];
    }

    private function fetchBreadcrumbValues(array $data) {
        $item = [];
        
        for ($i = 0; $i < count($data); $i++) {
           
            $item['id'] = $data[$i]->id;
            $item['title'] = $data[$i]->title[0]->value;
            $this->breadcrumbs[] = $item;
            if(isset($data[$i]->parent)) {
                 $this->fetchBreadcrumbValues((array)$data[$i]->parent);
            }
        }
        
    }
}
