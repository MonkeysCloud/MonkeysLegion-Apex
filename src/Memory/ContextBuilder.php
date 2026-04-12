<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Memory;

use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\Enum\Role;

/**
 * Builds optimized context from multiple memory sources.
 */
final class ContextBuilder
{
    /** @var list<Message> */
    private array $messages = [];
    private ?string $systemPrompt = null;

    public static function create(): self
    {
        return new self();
    }

    public function system(string $prompt): self
    {
        $this->systemPrompt = $prompt;
        return $this;
    }

    /**
     * Add messages from a memory source.
     *
     * @param list<Message> $messages
     */
    public function addMessages(array $messages): self
    {
        $this->messages = array_merge($this->messages, $messages);
        return $this;
    }

    /**
     * Add relevant context from vector memory recall.
     *
     * @param list<Message> $recalled
     */
    public function addContext(array $recalled, string $label = 'Relevant context'): self
    {
        if (empty($recalled)) return $this;

        $contextText = implode("\n", array_map(fn(Message $m) => $m->content, $recalled));
        $this->messages[] = Message::system("{$label}:\n{$contextText}");
        return $this;
    }

    /**
     * Build the final message list.
     *
     * @return list<Message>
     */
    public function build(): array
    {
        $result = [];

        if ($this->systemPrompt !== null) {
            $result[] = Message::system($this->systemPrompt);
        }

        foreach ($this->messages as $msg) {
            $result[] = $msg;
        }

        return $result;
    }
}
