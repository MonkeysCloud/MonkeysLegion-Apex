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

use MonkeysLegion\Apex\Enum\FinishReason;

/**
 * Immutable LLM response with metadata.
 */
final readonly class Response
{
    /**
     * @param list<ToolCall>|null   $toolCalls
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string        $content,
        public FinishReason  $finishReason,
        public Usage         $usage,
        public ?array        $toolCalls = null,
        public ?string       $reasoning = null,
        public string        $model = '',
        public string        $provider = '',
        public float         $latencyMs = 0.0,
        public ?Cost         $cost = null,
        public array         $metadata = [],
    ) {}

    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content'       => $this->content,
            'finish_reason' => $this->finishReason->value,
            'usage'         => $this->usage->toArray(),
            'model'         => $this->model,
            'provider'      => $this->provider,
            'latency_ms'    => $this->latencyMs,
            'cost'          => $this->cost?->toArray(),
        ];
    }
}
