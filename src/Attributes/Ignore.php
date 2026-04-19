<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Attributes;

use Attribute;

/**
 * Marks a public property as invisible to the ORM, validator, and OpenAPI generator.
 *
 * Useful for virtual property hooks that perform transformation on write (e.g. a
 * `set =>` hook that hashes a password) without exposing the property as a
 * persisted column or a schema field.
 *
 * An ignored property:
 * - is NOT included in INSERT/UPDATE (Entity::getColumnsAndValues)
 * - is NOT hydrated from SELECT rows (Entity::hydrate)
 * - is NOT read by Validator::validate (avoids triggering set-only hooks)
 * - is NOT emitted in response schemas (Entity::toArray)
 * - is NOT added to the sort/fields allowlist (QueryBuilder::getColumnAllowlist)
 * - is NOT written to the OpenAPI request/response schemas (SchemaGenerator)
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Ignore
{
}
