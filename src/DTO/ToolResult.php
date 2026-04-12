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

/**
 * Result of executing a tool call.
 */
final readonly class ToolResult
{
    public function __construct(
        public string $toolCallId,
        public mixed  $output,
        public bool   $success = true,
        public ?string $error = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tool_call_id' => $this->toolCallId,
            'output'       => $this->output,
            'success'      => $this->success,
            'error'        => $this->error,
        ];
    }
}
