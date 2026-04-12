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

namespace MonkeysLegion\Apex\Agent\Memory;

use MonkeysLegion\Apex\Contract\MemoryInterface;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\Enum\Role;

/**
 * Agent-scoped memory — isolates conversation per agent by key.
 *
 * Wraps any MemoryInterface backend and scopes it to a specific agent,
 * optionally injecting the agent's system prompt automatically.
 */
final class AgentMemory implements MemoryInterface
{
    public function __construct(
        private readonly MemoryInterface $backend,
        private readonly string          $agentName,
        private readonly ?string         $systemPrompt = null,
    ) {}

    public function add(Message $message): void
    {
        $this->backend->add($message);
    }

    /** @return list<Message> */
    public function messages(): array
    {
        $messages = $this->backend->messages();

        // Inject system prompt if not already present
        if ($this->systemPrompt !== null) {
            $hasSystem = false;
            foreach ($messages as $msg) {
                if ($msg->role === Role::System) {
                    $hasSystem = true;
                    break;
                }
            }

            if (!$hasSystem) {
                array_unshift($messages, Message::system($this->systemPrompt));
            }
        }

        return $messages;
    }

    public function clear(): void
    {
        $this->backend->clear();
    }

    /**
     * Get the agent name this memory is scoped to.
     */
    public function agentName(): string
    {
        return $this->agentName;
    }
}
