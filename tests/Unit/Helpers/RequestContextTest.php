<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Helpers\RequestContext;

beforeEach(function () {
    RequestContext::reset();
});

test('set and get request ID', function () {
    $ctx = RequestContext::getInstance();
    $ctx->setRequestId('abc-123');

    expect($ctx->getRequestId())->toBe('abc-123');
});

test('singleton returns same instance', function () {
    $ctx1 = RequestContext::getInstance();
    $ctx2 = RequestContext::getInstance();

    expect($ctx1)->toBe($ctx2);
});

test('reset clears the instance', function () {
    $ctx = RequestContext::getInstance();
    $ctx->setRequestId('abc-123');
    $ctx->setUserId(42);

    RequestContext::reset();
    $newCtx = RequestContext::getInstance();

    expect($newCtx->getRequestId())->toBeNull()
        ->and($newCtx->getUserId())->toBeNull();
});

test('toArray returns non-null values', function () {
    $ctx = RequestContext::getInstance();
    $ctx->setRequestId('req-123');
    $ctx->setMethod('GET');
    $ctx->setUri('/api/v1/users');

    $array = $ctx->toArray();

    expect($array)->toBe([
        'request_id' => 'req-123',
        'method' => 'GET',
        'uri' => '/api/v1/users',
    ]);
});
