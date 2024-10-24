<?php
function toSnakeCase($pacalOrCamel)
{
  return ltrim(strtolower(preg_replace("/[A-Z]/", "_" . "$0", $pacalOrCamel)), '_');
}

function toPascalCase($snakeCase)
{
  $data = array_filter(explode('-', strtolower($snakeCase)));
  $pascalCase = '';
  foreach ($data as $word)
    $pascalCase .= ucwords($word);

  return $pascalCase;
}

function toSnakeCasePlural($pacalOrCamel)
{
  $poc = toSnakeCase($pacalOrCamel);
  return substr($poc, -1) === 'y' ? substr($poc, 0, -1) . 'ies' : $poc . 's';
}

function toSingular($word)
{
  return substr($word, -3) == 'ies'
    ? substr($word, 0, -3) . 'y'
    : (substr($word, -1) == 's' ? substr($word, 0, -1) : $word);
}

function toKebabCase($pacalOrCamel)
{
  return str_replace('_', '-', toSnakeCase($pacalOrCamel));
}

function getStrArray($array)
{
  $str = '';
  foreach ($array as $s)
    $str .= ($str == '' ? '' : ',') . $s;
  return $str;
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

function inEnv($key) {
  if (!isset($_ENV[$key])) {
    logError(SC_ERROR_NOT_FOUND, "$key not configured in .env file");
    return false;
  }

  return true;
}

function loadEnv() {
  if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/.env')) {
    logError(SC_ERROR_NOT_FOUND, 'Not exists .env file.');
    error('Environment Error');
  }

  $dotenv = Dotenv\Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT']);
  $dotenv->load();
}
