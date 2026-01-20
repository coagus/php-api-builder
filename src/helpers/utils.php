<?php
use ApiBuilder\Attributes\Table;
use ApiBuilder\Attributes\Route;

function isPlural($word)
{
  return preg_match('/[^s]s$/', $word) || preg_match('/ies$/', $word);
}

function toLowerCamelCase($snakeCase)
{
  return lcfirst(str_replace('_', '', ucwords($snakeCase, '_')));
}

function toSnakeCase($pacalOrCamel)
{
  return ltrim(strtolower(preg_replace("/[A-Z]/", "_" . "$0", $pacalOrCamel)), '_');
}

function toPascalCase($kebabCase)
{
  $data = array_filter(explode('-', $kebabCase));
  $pascalCase = '';
  foreach ($data as $word)
    $pascalCase .= ucwords($word);

  return $pascalCase;
}

function toSnakeCasePlural($pacalOrCamel)
{
  $poc = toSnakeCase($pacalOrCamel);

  // Handle words ending in 'y'
  if (substr($poc, -1) === 'y') {
    return substr($poc, 0, -1) . 'ies';
  }
  // Handle words ending in 'ss'
  else if (substr($poc, -2) === 'ss') {
    return $poc . 'es';
  }
  // Regular cases
  return $poc . 's';
}

function toSingular($word)
{
  // Handle words ending in 'ies' (e.g., 'categories' -> 'category')
  if (substr($word, -3) == 'ies') {
    return substr($word, 0, -3) . 'y';
  }
  // Handle words ending in 'sses' or 'ses' (e.g., 'businesses' -> 'business')
  else if (substr($word, -4) == 'sses' || substr($word, -3) == 'ses') {
    return substr($word, 0, -2);
  }
  // Handle regular plural words ending in 's'
  else if (substr($word, -1) == 's' && substr($word, -2) != 'ss') {
    return substr($word, 0, -1);
  }

  return $word;
}

function logError($errno, $errstr, $errfile = '', $errline = '')
{
  $log = new Monolog\Logger(SERVICE_LOG);
  $log->pushHandler(new Monolog\Handler\RotatingFileHandler(SERVICE_LOG_FILE, 5, Monolog\Logger::ERROR));
  $error = $errno >= 400 ? '[Controlled Error]' : '[Error]';
  $error .= $errfile == '' ? '' : "[File: $errfile]";
  $error .= $errline == '' ? '' : "[Line: $errline]";
  $log->error("$error $errstr");
}

function inEnv($key)
{
  if (!isset($_ENV[$key])) {
    logError(SC_ERROR_NOT_FOUND, "$key not configured in .env file");
    return false;
  }

  return true;
}

function loadEnv()
{
  if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/.env')) {
    logError(SC_ERROR_NOT_FOUND, 'Not exists .env file.');
    error('Environment Error');
  }

  $dotenv = Dotenv\Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
  $dotenv->load();
}

function getProjectPath()
{
  $dir = explode('vendor',__DIR__)[0];
  return is_dir($dir) ? $dir : $_SERVER['DOCUMENT_ROOT'].'/';
}

function toFilter($string)
{
  $singular = toSingular($string);
  // si la ultima letra es vocal, quitarla
  if (substr($singular, -1) === 'a' || substr($singular, -1) === 'e' || substr($singular, -1) === 'i' || substr($singular, -1) === 'o' || substr($singular, -1) === 'u') {
    $singular = substr($singular, 0, -1);
  }
  return $singular;
}

