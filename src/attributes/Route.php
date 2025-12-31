<?php
namespace ApiBuilder\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Route
{
  public function __construct(
    public string $path
  ) {}
}