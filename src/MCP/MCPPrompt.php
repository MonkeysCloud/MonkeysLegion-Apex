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

use MonkeysLegion\Mcp\Prompt\PromptDefinition;

/**
 * MCP Prompt — reusable prompt template for MCP servers.
 *
 * @deprecated 1.3.0 Use {@see \MonkeysLegion\Mcp\Prompt\PromptDefinition} from monkeyslegion-mcp instead.
 *             This class now includes a toPromptDefinition() bridge method.
 *             Install: composer require monkeyscloud/monkeyslegion-mcp
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
     * @deprecated Use PromptDefinition::resolve() instead.
     *
     * @param array<string, string> $values
     * @return list<array{role: string, content: array{type: string, text: string}}>
     */
    public function resolve(array $values = []): array
    {
        return $this->toPromptDefinition()->resolve($values);
    }

    /**
     * Serialize to MCP-compatible format.
     *
     * @deprecated Use PromptDefinition::toArray() instead.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->toPromptDefinition()->toArray();
    }

    /**
     * Convert to the new PromptDefinition from monkeyslegion-mcp.
     */
    public function toPromptDefinition(): PromptDefinition
    {
        return new PromptDefinition(
            name: $this->name,
            description: $this->description,
            arguments: $this->arguments,
            messages: $this->messages,
        );
    }
}
