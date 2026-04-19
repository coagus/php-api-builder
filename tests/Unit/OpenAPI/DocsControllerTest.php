<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Http\Request;
use Coagus\PhpApiBuilder\OpenAPI\DocsController;
use Coagus\PhpApiBuilder\OpenAPI\SpecBuilder;

test('Swagger HTML HTML-escapes the derived spec URL (no raw quotes / tags leak)', function () {
    $controller = new DocsController(new SpecBuilder('API', '1.0.0'));
    $malicious = '/api/v1/docs/swagger"><script>alert(1)</script>';

    $request = new Request('GET', $malicious);
    $controller->setRequest($request);
    $controller->setAction('swagger');
    $controller->get();

    $html = $controller->getResponse()->getBody();

    expect($html)->toBeString()
        ->and($html)->not->toContain('<script>alert(1)</script>')
        // When the candidate URL contains unsafe characters we fall back to
        // the default spec path, so the raw angle brackets must never appear.
        ->and($html)->not->toContain('"><script>');
});

test('ReDoc HTML HTML-escapes the derived spec URL', function () {
    $controller = new DocsController(new SpecBuilder('API', '1.0.0'));
    $malicious = '/api/v1/docs/redoc"><img src=x onerror=alert(1)>';

    $request = new Request('GET', $malicious);
    $controller->setRequest($request);
    $controller->setAction('redoc');
    $controller->get();

    $html = $controller->getResponse()->getBody();

    expect($html)->toBeString()
        ->and($html)->not->toContain('onerror=alert(1)')
        ->and($html)->not->toContain('"><img');
});
