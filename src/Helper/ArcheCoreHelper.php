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
     * Extract the GUI data from the RDF data for a given resource (id)
     * @param object $obj
     * @param string $id
     * @return type
     */
    public function extractDataFromCoreApiWithId(object $obj, string $id) {
        $root = [];
        $relArr = [];

        while ($triple = $obj->fetchObject()) {
            $rid = (string) $triple->id;

            if ($rid === $id) {
                $triple->lang = ($triple->lang === null) ? 'en' : $triple->lang;
                if ($triple->type === 'REL') {
                    $root[$triple->property][$triple->value] = $triple;
                } else {
                    $root[$triple->property][] = $triple;
                }
            } else {
                $object = (object) [
                            'id' => $triple->id,
                            'property' => $triple->property,
                            'type' => $triple->type,
                            'lang' => ($triple->lang === null ? 'en' : $triple->lang),
                            'value' => $triple->value
                ];

                if ($triple->property === "https://vocabs.acdh.oeaw.ac.at/schema#hasTitle") {
                    $relArr[$triple->id][$triple->lang] = $object;
                }
            }
        }


        foreach ($root as $rpk => $rpv) {
            foreach ($rpv as $rk => $rv) {
                if (array_key_exists($rk, $relArr)) {
                    $root[$rpk][$rk]->values = $relArr[$rk];
                }
            }
        }


        return $root;
    }

    /**
     * Get all metadata for a given resource
     * @param object $pdoStmt
     * @param int $resId
     * @param array $contextRelatives
     * @return object
     */
    public function extractExpertView(object $pdoStmt, int $resId, array $contextRelatives, string $lang = "en"): object {
        $this->resources = [(string) $resId => (object) ['id' => $resId]];
        $relArr = [];
        while ($triple = $pdoStmt->fetchObject()) {

            $id = (string) $triple->id;
            $this->resources[$id] ??= (object) ['id' => (int) $id];

            if ($triple->id !== $resId && isset($contextRelatives[$triple->property])) {

                if ($triple->property === 'https://vocabs.acdh.oeaw.ac.at/schema#hasLicenseSummary') {
                    echo "MAIN";
                    echo "<pre>";
                    var_dump($this->resources[$id]->$property);
                    echo "</pre>";
                }

                $property = $contextRelatives[$triple->property];
                $relvalues = \acdhOeaw\arche\lib\TripleValue::fromDbRow($triple);

                if ($property === 'title') {
                    //get the titles with the lang codes
                    if (array_key_exists($triple->id, $relArr)) {
                        $relArr[$triple->id][$triple->lang] = $triple->value;
                        //unset($relArr[$triple->id]['id']);
                    }
                    //if we have the title for the actual gui lang then apply it
                    if ($relvalues->lang === $lang) {
                        $this->resources[$id]->relvalue = $relvalues->value;
                        $this->resources[$id]->value = $relvalues->value;
                    }
                    if ($lang === $relvalues->lang) {
                        $this->resources[$id]->value = $relvalues->value;
                        $this->resources[$id]->lang = $lang;
                    } else {
                        if (($lang == 'en') && $relvalues->lang === 'de') {
                            $this->resources[$id]->value = $relvalues->value;
                            $this->resources[$id]->lang = $lang;
                        } elseif (($lang == 'de') && $relvalues->lang === 'und') {
                            $this->resources[$id]->value = $relvalues->value;
                            $this->resources[$id]->lang = $lang;
                        } else {
                            $this->resources[$id]->value = $relvalues->value;
                            $this->resources[$id]->lang = $lang;
                        }
                    }
                    $this->resources[$id]->titles[$relvalues->lang] = $relvalues->value;
                }

                if ($property === 'class') {
                    $this->resources[$id]->property = $relvalues->value;
                }
                $this->resources[$id]->type = "REL";
                $this->resources[$id]->language = $relvalues->lang;
                $this->resources[$id]->repoid = $id;
            } elseif ($triple->id === $resId) {
                $property = $triple->property;
                
                if ($triple->property === 'https://vocabs.acdh.oeaw.ac.at/schema#hasLicenseSummary') {
                    echo "ELSE";
                    echo "<pre>";
                    var_dump($this->resources[$id]->$property);
                    echo "</pre>";
                }



                if ($triple->type === 'REL') {
                    
                    $relArr[$triple->value]['id'] = $triple->value;
                    $tid = $triple->value;
                    $this->resources[$tid] ??= (object) ['id' => (int) $tid];
                    $this->resources[$id]->$property[$lang][] = (object) $this->resources[$tid];
                } else {
                     if ($triple->property === 'https://vocabs.acdh.oeaw.ac.at/schema#hasLicenseSummary') {
                    echo "ELSE 2";
                    echo "<pre>";
                    var_dump($this->resources[$id]->$property);
                    echo "</pre>";
                }
                //we have to check if there is already a lang
                    $this->resources[$id]->$property[$lang][] = (object) $triple;
                    
                     if ($triple->property === 'https://vocabs.acdh.oeaw.ac.at/schema#hasLicenseSummary') {
                    echo "ELSE 2 after";
                    echo "<pre>";
                    var_dump($this->resources[$id]->$property);
                    echo "</pre>";
                }
                }
            }
        }
        if (count($this->resources) < 2) {
            return new \stdClass();
        }
        die();
        $this->changePropertyToShortcut((string) $resId);

//        $this->setDefaultTitle($lang, (string) $resId);
        return $this->resources[(string) $resId];
    }

    /**
     * If the property doesn't have the actual lang related value, then we 
     * have to create one based on en/de/und/or first array element
     */
    private function setDefaultTitle(string $lang, string $resId) {
        foreach ($this->resources[(string) $resId] as $prop => $val) {
            if (is_array($val)) {
                echo "<pre>";
                var_dump($val);
                echo "</pre>";

                foreach ($val[$lang] as $k => $v) {
                    if (!isset($v->type)) {
                        unset($this->resources[(string) $resId]->$prop[$lang][$k]);
                    }

                    if (!$v->value && $v->type) {
                        foreach ($v->titles as $tk => $tv) {
                            if (($lang == 'en') && $tk === 'de') {
                                $this->resources[(string) $resId]->$prop[$lang][$k]->value = $tv;
                                $this->resources[(string) $resId]->$prop[$lang][$k]->relvalue = $tv;
                            } elseif (($lang == 'de') && $tk === 'und') {
                                $this->resources[(string) $resId]->$prop[$lang][$k]->value = $tv;
                                $this->resources[(string) $resId]->$prop[$lang][$k]->relvalue = $tv;
                            } else {
                                $this->resources[(string) $resId]->$prop[$lang][$k]->value = $tv;
                                $this->resources[(string) $resId]->$prop[$lang][$k]->relvalue = $tv;
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
    private function changePropertyToShortcut(string $resId) {
        foreach ($this->resources[$resId] as $k => $v) {
            if (!empty($shortcut = $this::createShortcut($k))) {
                $this->resources[$resId]->$shortcut = $v;
                unset($this->resources[$resId]->$k);
            }
        }
    }

    public function extractInverseDataFromCoreApiWithId(object $obj, string $id) {
        $root = [];
        $relArr = [];

        while ($triple = $obj->fetchObject()) {

            if ($triple->value === $id) {
                if ($triple->type === 'REL') {

                    $tobj = new \stdClass();
                    $tobj->id = $triple->id;
                    $tobj->type = $triple->type;
                    $tobj->value = $triple->id;
                    $tobj->lang = 'en';

                    $data = \acdhOeaw\arche\lib\TripleValue::fromDbRow($tobj);

                    echo "________DATA _____________ ";
                    echo "<pre>";
                    var_dump($data);
                    echo "</pre>";

                    echo "______DATA END ______________";
                }
                $root[$triple->id][] = $triple;
            }

            if ($triple->property === 'search://count') {
                $root['count'] = $triple->value;
            }
        }

        echo "ROOT";

        echo "<pre>";
        var_dump($root);
        echo "</pre>";

        die();
        foreach ($root as $rpk => $rpv) {
            foreach ($rpv as $rk => $rv) {
                if (array_key_exists($rk, $relArr)) {
                    $root[$rpk][$rk]->values = $relArr[$rk];
                }
            }
        }


        return $root;
    }
}
