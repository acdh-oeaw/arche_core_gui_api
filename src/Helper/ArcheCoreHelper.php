<?php

namespace Drupal\arche_core_gui_api\Helper;

/**
 * Description of ArcheHelper Static Class
 *
 * @author nczirjak
 */
class ArcheCoreHelper {

    private static $prefixesToChange = array(
        "http://fedora.info/definitions/v4/repository#" => "fedora",
        "http://www.ebu.ch/metadata/ontologies/ebucore/ebucore#" => "ebucore",
        "http://www.loc.gov/premis/rdf/v1#" => "premis",
        "http://www.jcp.org/jcr/nt/1.0#" => "nt",
        "http://www.w3.org/2000/01/rdf-schema#" => "rdfs",
        "http://www.w3.org/ns/ldp#" => "ldp",
        "http://www.iana.org/assignments/relation/" => "iana",
        "https://vocabs.acdh.oeaw.ac.at/schema#" => "acdh",
        "https://id.acdh.oeaw.ac.at/" => "acdhID",
        "http://purl.org/dc/elements/1.1/" => "dc",
        "http://purl.org/dc/terms/" => "dcterms",
        "http://www.w3.org/2002/07/owl#" => "owl",
        "http://xmlns.com/foaf/0.1/" => "foaf",
        "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
        "http://www.w3.org/2004/02/skos/core#" => "skos",
        "http://hdl.handle.net/21.11115/" => "handle",
        "http://xmlns.com/foaf/spec/" => "foaf"
    );
    private $resources = [];

    /**
     * Check if the drupal DB has the cached data
     * @param string $cacheId
     * @return bool
     */
    public function isCacheExists(string $cacheId): bool {
        if (\Drupal::cache()->get($cacheId)) {
            return true;
        }
        return false;
    }

