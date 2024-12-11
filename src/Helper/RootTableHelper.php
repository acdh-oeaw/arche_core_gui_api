<?php

namespace Drupal\arche_core_gui_api\Helper;

/**
 * Description of RootTableHelper Class
 *
 * @author nczirjak
 */
class RootTableHelper extends \Drupal\arche_core_gui_api\Helper\ArcheCoreHelper {

    private $data;
    private $lang = "en";

    public function createHtml(array $data, string $lang): string {
        $this->data = $data;
        $this->lang = $lang;
        $html = "";
        $html .= $this->createRootTableHtml();
        return $html;
    }

    /**
     * The root table header html code
     * @return string
     */
    private function createRootTableHeader(): string {
        $html = "<style>
                table thead tr th {
                    position: sticky;
                    z-index: 100;
                    top: 0;
                }
                table, tr, th, td {
                    border: 1px solid black;
                }
                tr, th, td {
                    padding: 15px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                }
                th {
                    background-color: #4CAF50;
                    color: white;
                }
                tr:hover {background-color: #f5f5f5;}
                tr:nth-child(even) {background-color: #f2f2f2;}
                .sticky {position: sticky; z.index: 100; left: 0; background-color: #4CAF50; color:white;}
                </style>";
        $html .= "<table >";
        $html .= "<thead >";
        $html .= '<tr>';
        $html .= '<th><b>Property</b></th>';
        $html .= '<th><b>Project</b></th>';
        $html .= '<th><b>TopCollection</b></th>';
        $html .= '<th><b>Collection</b></th>';
        $html .= '<th><b>Resource</b></th>';
        $html .= '<th><b>Metadata</b></th>';
        $html .= '<th><b>Publication</b></th>';
        $html .= '<th><b>Place</b></th>';
        $html .= '<th><b>Organisation</b></th>';
        $html .= '<th><b>Person</b></th>';
        $html .= '<th><b>Order</b></th>';
        //$html .= '<th><b>domain</b></th>';
        $html .= '<th><b>Range</b></th>';
        $html .= '<th><b>Vocabulary</b></th>';
        //$html .= '<th><b>Recommended Class</b></th>';
        $html .= '<th><b>Automated Fill</b></th>';
        $html .= '<th><b>Default Value</b></th>';
        $html .= '<th><b>LangTag</b></th>';
        $html .= '<th><b>Comment</b></th>';
        $html .= "</thead >";
        $html .= '</tr>';

        return $html;
    }

    /**
     * Create the response html string
     * @return string
     */
    private function createRootTableHtml(): string {
        $html = '';

        if (count($this->data) > 0) {
            // Open the table
            $html .= $this->createRootTableHeader();
            // Cycle through the array
            foreach ($this->data as $val) {
               
                $html .= '<tr>';
                $html .= '<td class="sticky"><b>' . str_replace("https://vocabs.acdh.oeaw.ac.at/schema#", "", $val['property']) . '</b></td>';
                $html .= '<td>' . $val['Project'] . '</td>';
                $html .= '<td>' . $val['TopCollection'] . '</td>';
                $html .= '<td>' . $val['Collection'] . '</td>';
                $html .= '<td>' . $val['Resource'] . '</td>';
                $html .= '<td>' . $val['Metadata'] . '</td>';
                $html .= '<td>' . $val['Publication'] . '</td>';
                $html .= '<td>' . $val['Place'] . '</td>';
                $html .= '<td>' . $val['Organisation'] . '</td>';
                $html .= '<td>' . $val['Person'] . '</td>';
                $html .= '<td>' . $val['order'] . '</td>';
                $html .= '<td>' . $this->formatRange($val["range"]) . '</td>';
                $html .= '<td>' . $val['vocabulary'] . '</td>';
                $html .= '<td>' . $val['automatedFill'] . '</td>';
                $html .= '<td>' . $val['defaultValue'] . '</td>';
                $html .= '<td>' . $val['langTag'] . '</td>';
                $comment = $val['comment'][$this->lang] ? $val['comment'][$this->lang] : reset($val['comment']);
                $html .= '<td>' . $comment . '</td>';
                $html .= '</tr>';
            }
            $html .= "</table>";
        }
        return $html;
    }

    /**
     * Format the range values for the better readability
     * @param array $range
     * @return string
     */
    private function formatRange(array $range): string {
        $props = ['http://www.w3.org/1999/02/22-rdf-syntax-ns#' => 'rdf',
            'http://www.w3.org/2001/XMLSchema#' => 'xsd',
            'http://www.w3.org/2002/07/owl#' => 'owl',
            'http://www.w3.org/2004/02/skos/core#' => 'skosCore'];
        $string = "";
      
        $filteredUrls = array_filter($range, function ($url) {
            return strpos($url, 'arche') === false;
        });
       
        $updatedUrls = array_map(function ($url) use ($props) {
            foreach ($props as $key => $value) {
                if (strpos($url, $key) !== false) {
                    $url = str_replace($key, $value.':', $url);
                }
            }
            return $url;
        }, $filteredUrls);

        $string = implode(',<br> ', $updatedUrls);
        
        return $string;
    }
}
