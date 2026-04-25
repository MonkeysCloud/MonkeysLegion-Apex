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

namespace MonkeysLegion\Apex\MCP;

/**
 * MCP Prompt — reusable prompt template for MCP servers.
 *
 * Part of the MCP 2025-11-25 specification.
 */
final readonly class MCPPrompt
{
    /**
     * @param array<string, array{description: string, required?: bool}> $arguments
     * @param list<array{role: string, content: array{type: string, text: string}}> $messages
     */
    public function __construct(
        public string $name,
        public string $description,
        public array  $arguments = [],
        public array  $messages = [],
    ) {}

    /**
     * Resolve the prompt with given argument values.
     *
     * @param array<string, string> $values
     * @return list<array{role: string, content: array{type: string, text: string}}>
     */
    public function resolve(array $values = []): array
    {
        $resolved = [];
        foreach ($this->messages as $msg) {
            $text = $msg['content']['text'] ?? '';
            foreach ($values as $key => $value) {
                $text = str_replace("{{$key}}", $value, $text);
            }
            $resolved[] = [
                'role'    => $msg['role'],
                'content' => ['type' => 'text', 'text' => $text],
            ];
        }
        return $resolved;
    }

    /**
     * Serialize to MCP-compatible format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'description' => $this->description,
            'arguments'   => array_map(fn($key, $arg) => [
                'name'        => $key,
                'description' => $arg['description'],
                'required'    => $arg['required'] ?? false,
            ], array_keys($this->arguments), $this->arguments),
        ];
    }
}
