<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\CLI\Commands\MakeEntityCommand;

test('generates valid PHP file', function () {
    $cmd = new MakeEntityCommand();
    $content = $cmd->generate('Product', 'products', [], false);

    expect($content)->toContain('<?php')
        ->and($content)->toContain("class Product extends Entity")
        ->and($content)->toContain("#[Table('products')]")
        ->and($content)->toContain('#[PrimaryKey]')
        ->and($content)->toContain('public int $id');
});

test('--fields generates properties', function () {
    $cmd = new MakeEntityCommand();
    $fields = [
        ['name' => 'name', 'type' => 'string'],
        ['name' => 'price', 'type' => 'float'],
    ];
    $content = $cmd->generate('Product', 'products', $fields, false);

    expect($content)->toContain('public string $name')
        ->and($content)->toContain('public float $price')
        ->and($content)->toContain('#[Required]');
});

test('--soft-delete adds SoftDelete attribute', function () {
    $cmd = new MakeEntityCommand();
    $content = $cmd->generate('Product', 'products', [], true);

    expect($content)->toContain('#[SoftDelete]')
        ->and($content)->toContain('use Coagus\\PhpApiBuilder\\Attributes\\SoftDelete')
        ->and($content)->toContain('public ?string $deletedAt = null');
});
