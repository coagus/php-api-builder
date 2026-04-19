<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Validation\Attributes\In;
use Coagus\PhpApiBuilder\Validation\Validator;
use Tests\Fixtures\Entities\TestArticle;

test('In attribute normalizes array-form constructor to a flat list', function () {
    $attr = new In(['draft', 'published', 'archived']);

    expect($attr->values)->toBe(['draft', 'published', 'archived']);
});

test('In attribute preserves spread-form constructor as a flat list', function () {
    $attr = new In('draft', 'published', 'archived');

    expect($attr->values)->toBe(['draft', 'published', 'archived']);
});

test('In attribute array-form allows valid value without warnings', function () {
    $article = new TestArticle();
    $article->title = 'Hello';
    $article->status = 'draft';

    $caught = null;
    set_error_handler(function (int $no, string $msg) use (&$caught): bool {
        $caught = "{$no}: {$msg}";
        return true;
    });

    try {
        $errors = Validator::validate($article);
    } finally {
        restore_error_handler();
    }

    expect($errors)->toBeNull()
        ->and($caught)->toBeNull();
});

test('In attribute array-form rejects invalid value and lists allowed values', function () {
    $article = new TestArticle();
    $article->title = 'Hello';
    $article->status = 'in-progress';

    $caught = null;
    set_error_handler(function (int $no, string $msg) use (&$caught): bool {
        $caught = "{$no}: {$msg}";
        return true;
    });

    try {
        $errors = Validator::validate($article);
    } finally {
        restore_error_handler();
    }

    expect($errors)->not->toBeNull()
        ->and($errors)->toHaveKey('status')
        ->and($errors['status'][0])->toContain('draft, published, archived')
        ->and($caught)->toBeNull();
});
