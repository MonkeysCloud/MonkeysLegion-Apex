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

namespace MonkeysLegion\Apex\Contract;

use MonkeysLegion\Apex\DTO\Response;

/**
 * Agent contract — autonomous AI entity.
 */
interface AgentInterface
{
    /**
     * Run the agent with a task.
     */
    public function run(string $task): Response;

    /**
     * Get agent name.
     */
    public function name(): string;
}
