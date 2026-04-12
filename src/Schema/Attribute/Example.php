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
 * Provides example values for LLM guidance.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Example
{
    /** @var list<mixed> */
    public readonly array $values;

    public function __construct(mixed ...$values)
    {
        $this->values = $values;
    }
}
