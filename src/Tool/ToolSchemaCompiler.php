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
 * Compiles tool definitions into JSON Schema for LLM providers.
 */
final class ToolSchemaCompiler
{
    /**
     * Compile a list of tool objects into provider-agnostic tool schemas.
     *
     * @param list<object> $tools
     * @return list<array<string, mixed>>
     */
    public function compile(array $tools): array
    {
        $schemas = [];

        foreach ($tools as $tool) {
            $ref  = new \ReflectionObject($tool);

            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attrs = $method->getAttributes(Tool::class);
                if (empty($attrs)) {
                    continue;
                }

                /** @var Tool $toolAttr */
                $toolAttr = $attrs[0]->newInstance();

                $params     = $this->compileParams($method);
                $schemas[]  = [
                    'name'        => $toolAttr->name,
                    'description' => $toolAttr->description,
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => $params['properties'],
                        'required'   => $params['required'],
                    ],
                ];
            }
        }

        return $schemas;
    }

    /**
     * Compile for Anthropic format.
     *
     * @param list<object> $tools
     * @return list<array<string, mixed>>
     */
    public function compileForAnthropic(array $tools): array
    {
        $schemas = $this->compile($tools);

        return array_map(fn(array $schema) => [
            'name'         => $schema['name'],
            'description'  => $schema['description'],
            'input_schema' => $schema['parameters'],
        ], $schemas);
    }

    /**
     * Compile for OpenAI format.
     *
     * @param list<object> $tools
     * @return list<array<string, mixed>>
     */
    public function compileForOpenAI(array $tools): array
    {
        $schemas = $this->compile($tools);

        return array_map(fn(array $schema) => [
            'type'     => 'function',
            'function' => $schema,
        ], $schemas);
    }

    /**
     * Compile for Google/Gemini format.
     *
     * @param list<object> $tools
     * @return list<array<string, mixed>>
     */
    public function compileForGoogle(array $tools): array
    {
        return $this->compile($tools);
    }

    /**
     * @return array{properties: array<string, mixed>, required: list<string>}
     */
    private function compileParams(\ReflectionMethod $method): array
    {
        $properties = [];
        $required   = [];

        foreach ($method->getParameters() as $param) {
            $attrs = $param->getAttributes(ToolParam::class);
            $toolParam = !empty($attrs) ? $attrs[0]->newInstance() : null;

            $type = match ((string) $param->getType()) {
                'string' => 'string',
                'int'    => 'integer',
                'float'  => 'number',
                'bool'   => 'boolean',
                'array'  => 'array',
                default  => 'string',
            };

            $prop = ['type' => $type];
            if ($toolParam?->description) {
                $prop['description'] = $toolParam->description;
            }
            if ($toolParam?->enum !== null) {
                $prop['enum'] = $toolParam->enum;
            }

            $properties[$param->getName()] = $prop;

            if (!$param->isOptional()) {
                $required[] = $param->getName();
            }
        }

        return ['properties' => $properties, 'required' => $required];
    }
}
