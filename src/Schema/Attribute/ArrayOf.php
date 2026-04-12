<?php

declare(strict_types=1);

/**
 * MonkeysLegion Apex
 *
 * @package   MonkeysLegion\Apex
 * @author    MonkeysCloud <jorge@monkeys.cloud>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

namespace MonkeysLegion\Apex\Schema\Attribute;

/**
 * Specifies the type of array items.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ArrayOf
{
    /**
     * @param class-string|string $type Schema class or primitive type name.
     */
    public function __construct(
        public readonly string $type,
    ) {}
}
