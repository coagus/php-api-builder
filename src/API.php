<?php
namespace ApiBuilder;

class API
{
  private $project;

  public function __CONSTRUCT($project)
  {
    $this->project = $project;
  }

  private function setCors()
  {
    header('Content-Type: application/json');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // Si no hay origin, probablemente es una app móvil
    if (empty($origin)) {
      return;
    }

    // Si existe la clase Cors, usarla para obtener los origins permitidos desde una DB por ejemplo
    $CorsClass = "$this->project\\Cors";
    if (class_exists($CorsClass)) {
      $Cors = new $CorsClass();
      $allowedOrigins = $Cors->getAllowedOrigins();
      
      if (in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
      } else {
        error('Origin not allowed', SC_ERROR_FORBIDDEN);
      }
    } else {
      // Si no existe la clase Cors, usar el valor de la variable de entorno CORS_ALLOWED_ORIGINS
      $allowedOriginsString = $_ENV['CORS_ALLOWED_ORIGINS'] ?? 'http://localhost:3000';
      $allowedOrigins = array_filter(array_map('trim', explode(',', $allowedOriginsString)));
      
      if (in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
      } else {
        error('Origin not allowed', SC_ERROR_FORBIDDEN);
      }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
      header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
      header('Access-Control-Allow-Headers: Auth, Content-Type, X-Auth-Token, Origin, Authorization');
      header('Access-Control-Max-Age: 1728000');
      header('Content-Length: 0');
      die();
    }
  }

  private function validateRequest($requestUri)
  {
    $uri = $_SERVER['REQUEST_URI'];

    $reqUri = strpos($uri, "?")
      ? array_filter(explode('?', $uri))[0]
      : $uri;

    if (preg_match('/[A-Z]/', $reqUri))
      error('Request should not contain uppercase letters.', SC_ERROR_BAD_REQUEST);

    if (empty($requestUri))
      error('Request not exists.', SC_ERROR_BAD_REQUEST);

    if (
      strpos($uri, '<') !== false ||
      strpos($uri, '>') !== false ||
      strpos($uri, '|') !== false ||
      strpos($uri, '..') !== false ||
      strpos($uri, './') !== false ||
      strpos($uri, ' ') !== false
    )
      error('Request contains invalid characters (<, >, |, .., ./ and spaces).', SC_ERROR_BAD_REQUEST);

    if (substr($uri, -1) === '/')
      error('Request URI should not end with a slash (/).', SC_ERROR_BAD_REQUEST);

    if (!isset($requestUri[URI_API]) || $requestUri[URI_API] != 'api')
      error('Do not have API: host/[api!!!].', SC_ERROR_BAD_REQUEST);

    if (!isset($requestUri[URI_VERSION]) || $requestUri[URI_VERSION] == '')
      error('Do not have version: host/api/[version!!!].', SC_ERROR_BAD_REQUEST);

    if (!preg_match('/^v\d+$/', $requestUri[URI_VERSION]))
      error('Version must be in format: host/api/v{number} (e.g. v1).', SC_ERROR_BAD_REQUEST);

    if (!isset($requestUri[URI_RESOURCE]) || $requestUri[URI_RESOURCE] == '')
      error('Do not have resource: host/api/version/[resource!!!].', SC_ERROR_BAD_REQUEST);

    $resource = $requestUri[URI_RESOURCE];
    $entityClass = "$this->project\\Entities\\" . toPascalCase(toSingular($resource));
    $resourceClass = "$this->project\\" . toPascalCase(toSingular($resource));

    if (!isPlural($resource) && class_exists($entityClass))
      error("Resource '$resource' must be plural.", SC_ERROR_BAD_REQUEST);

    if (!class_exists($resourceClass) && !class_exists($entityClass))
      error("Class $resourceClass or $entityClass is not defined.", SC_ERROR_BAD_REQUEST);

    if (!class_exists(APIDB))
      error("APIDB Class is not defined.", SC_ERROR_BAD_REQUEST);

    if (isset($requestUri[URI_SECONDARY_ID]) && !isset($requestUri[URI_OPERATION]))
      error('Operation is required when secondary ID is provided: host/api/version/resource/primaryId/[operation???]/secondaryId.', SC_ERROR_BAD_REQUEST);

    if (isset($requestUri[URI_SECONDARY_ID]) && !isset($requestUri[URI_OPERATION_PRIMARY_ID]))
      error('Primary ID is required when secondary ID is provided: host/api/version/resource/[primaryId???]/operation/secondaryId.', SC_ERROR_BAD_REQUEST);

    if (isset($requestUri[URI_SECONDARY_ID]) && !is_numeric($requestUri[URI_SECONDARY_ID]))
      error('Secondary ID must be a number: host/api/version/resource/primaryId/operation/[secondaryId!!!].', SC_ERROR_BAD_REQUEST);

    if (isset($requestUri[URI_SECONDARY_ID])) {
      $secondaryResource = $requestUri[URI_OPERATION];
      $entityClass = "$this->project\\Entities\\" . toPascalCase(toSingular($secondaryResource));

      if (class_exists($entityClass) && !isPlural($secondaryResource))
        error("Resource '$secondaryResource' must be plural.", SC_ERROR_BAD_REQUEST);

      if (!class_exists($entityClass))
        error("Must be entity class '$entityClass' if secondary Id is provided.", SC_ERROR_BAD_REQUEST);
    }

    if (isset($requestUri[URI_OPERATION]) && !isset($requestUri[URI_OPERATION_PRIMARY_ID]))
      error('Primary ID is required when operation is provided: host/api/version/resource/[primaryId???]/operation.', SC_ERROR_BAD_REQUEST);

    if (isset($requestUri[URI_OPERATION]) && !is_numeric($requestUri[URI_OPERATION_PRIMARY_ID]))
      error('Primary ID must be a number: host/api/version/resource/[primaryId!!!]/operation.', SC_ERROR_BAD_REQUEST);
  }

