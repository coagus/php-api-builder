<?php
namespace DemoApi;

class Demo
{
  public function post()
  {
    success('Hello World!');
  }

  public function postHello()
  {
    $input = getImput();
    success('Hello ' . $input['name'] . '!');
  }
}