<?php
namespace DemoApi;

use DemoApi\Entities\User;

class Demo
{
  public function get()
  {
    success('Hello World!');
  }

  public function postHello()
  {
    $input = getImput();
    success('Hello ' . $input['name'] . '!');
  }

  public function getUser()
  {
    $user = new User();
    // $user->name = 'Claudia Aragón';
    // $user->username = 'test2';
    // $user->password = 'abc123';
    // $user->email = 'otro@otro.com';
    // $user->active = 1;
    // $user->roleId = 1;
    // $user->save();

    $user->delete(2);
    success($user->getAll());
  }
}