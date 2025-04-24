<?php
namespace DemoApi;

class Demo
{
  public function get()
  {
    success('Hello World!');
  }

  public function postHello()
  {
    $input = getInput();
    success('Hello ' . $input['name'] . '!');
  }

  public function getDebug() 
  {
    debug('tag1', 'debug1');
    debug('tag2', 'debug2');
    success('test');
  }
}