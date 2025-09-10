<?php
namespace ApiBuilder\ORM;

use ApiBuilder\ORM\SqlBuilder;
class Entity extends DataBase
{
  private $sql;

  public function __CONSTRUCT($isLocal = false)
  {
    parent::__construct();
    $entityClass = array_filter(explode('\\', get_class($this)));
    $entity = toSnakeCasePlural($entityClass[count($entityClass) - 1]);
    $this->sql = new SqlBuilder($entity, ['sql']);
  }

  public function getAll()
  {
    return $this->query($this->sql->getAll($this), false, 'id DESC');
  }

  public function getById($id)
  {
    return $this->query($this->sql->getById($this, $id));
  }

  public function getWhere()
  {
    return $this->query($this->sql->getWhere($this), false);
  }

  public function save()
  {
    return $this->mutation($this->sql->getPersistence($this));
  }

  public function delete($id)
  {
    return $this->mutation($this->sql->getDelete($id));
  }
}