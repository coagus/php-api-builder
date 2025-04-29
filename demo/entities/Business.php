<?php
namespace DemoApi\Entities;

use ApiBuilder\ORM\Entity;

class Business extends Entity
{
  public $id;
  public $businessName;
  public $tradeName;
}
