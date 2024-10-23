<?php
$debugData;

function debug($tag, $data)
{
  global $debugData;
  if (!is_array($debugData))
    $debugData = [];

  array_push($debugData, array($tag => $data));
}

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

  header('Content-Type: application/json');
  echo json_encode($json, http_response_code($code));
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
  errorHandler($code, $msg, '', '');
}

function errorHandler($errno, $errstr, $errfile, $errline)
{
  logError($errno, $errstr, $errfile, $errline);
  throw new \Exception($errstr, $errno < 400 ? SC_ERROR_NOT_FOUND : $errno);
}

function exceptionHandler($e)
{
  errorHandler(SC_ERROR_NOT_FOUND, $e->getMessage() . ', trace: ' . $e->getTraceAsString(), $e->getFile(), $e->getLine());
}

function shutdownHandler()
{
  $error = error_get_last();
  if ($error !== null && $error['type'] === E_ERROR) {
    errorHandler(SC_ERROR_NOT_FOUND, 'Fatal Error: ' . $error['message'], $error['file'], $error['line']);    
  }
}

set_error_handler("errorHandler");
set_exception_handler("exceptionHandler");
register_shutdown_function("shutdownHandler");