<?php
namespace ApiBuilder\ORM;

class SqlBuilder
{
  private $entity;
  private $keysToExlude;

  public function __CONSTRUCT($entity, $keysToExlude)
  {
    $this->entity = $entity;
    $this->keysToExlude = [];
    $this->keysToExlude = array_merge($this->keysToExlude, $keysToExlude);
  }

  public function getObj($obj, $keysToExlude = [])
  {
    $exclude = array_merge($this->keysToExlude, $keysToExlude);
    return array_diff_key(get_object_vars($obj), array_flip($exclude));
  }

  public function fillAliasFields($obj)
  {
    $fieldsArray = array_map(
      fn($key) => toSnakeCase($key) === $key ? $key : toSnakeCase($key) . " AS " . $key,
      array_keys($this->getObj($obj))
    );

    return implode(', ', $fieldsArray);
  }

  public function fillWhere($obj)
  {
    $where = array_filter($this->getObj($obj), fn($value) => $value !== null);

    return empty($where)
      ? ''
      : 'WHERE ' . implode(' AND ', array_map(
        fn($key, $value) => toSnakeCase($key) . " = '$value'",
        array_keys($where),
        $where
      ));
  }

  public function getAll($obj)
  {
    return "SELECT " . $this->fillAliasFields($obj) . " FROM $this->entity";
  }

  public function getById($obj, $id)
  {
    return "SELECT " . $this->fillAliasFields($obj) .
      " FROM $this->entity WHERE id = $id";
  }

  public function getWhere($obj)
  {
    return "SELECT " . $this->fillAliasFields($obj) .
      " FROM $this->entity " . $this->fillWhere($obj);
  }

  public function getInsert($obj)
  {
    $filteredObj = array_filter($this->getObj($obj, ['id']), fn($value) => $value !== null);
    $fields = implode(',', array_map(fn($key) => toSnakeCase($key), array_keys($filteredObj)));
    $values = implode(',', array_map(fn($value) => "'$value'", $filteredObj));

    return "INSERT INTO $this->entity($fields) VALUES($values)";
  }

  public function getUpdate($obj, $id)
  {
    $filteredObj = array_filter($this->getObj($obj, ['id']), fn($value) => $value !== null);
    $update = implode(', ', array_map(
      fn($key, $value) => toSnakeCase($key) . "='$value'",
      array_keys($filteredObj),
      $filteredObj
    ));

    return "UPDATE $this->entity SET $update WHERE id = $id";
  }

  public function getPersistence($obj)
  {
    $id = $this->getObj($obj)['id'];
    return empty($id) ? $this->getInsert($obj) : $this->getUpdate($obj, $id);
  }

  public function getDelete($id)
  {
    return "DELETE FROM $this->entity WHERE id = $id";
  }
}