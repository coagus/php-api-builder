<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Http\Response;

test('creates response with correct status code', function () {
    $response = new Response(['data' => 'test'], 200);
    expect($response->getStatusCode())->toBe(200);

    $response = new Response(null, 201);
    expect($response->getStatusCode())->toBe(201);

    $response = new Response(null, 204);
    expect($response->getStatusCode())->toBe(204);
});

test('generates correct JSON output', function () {
    $data = ['name' => 'Carlos', 'email' => 'carlos@test.com'];
    $response = new Response($data, 200);

    $json = $response->toJson();
    $decoded = json_decode($json, true);

    expect($decoded)->toBe($data);
});

test('headers are added correctly', function () {
    $response = new Response(['data' => 'test']);
    $response->header('X-Custom', 'value');
    $response->header('X-Another', 'test');

    expect($response->getHeader('X-Custom'))->toBe('value')
        ->and($response->getHeader('X-Another'))->toBe('test');
});

test('default Content-Type is application/json', function () {
    $response = new Response(['data' => 'test']);
    expect($response->getHeader('Content-Type'))->toBe('application/json; charset=utf-8');
});

test('toJson returns empty string for null body', function () {
    $response = new Response(null, 204);
    expect($response->toJson())->toBe('');
});

test('status code can be changed', function () {
    $response = new Response(['data' => 'test'], 200);
    $response->setStatusCode(404);
    expect($response->getStatusCode())->toBe(404);
});

test('json static constructor works', function () {
    $response = Response::json(['foo' => 'bar'], 201);
    expect($response->getStatusCode())->toBe(201)
        ->and($response->getBody())->toBe(['foo' => 'bar']);
});
