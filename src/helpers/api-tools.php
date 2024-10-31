<?php
$debugData;

function response($code, $result)
{
  $successful = $code < 400;
  global $debugData;

  if (is_array($debugData) && count($debugData) > 0)
    $result = array('data' => $result, 'debug' => $debugData);

  $json = array(
    'successful' => $successful,
    'result' => $result
  );

  echo json_encode($json, http_response_code($code));
}

function debug($tag, $data)
{
  global $debugData;
  if (!is_array($debugData))
    $debugData = [];

  array_push($debugData, array($tag => $data));
}

function getImput()
{
  return json_decode(file_get_contents('php://input'), true);
}

function success($result, $code = SC_SUCCESS_OK)
{
  response($code, $result);
}

function error($msg, $code = SC_ERROR_NOT_FOUND)
{
  throw new \Exception($msg, $code);
}

function errorHandler($errno, $errstr, $errfile, $errline)
{
  logError($errno, $errstr, $errfile, $errline);
  throw new \Exception($errstr, $errno < 400 ? SC_ERROR_NOT_FOUND : $errno);
}

function shutdownHandler()
{
  $error = error_get_last();
  if ($error !== null && $error['type'] === E_ERROR) {
    logError(SC_ERROR_NOT_FOUND, $error['message'], $error['file'], $error['line']);
    response(SC_ERROR_NOT_FOUND, $error['message']);
  }
}

ini_set('display_errors', '0');
set_error_handler("errorHandler");
register_shutdown_function("shutdownHandler");