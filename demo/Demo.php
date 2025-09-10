<?php
namespace DemoApi;

use ApiBuilder\PublicResource;
use ApiBuilder\Auth;

class Demo
{
  public function get()
  {
    success('Hello World!');
  }

  #[PublicResource]
  public function getToken()
  {
    $auth = new Auth();
    $token = $auth->getToken('test');
    success(['token' => $token]);
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