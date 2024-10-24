<?php
namespace DemoApi;

use ApiBuilder\ORM\DataBase;

class Demo
{
  public function post()
  {
    $db = new DataBase();
    // $db->mutation("insert into users (name, username, password, email, active, role_id) values ('Agustin','coagus','abc123','algo@algo.com',0,1)");
    $result = $db->query("select * from user", false);
    success($result);
  }

  public function postHello()
  {
    $input = getImput();
    success('Hello ' . $input['name'] . '!');
  }
}