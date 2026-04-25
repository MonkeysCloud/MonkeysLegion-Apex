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
 * Conversational orchestration — agents debate/refine in rounds.
 *
 * Agents take turns responding to each other's output.
 * The conversation continues until maxIterations is reached.
 */
final class ConversationalOrchestrator implements OrchestratorInterface
{
    public function __construct(
        private readonly int $maxIterations = 10,
    ) {}

    /**
     * @param list<Agent> $agents
     * @return list<array{agent: string, response: Response}>
     */
    public function run(array $agents, string $task, array $options = []): array
    {
        $maxIterations = $options['maxIterations'] ?? $this->maxIterations;
        $results       = [];
        $input         = $task;
        $iterations    = 0;

        while ($iterations < $maxIterations) {
            foreach ($agents as $agent) {
                if ($iterations >= $maxIterations) {
                    break 2;
                }

                $response  = $agent->run($input);
                $results[] = ['agent' => $agent->name, 'response' => $response];
                $input     = $response->content;
                $iterations++;
            }
        }

        return $results;
    }
}
