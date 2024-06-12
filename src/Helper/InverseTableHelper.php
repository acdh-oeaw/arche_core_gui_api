<?php

namespace Drupal\arche_core_gui_api\Helper;

/**
 * Description of InverseTableHelper Class
 *
 * @author nczirjak
 */
class InverseTableHelper extends \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper {

    private $breadcrumbs = [];

    /**
     * Generate the breadcrumb data
     * @param object $pdoStmt
     * @param int $resId
     * @param array $context
     * @param string $lang
     * @return object
     */
    public function extractinverseTableView(array $result, string $lang = "en"): array {
        $arr = [];
        foreach ($result as $k => $v) {
            $item = [];
           
            if (isset($v->property)) {
                $item['id'] = $v->resource->id;
                $item['acdhid'] = $v->resource->id;
                $item['property'] = $v->property;
                $item['title'] = $this->setDefaultTitle($v->resource->title, $lang);
                $item['type'] = $this->setDefaultTitle($v->resource->rdftype, $lang);
                if(isset($v->resource->customCitation)) {
                    $item['customCitation'] = $this->setDefaultTitle($v->resource->customCitation, $lang);
                }
                $arr[$k] = $item;
            }
        }
        return $arr;
    }

    /**
     * Get The triple object title
     * @param array $obj
     * @param string $lang
     * @return type
     */
    protected function setDefaultTitle(array $obj, string $lang): string {
        if (array_key_exists($lang, $obj)) {
            return $obj[$lang]->value;
        } else {
            if ($lang === "en" && array_key_exists('de', $obj)) {
                return $obj['de']->value;
            } elseif ($lang === "de" && array_key_exists('en', $obj)) {
                return $obj['en']->value;
            } elseif (array_key_exists('und', $obj)) {
                return $obj['und']->value;
            }
        }
        return reset($obj)->value;
    }
    
   
}
