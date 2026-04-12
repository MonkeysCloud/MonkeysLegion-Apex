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

namespace MonkeysLegion\Apex\Agent;

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\Contract\MemoryInterface;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\Memory\SlidingWindowMemory;

/**
 * AI Agent — autonomous entity with role, tools, and memory.
 */
final class Agent
{
    private MemoryInterface $memory;

    /**
     * @param list<object> $tools
     * @param array<string, mixed> $config
     */
    public function __construct(
        public readonly string  $name,
        public readonly string  $role,
        private readonly AI     $ai,
        private readonly array  $tools = [],
        private readonly array  $config = [],
        ?MemoryInterface        $memory = null,
    ) {
        $this->memory = $memory ?? new SlidingWindowMemory();
    }

    /**
     * Run the agent with a task/message.
     */
    public function run(string $task): Response
    {
        $options = [];

        if (!empty($this->tools)) {
            $options['tools'] = $this->tools;
        }

        if (isset($this->config['model'])) {
            $options['model'] = $this->config['model'];
        }

        $this->memory->add(Message::user($task));

        $response = $this->ai->generate(
            $this->memory->messages(),
            system: $this->role,
            options: $options,
        );

        $this->memory->add(Message::assistant($response->content));

        return $response;
    }

    /**
     * Get this agent's memory.
     */
    public function memory(): MemoryInterface
    {
        return $this->memory;
    }

    /**
     * Create a handoff message to transfer context to another agent.
     */
    public function handoff(Agent $target, string $summary): Handoff
    {
        return new Handoff(
            from:    $this->name,
            to:      $target->name,
            summary: $summary,
            context: $this->memory->messages(),
        );
    }
}
