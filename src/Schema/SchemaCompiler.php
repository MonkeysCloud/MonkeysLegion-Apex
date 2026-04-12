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

use MonkeysLegion\Apex\Schema\Attribute\ArrayOf;
use MonkeysLegion\Apex\Schema\Attribute\Constrain;
use MonkeysLegion\Apex\Schema\Attribute\Description;
use MonkeysLegion\Apex\Schema\Attribute\Example;
use MonkeysLegion\Apex\Schema\Attribute\Optional;

/**
 * Compiles a PHP Schema class into a JSON Schema definition.
 *
 * Reads PHP type declarations and #[Description], #[Constrain],
 * #[Optional], #[ArrayOf], #[Example] attributes to produce a
 * JSON Schema compatible with OpenAI/Anthropic structured output.
 */
final class SchemaCompiler
{
    /**
     * @param class-string<Schema> $class
     * @return array<string, mixed>
     */
    public static function compile(string $class): array
    {
        $ref        = new \ReflectionClass($class);
        $properties = [];
        $required   = [];

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name  = $prop->getName();
            $type  = $prop->getType();
            $attrs = $prop->getAttributes();

            // Build property schema from PHP type
            $propSchema = self::compileType($type, $attrs);

            // Add description from #[Description]
            foreach ($attrs as $attr) {
                if ($attr->getName() === Description::class) {
                    $propSchema['description'] = $attr->newInstance()->text;
                }
                if ($attr->getName() === Example::class) {
                    $propSchema['examples'] = $attr->newInstance()->values;
                }
                if ($attr->getName() === Constrain::class) {
                    $propSchema = array_merge($propSchema, $attr->newInstance()->toSchema());
                }
            }

            $properties[$name] = $propSchema;

            // Required unless #[Optional] or nullable type
            $isOptional = !empty(array_filter(
                $attrs,
                fn(\ReflectionAttribute $a) => $a->getName() === Optional::class,
            ));
            if (!$isOptional && !$type?->allowsNull()) {
                $required[] = $name;
            }
        }

        return [
            'type'                 => 'object',
            'properties'           => $properties,
            'required'             => $required,
            'additionalProperties' => false,
        ];
    }

    /**
     * @param list<\ReflectionAttribute> $attrs
     * @return array<string, mixed>
     */
    private static function compileType(?\ReflectionType $type, array $attrs): array
    {
        if ($type instanceof \ReflectionNamedType) {
            $typeName = $type->getName();

            return match (true) {
                $typeName === 'string'  => ['type' => 'string'],
                $typeName === 'int'     => ['type' => 'integer'],
                $typeName === 'float'   => ['type' => 'number'],
                $typeName === 'bool'    => ['type' => 'boolean'],
                $typeName === 'array'   => self::compileArrayType($attrs),
                enum_exists($typeName)  => self::compileEnum($typeName),
                is_subclass_of($typeName, Schema::class) => $typeName::toJsonSchema(),
                default => ['type' => 'string'],
            };
        }

        // Union types: string|int → oneOf
        if ($type instanceof \ReflectionUnionType) {
            return [
                'oneOf' => array_map(
                    fn(\ReflectionType $t) => self::compileType($t, []),
                    $type->getTypes(),
                ),
            ];
        }

        return ['type' => 'string'];
    }

    /**
     * @param list<\ReflectionAttribute> $attrs
     * @return array<string, mixed>
     */
    private static function compileArrayType(array $attrs): array
    {
        $schema = ['type' => 'array'];

        foreach ($attrs as $attr) {
            if ($attr->getName() === ArrayOf::class) {
                $itemType = $attr->newInstance()->type;
                if (is_subclass_of($itemType, Schema::class)) {
                    $schema['items'] = $itemType::toJsonSchema();
                } else {
                    $schema['items'] = ['type' => match ($itemType) {
                        'string' => 'string',
                        'int'    => 'integer',
                        'float'  => 'number',
                        'bool'   => 'boolean',
                        default  => 'string',
                    }];
                }
            }
        }

        return $schema;
    }

    /**
     * @param class-string $enumClass
     * @return array<string, mixed>
     */
    private static function compileEnum(string $enumClass): array
    {
        $cases = array_map(
            fn(\UnitEnum $case) => $case instanceof \BackedEnum ? $case->value : $case->name,
            $enumClass::cases(),
        );

        return ['type' => 'string', 'enum' => $cases];
    }
}
