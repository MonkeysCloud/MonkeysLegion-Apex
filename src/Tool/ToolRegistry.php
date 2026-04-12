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

namespace MonkeysLegion\Apex\Tool;

use MonkeysLegion\Apex\Tool\Attribute\Tool;
use MonkeysLegion\Apex\Tool\Attribute\ToolParam;

/**
 * Discovers and compiles #[Tool] methods into tool definitions.
 */
final class ToolRegistry
{
    /** @var array<string, array{object: object, method: \ReflectionMethod, schema: array<string, mixed>}> */
    private array $tools = [];

    /**
     * Register one or more tool objects.
     *
     * @param list<object> $objects
     */
    public function register(array $objects): void
    {
        foreach ($objects as $obj) {
            $ref = new \ReflectionClass($obj);
            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attrs = $method->getAttributes(Tool::class);
                if (empty($attrs)) {
                    continue;
                }

                $toolAttr = $attrs[0]->newInstance();
                $name = $toolAttr->name ?? $method->getName();
                $desc = $toolAttr->description ?? $this->extractDocDescription($method);

                $this->tools[$name] = [
                    'object' => $obj,
                    'method' => $method,
                    'schema' => $this->compileToolSchema($name, $desc, $method),
                ];
            }
        }
    }

    /**
     * Compile all registered tools into provider-format definitions.
     *
     * @return list<array<string, mixed>>
     */
    public function compile(): array
    {
        return array_map(fn(array $t) => $t['schema'], array_values($this->tools));
    }

    /**
     * Get a registered tool by name.
     *
     * @return array{object: object, method: \ReflectionMethod}|null
     */
    public function get(string $name): ?array
    {
        if (!isset($this->tools[$name])) {
            return null;
        }
        return [
            'object' => $this->tools[$name]['object'],
            'method' => $this->tools[$name]['method'],
        ];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    /**
     * @return array<string, mixed>
     */
    private function compileToolSchema(string $name, string $description, \ReflectionMethod $method): array
    {
        $properties = [];
        $required = [];

        foreach ($method->getParameters() as $param) {
            $paramName = $param->getName();
            $paramType = $param->getType();
            $paramSchema = $this->compileParamType($paramType);

            // Read #[ToolParam] attribute
            $toolParamAttrs = $param->getAttributes(ToolParam::class);
            if (!empty($toolParamAttrs)) {
                $tp = $toolParamAttrs[0]->newInstance();
                if ($tp->description !== null) { $paramSchema['description'] = $tp->description; }
                if ($tp->enum !== null)        { $paramSchema['enum'] = $tp->enum; }
                if ($tp->min !== null)          { $paramSchema['minimum'] = $tp->min; }
                if ($tp->max !== null)          { $paramSchema['maximum'] = $tp->max; }
            }

            $properties[$paramName] = $paramSchema;

            if (!$param->isDefaultValueAvailable() && !$paramType?->allowsNull()) {
                $required[] = $paramName;
            }
        }

        return [
            'type'     => 'function',
            'function' => [
                'name'        => $name,
                'description' => $description,
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => $properties,
                    'required'   => $required,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function compileParamType(?\ReflectionType $type): array
    {
        if (!$type instanceof \ReflectionNamedType) {
            return ['type' => 'string'];
        }

        return match ($type->getName()) {
            'string' => ['type' => 'string'],
            'int'    => ['type' => 'integer'],
            'float'  => ['type' => 'number'],
            'bool'   => ['type' => 'boolean'],
            'array'  => ['type' => 'array'],
            default  => ['type' => 'string'],
        };
    }

    private function extractDocDescription(\ReflectionMethod $method): string
    {
        $doc = $method->getDocComment();
        if ($doc === false) {
            return $method->getName();
        }

        // Extract first line of doc comment
        $lines = explode("\n", $doc);
        foreach ($lines as $line) {
            $line = trim($line, " \t/*");
            if ($line !== '' && !str_starts_with($line, '@')) {
                return $line;
            }
        }

        return $method->getName();
    }
}
