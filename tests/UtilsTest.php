<?php
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
  public function testToStakeCase()
  {
    $this->assertEquals('snake_case',toSnakeCase('SnakeCase'));
    $this->assertEquals('snake_case',toSnakeCase('snakeCase'));
  }

  public function testToPascalCase()
  {
    $this->assertEquals('PascalCase',toPascalCase('pascal-case'));
  }

  public function testToStakeCaseplural()
  {
    $this->assertEquals('my_entities', toSnakeCasePlural('MyEntity'));
    $this->assertEquals('my_tables', toSnakeCasePlural('MyTable'));
  }

  public function testToSingular()
  {
    $this->assertEquals('MyEntity',toSingular('MyEntities'));
    $this->assertEquals('MyTable',toSingular('MyTables'));
  }

  public function testInEnv()
  {
    $this->assertTrue(inEnv('DB_HOST'));
    $this->assertFalse(inEnv('HOST'));
  }
}