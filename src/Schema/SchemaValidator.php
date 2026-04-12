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

use MonkeysLegion\Apex\Exception\SchemaValidationException;

/**
 * Validates LLM output against a Schema class and hydrates it.
 */
final class SchemaValidator
{
    /**
     * Validate data and create a Schema instance.
     *
     * @template T of Schema
     * @param class-string<T>      $class
     * @param array<string, mixed> $data
     * @return T
     * @throws SchemaValidationException
     */
    public static function validate(string $class, array $data): Schema
    {
        $ref    = new \ReflectionClass($class);
        $errors = [];
        $args   = [];

        $constructor = $ref->getConstructor();
        if ($constructor === null) {
            throw new SchemaValidationException("Schema {$class} has no constructor");
        }

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            // Check required
            if (!array_key_exists($name, $data)) {
                if ($param->isDefaultValueAvailable()) {
                    $args[$name] = $param->getDefaultValue();
                    continue;
                }
                if ($type?->allowsNull()) {
                    $args[$name] = null;
                    continue;
                }
                $errors[] = "Missing required field: {$name}";
                continue;
            }

            $value = $data[$name];

            // Handle nested Schema objects
            if ($type instanceof \ReflectionNamedType && is_subclass_of($type->getName(), Schema::class)) {
                if (is_array($value)) {
                    try {
                        $value = $type->getName()::fromArray($value);
                    } catch (SchemaValidationException $e) {
                        $errors[] = "Nested validation failed for {$name}: " . $e->getMessage();
                        continue;
                    }
                } else {
                    $errors[] = "Expected object for {$name}, got " . get_debug_type($value);
                    continue;
                }
            }

            // Type validation for primitives
            if ($type instanceof \ReflectionNamedType) {
                $typeName = $type->getName();
                $valid = match ($typeName) {
                    'string' => is_string($value),
                    'int'    => is_int($value),
                    'float'  => is_float($value) || is_int($value),
                    'bool'   => is_bool($value),
                    'array'  => is_array($value),
                    default  => true,
                };

                if (!$valid && !($type->allowsNull() && $value === null)) {
                    $errors[] = "Expected {$typeName} for {$name}, got " . get_debug_type($value);
                    continue;
                }

                // Coerce int to float
                if ($typeName === 'float' && is_int($value)) {
                    $value = (float) $value;
                }
            }

            $args[$name] = $value;
        }

        if (!empty($errors)) {
            throw new SchemaValidationException(
                'Schema validation failed: ' . implode('; ', $errors),
                errors: $errors,
            );
        }

        return $ref->newInstanceArgs($args);
    }
}
