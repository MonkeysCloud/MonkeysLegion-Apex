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

/**
 * Manages per-agent memory instances.
 *
 * Creates, stores, and retrieves isolated memory backends
 * keyed by agent name for multi-agent orchestration.
 */
final class AgentMemoryManager
{
    /** @var array<string, MemoryInterface> */
    private array $memories = [];

    /**
     * @param callable(string): MemoryInterface $factory
     */
    public function __construct(
        private readonly mixed $factory,
    ) {}

    /**
     * Get or create memory for an agent.
     */
    public function forAgent(string $agentName): MemoryInterface
    {
        if (!isset($this->memories[$agentName])) {
            $this->memories[$agentName] = ($this->factory)($agentName);
        }

        return $this->memories[$agentName];
    }

    /**
     * Set a specific memory instance for an agent.
     */
    public function setMemory(string $agentName, MemoryInterface $memory): void
    {
        $this->memories[$agentName] = $memory;
    }

    /**
     * Check if memory exists for an agent.
     */
    public function has(string $agentName): bool
    {
        return isset($this->memories[$agentName]);
    }

    /**
     * Clear all agent memories.
     */
    public function clearAll(): void
    {
        foreach ($this->memories as $memory) {
            $memory->clear();
        }
        $this->memories = [];
    }

    /**
     * Get all managed agent names.
     *
     * @return list<string>
     */
    public function agents(): array
    {
        return array_keys($this->memories);
    }
}
