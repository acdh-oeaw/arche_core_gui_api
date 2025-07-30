<?php

namespace Drupal\arche_core_gui_api\Helper;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Description of AcceptedFormatsHelper Class
 *
 * @author nczirjak
 */
class AcceptedFormatsHelper extends \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper {

    use StringTranslationTrait;

    private $data;
    private $lang = "en";

    public function createHtml(array $data, string $lang): string {
        $this->data = $data;
        $this->lang = $lang;
        $html = "";
        $html .= $this->createHtmlTable();
        return $html;
    }

    /**
     * The root table header html code
     * @return string
     */
    private function createHeader(): string {

        $html = "<table class='table table-hover'>";
        $html .= "<thead >";
        $html .= '<tr>';
        $html .= '<th class="text-center"><b>' . $this->t("EXTENSION") . '</b></th>';
        $html .= '<th class="text-center"><b>' . $this->t("FORMAT NAME & VERSION") . '</b></th>';
        $html .= '<th class="text-center"><b>' . $this->t("PREFERENCE") . '</b></th>';
        $html .= "</thead >";
        $html .= '</tr>';

        return $html;
    }

    /**
     * Create the response html string
     * @return string
     */
    private function createHtmlTable(): string {
        $html = '';

        if (count($this->data) > 0) {
            // Open the table
            $html .= $this->createHeader();
            // Cycle through the array
            $categories = array_keys($this->data);

            foreach ($categories as $cat => $catTitle) {
                $html .= '<tr class="table-row-acdhBlue"><td colspan="6" >' . strtoupper($catTitle) . '</td></tr>';
                foreach ($this->data[$catTitle] as $val) {
                    $html .= '<tr>';
                    $html .= '<td class="text-end">' . $val[0] . '</td>';
                    $html .= '<td>' . $val[1] . '</td>';
                    $html .= '<td>' . $val[2] . '</td>';
                    $html .= '</tr>';
                }
            }

            $html .= "</table>";
        }
        return $html;
    }
}
