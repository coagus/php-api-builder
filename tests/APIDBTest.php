<?php
use PHPUnit\Framework\TestCase;
use ApiBuilder\ORM\APIDB;
use ApiBuilder\ORM\DataBase;
use ApiBuilder\API;

class APIDBTest extends TestCase
{
  private $apiDB;
  private $dbMock;

  private $api;

  public static function setUpBeforeClass(): void
  {
    eval ("
      namespace TestProject\\Entities;     
      class TestEntity {
        public \$id;
        public \$field;
        public \$field2;

        public function save() {}
        public function lastInsertId() {return '2';}
        public function getAll() {return ['data' => 'test'];}
        public function getById(\$id) {return \$id == '1' ? ['data' => 'testId'] : false;}
        public function delete(\$id) {}
      }
      ");

    eval ("
      namespace ApiBuilder\ORM;

      function getInput() {
          return ['field' => 'Hello'];
      }
    ");
  }

  protected function setUp(): void
  {
    $this->dbMock = $this->createMock(DataBase::class);
    $this->apiDB = new APIDB('TestProject', 'TestEntity', '', '', $this->dbMock);
    $this->dbMock->expects($this->any())
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
    $method->invokeArgs($this->apiDB, ['1']);
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

  public function testPutErrorPrimaryId()
  {
    // Configurar el ID primario
    $reflection = new ReflectionClass(APIDB::class);
    $priId = $reflection->getProperty('priId');
    $priId->setAccessible(true);
    $priId->setValue($this->apiDB, '');

    // Ejecutar el método patch
    $output = '';
    try {
      ob_start();
      $this->apiDB->put();
    } catch (\Exception $e) {
      $output = $e->getMessage();
    } finally {
      ob_get_clean();
    }

    // Verificar el resultado
    $expected = 'Primary ID is required.';
    $this->assertEquals($expected, $output);
  }

  public function testPutErrorNotExists()
  {
    // Configurar el ID primario
    $reflection = new ReflectionClass(APIDB::class);
    $priId = $reflection->getProperty('priId');
    $priId->setAccessible(true);
    $priId->setValue($this->apiDB, '2');

    // Ejecutar el método patch
    $output = '';
    try {
      ob_start();
      $this->apiDB->put();
    } catch (\Exception $e) {
      $output = $e->getMessage();
    } finally {
      ob_get_clean();
    }

    // Verificar el resultado
    $expected = 'Not exists';
    $this->assertEquals($expected, $output);
  }

  public function testPutErrorInvalidInput()
  {
    // Configurar el ID primario
    $reflection = new ReflectionClass(APIDB::class);
    $priId = $reflection->getProperty('priId');
    $priId->setAccessible(true);
    $priId->setValue($this->apiDB, '1');

    // Ejecutar el método put
    $output = '';
    try {
      ob_start();
      $this->apiDB->put();
    } catch (\Exception $e) {
      $output = $e->getMessage();
    } finally {
      ob_get_clean();
    }

    $expected = 'Invalid input, the input must be the same as the entity.';
    $this->assertEquals($expected, $output);
  }
  public function testPatch()
  {
    // Configurar el ID primario
    $reflection = new ReflectionClass(APIDB::class);
    $priId = $reflection->getProperty('priId');
    $priId->setAccessible(true);
    $priId->setValue($this->apiDB, '1');

    // Ejecutar el método patch
    ob_start();
    $this->apiDB->patch();
    $output = ob_get_clean();

    // Verificar el resultado
    $expected = '{"successful":true,"result":{"data":"testId"}}';
    $this->assertEquals(
      json_decode($expected, true),
      json_decode($output, true)
    );
  }

  public function testPatchErrorPrimaryId()
  {
    // Configurar el ID primario
    $reflection = new ReflectionClass(APIDB::class);
    $priId = $reflection->getProperty('priId');
    $priId->setAccessible(true);
    $priId->setValue($this->apiDB, '');

    // Ejecutar el método patch
    $output = '';
    try {
      ob_start();
      $this->apiDB->patch();
    } catch (\Exception $e) {
      $output = $e->getMessage();
    } finally {
      ob_get_clean();
    }

    // Verificar el resultado
    $expected = 'Primary ID is required.';
    $this->assertEquals($expected, $output);
  }

  public function testPatchErrorNotExists()
  {
    // Configurar el ID primario
    $reflection = new ReflectionClass(APIDB::class);
    $priId = $reflection->getProperty('priId');
    $priId->setAccessible(true);
    $priId->setValue($this->apiDB, '2');

    // Ejecutar el método patch
    $output = '';
    try {
      ob_start();
      $this->apiDB->patch();
    } catch (\Exception $e) {
      $output = $e->getMessage();
    } finally {
      ob_get_clean();
    }

    // Verificar el resultado
    $expected = 'Not exists';
    $this->assertEquals($expected, $output);
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
    $reflection = new ReflectionClass(APIDB::class);
    $priId = $reflection->getProperty('priId');
    $priId->setAccessible(true);
    $priId->setValue($this->apiDB, '1');

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