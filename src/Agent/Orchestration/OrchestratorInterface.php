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
 * Contract for agent orchestration strategies.
 */
interface OrchestratorInterface
{
    /**
     * Orchestrate a list of agents to complete a task.
     *
     * @param list<Agent> $agents
     * @param array<string, mixed> $options
     * @return list<array{agent: string, response: Response}>
     */
    public function run(array $agents, string $task, array $options = []): array;
}
