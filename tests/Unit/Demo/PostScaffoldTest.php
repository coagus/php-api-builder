<?php

declare(strict_types=1);

/**
 * Regression guard for the demo Post service template.
 *
 * The v2.0.0-alpha.19 smoke suite caught `PATCH /posts/:id/publish` returning
 * `400 "Post ID is required"` because the scaffold read `$this->request->resourceId`
 * (a property that does not exist on Request) instead of `$this->resourceId`
 * (inherited from the Resource base class). This test freezes that fix at unit speed.
 */

test('demo Post service reads id from Resource::$resourceId, not from Request', function () {
    $template = file_get_contents(__DIR__ . '/../../../resources/demo/services/Post.php');

    expect($template)->not->toBeNull()
        ->and($template)->toContain('$this->resourceId')
        ->and($template)->not->toContain('$this->request->resourceId');
});

test('demo Post service calls Connection::transaction on an instance, not statically', function () {
    $template = file_get_contents(__DIR__ . '/../../../resources/demo/services/Post.php');

    // Regression guard: Connection::transaction is an instance method.
    // The `Connection::transaction(` static call throws at runtime.
    expect($template)->toContain('Connection::getInstance()->transaction(')
        ->and($template)->not->toMatch('/Connection::transaction\s*\(/');
});
