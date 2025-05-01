<?php
namespace ApiBuilder;

class API
{
  private $project;

  public function __CONSTRUCT($project)
  {
    $this->project = $project;
  }
  public function run()
  {
    try {
      loadEnv();
      $this->setCors();
      $requestUri = $this->getRequestUri();

      if (empty($requestUri))
        error('Request not exists.', SC_ERROR_BAD_REQUEST);

      $instanceResourceService = $this->getInstanceResourceService($requestUri);
      $operation = $this->getOperation($requestUri);

      if (!is_callable(array($instanceResourceService, $operation)))
        error("Operation '$operation' not exists.", SC_ERROR_BAD_REQUEST);

      call_user_func(array($instanceResourceService, $operation));
    } catch (\Exception $e) {
      $errno = $e->getCode() < 400 ? SC_ERROR_NOT_FOUND : $e->getCode();
      logError($errno, $e->getMessage(), $e->getFile(), $e->getLine());
      return response($errno, $e->getMessage());
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

  private function getResource($requestUri)
  {
    if (!isset($requestUri[URI_RESOURCE]) || $requestUri[URI_RESOURCE] == '')
      error('Do not have resource (host/api/version/[resource!!!].', SC_ERROR_BAD_REQUEST);

    return toPascalCase(toSingular($requestUri[URI_RESOURCE]));
  }

  private function getClassResource($resource)
  {
    $service = "$this->project\\$resource";
    $class = class_exists($service) ? $service : APIDB;

    if (!class_exists($class))
      error("$service not exists and APIDB Class is not defined.");

    return $class;
  }

  private function getInstanceResourceService($requestUri)
  {
    $resource = $this->getResource($requestUri);
    $class = $this->getClassResource($resource);

    $prmId = isset($requestUri[URI_OPERATION_PRIMARY_ID]) && is_numeric($requestUri[URI_OPERATION_PRIMARY_ID])
      ? $requestUri[URI_OPERATION_PRIMARY_ID] : '';

    $scnId = isset($requestUri[URI_SECONDARY_ID]) && is_numeric($requestUri[URI_SECONDARY_ID])
      ? $requestUri[URI_SECONDARY_ID] : '';

    return new $class($this->project, $resource, $prmId, $scnId);
  }

  private function getOperation($requestUri)
  {
    $operation = isset($requestUri[URI_OPERATION])
      ? $requestUri[URI_OPERATION]
      : (isset($requestUri[URI_OPERATION_PRIMARY_ID]) && !is_numeric($requestUri[URI_OPERATION_PRIMARY_ID])
        ? toPascalCase($requestUri[URI_OPERATION_PRIMARY_ID])
        : '');
    return strtolower($_SERVER['REQUEST_METHOD']) . $operation;
  }
}
