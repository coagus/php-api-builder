<?php
namespace ApiBuilder\ORM;

use ApiBuilder\ORM\SqlBuilder;
use ApiBuilder\Attributes\Table;
use ReflectionClass;

class Entity extends DataBase
{
  private $sql;

  public function __CONSTRUCT()
  {
    parent::__construct();
    $reflection = new ReflectionClass(\get_class($this));
    $tableClass = Table::class;
    $attributes = $reflection->getAttributes($tableClass);
    $table = $attributes ? $attributes[0]->getArguments()[0] : null;

    $entityClass = array_filter(explode('\\', \get_class($this)));

    $entity = $table ?? toSnakeCasePlural($entityClass[count($entityClass) - 1]);
    $this->sql = new SqlBuilder($entity, ['sql']);
  }

  protected function getBooleanFields()
  {
    return [];
  }

  private function convertObjectBooleanFields($object, $booleanFields)
  {
    foreach ($booleanFields as $field) {
      if (property_exists($object, $field)) {
        $object->$field = $object->$field === null ? false : (bool) $object->$field;
      }
    }
  }

  private function convertBooleanFields($data)
  {
    $booleanFields = $this->getBooleanFields();

    if (!$data || empty($data) || empty($booleanFields)) {
      return $data;
    }

    if (\is_object($data)) {
      $this->convertObjectBooleanFields($data, $booleanFields);
    } else {
      foreach ($data['data'] as $item) {
        $this->convertObjectBooleanFields($item, $booleanFields);
      }
    }

    return $data;
  }

  public function getAll()
  {
    return $this->convertBooleanFields($this->query($this->sql->getAll($this), false, 'id DESC'));
  }

  public function getById($id)
  {
    return $this->convertBooleanFields($this->query($this->sql->getById($this, $id)));
  }

  public function getWhere()
  {
    return $this->convertBooleanFields($this->query($this->sql->getWhere($this), false));
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