<?php
namespace ApiBuilder\ORM;

class Entity extends DataBase
{
  private $entity;

  public function __CONSTRUCT()
  {
    parent::__construct();
    $entityClass = array_filter(explode('\\', get_class($this)));
    $this->entity = toSnakeCasePlural($entityClass[count($entityClass) - 1]);
  }

  private function fillAliasFields()
  {
    $fields = '';

    foreach (get_object_vars($this) as $key => $value)
      if ($key != 'entity')
        $fields .= toSnakeCase($key) == $key ? "$key, " : toSnakeCase($key) . " AS $key, ";

    return substr($fields, 0, -2);
  }

  private function insert()
  {
    $fields = '';
    $values = '';

    foreach (get_object_vars($this) as $key => $value)
      if ($value != null && $key != 'id' && $key != 'entity') {
        $fields .= toSnakeCase($key) . ',';
        $values .= "'$value',";
      }

    $fields = substr($fields, 0, -1);
    $values = substr($values, 0, -1);

    return $this->mutation("INSERT INTO $this->entity($fields) VALUES($values)");
  }

  private function update()
  {
    $obj = get_object_vars($this);
    $update = '';

    foreach ($obj as $key => $value)
      if ($value != null && $key != 'id' && $key != 'entity')
        $update .= toSnakeCase($key) . "='$value', ";

    $update = substr($update, 0, -2);

    return $this->mutation("UPDATE $this->entity SET $update where id = " . $obj['id']);
  }

  public function save()
  {
    $obj = get_object_vars($this);
    return $obj['id'] == '' ? $this->insert() : $this->update();
  }

  public function getAll()
  {
    return $this->query("SELECT " . $this->fillAliasFields() . " FROM $this->entity", false, 'id DESC');
  }

  public function getById($id)
  {
    return $this->query("SELECT " . $this->fillAliasFields() . " FROM $this->entity WHERE id = $id");
  }

  public function getWhere()
  {
    $where = '';
    foreach (get_object_vars($this) as $key => $value)
      if ($value != null && $key != 'entity')
        $where .= toSnakeCase($key) . "='$value' and ";

    $where = $where == '' ? '' : ' where ' . substr($where, 0, -4);

    return $this->query("SELECT " . $this->fillAliasFields() . " FROM $this->entity $where", false);
  }

  public function delete($id)
  {
    return $this->mutation("DELETE FROM $this->entity WHERE id = $id");
  }
}