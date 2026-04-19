<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\ORM\Connection;
use Tests\Fixtures\Entities\TestArticle;
use Tests\Fixtures\TestArticleApidb;

beforeEach(function () {
    Connection::reset();
    Connection::configure(['driver' => 'sqlite', 'database' => ':memory:']);
    $db = Connection::getInstance();
    $db->exec(file_get_contents(__DIR__ . '/../../Fixtures/migrations.sql'));
    TestArticle::clearMetadataCache();
});

test('POST with valid In value returns 201', function () {
    $handler = new TestArticleApidb();
    $handler->setRequest(new Request(
        'POST',
        '/api/v1/articles',
        [],
        '{"title":"Launch","status":"draft"}'
    ));
    $handler->post();
    $response = $handler->getResponse();
    $body = $response->getBody();

    expect($response->getStatusCode())->toBe(201)
        ->and($body['data']['title'])->toBe('Launch')
        ->and($body['data']['status'])->toBe('draft');
});

test('POST with invalid In value returns 422 with RFC 7807 body', function () {
    $handler = new TestArticleApidb();
    $handler->setRequest(new Request(
        'POST',
        '/api/v1/articles',
        [],
        '{"title":"Launch","status":"in-progress"}'
    ));

    $warning = null;
    set_error_handler(function (int $no, string $msg) use (&$warning): bool {
        $warning = "{$no}: {$msg}";
        return true;
    });

    try {
        $handler->post();
    } finally {
        restore_error_handler();
    }

    $response = $handler->getResponse();
    $body = $response->getBody();

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getHeaders()['Content-Type'] ?? null)
            ->toBe('application/problem+json; charset=utf-8')
        ->and($body)->toHaveKey('type')
        ->and($body)->toHaveKey('title')
        ->and($body['status'])->toBe(422)
        ->and($body['errors'])->toHaveKey('status')
        ->and($body['errors']['status'][0])->toContain('draft, published, archived')
        ->and($warning)->toBeNull();
});

test('PUT with valid In value returns 200', function () {
    // seed an article
    $postHandler = new TestArticleApidb();
    $postHandler->setRequest(new Request(
        'POST',
        '/api/v1/articles',
        [],
        '{"title":"Launch","status":"draft"}'
    ));
    $postHandler->post();
    $id = $postHandler->getResponse()->getBody()['data']['id'];

    $putHandler = new TestArticleApidb();
    $putHandler->setResourceId((string) $id);
    $putHandler->setRequest(new Request(
        'PUT',
        "/api/v1/articles/{$id}",
        [],
        '{"title":"Launch","status":"published"}'
    ));
    $putHandler->put();
    $response = $putHandler->getResponse();
    $body = $response->getBody();

    expect($response->getStatusCode())->toBe(200)
        ->and($body['data']['status'])->toBe('published');
});
