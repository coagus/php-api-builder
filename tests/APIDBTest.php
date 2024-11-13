<?php
use PHPUnit\Framework\TestCase;
use ApiBuilder\ORM\APIDB;
use ApiBuilder\ORM\DataBase;

class APIDBTest extends TestCase
{
  private $apiDB;
  private $dbMock;

  public static function setUpBeforeClass(): void
  {
    eval ("
      namespace TestProject\\Entities;     
      class TestEntity {
        public \$id;
        public \$field;

        public function save() {}
        public function lastInsertId() {return '2';}
        public function getAll() {return ['data' => 'test'];}
        public function getById(\$id) {return ['data' => 'testId'];}
        public function delete(\$id) {}
      }
      ");

    eval ("
      namespace ApiBuilder\ORM;

      function getImput() {
          return ['field' => 'Hello'];
      }
    ");
  }

  protected function setUp(): void
  {
    $this->dbMock = $this->createMock(DataBase::class);
    $this->apiDB = new APIDB('TestProject', 'TestEntity', '', '', $this->dbMock);
    $this->dbMock->expects($this->once())
      ->method('existsEntity')
      ->willReturn(true);
  }

  public function testGetEmptyEntity()
  {
    $className = 'TestProject\\Entities\\TestEntity';
    $class = $this->apiDB->getEmptyEntity();
    $this->assertInstanceOf($className, $class);
  }

  public function testGetFilledEntity()
  {
    $class = $this->apiDB->getFilledEntity();
    $this->assertEquals('Hello', $class->field);
  }

  public function testSavePost()
  {
    $reflection = new ReflectionClass(APIDB::class);
    $method = $reflection->getMethod('save');
    $method->setAccessible(true);

    ob_start();
    $method->invokeArgs($this->apiDB, []);
    $output = ob_get_clean();

    $expected = '{"successful":true,"result":{"data":"testId"}}';
    $this->assertEquals(
      json_decode($expected, true),
      json_decode($output, true)
    );
  }

  public function testSavePut()
  {
    $reflection = new ReflectionClass(APIDB::class);
    $method = $reflection->getMethod('save');
    $method->setAccessible(true);

    ob_start();
    $method->invokeArgs($this->apiDB, ['1']);
    $output = ob_get_clean();

    $expected = '{"successful":true,"result":{"data":"testId"}}';
    $this->assertEquals(
      json_decode($expected, true),
      json_decode($output, true)
    );
  }

  public function testGetAll()
  {
    ob_start();
    $this->apiDB->get();
    $output = ob_get_clean();

    $expected = '{"successful":true,"result":{"data":"test"}}';
    $this->assertEquals(
      json_decode($expected, true),
      json_decode($output, true)
    );
  }

  public function testGetById()
  {
    $reflection = new ReflectionClass(APIDB::class);
    $priId = $reflection->getProperty('priId');
    $priId->setAccessible(true);
    $priId->setValue($this->apiDB, '1');

    ob_start();
    $this->apiDB->get();
    $output = ob_get_clean();

    $expected = '{"successful":true,"result":{"data":"testId"}}';
    $this->assertEquals(
      json_decode($expected, true),
      json_decode($output, true)
    );
  }

  public function testDelete()
  {
    ob_start();
    $this->apiDB->delete();
    $output = ob_get_clean();

    $expected = '{"successful":true,"result":"Deleted!"}';
    $this->assertEquals(
      json_decode($expected, true),
      json_decode($output, true)
    );
  }
}