<?php
namespace ApiBuilder\ORM;

use ApiBuilder\ORM\SqlBuilder;
use ApiBuilder\Auth;
class Entity extends DataBase
{
  private $sql;
  protected $jwt;
  private $isLocal;

  public function __CONSTRUCT($isLocal = false)
  {
    parent::__construct();
    $entityClass = array_filter(explode('\\', get_class($this)));
    $entity = toSnakeCasePlural($entityClass[count($entityClass) - 1]);
    $this->sql = new SqlBuilder($entity, ['sql', 'jwt', 'local']);
    $this->jwt = new Auth();
    $this->isLocal = $isLocal;
  }

  public function getAll()
  {
    if ($this->isLocal || $this->jwt->validToken())
      return $this->query($this->sql->getAll($this), false, 'id DESC');
  }

  public function getById($id)
  {
    if ($this->isLocal || $this->jwt->validToken())
      return $this->query($this->sql->getById($this, $id));
  }

  public function getWhere()
  {
    if ($this->isLocal || $this->jwt->validToken())
      return $this->query($this->sql->getWhere($this), false);
  }

  public function save()
  {
    if ($this->isLocal || $this->jwt->validToken())
      return $this->mutation($this->sql->getPersistence($this));
  }

  public function delete($id)
  {
    if ($this->isLocal || $this->jwt->validToken())
      return $this->mutation($this->sql->getDelete($id));
  }
}