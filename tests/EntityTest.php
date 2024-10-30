<?php
use PHPUnit\Framework\TestCase;
use ApiBuilder\ORM\Entity;
use ApiBuilder\ORM\SqlBuilder;

class EntityTest extends TestCase
{
  private $entityMock;
  private $sqlMock;

  protected function setUp(): void
  {
    $this->sqlMock = $this->createMock(SqlBuilder::class);
    $this->entityMock = $this->getMockBuilder(Entity::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['query', 'mutation'])
      ->getMock();

    $reflectionClass = new ReflectionClass($this->entityMock);
    $sqlProperty = $reflectionClass->getParentClass()->getProperty('sql');
    $sqlProperty->setAccessible(true);
    $sqlProperty->setValue($this->entityMock, $this->sqlMock);
  }

  public function testGetAll()
  {
    $this->sqlMock->method('getAll')->willReturn('SELECT * FROM test_table');
    $this->entityMock->method('query')->willReturn(['mockedResult']);
    $this->assertEquals(['mockedResult'], $this->entityMock->getAll());
  }

  public function testGetById()
  {
    $this->sqlMock->method('getById')->willReturn('SELECT * FROM test_table WHERE id = 1');
    $this->entityMock->method('query')->willReturn(['mockedResult']);
    $this->assertEquals(['mockedResult'], $this->entityMock->getById('1'));
  }

  public function testGetWhere()
  {
    $this->sqlMock->method('getWhere')->willReturn('SELECT * FROM test_table WHERE field = 1');
    $this->entityMock->method('query')->willReturn(['mockedResult']);
    $this->assertEquals(['mockedResult'], $this->entityMock->getWhere());
  }

  public function testSave()
  {
    $this->sqlMock->method('getPersistence')->willReturn('UPDATE test_table SET field = 1 WHERE id = 1');
    $this->entityMock->method('mutation')->willReturn(true);
    $this->assertTrue($this->entityMock->save());
  }

  public function testDelete()
  {
    $this->sqlMock->method('getDelete')->willReturn('DELETE test_table WHERE id = 1');
    $this->entityMock->method('mutation')->willReturn(true);
    $this->assertTrue($this->entityMock->delete('1'));
  }
}