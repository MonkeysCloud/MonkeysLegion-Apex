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

namespace MonkeysLegion\Apex\Agent\Orchestration;

use MonkeysLegion\Apex\Agent\Agent;
use MonkeysLegion\Apex\DTO\Response;

/**
 * Hierarchical orchestration — manager agent delegates to workers.
 *
 * The first agent acts as manager:
 *   1. Manager decomposes the task into subtasks
 *   2. Worker agents each process the manager's plan
 *   3. Manager synthesizes all worker outputs into a final answer
 */
final class HierarchicalOrchestrator implements OrchestratorInterface
{
    /**
     * @param list<Agent> $agents
     * @return list<array{agent: string, response: Response}>
     */
    public function run(array $agents, string $task, array $options = []): array
    {
        if (empty($agents)) {
            return [];
        }

        // First agent is the manager/coordinator
        $manager = $agents[0];
        $workers = array_slice($agents, 1);
        $results = [];

        // Step 1: Manager decomposes the task
        $plan = $manager->run(
            "You are the team manager. Break this task into subtasks for your "
            . count($workers) . " team members and provide clear instructions for each: {$task}"
        );
        $results[] = ['agent' => $manager->name, 'response' => $plan];

        // Step 2: Workers execute based on manager's plan
        $workerOutputs = [];
        foreach ($workers as $worker) {
            $response      = $worker->run($plan->content);
            $results[]     = ['agent' => $worker->name, 'response' => $response];
            $workerOutputs[] = "[{$worker->name}]: {$response->content}";
        }

        // Step 3: Manager synthesizes results
        $synthesisInput = "Your team has completed their work. Here are their outputs:\n\n"
            . implode("\n\n", $workerOutputs)
            . "\n\nSynthesize these into a final, cohesive answer for the original task: {$task}";

        $synthesis = $manager->run($synthesisInput);
        $results[] = ['agent' => $manager->name, 'response' => $synthesis];

        return $results;
    }
}
