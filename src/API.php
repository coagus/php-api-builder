<?php
namespace ApiBuilder;

class API
{
  private $project;

  public function run($project)
  {
    $this->project = $project;
    $this->setCors();
    $requestUri = $this->getRequestUri();

    try {
      if (empty($requestUri))
        error('Request not exists.', SC_ERROR_BAD_REQUEST);

      $resourceController = $this->getResourceService($requestUri);
      $operation = $this->getOperation($requestUri);

      if (!is_callable(array($resourceController, $operation)))
        error("Operation '$operation' not exists.", SC_ERROR_BAD_REQUEST);

      call_user_func(array($resourceController, $operation));
    } catch (ApiException $e) {
      $e->responseException();
    }
  }

  private function setCors()
  {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
      header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
      header('Access-Control-Allow-Headers: Auth, Content-Type, X-Auth-Token, Origin, Authorization');
      header('Access-Control-Max-Age: 1728000');
      header('Content-Length: 0');
      die();
    }
  }

  private function getRequestUri()
  {
    $reqUri = strpos($_SERVER['REQUEST_URI'], "?")
      ? array_filter(explode('?', $_SERVER['REQUEST_URI']))[0]
      : $_SERVER['REQUEST_URI'];
    return array_filter(explode('/', $reqUri));
  }

  private function getResourceService($requestUri)
  {
    $resource = $requestUri[URI_RESOURCE];

    if ($resource == '')
      error('Do not have resource (url/api/version/[resource!!!].');

    $singularResource = toSingular($resource);
    $service = "$this->project\\" . ucwords($singularResource);
    $class = class_exists($service) ? $service : 'APIBuilder\\Service';

    $prmId = isset($requestUri[URI_OPERATION_PRIMARY_ID]) && is_numeric($requestUri[URI_OPERATION_PRIMARY_ID])
      ? $requestUri[URI_OPERATION_PRIMARY_ID] : '';

    $scnId = isset($requestUri[URI_SECONDARY_ID]) && is_numeric($requestUri[URI_SECONDARY_ID])
      ? $requestUri[URI_SECONDARY_ID] : '';

    return new $class($singularResource, $prmId, $scnId);
  }

  private function getOperation($requestUri)
  {
    $operation = isset($requestUri[URI_OPERATION])
      ? $requestUri[URI_OPERATION]
      : (isset($requestUri[URI_OPERATION_PRIMARY_ID]) && !is_numeric($requestUri[URI_OPERATION_PRIMARY_ID])
        ? ucwords($requestUri[URI_OPERATION_PRIMARY_ID])
        : '');
    return strtolower($_SERVER['REQUEST_METHOD']) . $operation;
  }
}