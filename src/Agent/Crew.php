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

use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\Enum\AgentProcess;

/**
 * Multi-agent crew — orchestrates multiple agents.
 */
final class Crew
{
    /** @var list<Agent> */
    private array $agents;

    /**
     * @param list<Agent> $agents
     */
    public function __construct(
        public readonly string       $name,
        array                        $agents,
        public readonly AgentProcess $process = AgentProcess::Sequential,
        public readonly int          $maxIterations = 10,
    ) {
        $this->agents = $agents;
    }

    /**
     * Run the crew with a task.
     *
     * @return list<array{agent: string, response: Response}>
     */
    public function run(string $task): array
    {
        return match ($this->process) {
            AgentProcess::Sequential     => $this->runSequential($task),
            AgentProcess::Hierarchical   => $this->runHierarchical($task),
            AgentProcess::Parallel       => $this->runParallel($task),
            AgentProcess::Conversational => $this->runConversational($task),
        };
    }

    /**
     * @return list<array{agent: string, response: Response}>
     */
    private function runSequential(string $task): array
    {
        $results = [];
        $input   = $task;

        foreach ($this->agents as $agent) {
            $response  = $agent->run($input);
            $results[] = ['agent' => $agent->name, 'response' => $response];
            $input     = $response->content;
        }

        return $results;
    }

    /**
     * @return list<array{agent: string, response: Response}>
     */
    private function runHierarchical(string $task): array
    {
        if (empty($this->agents)) {
            return [];
        }

        // First agent is the manager/coordinator
        $manager  = $this->agents[0];
        $workers  = array_slice($this->agents, 1);
        $results  = [];

        $plan = $manager->run("Break this task into subtasks for team members: {$task}");
        $results[] = ['agent' => $manager->name, 'response' => $plan];

        foreach ($workers as $worker) {
            $response  = $worker->run($plan->content);
            $results[] = ['agent' => $worker->name, 'response' => $response];
        }

        // Manager synthesizes
        $synthesis = $manager->run('Synthesize the team results into a final answer.');
        $results[] = ['agent' => $manager->name, 'response' => $synthesis];

        return $results;
    }

    /**
     * @return list<array{agent: string, response: Response}>
     */
    private function runParallel(string $task): array
    {
        $results = [];

        foreach ($this->agents as $agent) {
            $response  = $agent->run($task);
            $results[] = ['agent' => $agent->name, 'response' => $response];
        }

        return $results;
    }

    /**
     * @return list<array{agent: string, response: Response}>
     */
    private function runConversational(string $task): array
    {
        $results = [];
        $input   = $task;
        $i       = 0;

        while ($i < $this->maxIterations) {
            foreach ($this->agents as $agent) {
                $response  = $agent->run($input);
                $results[] = ['agent' => $agent->name, 'response' => $response];
                $input     = $response->content;
                $i++;
                if ($i >= $this->maxIterations) {
                    break;
                }
            }
        }

        return $results;
    }
}
