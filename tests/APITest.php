<?php
use PHPUnit\Framework\TestCase;
use ApiBuilder\API;

class APITest extends TestCase
{
    private $api;

    public static function setUpBeforeClass(): void
    {
        eval ("
            namespace ApiBuilder; 
            function loadEnv() {    
                \$dotenv = \\Dotenv\\Dotenv::createImmutable(__DIR__,'/../example.env');
                \$dotenv->load();
            }
        ");
    }

    protected function setUp(): void
    {
        $this->api = new API('Tests');
    }

    public function testGetRequestUri()
    {
        $_SERVER['REQUEST_URI'] = 'host/api/v1/demo';

        $reflection = new ReflectionClass(API::class);
        $method = $reflection->getMethod('getRequestUri');
        $method->setAccessible(true);

        $requestUri = $method->invoke($this->api);

        $this->assertIsArray($requestUri);
        $this->assertEquals(['host', 'api', 'v1', 'demo'], $requestUri);
    }

    public function testGetOperationGet()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $requestUri = ['host', 'api', 'v1', 'resource'];

        $reflection = new ReflectionClass(API::class);
        $method = $reflection->getMethod('getOperation');
        $method->setAccessible(true);

        $operation = $method->invokeArgs($this->api, [$requestUri]);
        $this->assertEquals('get', $operation);
    }

    public function testGetOperationPost()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $requestUri = ['host', 'api', 'v1', 'resource'];

        $reflection = new ReflectionClass(API::class);
        $method = $reflection->getMethod('getOperation');
        $method->setAccessible(true);

        $operation = $method->invokeArgs($this->api, [$requestUri]);
        $this->assertEquals('post', $operation);
    }

    public function testGetOperationPut()
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $requestUri = ['host', 'api', 'v1', 'resource'];

        $reflection = new ReflectionClass(API::class);
        $method = $reflection->getMethod('getOperation');
        $method->setAccessible(true);

        $operation = $method->invokeArgs($this->api, [$requestUri]);
        $this->assertEquals('put', $operation);
    }

    public function testGetOperationDelete()
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $requestUri = ['host', 'api', 'v1', 'resource'];

        $reflection = new ReflectionClass(API::class);
        $method = $reflection->getMethod('getOperation');
        $method->setAccessible(true);

        $operation = $method->invokeArgs($this->api, [$requestUri]);
        $this->assertEquals('delete', $operation);
    }

    public function testGetOperationGetOperation()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $requestUri = ['host', 'api', 'v1', 'resource', 'operation'];

        $reflection = new ReflectionClass(API::class);
        $method = $reflection->getMethod('getOperation');
        $method->setAccessible(true);

        $operation = $method->invokeArgs($this->api, [$requestUri]);
        $this->assertEquals('getOperation', $operation);
    }

    public function testGetOperationPostOperation()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $requestUri = ['host', 'api', 'v1', 'resource', 'operation'];

        $reflection = new ReflectionClass(API::class);
        $method = $reflection->getMethod('getOperation');
        $method->setAccessible(true);

        $operation = $method->invokeArgs($this->api, [$requestUri]);
        $this->assertEquals('postOperation', $operation);
    }

    public function testGetOperationCompound()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $requestUri = ['host', 'api', 'v1', 'resource', 'operation-compound-method'];

        $reflection = new ReflectionClass(API::class);
        $method = $reflection->getMethod('getOperation');
        $method->setAccessible(true);

        $operation = $method->invokeArgs($this->api, [$requestUri]);
        $this->assertEquals('postOperationCompoundMethod', $operation);
    }

    public function testGetResource()
    {
        $requestUri = ['host', 'api', 'v1', 'resource'];

        $reflection = new ReflectionClass(API::class);
        $method = $reflection->getMethod('getResource');
        $method->setAccessible(true);

        $operation = $method->invokeArgs($this->api, [$requestUri]);
        $this->assertEquals('Resource', $operation);
    }

    public function testGetResourceCompound()
    {
        $requestUri = ['host', 'api', 'v1', 'resource-compound-test'];

        $reflection = new ReflectionClass(API::class);
        $method = $reflection->getMethod('getResource');
        $method->setAccessible(true);

        $operation = $method->invokeArgs($this->api, [$requestUri]);
        $this->assertEquals('ResourceCompoundTest', $operation);
    }

    public function testGetClassResource()
    {
        eval ('namespace Tests; class TestResource {}');

        $reflection = new ReflectionClass(API::class);
        $method = $reflection->getMethod('getClassResource');
        $method->setAccessible(true);

        $class = $method->invokeArgs($this->api, ['TestResource']);
        $this->assertEquals("Tests\\TestResource", $class);
    }

    public function testGetInstanceResourceService()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $requestUri = ['host', 'api', 'v1', 'resource', 'operation'];

        eval ('namespace Tests; class Resource { public function postOperation() {} }');

        $reflection = new ReflectionClass(API::class);
        $method = $reflection->getMethod('getInstanceResourceService');
        $method->setAccessible(true);

        $instance = $method->invokeArgs($this->api, [$requestUri]);
        $this->assertTrue(is_callable(array($instance, 'postOperation')));
    }

    public function testRun()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = 'host/api/v1/test-run/operation';

        eval ('namespace Tests; class TestRun { public function postOperation() { success("OK"); } }');

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": true,"result": "OK"}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorRequestNotExists()
    {
        $_SERVER['REQUEST_URI'] = '';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Request not exists."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorOperationNotExists()
    {
        $_SERVER['REQUEST_URI'] = 'host/api/v1/test-error-operation/operation';

        eval ('namespace Tests; class TestErrorOperation { }');

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Operation \'postOperation\' not exists."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorResourceNotExists()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = 'host/api/v1';

        eval ('namespace Tests; class TestErrorResource { }');

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Do not have resource (host/api/version/[resource!!!]."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorClassResourceNotExists()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = 'host/api/v1/class-Test';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Resource or Entity \'class_tests\' not exists with \'POST\' method."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }
}