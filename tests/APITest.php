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

        eval ('
        namespace Tests; 
        
        use ApiBuilder\PublicResource;

        #[PublicResource]
        class TestRun {  
            public function postOperation() { 
                success("OK"); 
            } 
        }');

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": true,"result": "OK"}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    // Validate Error Request

    public function testErrorRequestUppercase()
    {
        $_SERVER['REQUEST_URI'] = 'HOST/API/V1/TEST';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Request should not contain uppercase letters."}';

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

    public function testErrorRequestInvalidCharacters()
    {
        $_SERVER['REQUEST_URI'] = 'host/api/v1/test<>';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Request contains invalid characters (<, >, |, .., ./ and spaces)."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorRequestEndWithSlash()
    {
        $_SERVER['REQUEST_URI'] = 'host/api/v1/test/';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Request URI should not end with a slash (/)."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorRequestNotHaveAPI()
    {
        $_SERVER['REQUEST_URI'] = 'host/error-request-not-have-api';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Do not have API: host/[api!!!]."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorRequestNotHaveVersion()
    {
        $_SERVER['REQUEST_URI'] = 'host/api';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Do not have version: host/api/[version!!!]."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorRequestBadVersion()
    {
        $_SERVER['REQUEST_URI'] = 'host/api/v1.1/test';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Version must be in format: host/api/v{number} (e.g. v1)."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorRequestNotHaveResource()
    {
        $_SERVER['REQUEST_URI'] = 'host/api/v1';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Do not have resource: host/api/version/[resource!!!]."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorRequestResourceNotPlural()
    {
        $_SERVER['REQUEST_URI'] = 'host/api/v1/table';

        eval ('namespace Tests\\Entities; class Table { }');

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Resource \'table\' must be plural."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorClassResourceNotExists()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = 'host/api/v1/class-test';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();
        $expected = '{"successful": false,"result": "Class Tests\\\\ClassTest or Tests\\\\Entities\\\\ClassTest is not defined."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorOperationIsRequiredWhenSecondaryIdIsProvided()
    {
        $_SERVER['REQUEST_URI'] = 'host/api/v1/resource/1//1';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Operation is required when secondary ID is provided: host/api/version/resource/primaryId/[operation???]/secondaryId."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorPrimaryIdIsRequiredWhenSecondaryIdIsProvided()
    {
        $_SERVER['REQUEST_URI'] = 'host/api/v1/resource//operation/1';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Primary ID is required when secondary ID is provided: host/api/version/resource/[primaryId???]/operation/secondaryId."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorSecondaryIdIsNotNumeric()
    {
        $_SERVER['REQUEST_URI'] = 'host/api/v1/resource/1/operation/not-numeric';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Secondary ID must be a number: host/api/version/resource/primaryId/operation/[secondaryId!!!]."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorSecondaryResourceNotPlural()
    {
        $_SERVER['REQUEST_URI'] = 'host/api/v1/tables/1/other-table/1';

        eval ('namespace Tests\\Entities; class OtherTable { }');

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Resource \'other-table\' must be plural."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorSecondaryResourceNotExists()
    {
        $_SERVER['REQUEST_URI'] = 'host/api/v1/tables/1/not-exists/1';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Must be entity class \'Tests\\\\Entities\\\\NotExist\' if secondary Id is provided."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorOperationIsRequiredWhenPrimaryIdIsProvided()
    {
        $_SERVER['REQUEST_URI'] = 'host/api/v1/resource//operation';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Primary ID is required when operation is provided: host/api/version/resource/[primaryId???]/operation."}';

        $this->assertEquals(
            json_decode($expected, true),
            json_decode($output, true)
        );
    }

    public function testErrorPrimaryIdIsNotNumeric()
    {
        $_SERVER['REQUEST_URI'] = 'host/api/v1/resource/not-numeric/operation';

        ob_start();
        $this->api->run();
        $output = ob_get_clean();

        $expected = '{"successful": false,"result": "Primary ID must be a number: host/api/version/resource/[primaryId!!!]/operation."}';

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
}