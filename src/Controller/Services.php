<?php

namespace Drupal\services\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class \Drupal\services\Controller\Services.
 */
class Services extends ControllerBase {

  /**
   * Processing the service API request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   An HTTP request object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   An route match object.
   * @param string $service_endpoint_id
   *   The service endpoint identifier.
   * @param string $service_definition_id
   *   The service definition identifier.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An HTTP response object.
   */
  public function processRequest(Request $request, RouteMatchInterface $route_match, $service_endpoint_id, $service_definition_id) {
    $resource = $this->entityManager()
      ->getStorage('service_endpoint')
      ->load($service_endpoint_id)
      ->loadResourceProvider($service_definition_id);

    return $resource
      ->createServicePluginInstance()
      ->buildRequestResponse($request, $route_match);
  }

  /**
   * Generate a CSRF session token.
   *
   * @return \Symfony\Component\HttpFoundation\Response.
   *   A HTTP response object.
   */
  public function csrfToken() {
    return new Response(
      \Drupal::csrfToken()->get('services'), 200, ['Content-Type' => 'text/plain']
    );
  }

}
