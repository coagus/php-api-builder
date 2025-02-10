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
}