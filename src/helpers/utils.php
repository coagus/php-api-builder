<?php
function toSnakeCase($pacalOrCamel)
{
  return ltrim(strtolower(preg_replace("/[A-Z]/", "_" . "$0", $pacalOrCamel)), '_');
}

function toPascalCase($kebabCase)
{
  $data = array_filter(explode('-', strtolower($kebabCase)));
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
