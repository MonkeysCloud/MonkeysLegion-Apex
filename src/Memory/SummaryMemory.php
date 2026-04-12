<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Memory;

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\Contract\MemoryInterface;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\Enum\Role;

/**
 * Summary memory — periodically summarizes older messages to save context.
 */
final class SummaryMemory implements MemoryInterface
{
    /** @var list<Message> */
    private array $messages = [];
    private ?string $summary = null;

    public function __construct(
        private readonly AI  $ai,
        private readonly int $summarizeEvery = 10,
        private int          $messageCount = 0,
    ) {}

    public function add(Message $message): void
    {
        $this->messages[] = $message;
        $this->messageCount++;

        if ($this->messageCount % $this->summarizeEvery === 0 && $this->messageCount > 0) {
            $this->summarize();
        }
    }

    /** @return list<Message> */
    public function messages(): array
    {
        if ($this->summary !== null) {
            return array_merge(
                [Message::system("Previous conversation summary: {$this->summary}")],
                $this->messages,
            );
        }
        return $this->messages;
    }

    public function clear(): void
    {
        $this->messages = [];
        $this->summary  = null;
        $this->messageCount = 0;
    }

    private function summarize(): void
    {
        $text = implode("\n", array_map(
            fn(Message $m) => "{$m->role->value}: {$m->content}",
            $this->messages,
        ));

        $response = $this->ai->generate(
            "Summarize this conversation concisely, keeping key facts and decisions:\n\n{$text}",
        );

        $this->summary = ($this->summary ? $this->summary . "\n\n" : '') . $response->content;
        $this->messages = [];
    }
}
