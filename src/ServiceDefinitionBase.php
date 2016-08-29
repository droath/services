<?php

namespace Drupal\services;

use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginBase;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class \Drupal\services\ServiceDefinitionBase.
 */
abstract class ServiceDefinitionBase extends ContextAwarePluginBase implements ContainerFactoryPluginInterface, ServiceDefinitionInterface {

  /**
   * Serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * Constructor for \Drupal\services\Controller\Services.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, SerializerInterface $serializer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('serializer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCategory() {
    return $this->pluginDefinition['category'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    return $this->pluginDefinition['path'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function supportsTranslation() {
    return $this->pluginDefinition['translatable'];
  }

  /**
   * {@inheritdoc}
   */
  public function getMethods() {
    return $this->pluginDefinition['methods'];
  }

  /**
   * {@inheritdoc}
   */
  public function getArguments() {
    return $this->pluginDefinition['arguments'];
  }

  /**
   * {@inheritdoc}
   */
  public function getResponseCode() {
    return $this->pluginDefinition['response_code'];
  }

  /**
   * {@inheritdoc}
   */
  public function processRoute(Route $route) {
    $route->addRequirements(array('_access' => 'TRUE'));
  }

  /**
   * {@inheritdoc}
   */
  public function processResponse(Response $response) {}

  /**
   * {@inheritdoc}
   */
  public function processRequest(Request $request, RouteMatchInterface $route_match) {}

  /**
   * Build the HTTP request response object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   An HTTP request object
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   An route match object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A HTTP response object.
   */
  public function buildRequestResponse(Request $request, RouteMatchInterface $route_match) {
    $data = $this
      ->setContextByRequest($request)
      ->processRequest($request, $route_match);

    $content = $this->serializeDataByRequest($request, $data);

    /*
     * Create a new Cacheable Response object with our serialized data, set its
     * Content-Type to match the format of our Request and add the service
     * definition plugin as a cacheable dependency.
     *
     * This last step will extract the cache context, tags and max-ages from
     * any context the plugin required to operate.
     */
    $response = (new CacheableResponse(
      $content,
      $this->getResponseCode(),
      $this->getResponseHeadersByRequest($request)
    ))
    ->setVary('Accept')
    ->addCacheableDependency($this);

    $this->processResponse($response);

    return $response;
  }

  /**
   * Set service context based on request object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   An HTTP request object.
   */
  protected function setContextByRequest(Request $request) {
    foreach ($this->getContextDefinitions() as $context_id => $definition) {
      if (!$request->attributes->has($context_id)) {
        continue;
      }
      $this->setContext(
        $context_id, new Context($definition, $request->attributes->get($context_id))
      );
    }

    return $this;
  }

  /**
   * Get response header based on request object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   An HTTP request object.
   */
  protected function getResponseHeadersByRequest(Request $request) {
    $headers = [];

    // Set the content-type header based on the request format.
    if ($format = $request->getRequestFormat()) {
      $headers['Content-Type'] = $request->getMimeType($format);
    }

    foreach (drupal_get_messages() as $type => $type_message) {
      $headers["X-Drupal-Services-Messages-$type"] = implode('; ', $type_message);
    }

    return $headers;
  }

  /**
   * Serialize data based on request object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   An HTTP request object.
   * @param array $data
   *   An array of data that need to be formatted.
   *
   * @return string
   *   A serialized representation of the data.
   */
  protected function serializeDataByRequest(Request $request, array $data = []) {
    return $this->serializer->serialize($data, $request->getRequestFormat());
  }

}
