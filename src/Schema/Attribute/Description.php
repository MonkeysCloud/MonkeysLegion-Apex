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
 * Describes a schema property for LLM context.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Description
{
    public function __construct(
        public readonly string $text,
    ) {}
}