    /**
     * Fetch the defined ARCHE GUI endpoint 
     * @param string $url
     * @param array $params
     * @return string
     */
    public function fetchApiEndpoint(string $url, array $params = []): string {
        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->request('GET', $url, [
                'query' => $params,
            ]);
            return $response->getBody()->getContents();
        } catch (\Exception $ex) {
            return "";
        }
    }

    /**
     * Create shortcut from the property for the gui
     *
     * @param string $prop
     * @return string
     */
    public static function createShortcut(string $prop): string {
        $prefix = array();

        if (strpos($prop, '#') !== false) {
            $prefix = explode('#', $prop);
            $property = end($prefix);
            $prefix = $prefix[0];
            if (isset(self::$prefixesToChange[$prefix . '#'])) {
                return self::$prefixesToChange[$prefix . '#'] . ':' . $property;
            }
        } else {
            $prefix = explode('/', $prop);
            $property = end($prefix);
            $pref = str_replace($property, '', $prop);
            if (isset(self::$prefixesToChange[$pref])) {
                return self::$prefixesToChange[$pref] . ':' . $property;
            }
        }
        return '';
    }

    public static function createFullPropertyFromShortcut(string $prop): string {
        $domain = self::getDomainFromShortCut($prop);
        $value = self::getValueFromShortCut($prop);
        if ($domain) {
            foreach (self::$prefixesToChange as $k => $v) {
                if ($v == $domain) {
                    return $k . $value;
                }
            }
        }
        return "";
    }

    /**
     * Ectract the api data from the rdf data
     * @param array $result
     * @param array $properties
     * @return array
     */
    public function extractChildView(array $result, array $properties, string $totalCount, string $baseUrl, string $lang = "en"): array {
        $return = [];

        foreach ($result as $v) {
            $order = $v->resource->searchOrder[0]->value;
            $obj = [];

            $obj['title'] = $v->resource->title[0]->value;
            $obj['property'] = $v->property;
            $obj['type'] = $v->resource->class[0]->value;
            //$obj['avDate'] = $v->resource->avDate[0]->value;
            $obj['shortcut'] = str_replace('https://vocabs.acdh.oeaw.ac.at/schema#', '', $v->resource->class[0]->value);
            $obj['acdhid'] = $baseUrl . $v->resource->id;
            $obj['identifier'] = $v->resource->id;
            $obj['sumcount'] = $totalCount;
            $return[] = $obj;
        }
        return $return;
    }

    public function extractChildTreeView(array $result, string $baseUrl, string $lang = "en"): array {
        $return = [];

        if (count($result) > 0) {
            foreach ($result as $k => $v) {
                $return[$k] = $v;
                $this->createBaseProperties($v, $baseUrl, $lang);
                $this->isPublic($v);
                $this->isDirOrFile($v);
            }
        } else {
            $return = array();
        }
        return $return;
    }

    /**
     * Set up the base parameters
     * @param type $v
     * @return void
     */
    private function createBaseProperties(&$v, string $baseUrl, string $lang): void {
        $v->uri = $v->id;
        $v->uri_dl = $baseUrl . $v->id;
        $v->text = $this->setTripleValueTitle($v->title, $lang);
        //$v->resShortId = $v->id;
        if (isset($v->accessRestriction)) {
            $v->accessRestriction = $this->setTripleValueTitle($v->accessRestriction, $lang);
        }
        $v->rdftype = $v->class;
        $v->title = $this->setTripleValueTitle($v->title, $lang);
        $v->a_attr = array("href" => str_replace('api/', 'browser/metadata/', $baseUrl . $v->id));
    }

    protected function setTripleValueTitle(array $triple, string $lang): string {
        if (isset($triple[$lang])) {
            return $triple[$lang];
        } else {
            if (is_array($triple)) {
                if(isset($triple[0]->title)) {
                    if(isset($triple[0]->title[$lang])) {
                        return $triple[0]->title[$lang];
                    }
                    return reset($triple[0]->title);
                }
            }
        }
        return reset($triple);
    }

    /**
     * Actual resource accessrestriction
     * @param type $v
     */
    private function isPublic(&$v): void {
        if (!isset($v->accessRestriction)) {
            $v->accessRestriction = 'public';
        }
        if ($v->accessRestriction == 'public') {
            $v->userAllowedToDL = true;
        } else {
            $v->userAllowedToDL = false;
        }
    }

    /**
     * The actual resource is a binary file or a directory
     * @param type $v
     */
    private function isDirOrFile(&$v): void {
        $allowedFormats = ['https://vocabs.acdh.oeaw.ac.at/schema#Resource', 'https://vocabs.acdh.oeaw.ac.at/schema#Metadata'];

        if (isset($v->rdftype) && in_array($v->rdftype, $allowedFormats)) {
            $v->dir = false;
            $v->icon = "jstree-file";
        } else {
            $v->dir = true;
            $v->children = true;
        }
    }

    public function extractRootView(object $pdoStmt, array $properties, array $propertyLabel, string $lang) {
        $this->resources = [];
        while ($triple = $pdoStmt->fetchObject()) {

            $id = (string) $triple->id;
            $property = $triple->property;

            if (in_array($triple->property, $properties)) {
                $tLang = (empty($triple->lang)) ? $triple->lang = $lang : $triple->lang;
                $this->resources[$id][$triple->property][$triple->lang] = $triple;
            }
        }
        $this->setRootDefaultTitle($lang);
        foreach ($this->resources as $id => $obj) {
            foreach ($obj as $prop => $o) {

                if (array_key_exists($prop, $propertyLabel)) {
                    $this->resources[$id][$propertyLabel[$prop]] = $o[$lang];
                    unset($this->resources[$id][$prop]);
                }
            }
        }
        return $this->resources;
    }

    public function extractRootDTView(object $pdoStmt, array $properties, array $propertyLabel, string $lang) {
        $this->resources = [];
        $i = 0;

        while ($triple = $pdoStmt->fetchObject()) {

            if ($triple->property === "search://count") {
                $this->resources['sumcount'] = $triple->value;
            }

            $id = (string) $triple->id;
            $property = $triple->property;

            if (in_array($triple->property, $properties)) {
                $tLang = (empty($triple->lang)) ? $triple->lang = $lang : $triple->lang;
                $this->resources[$i]['acdhresId'] = $triple->id;
                $this->resources[$i][$triple->property][$triple->lang] = $triple;
            }
        }
        $i++;
        $this->setRootDefaultTitle($lang);

        foreach ($this->resources as $id => $obj) {
            foreach ($obj as $prop => $o) {

                if (array_key_exists($prop, $propertyLabel)) {
                    $this->resources[$id][$propertyLabel[$prop]] = $o[$lang];
                    unset($this->resources[$id][$prop]);
                }
            }
        }


        return $this->resources;
    }

    /**
     * Get all metadata for a given resource
     * @param object $pdoStmt
     * @param int $resId
     * @param array $contextRelatives
     * @return object
     */
    public function extractExpertView(object $pdoStmt, int $resId, array $contextRelatives, string $lang = "en"): object {
        $this->resources = [(string) $resId => (object) ['id' => $resId, 'language' => $lang]];
        $relArr = [];

        while ($triple = $pdoStmt->fetchObject()) {

            $id = (string) $triple->id;
            $this->resources[$id] ??= (object) ['id' => (int) $id];
            

            
            if ($triple->id !== $resId && isset($contextRelatives[$triple->property])) {
               
                
                
                $property = $contextRelatives[$triple->property];
                

                
                $relvalues = \acdhOeaw\arche\lib\TripleValue::fromDbRow($triple);

                if ($property === 'title') {
                    //if we have the title for the actual gui lang then apply it
                    if ($relvalues->lang === $lang) {
                        $this->resources[$id]->relvalue = $relvalues->value;
                        $this->resources[$id]->value = $relvalues->value;
                        $this->resources[$id]->lang = $lang;
                    } elseif (!isset($this->resources[$id]->value)) {
                        //if the lang is different then we add it to the titles arr
                        $this->resources[$id]->titles[$relvalues->lang] = $relvalues->value;
                        $this->resources[$id]->value = $relvalues->value;
                        $this->resources[$id]->relvalue = $relvalues->value;
                        $this->resources[$id]->lang = $lang;
                    }
                }

                if ($property === 'class') {
                    $this->resources[$id]->property[$lang] = $relvalues->value;
                }
                if ($property === 'identifiers') {
                    $this->resources[$id]->{'identifiers'}[] = $triple->value;
                }

                $this->resources[$id]->type = "REL";
                $this->resources[$id]->repoid = $id;
            } elseif ($triple->id === $resId) {
                $property = $triple->property;

                if ($triple->type === 'REL') {
                    $relArr[$triple->value]['id'] = $triple->value;
                    $tid = $triple->value;
                    $this->resources[$tid] ??= (object) ['id' => (int) $tid];
                    $this->resources[$id]->$property[$tid][$lang] = (object) $this->resources[$tid];
                } elseif ($triple->type === 'ID') {
                   
                    $this->resources[$id]->$property[$id][$lang][] = (object) $triple;
                } elseif ($triple->type === 'http://www.w3.org/2001/XMLSchema#anyURI') {
                    $this->resources[$id]->$property[$id][$lang][] = (object) $triple;
                } else {
                    if (!($triple->lang)) {
                        $triple->lang = $lang;
                    }
                    if ($triple->lang === $lang) {
                        $this->resources[$id]->$property[$id][$lang][] = (object) $triple;
                    } else {
                        $this->resources[$id]->$property[$id][$triple->lang][] = (object) $triple;
                    }
                }
            } elseif ($triple->property === 'ID') {
                $this->resources[$id]->$property[$id][$lang][] = (object) $triple;
            }
        }

        if (count($this->resources) < 1) {
            return new \stdClass();
        }

        $this->changePropertyToShortcut((string) $resId);
        $this->setDefaultTitle($lang, $resId);
        if($resId === 67346) {
            unset($this->resources[(string) $resId]->{'acdh:isPartOf'});
        }
        
        return $this->resources[(string) $resId];
    }

    /**
     * this is for the single objects, where we dont have multiple value
     * @param string $lang
     */
    protected function setRootDefaultTitle(string $lang) {

        foreach ($this->resources as $id => $object) {
            foreach ($object as $prop => $obj) {
                if (is_array($obj)) {
                    if (array_key_exists($lang, $obj)) {
                        $this->resources[$id][$prop][$lang] = $obj[$lang]->value;
                    } else {
                        if ($lang === "en" && array_key_exists('de', $obj)) {
                            $this->resources[$id][$prop][$lang] = $obj['de']->value;
                            unset($this->resources[$id][$prop]['de']);
                        } elseif ($lang === "de" && array_key_exists('en', $obj)) {
                            $this->resources[$id][$prop][$lang] = $obj['en']->value;
                            unset($this->resources[$id][$prop]['en']);
                        } elseif (array_key_exists('und', $obj)) {
                            $this->resources[$id][$prop][$lang] = $obj['und']->value;
                        } else {
                            $this->resources[$id][$prop][$lang] = reset($obj)->value;
                        }
                    }
                }
            }
        }
    }

    /**
     * If the property doesn't have the actual lang related value, then we 
     * have to create one based on en/de/und/or first array element
     */
    private function setDefaultTitle(string $lang, string $resId) {
        //var_dump($this->resources[$resId]->{'acdh:hasCurator'}[11214]->id);
        foreach ($this->resources[$resId] as $prop => $pval) {

            if (is_array($pval)) {
                foreach ($pval as $rid => $tv) {
                    if (!isset($tv->value)) {
                        if (isset($tv->titles) && is_array($tv->titles)) {
                            if (array_key_exists($lang, $tv->titles)) {
                                $this->resources[$resId]->$prop[$rid]->value = $tv->titles[$lang];
                            } else {
                                if ($lang === "en" && array_key_exists('de', $tv->titles)) {
                                    $this->resources[$resId]->$prop[$rid]->value = $tv->titles['de'];
                                } elseif ($lang === "de" && array_key_exists('en', $tv->titles)) {
                                    $this->resources[$resId]->$prop[$rid]->value = $tv->titles['en'];
                                } elseif (array_key_exists('und', $tv->titles)) {
                                    $this->resources[$resId]->$prop[$rid]->value = $tv->titles['und'];
                                } else {
                                    $this->resources[$resId]->$prop[$rid]->value = reset($tv->titles);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * change the long proeprty urls inside the resource array
     * @param string $resId
     */
    private function changePropertyToShortcut(string $resId = "") {
        if ($resId) {
            foreach ($this->resources[$resId] as $k => $v) {
                if (!empty($shortcut = $this::createShortcut($k))) {
                    $this->resources[$resId]->$shortcut = $v;
                    unset($this->resources[$resId]->$k);
                }
            }
        } else {
            foreach ($this->resources as $k => $v) {

                if (!empty($shortcut = $this::createShortcut($k))) {
                    $this->resources[$shortcut] = $v;
                    unset($this->resources[$k]);
                }
            }
        }
    }
    
    /**
     * Fetch the previous and next item from the child list
     * @param array $result
     * @param string $resourceId
     * @param string $lang
     * @return array
     */
    public function extractPrevNextItem(array $result, string $resourceId, string $lang): array {
        $return = [];
        foreach($result as $k => $v) {
            if($v->id === $resourceId) {
                if(isset($result[$k-1]->id)) {
                    $return['previous']['id'] = $result[$k-1]->id;
                    $return['previous']['title'] = $this->setTripleValueTitle($result[$k-1]->title, $lang);
                }
                if(isset($result[$k+1]->id)) {
                    $return['next']['id'] = $result[$k+1]->id;
                    $return['next']['title'] = $this->setTripleValueTitle($result[$k+1]->title, $lang);
                }

            }
        }
        
        return $return;
    }
    
}
