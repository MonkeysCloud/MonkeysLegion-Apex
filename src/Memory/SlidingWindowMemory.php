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

namespace MonkeysLegion\Apex\Memory;

use MonkeysLegion\Apex\Contract\MemoryInterface;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\Enum\Role;

/**
 * Sliding window memory — trims oldest messages when limits are exceeded.
 */
final class SlidingWindowMemory implements MemoryInterface
{
    /** @var list<Message> */
    private array $messages = [];

    public function __construct(
        private readonly int $maxTokens = 4096,
        private readonly int $maxMessages = 50,
    ) {}

    public function add(Message $message): void
    {
        $this->messages[] = $message;
        $this->trim();
    }

    /**
     * @return list<Message>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    public function clear(): void
    {
        $this->messages = [];
    }

    /**
     * Trim oldest non-system messages when limits are exceeded.
     */
    private function trim(): void
    {
        while (
            ($this->estimateTokens() > $this->maxTokens
              || count($this->messages) > $this->maxMessages)
            && count($this->messages) > 1
        ) {
            $firstNonSystem = null;
            foreach ($this->messages as $i => $m) {
                if ($m->role !== Role::System) {
                    $firstNonSystem = $i;
                    break;
                }
            }

            if ($firstNonSystem !== null) {
                array_splice($this->messages, $firstNonSystem, 1);
            } else {
                break;
            }
        }
    }

    /**
     * Rough token estimate (~4 chars per token).
     */
    private function estimateTokens(): int
    {
        return (int) (array_sum(array_map(
            fn(Message $m) => strlen($m->content),
            $this->messages,
        )) / 4);
    }
}