function getEntityClass($project, $resource)
{
  // obtener el nombre de la clase a partir del path del resource
  // resoruce: mis-productos-propios -> Clase: MiProductoPropio
  $rscWords = array_filter(explode('-', $resource));
  $rscSingularWords = array_map('toSingular', $rscWords);
  $rscPascalWords = array_map('toPascalCase', $rscSingularWords);
  $spectedClassName = implode('', $rscPascalWords);

  $resourceClass = "$project\\Entities\\$spectedClassName";
  if (class_exists($resourceClass)) {
    return $resourceClass;
  }

  // Si no se encontró con una conversión simple a singular
  // resource: sectores-proveedores -> Clase a buscar: Sector*Proveedor*.php
  $rscSingularWords = array_map('toFilter', $rscWords);
  $rscPascalWords = array_map('toPascalCase', $rscSingularWords);
  $classToFind = '*' . implode('*', $rscPascalWords) . '*.php';

  // Si no se encontró, busco en el directorio de entidades
  $entitiesPath = getProjectPath() . 'services/entities/';

  if (is_dir($entitiesPath)) {
    $files = glob($entitiesPath . $classToFind);
    $classes = array_map(fn($file) => pathinfo($file, PATHINFO_FILENAME), $files);

    foreach ($classes as $class) {
      $class = "$project\\Entities\\$class";
      if (class_exists($class)) {
        $reflection = new ReflectionClass($class);
        $tableClass = Table::class;
        $attributes = $reflection->getAttributes($tableClass);
        $table = $attributes ? $attributes[0]->getArguments()[0] : null;

        if ($table == str_replace('-', '_', $resource)) {
          return $class;
        }
      }
    }
  }

  error("Entity Class not found for resource: $resource");
}

function getClass($project, $resource)
{
  // obtener el nombre de la clase a partir del path del resource
  // resoruce: mis-productos-propios -> Clase: MiProductoPropio
  $rscWords = array_filter(explode('-', $resource));
  $rscSingularWords = array_map('toSingular', $rscWords);
  $rscPascalWords = array_map('toPascalCase', $rscSingularWords);
  $spectedClassName = implode('', $rscPascalWords);

  // Si la clase existe, retornarla MiProyecto\MiProductoPropio
  $resourceClass = "$project\\$spectedClassName";
  if (class_exists($resourceClass)) {
    return $resourceClass;
  }

  // Si no es un recurso directo, debería ser un recurso de base de datos
  // y retornará ApiBuilder\ORM\APIDB
  $resourceClass = "$project\\Entities\\$spectedClassName";
  if (class_exists($resourceClass)) {
    return APIDB;
  }

  // Si no se encontró con una conversión simple a singular
  // resource: sectores-proveedores -> Clase a buscar: Sector*Proveedor*.php
  $rscSingularWords = array_map('toFilter', $rscWords);
  $rscPascalWords = array_map('toPascalCase', $rscSingularWords);
  $classToFind = '*' . implode('*', $rscPascalWords) . '*.php';

  // Buscar las clases en el directorio de servicios
  $files = glob(getProjectPath() . "services/" . $classToFind);

  // Obtengo los nombres que deben el nombre de su respectiva clase
  $classes = array_map(fn($file) => pathinfo($file, PATHINFO_FILENAME), $files);

  // buscar el atributo Route en las clases encontradas
  foreach ($classes as $class) {
    $class = "$project\\$class";
    if (class_exists($class)) {
      $reflection = new ReflectionClass($class);
      $routeClass = Route::class;
      $attributes = $reflection->getAttributes($routeClass);

      // Obtengo el path del atributo Route
      $path = $attributes ? $attributes[0]->getArguments()[0] : null;

      // Comparo el path del atributo Route con el path del resource
      if ($path == $resource) {
        // Si coinciden, retorno la clase
        // por ejemplo: Services\SectorProveedor
        return $class;
      }
    }
  }

  // Si no se encontró, busco en el directorio de entidades
  $entitiesPath = getProjectPath() . 'services/entities/';

  if (is_dir($entitiesPath)) {
    $files = glob($entitiesPath . $classToFind);
    $classes = array_map(fn($file) => pathinfo($file, PATHINFO_FILENAME), $files);

    foreach ($classes as $class) {
      $class = "$project\\Entities\\$class";
      if (class_exists($class)) {
        $reflection = new ReflectionClass($class);  
        $tableClass = Table::class;
        $attributes = $reflection->getAttributes($tableClass);
        $table = $attributes ? $attributes[0]->getArguments()[0] : null;

        if ($table == str_replace('-', '_', $resource)) {
          return APIDB;
        }
      }
    }
  }

  error("Resource Class not found for resource: $resource");
}