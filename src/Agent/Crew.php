<?php



/**
 * MonkeysLegion Apex
 *
 * @package   MonkeysLegion\Apex
 * @author    MonkeysCloud <jorge@monkeys.cloud>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

declare(strict_types=1);

namespace MonkeysLegion\Apex\Agent;

use MonkeysLegion\Apex\Agent\Orchestration\ConversationalOrchestrator;
use MonkeysLegion\Apex\Agent\Orchestration\HierarchicalOrchestrator;
use MonkeysLegion\Apex\Agent\Orchestration\OrchestratorInterface;
use MonkeysLegion\Apex\Agent\Orchestration\ParallelOrchestrator;
use MonkeysLegion\Apex\Agent\Orchestration\SequentialOrchestrator;
use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\Enum\AgentProcess;

/**
 * Multi-agent crew — orchestrates multiple agents.
 *
 * Delegates execution to pluggable orchestration strategies.
 */
final class Crew
{
    /** @var list<Agent> */
    private array $agents;

    private OrchestratorInterface $orchestrator;

    /**
     * @param list<Agent> $agents
     */
    public function __construct(
        public readonly string       $name,
        array                        $agents,
        public readonly AgentProcess $process = AgentProcess::Sequential,
        public readonly int          $maxIterations = 10,
        ?OrchestratorInterface       $orchestrator = null,
    ) {
        $this->agents = $agents;
        $this->orchestrator = $orchestrator ?? $this->resolveOrchestrator($process);
    }

    /**
     * Run the crew with a task.
     *
     * @return list<array{agent: string, response: Response}>
     */
    public function run(string $task): array
    {
        return $this->orchestrator->run($this->agents, $task, [
            'maxIterations' => $this->maxIterations,
        ]);
    }

    /**
     * Get the agents in this crew.
     *
     * @return list<Agent>
     */
    public function agents(): array
    {
        return $this->agents;
    }

    /**
     * Get the orchestrator in use.
     */
    public function orchestrator(): OrchestratorInterface
    {
        return $this->orchestrator;
    }

    /**
     * Resolve the default orchestrator for a given process type.
     */
    private function resolveOrchestrator(AgentProcess $process): OrchestratorInterface
    {
        return match ($process) {
            AgentProcess::Sequential     => new SequentialOrchestrator(),
            AgentProcess::Parallel       => new ParallelOrchestrator(),
            AgentProcess::Hierarchical   => new HierarchicalOrchestrator(),
            AgentProcess::Conversational => new ConversationalOrchestrator($this->maxIterations),
        };
    }
}
