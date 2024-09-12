<?php

namespace Drupal\arche_core_gui_api\Helper;

/**
 * Description of RootTableHelper Class
 *
 * @author nczirjak
 */
class OntologyJsHelper extends \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper {

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
        $html .= '<th><b>PROPERTY</b></th>';
        $html .= '<th><b>MACHINE NAME</b></th>';
        $html .= '<th><b>PROJECT</b></th>';
        $html .= '<th><b>COLLECTION</b></th>';
        $html .= '<th><b>RESOURCE</b></th>';
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
            $categories = ['basic' => '', 'actors_involved' => 'Actors involved', 'coverage' => 'Coverage',
                'right_access' => 'Rights & Access', 'dates' => 'Dates',
                'relations_other_projects' => 'Relations To Other Projects, Collections Or Resources',
                'curation' => 'Curation'];

            foreach ($categories as $cat => $catTitle) {
                $html .= '<tr class="table-row-acdhBlue"><td colspan="6" >' . strtoupper($catTitle) . '</td></tr>';
                foreach ($this->data[$cat] as $val) {
                    if (isset($val['label']) && !empty($val['label'])) {
                        $html .= '<tr>';
                        $html .= '<td>' . $val['label'] . '</td>';
                        $html .= '<td>' . str_replace("https://vocabs.acdh.oeaw.ac.at/schema#", "", $val['property']) . '</td>';
                        $html .= '<td>' . $this->checkCardinality($val['Project']) . '</td>';
                        $html .= '<td>' . $this->checkCardinality($val['Collection']) . '</td>';
                        $html .= '<td>' . $this->checkCardinality($val['Resource']) . '</td>';
                        $html .= '</tr>';
                    }
                }
            }
           
            $html .= "</table>";
        }
        return $html;
    }

    private function checkCardinality(string $val): string {
        switch ($val) {
            case (int) 1:
                return "m";
                break;
            case "x":
                return "-";
                break;
            case "0-n":
                return 'o<span style="font-size: 0.7em; vertical-align: super;">*</span>';
                break;
            case "1-n":
                return 'm<span style="font-size: 0.7em; vertical-align: super;">*</span>';
                break;
            case "0R-n":
                return 'r<span style="font-size: 0.7em; vertical-align: super;">*</span>';
                break;
            case "0R-1":
                return 'r';
                break;
            case "0-1":
                return 'o';
                break;
            default:
                echo "-";
                break;
        }
    }
}
