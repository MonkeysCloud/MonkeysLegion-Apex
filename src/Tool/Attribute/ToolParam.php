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

namespace MonkeysLegion\Apex\Tool\Attribute;

/**
 * Annotates a tool method parameter with metadata.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class ToolParam
{
    /**
     * @param list<mixed>|null $enum
     */
    public function __construct(
        public readonly ?string $description = null,
        public readonly ?array  $enum = null,
        public readonly ?float  $min = null,
        public readonly ?float  $max = null,
    ) {}
}
