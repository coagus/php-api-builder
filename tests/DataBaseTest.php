<?php
use ApiBuilder\ORM\DataBase;
use PHPUnit\Framework\TestCase;

class DataBaseTest extends TestCase
{
  private $db;
  private $pdoMock;
  private $statementMock;

  protected function setUp(): void
  {
    $this->db = new DataBase();
    $this->pdoMock = $this->createMock(PDO::class);
    $reflection = new ReflectionClass($this->db);
    $pdoProperty = $reflection->getProperty('pdo');
    $pdoProperty->setAccessible(true);
    $pdoProperty->setValue($this->db, $this->pdoMock);
    $this->statementMock = $this->createMock(PDOStatement::class);
  }

  public function testConstructorSuccess()
  {
    $this->assertInstanceOf(DataBase::class, $this->db);
  }

  public function testMutationSuccess()
  {
    $this->pdoMock->method('prepare')->willReturn($this->statementMock);
    $this->statementMock->expects($this->once())->method('execute')->willReturn(true);
    $result = $this->db->mutation("INSERT INTO test_table (column) VALUES ('value')");
    $this->assertTrue($result);
  }

  public function testQuerySingleResult()
  {
    $this->pdoMock->method('query')->willReturn($this->statementMock);
    $this->statementMock->method('fetch')->willReturn((object) ['cnt' => '1']);
    $result = $this->db->query("SELECT * FROM test_table LIMIT 1");
    $this->assertEquals((object) ['cnt' => '1'], $result);
  }

  public function testQueryResult()
  {
    $this->pdoMock->method('query')->willReturn($this->statementMock);
    $this->statementMock->method('fetch')->willReturn((object) ['cnt' => '1']);
    $this->statementMock->method('fetchAll')->willReturn([(object) ['column' => 'value']]);
    $result = $this->db->query('SELECT * FROM test_table', false);
    $this->assertEquals(
      [
        'pagination' => [
          'count' => '1',
          'page' => '0',
          'rowsPerPage' => '10'
        ],
        'data' => [(object) ['column' => 'value']]
      ],
      $result
    );
  }

  public function testLastInsertId()
  {
    $this->pdoMock->method('lastInsertId')->willReturn('1');
    $this->assertEquals($this->db->lastInsertId(), '1');
  }

  public function testExistsEntity()
  {
    $this->pdoMock->method('query')->willReturn($this->statementMock);
    $this->statementMock->method('fetch')->willReturn((object) ['cnt' => '1']);
    $this->assertEquals($this->db->existsEntity('table'), true);
  }

  public function testNotExistsEntity()
  {
    $this->pdoMock->method('query')->willReturn($this->statementMock);
    $this->statementMock->method('fetch')->willReturn((object) ['cnt' => '0']);
    $this->assertEquals($this->db->existsEntity('table'), false);
  }
}