  private function getRequestUri()
  {
    $reqUri = strpos($_SERVER['REQUEST_URI'], "?")
      ? array_filter(explode('?', $_SERVER['REQUEST_URI']))[0]
      : $_SERVER['REQUEST_URI'];
    return array_filter(explode('/', $reqUri));
  }

  private function getInstanceResourceService($requestUri)
  {
    $resource = toPascalCase(toSingular($requestUri[URI_RESOURCE]));
    $resourceClass = "$this->project\\$resource";
    $class = class_exists($resourceClass) ? $resourceClass : APIDB;

    $prmId = isset($requestUri[URI_OPERATION_PRIMARY_ID]) && is_numeric($requestUri[URI_OPERATION_PRIMARY_ID])
      ? $requestUri[URI_OPERATION_PRIMARY_ID] : '';

    $scnId = isset($requestUri[URI_SECONDARY_ID]) && is_numeric($requestUri[URI_SECONDARY_ID])
      ? $requestUri[URI_SECONDARY_ID] : '';

    return new $class($this->project, $resource, $prmId, $scnId);
  }

  private function getOperation($requestUri)
  {
    $operation = $requestUri[URI_OPERATION] ??
      (isset($requestUri[URI_OPERATION_PRIMARY_ID]) && !is_numeric($requestUri[URI_OPERATION_PRIMARY_ID])
        ? $requestUri[URI_OPERATION_PRIMARY_ID] : '');

    return strtolower($_SERVER['REQUEST_METHOD']) . toPascalCase(toSingular($operation));
  }

  private function authorizeOperation($instanceResourceService, $operation)
  {
    $className = $instanceResourceService::class;

    $reflectionClass = new \ReflectionClass($className);
    $attributes = $reflectionClass->getAttributes(PublicResource::class);
    if (!empty($attributes))
      return true;

    $reflectionMethod = \ReflectionMethod::createFromMethodName($className . '::' . $operation);
    $attributes = $reflectionMethod->getAttributes(PublicResource::class);
    if (!empty($attributes))
      return true;

    $apiKeyClass = "$this->project\\APIKey";
    if (class_exists($apiKeyClass)) {
      $apiKey = new $apiKeyClass();
      if ($apiKey->validate())
        return true;
    } else {
      $auth = new Auth();
      if ($auth->validateSession())
        return true;
    }

    error("Operation is not authorized.", SC_ERROR_UNAUTHORIZED);
  }

  public function run()
  {
    try {
      loadEnv();
      $this->setCors();

      $requestUri = $this->getRequestUri();
      $this->validateRequest($requestUri);

      $instanceResourceService = $this->getInstanceResourceService($requestUri);
      $operation = $this->getOperation($requestUri);

      if (!is_callable([$instanceResourceService, $operation]))
        error("Operation '$operation' not exists.", SC_ERROR_BAD_REQUEST);

      $this->authorizeOperation($instanceResourceService, $operation);

      call_user_func([$instanceResourceService, $operation]);
    } catch (\Exception $e) {
      $errno = $e->getCode() < 400 ? SC_ERROR_NOT_FOUND : $e->getCode();
      logError($errno, $e->getMessage(), $e->getFile(), $e->getLine());
      return response($errno, $e->getMessage());
    }
  }
}
