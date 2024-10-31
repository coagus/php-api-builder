<?php
namespace ApiBuilder\ORM;

class APIDB
{
  private $project;
  private $db;
  protected $entity;
  protected $priId;
  protected $secId;

  public function __CONSTRUCT($project = '', $entity = '', $priId = '', $secId = '', $db = null)
  {
    $this->project = $project;
    $this->entity = toSingular($entity);
    $this->priId = $priId;
    $this->secId = $secId;
    $this->db = $db ?? new DataBase();
  }

  public function getEmptyEntity()
  {
    $dbEntity = toSnakeCasePlural($this->entity);
    if (!$this->db->existsEntity($dbEntity))
      error("Resource or Entity '$dbEntity' not exists with '" . $_SERVER['REQUEST_METHOD'] . "' method.");

    $class = "$this->project\\Entities\\$this->entity";
    if (!class_exists($class))
      error("Entity '$this->entity' Class is not defined.");

    return new $class();
  }

  public function getFilledEntity()
  {
    $entity = $this->getEmptyEntity();

    $input = getImput();

    if (empty($input))
      error("input is empty.");

    foreach ($input as $key => $value) {
      if (!array_key_exists($key, get_object_vars($entity)))
        error("key '$key' not exists");

      $entity->$key = $value;
    }

    return $entity;
  }

  private function save($id = '')
  {
    $entity = $this->getFilledEntity();
    $entity->id = $id ?: null;
    $entity->save();
    
    success(['id' => $id ?: $entity->lastInsertId()]);
  }

  public function post()
  {
    $this->save();
  }

  public function get()
  {
    $entity = $this->getEmptyEntity();
    $result = empty($this->priId) ? $entity->getAll() : $entity->getById($this->priId);

    if (empty($result))
      error('Not exists');

    success($result);
  }

  public function put()
  {
    $this->save($this->priId);
  }

  public function delete()
  {
    $this->getEmptyEntity()->delete($this->priId);
    success('Deleted!');
  }
}