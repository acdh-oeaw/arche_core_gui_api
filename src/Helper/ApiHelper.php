<?php

namespace Drupal\arche_core_gui_api\Helper;


class ApiHelper {
    
    
    public function getLangValue(array $elements, string $lang = "en"): array {
         // Define fallback languages
        $fallbackLanguages = ['en', 'de', 'und'];

// Find the element based on user preference or fallback languages
        $selectedElement = null;
        foreach ($elements as $element) {
            if ($element['lang'] === $lang) {
                $selectedElement = $element;
                break; // Exit the loop if the user-preferred language is found
            } elseif (in_array($element['lang'], $fallbackLanguages)) {
                // Check if the element's language is one of the fallback languages
                $selectedElement = $element;
                // Continue the loop to check for a more specific language match
            }
        }

// If no match is found, $selectedElement will be null
        if ($selectedElement === null) {
            // Output the selected element or perform further actions
            $selectedElement = $elements[0];
        } 
        return $selectedElement;
    }
    
    /**
     * Fetch the acdih from the results
     * @param array $elements
     * @return string
     */
    public function getAcdhIdValue(array $elements): string {
        foreach($elements as $element) {
            if (strpos($element['value'], 'https://id.acdh.oeaw.ac.at/') !== false) {
                return $element['value'];
            }
        }
        
        return "";
    }
    
    
}