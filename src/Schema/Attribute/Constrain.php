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
 * Validation constraints for a schema property.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Constrain
{
    /**
     * @param list<mixed>|null $enum
     */
    public function __construct(
        public readonly ?float  $min = null,
        public readonly ?float  $max = null,
        public readonly ?string $pattern = null,
        public readonly ?array  $enum = null,
        public readonly ?int    $minLength = null,
        public readonly ?int    $maxLength = null,
        public readonly ?int    $minItems = null,
        public readonly ?int    $maxItems = null,
    ) {}

    /**
     * Convert to JSON Schema constraints.
     *
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        $schema = [];
        if ($this->min !== null)       { $schema['minimum']   = $this->min; }
        if ($this->max !== null)       { $schema['maximum']   = $this->max; }
        if ($this->pattern !== null)   { $schema['pattern']   = $this->pattern; }
        if ($this->enum !== null)      { $schema['enum']      = $this->enum; }
        if ($this->minLength !== null) { $schema['minLength'] = $this->minLength; }
        if ($this->maxLength !== null) { $schema['maxLength'] = $this->maxLength; }
        if ($this->minItems !== null)  { $schema['minItems']  = $this->minItems; }
        if ($this->maxItems !== null)  { $schema['maxItems']  = $this->maxItems; }
        return $schema;
    }
}
