<?php

use ApiBuilder\ApiException;
function response($code, $result)
{
  $successful = $code < 400;

  $json = array(
    'successful' => $successful,
    'status' => $code,
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
  throw new ApiException($msg, $code);
}

function success($result, $code = SC_SUCCESS_OK)
{
  response($code, $result);
}


function toSnakeCase($pacalOrCamel)
{
  return ltrim(strtolower(preg_replace("/[A-Z]/", "_" . "$0", $pacalOrCamel)), '_');
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

function logError($e, $msg = '', $qry = '')
{
  $date = new DateTimeImmutable();
  $codeError = $date->format("isu");
  $filename = "log/error.csv";
  $dirname = dirname($filename);
  $mkdir = false;

  if (is_dir($dirname)) {
    if (filesize($dirname) > 10000) {
      rename($filename, $filename . $date->format("Y-m-d-is"));
      $mkdir = true;
    }
  } else {
    $mkdir = true;
  }

  if ($mkdir) {
    mkdir($dirname, 0755, true);
    $handle = fopen($filename, "a");
    $reg = array('Datetime', 'CodeError', 'Message', 'Exception', 'Query', 'SessionId');
    fputcsv($handle, $reg);
    fclose($handle);
  }

  $handle = fopen($filename, "a");
  $reg = array(date("Y-m-d H:i:s"), $codeError, $msg, $e, $qry, $_SESSION["id"]);
  fputcsv($handle, $reg);
  fclose($handle);
  return $codeError;
}
