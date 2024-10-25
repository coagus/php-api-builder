<?php
namespace DemoApi\Entities;

use ApiBuilder\ORM\Entity;

class User extends Entity
{
  public $id;
  public $name;
  public $username;
  public $password;
  public $email;
  public $active;
  public $roleId;
}