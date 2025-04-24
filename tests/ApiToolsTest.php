<?php
use PHPUnit\Framework\TestCase;

class ApiToolsTest extends TestCase
{
  public function testResponse()
  {
    ob_start();
    response(SC_SUCCESS_OK, 'test');
    $output = ob_get_clean();

    $expected = '{"successful":true,"result":"test"}';
    $this->assertEquals(
      json_decode($expected, true),
      json_decode($output, true)
    );
  }

  public function testSuccess()
  {
    ob_start();
    success('test');
    $output = ob_get_clean();

    $expected = '{"successful":true,"result":"test"}';
    $this->assertEquals(
      json_decode($expected, true),
      json_decode($output, true)
    );
  }

  public function testError()
  {
    ob_start();
    try {
      error('test');
    } catch (Exception) {
      response(SC_ERROR_NOT_FOUND, 'test');
    }
    $output = ob_get_clean();

    $expected = '{"successful":false,"result":"test"}';
    $this->assertEquals(
      json_decode($expected, true),
      json_decode($output, true)
    );
  }

  public function testErrorHandler()
  {
    ob_start();
    try {
      errorHandler(0, "test", '', 0);
    } catch (Exception) {
      response(SC_ERROR_NOT_FOUND, 'test');
    }
    $output = ob_get_clean();

    $expected = '{"successful":false,"result":"test"}';
    $this->assertEquals(
      json_decode($expected, true),
      json_decode($output, true)
    );
  }

  public function testDebug()
  {
    debug('tag', "debug");

    ob_start();
    success('test');
    $output = ob_get_clean();

    $expected = '{"successful":true,"result":{"data":"test","debug":{"tag":"debug"}}}';
    $this->assertEquals(
      json_decode($expected, true),
      json_decode($output, true)
    );
  }
}