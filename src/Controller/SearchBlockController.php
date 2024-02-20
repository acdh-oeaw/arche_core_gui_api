<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Description of CollectionController
 *
 * @author nczirjak
 */
class SearchBlockController extends \Drupal\Core\Controller\ControllerBase
{
    public function getData(string $lang = "en"): Response
    {
        /*
         * Usage:
         *  https://domain.com/browser/api/getSearchBlock/lng?_format=json
         */
        $object = new \Drupal\arche_core_gui_api\Object\SearchBlockObject();
        $object->init($lang);
        
        if ($object === false) {
            return new Response(array("There is no resource"), 404, ['Content-Type' => 'application/json']);
        }
        
        $result = [];
        $result['category'] = $object->getCategories();
        $result['year'] = $object->getYears();
        $result['entity'] = $object->getEntities();
       
        return new Response(json_encode($result));
        
        $build = [
            '#theme' => 'acdh-repo-gui-search-left-block',
            '#result' => $result,
            '#cache' => ['max-age' => 0],
            '#attached' => [
                'library' => [
                    'acdh_repo_gui/repo-search-ajax',
                ]
            ]
        ];
     
        return new Response(render($build));
    }
    
    public function dateFacets(): Response
    {
        try {
            $config = \acdhOeaw\arche\lib\Config::fromYaml(\Drupal::service('extension.list.module')->getPath('acdh_repo_gui') . '/config/config.yaml');
            return new Response(json_encode($config->smartSearch->dateFacets));
        } catch (Throwable $e) {
            return new Response(array(), 404, ['Content-Type' => 'application/json']);
        }
    }
}
