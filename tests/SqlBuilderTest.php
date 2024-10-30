<?php
use ApiBuilder\ORM\SqlBuilder;
use PHPUnit\Framework\TestCase;

class SqlBuilderTest extends TestCase
{
  private $sql;
  private $obj;

  protected function setUp(): void
  {
    $this->sql = new SqlBuilder('Entity', ['sql']);
    $this->obj = (object) [
      'sql' => null,
      'id' => null,
      'name' => null,
      'otherField' => null
    ];
  }

  public function testGetObj()
  {
    $this->assertEquals(
      ['id' => null, 'name' => null, 'otherField' => null],
      $this->sql->getObj($this->obj)
    );
  }

  public function testFillAliasFields()
  {
    $this->assertEquals(
      'id, name, other_field AS otherField',
      $this->sql->fillAliasFields($this->obj)
    );
  }

  public function testGetAll()
  {
    $this->assertEquals(
      'SELECT id, name, other_field AS otherField FROM Entity',
      $this->sql->getAll($this->obj)
    );
  }

  public function testGetById()
  {
    $this->assertEquals(
      'SELECT id, name, other_field AS otherField FROM Entity WHERE id = 1',
      $this->sql->getById($this->obj, '1')
    );
  }

  public function testFillWhere()
  {
    $this->obj->otherField = 'something';
    $this->assertEquals(
      "WHERE other_field = 'something'",
      $this->sql->fillWhere($this->obj)
    );
  }

  public function testGetWhere()
  {
    $this->obj->name = 'Agustin';
    $this->obj->otherField = 'something';
    $this->assertEquals(
      "SELECT id, name, other_field AS otherField FROM Entity WHERE name = 'Agustin' AND other_field = 'something'",
      $this->sql->getWhere($this->obj)
    );
  }

  public function testGetInsert()
  {
    $this->obj->name = 'Agustin';
    $this->obj->otherField = 'something';
    $this->assertEquals(
      "INSERT INTO Entity(name,other_field) VALUES('Agustin','something')",
      $this->sql->getInsert($this->obj)
    );
  }

  public function testGetUpdate()
  {
    $this->obj->name = 'Agustin';
    $this->obj->otherField = 'something';
    $this->assertEquals(
      "UPDATE Entity SET name='Agustin', other_field='something' WHERE id = 1",
      $this->sql->getUpdate($this->obj, '1')
    );
  }

  public function testGetPersistenceInsert()
  {
    $this->obj->name = 'Agustin';
    $this->obj->otherField = 'something';
    $this->assertEquals(
      "INSERT INTO Entity(name,other_field) VALUES('Agustin','something')",
      $this->sql->getPersistence($this->obj)
    );
  }

  public function testGetPersistenceUpdate()
  {
    $this->obj->id = '1';
    $this->obj->name = 'Agustin';
    $this->obj->otherField = 'something';
    $this->assertEquals(
      "UPDATE Entity SET name='Agustin', other_field='something' WHERE id = 1",
      $this->sql->getPersistence($this->obj)
    );
  }

  public function testGetDelete()
  {
    $this->assertEquals(
      "DELETE FROM Entity WHERE id = 1",
      $this->sql->getDelete('1')
    );
  }
}