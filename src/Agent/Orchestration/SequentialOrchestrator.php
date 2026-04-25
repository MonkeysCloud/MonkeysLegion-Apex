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
 * Sequential orchestration — agents run one after another.
 *
 * Output of agent N feeds as input to agent N+1.
 */
final class SequentialOrchestrator implements OrchestratorInterface
{
    /**
     * @param list<Agent> $agents
     * @return list<array{agent: string, response: Response}>
     */
    public function run(array $agents, string $task, array $options = []): array
    {
        $results = [];
        $input   = $task;

        foreach ($agents as $agent) {
            $response  = $agent->run($input);
            $results[] = ['agent' => $agent->name, 'response' => $response];
            $input     = $response->content;
        }

        return $results;
    }
}
