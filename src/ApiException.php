<?php
namespace ApiBuilder;

use Exception;

class ApiException extends Exception
{
  protected $code;
  public function __CONSTRUCT($msg, $code)
  {
    parent::__construct($msg);
    $this->code = $code;
  }

  public function responseException()
  {
    response($this->code, $this->getMessage());
  }
}