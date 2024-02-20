<?php

namespace Drupal\arche_core_gui_api\Object;

/**
 * Description of CollectionObject
 *
 * @author nczirjak
 */
class SearchBlockObject extends \Drupal\arche_core_gui_api\Object\MainObject
{
    protected $model;

    protected function createModel(): void
    {
        $this->model = new \Drupal\arche_gui_api\Model\SearchBlock\SearchBlockModel();
    }

    public function init(string $lang): bool
    {
        $this->createModel();
        $this->processData($this->model->getData($lang));
        
        if (count($this->data['category']) < 0 && count($this->data['year']) < 0 && count($this->data['entity']) < 0) {
            return false;
        }
        
        return true;
    }

    private function processData(array $data): void
    {
        $this->data = $data;
        $this->extendValuesForForm();
    }
    
    public function getCategories(): array
    {
        return $this->data['category'];
    }
    
    public function getYears(): array
    {
        return $this->data['year'];
    }
    
    public function getEntities(): array
    {
        return $this->data['entity'];
    }

    /**
     *
     * // name = searchbox_types[acdh:Collection] value = acdh:Collection
     *   // name = searchbox_category[3d-data:17683] value = 3d-data:17683
     *    // name = datebox_years[2022] value = 2022
     */
    private function extendValuesForForm(): void
    {
        $this->extendEntity();
        $this->extendCategory();
        $this->extendYears();
    }
    
    private function extendYears()
    {
        foreach ($this->data['year'] as $k => $v) {
            $v = (array)$v;
            $v['inputName'] = 'datebox_years['.$v["year"].']';
            $v['inputValue'] = $v["year"];
            $v['inputLabel'] = $v["year"].' ('.$v["count"].')';
            $this->data['year'][$k] = $v;
        }
    }
    
    private function extendCategory(): void
    {
        foreach ($this->data['category'] as $k => $v) {
            $v = (array)$v;
            $name = $this->formatCategoryTitleForValue($v["value"]);
            $v['inputName'] = 'searchbox_category['.$name.':'.$v["count"].']';
            $v['inputValue'] = $name;
            $v['inputLabel'] = $v["value"].' ('.$v["count"].')';
            $this->data['category'][$k] = $v;
        }
    }
    
    private function extendEntity(): void
    {
        foreach ($this->data['entity'] as $k => $v) {
            $v = (array)$v;
            $name = str_replace('https://vocabs.acdh.oeaw.ac.at/schema#', 'acdh:', $v["value"]);
            $v['inputName'] = 'searchbox_types['.$name.']';
            $v['inputValue'] = $name;
            $v['inputLabel'] = str_replace('acdh:', '', $name.' ('.$v["count"].')');
            $this->data['entity'][$k] = $v;
        }
    }

    /**
     * Transform the string to remove special chars
     * @param string $string
     * @return string
     */
    private function formatCategoryTitleForValue(string $string): string
    {
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
        return preg_replace('/[^A-Za-z0-9\-]/', '-', $string);
    }
}
