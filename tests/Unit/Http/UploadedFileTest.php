<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Http\UploadedFile;

test('sanitizes file name — removes path traversal', function () {
    $file = new UploadedFile([
        'name' => '../../../etc/passwd',
        'tmp_name' => '/tmp/php123',
        'size' => 100,
        'error' => UPLOAD_ERR_OK,
    ]);

    expect($file->originalName())->toBe('passwd');
});

test('sanitizes file name — removes special characters', function () {
    $file = new UploadedFile([
        'name' => 'my file (1).jpg',
        'tmp_name' => '/tmp/php123',
        'size' => 100,
        'error' => UPLOAD_ERR_OK,
    ]);

    $name = $file->originalName();
    expect($name)->not->toContain(' ')
        ->and($name)->not->toContain('(')
        ->and($name)->toContain('.jpg');
});

test('extracts extension correctly', function () {
    $file = new UploadedFile([
        'name' => 'photo.JPG',
        'tmp_name' => '/tmp/php123',
        'size' => 100,
        'error' => UPLOAD_ERR_OK,
    ]);

    expect($file->extension())->toBe('jpg');
});

test('validates file size', function () {
    $file = new UploadedFile([
        'name' => 'file.pdf',
        'tmp_name' => '/tmp/php123',
        'size' => 5 * 1024 * 1024, // 5MB
        'error' => UPLOAD_ERR_OK,
    ]);

    expect($file->validateMaxSize(10 * 1024 * 1024))->toBeTrue()  // 10MB max
        ->and($file->validateMaxSize(1 * 1024 * 1024))->toBeFalse(); // 1MB max
});

test('validates MIME type against allowed list', function () {
    // Create a real temp file for MIME detection
    $tmpFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tmpFile, 'plain text content');

    $file = new UploadedFile([
        'name' => 'file.txt',
        'tmp_name' => $tmpFile,
        'size' => 100,
        'error' => UPLOAD_ERR_OK,
    ]);

    expect($file->validateType(['text/plain']))->toBeTrue()
        ->and($file->validateType(['image/jpeg', 'image/png']))->toBeFalse();

    unlink($tmpFile);
});

test('isValid returns false for upload errors', function () {
    $file = new UploadedFile([
        'name' => 'file.txt',
        'tmp_name' => '/tmp/nonexistent',
        'size' => 100,
        'error' => UPLOAD_ERR_NO_FILE,
    ]);

    expect($file->isValid())->toBeFalse();
});

test('size returns correct value', function () {
    $file = new UploadedFile([
        'name' => 'file.txt',
        'tmp_name' => '/tmp/php123',
        'size' => 12345,
        'error' => UPLOAD_ERR_OK,
    ]);

    expect($file->size())->toBe(12345);
});
