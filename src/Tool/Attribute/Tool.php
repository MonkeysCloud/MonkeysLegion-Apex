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
 * Marks a method or class as an AI-callable tool.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class Tool
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
    ) {}
}
