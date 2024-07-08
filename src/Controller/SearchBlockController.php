<?php

namespace Drupal\arche_core_gui_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Description of SearchBlockController
 *
 * @author nczirjak
 */
class SearchBlockController extends \Drupal\Core\Controller\ControllerBase {

    public function dateFacets(): Response {
        try {
            if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
                $config = \acdhOeaw\arche\lib\Config::fromYaml(\Drupal::service('extension.list.module')->getPath('arche_core_gui') . '/config/config-gui.yaml');
            } else {
                $config = \acdhOeaw\arche\lib\Config::fromYaml(\Drupal::service('extension.list.module')->getPath('arche_core_gui') . '/config/config.yaml');
            }
            return new Response(json_encode($config->smartSearch->dateFacets));
        } catch (Throwable $e) {
            return new Response(array(), 404, ['Content-Type' => 'application/json']);
        }
    }
}
