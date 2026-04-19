<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Exceptions\EntityNotFoundException;
use Coagus\PhpApiBuilder\Exceptions\ValidationException;

test('ValidationException carries structured errors on a readonly property', function () {
    $errors = [
        'email' => ['must be a valid email address'],
        'name' => ['is required', 'must be at most 100 characters'],
    ];

    $e = new ValidationException($errors, 'Bad input');

    expect($e)->toBeInstanceOf(DomainException::class)
        ->and($e->errors)->toBe($errors)
        ->and($e->getMessage())->toBe('Bad input');
});

test('ValidationException is final', function () {
    $ref = new ReflectionClass(ValidationException::class);
    expect($ref->isFinal())->toBeTrue();
});

test('EntityNotFoundException is final and extends DomainException', function () {
    $ref = new ReflectionClass(EntityNotFoundException::class);
    expect($ref->isFinal())->toBeTrue()
        ->and($ref->isSubclassOf(DomainException::class))->toBeTrue();
});

test('ValidationException errors property is readonly', function () {
    $ref = new ReflectionClass(ValidationException::class);
    $property = $ref->getProperty('errors');

    expect($property->isReadOnly())->toBeTrue();
});
