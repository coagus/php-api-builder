<?php
function response($code, $result)
{
  $successful = $code < 400;
  $json = array(
    'successful' => $successful,
    'result' => $result
  );

  header('Content-Type: application/json');
  echo json_encode($json, http_response_code($code));
  exit;
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
  response($errno < 400 ? SC_ERROR_NOT_FOUND : $errno, $errstr);
}

function exceptionHandler($exception)
{
  $error = $exception->getMessage() . ', ' . $exception->getStrArray();
  logError('', $error, $exception->getFile(), $exception->getLine());
  response(SC_ERROR_NOT_FOUND, 'Exception Error: ' . $error);
}

function shutdownHandler()
{
  $error = error_get_last();
  if ($error !== null && $error['type'] === E_ERROR) {
    logError('', $error['message'], $error['file'], $error['line']);
    response(SC_ERROR_NOT_FOUND, 'Fatal Error: ' . $error['message']);
  }
}

set_error_handler("errorHandler");
set_exception_handler("exceptionHandler");
register_shutdown_function("shutdownHandler");