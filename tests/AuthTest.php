<?php
use PHPUnit\Framework\TestCase;
use ApiBuilder\Auth;

class AuthTest extends TestCase
{
  private $auth;
  private static $headers = [];

  public static function setUpBeforeClass(): void
  {
    // Mock de apache_request_headers
    eval ("
            namespace ApiBuilder;
            function apache_request_headers() {
                return \AuthTest::\$headers;
            }
        ");
  }

  protected function setUp(): void
  {
    $this->auth = new Auth();
    $_ENV[KEY] = 'test-key';
    $_ENV[ALG] = 'HS256';
    $_ENV[EXP] = '15';
    self::$headers = [];
  }

  public function testGetToken()
  {
    $token = $this->auth->getToken('test');
    $this->assertNotEmpty($token);
  }

  public function testValidTokenWithValidToken()
  {
    // Crear un token válido primero
    $token = $this->auth->getToken('test');

    // Establecer headers con token válido
    self::$headers = ['Authorization' => 'Bearer ' . $token];

    $this->assertTrue($this->auth->validToken());
  }

  public function testValidTokenWithInvalidToken()
  {
    // Establecer headers con token inválido
    self::$headers = ['Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.invalid'];

    ob_start();
    try {
      $this->auth->validToken();
      $this->fail('Se esperaba una excepción');
    } catch (\Exception $e) {
      $this->assertTrue(true); // La excepción fue lanzada como se esperaba
    }
    ob_end_clean();
  }

  public function testValidTokenWithNoToken()
  {
    // Headers vacíos
    self::$headers = [];

    ob_start();
    try {
      $this->auth->validToken();
      $this->fail('Se esperaba una excepción');
    } catch (\Exception $e) {
      $this->assertTrue(true); // La excepción fue lanzada como se esperaba
    }
    ob_end_clean();
  }

  public function testValidTokenWhenSecurityDisabled()
  {
    $_ENV[SECURE] = 'false';
    $this->assertTrue($this->auth->validToken());
    unset($_ENV[SECURE]);
  }

  protected function tearDown(): void
  {
    self::$headers = [];
  }
}