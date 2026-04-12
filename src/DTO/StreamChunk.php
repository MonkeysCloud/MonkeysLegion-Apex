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

use MonkeysLegion\Apex\Enum\StreamEvent;

/**
 * Single streaming chunk from an LLM response.
 */
final readonly class StreamChunk
{
    public function __construct(
        public StreamEvent $event,
        public string      $delta = '',
        public ?array      $toolCall = null,
        public ?array      $partialObject = null,
        public ?Usage      $usage = null,
        public ?string     $finishReason = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'event'          => $this->event->value,
            'delta'          => $this->delta,
            'tool_call'      => $this->toolCall,
            'partial_object' => $this->partialObject,
            'usage'          => $this->usage?->toArray(),
            'finish_reason'  => $this->finishReason,
        ], fn(mixed $v) => $v !== null && $v !== '');
    }
}
