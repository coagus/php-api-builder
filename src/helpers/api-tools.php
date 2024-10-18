<?php
function response($code, $result)
{
  $successful = $code < 400;

  $json = array(
    'successful' => $successful,
    'result' => $result
  );

  echo json_encode($json, http_response_code($code));
}

function getImput()
{
  return json_decode(file_get_contents('php://input'), true);
}

function error($msg, $code = SC_ERROR_NOT_FOUND)
{
  throw new ApiBuilder\ApiException($msg, $code);
}

function success($result, $code = SC_SUCCESS_OK)
{
  response($code, $result);
}