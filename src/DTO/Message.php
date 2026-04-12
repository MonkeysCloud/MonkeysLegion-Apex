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

namespace MonkeysLegion\Apex\DTO;

use MonkeysLegion\Apex\Enum\Role;

/**
 * Immutable chat message.
 */
final readonly class Message
{
    /**
     * @param list<array{type:string,data:string,mimeType?:string}>|null $attachments
     * @param list<ToolCall>|null                                        $toolCalls
     */
    public function __construct(
        public Role    $role,
        public string  $content,
        public ?string $name = null,
        public ?array  $toolCalls = null,
        public ?string $toolCallId = null,
        public ?array  $attachments = null,
    ) {}

    public static function system(string $content): self
    {
        return new self(Role::System, $content);
    }

    public static function user(string $content, ?array $attachments = null): self
    {
        return new self(Role::User, $content, attachments: $attachments);
    }

    public static function assistant(string $content, ?array $toolCalls = null): self
    {
        return new self(Role::Assistant, $content, toolCalls: $toolCalls);
    }

    public static function tool(string $content, string $toolCallId): self
    {
        return new self(Role::Tool, $content, toolCallId: $toolCallId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'role'        => $this->role->value,
            'content'     => $this->content,
            'name'        => $this->name,
            'tool_calls'  => $this->toolCalls !== null
                ? array_map(fn(ToolCall $tc) => $tc->toArray(), $this->toolCalls)
                : null,
            'tool_call_id' => $this->toolCallId,
        ], fn(mixed $v) => $v !== null);
    }
}
