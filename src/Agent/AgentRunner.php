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

use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\Enum\AgentProcess;
use Psr\Log\LoggerInterface;

/**
 * Agent runner — manages agent/crew execution with lifecycle hooks.
 */
final class AgentRunner
{
    /** @var list<callable(string, Response): void> */
    private array $onStep = [];

    /** @var list<callable(Handoff): void> */
    private array $onHandoff = [];

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Register a step callback (called after each agent response).
     *
     * @param callable(string, Response): void $callback
     */
    public function onStep(callable $callback): self
    {
        $this->onStep[] = $callback;
        return $this;
    }

    /**
     * Register a handoff callback.
     *
     * @param callable(Handoff): void $callback
     */
    public function onHandoff(callable $callback): self
    {
        $this->onHandoff[] = $callback;
        return $this;
    }

    /**
     * Run a single agent.
     */
    public function runAgent(Agent $agent, string $task): Response
    {
        $this->logger?->info("Agent [{$agent->name}] starting", ['task' => mb_substr($task, 0, 100)]);

        $response = $agent->run($task);

        foreach ($this->onStep as $callback) {
            $callback($agent->name, $response);
        }

        $this->logger?->info("Agent [{$agent->name}] completed", [
            'tokens' => $response->usage->totalTokens,
        ]);

        return $response;
    }

    /**
     * Run a crew.
     *
     * @return list<array{agent: string, response: Response}>
     */
    public function runCrew(Crew $crew, string $task): array
    {
        $this->logger?->info("Crew [{$crew->name}] starting ({$crew->process->value})", [
            'task' => mb_substr($task, 0, 100),
        ]);

        $results = $crew->run($task);

        foreach ($results as $result) {
            foreach ($this->onStep as $callback) {
                $callback($result['agent'], $result['response']);
            }
        }

        $this->logger?->info("Crew [{$crew->name}] completed", [
            'agents'  => count($results),
            'tokens'  => array_sum(array_map(fn($r) => $r['response']->usage->totalTokens, $results)),
        ]);

        return $results;
    }

    /**
     * Execute a handoff between agents.
     */
    public function handoff(Agent $from, Agent $to, string $summary): Handoff
    {
        $handoff = $from->handoff($to, $summary);

        foreach ($this->onHandoff as $callback) {
            $callback($handoff);
        }

        $this->logger?->info("Handoff: {$from->name} → {$to->name}", [
            'summary' => $summary,
        ]);

        return $handoff;
    }
}
