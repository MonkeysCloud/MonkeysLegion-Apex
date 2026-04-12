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

namespace MonkeysLegion\Apex\Schema;

/**
 * Base class for structured output schemas.
 *
 * Extend this class to define typed LLM output. The SchemaCompiler
 * converts PHP type declarations and attributes into JSON Schema
 * for use with OpenAI, Anthropic, and Google structured output APIs.
 */
abstract class Schema implements \JsonSerializable
{
    /**
     * Compile this schema class to JSON Schema.
     *
     * @return array<string, mixed>
     */
    public static function toJsonSchema(): array
    {
        return SchemaCompiler::compile(static::class);
    }

    /**
     * Create instance from validated LLM output.
     *
     * @param array<string, mixed> $data
     * @throws \MonkeysLegion\Apex\Exception\SchemaValidationException
     */
    public static function fromArray(array $data): static
    {
        return SchemaValidator::validate(static::class, $data);
    }

    /**
     * Convert to associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $ref = new \ReflectionClass($this);
        $result = [];

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $value = $prop->getValue($this);
            if ($value instanceof self) {
                $value = $value->toArray();
            } elseif (is_array($value)) {
                $value = array_map(
                    fn(mixed $v) => $v instanceof self ? $v->toArray() : $v,
                    $value,
                );
            }
            $result[$prop->getName()] = $value;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
