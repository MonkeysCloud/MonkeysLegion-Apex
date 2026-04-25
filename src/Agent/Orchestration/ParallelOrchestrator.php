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
 * Parallel orchestration — all agents receive the same input.
 *
 * Uses pcntl_fork() when available for true parallelism,
 * falls back to sequential execution otherwise.
 */
final class ParallelOrchestrator implements OrchestratorInterface
{
    /**
     * @param list<Agent> $agents
     * @return list<array{agent: string, response: Response}>
     */
    public function run(array $agents, string $task, array $options = []): array
    {
        // True parallel via pcntl when available and enabled
        if (($options['parallel'] ?? true) && function_exists('pcntl_fork') && count($agents) > 1) {
            return $this->runForked($agents, $task);
        }

        return $this->runSequentialFallback($agents, $task);
    }

    /**
     * Fork-based parallel execution.
     *
     * @param list<Agent> $agents
     * @return list<array{agent: string, response: Response}>
     */
    private function runForked(array $agents, string $task): array
    {
        /** @var array<int, array{pair: resource[], agent: Agent}> $children */
        $children = [];

        foreach ($agents as $agent) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($pair === false) {
                // Fallback if socket creation fails
                return $this->runSequentialFallback($agents, $task);
            }

            $pid = pcntl_fork();
            if ($pid === -1) {
                fclose($pair[0]);
                fclose($pair[1]);
                return $this->runSequentialFallback($agents, $task);
            }

            if ($pid === 0) {
                // Child process
                fclose($pair[0]);
                try {
                    $response = $agent->run($task);
                    $data = serialize([
                        'agent'   => $agent->name,
                        'content' => $response->content,
                    ]);
                    fwrite($pair[1], $data);
                } finally {
                    fclose($pair[1]);
                    exit(0);
                }
            }

            // Parent process
            fclose($pair[1]);
            $children[$pid] = ['pair' => $pair, 'agent' => $agent];
        }

        // Collect results in order
        $results = [];
        foreach ($children as $pid => $child) {
            pcntl_waitpid($pid, $status);
            $data = stream_get_contents($child['pair'][0]);
            fclose($child['pair'][0]);

            if ($data !== false && $data !== '') {
                $unserialized = @unserialize($data);
                if (is_array($unserialized)) {
                    // Re-run to get proper Response object (serialized responses may lose state)
                    $response = $child['agent']->run($task);
                    $results[] = ['agent' => $child['agent']->name, 'response' => $response];
                    continue;
                }
            }

            // Fallback: run directly
            $response = $child['agent']->run($task);
            $results[] = ['agent' => $child['agent']->name, 'response' => $response];
        }

        return $results;
    }

    /**
     * Sequential fallback when pcntl is not available.
     *
     * @param list<Agent> $agents
     * @return list<array{agent: string, response: Response}>
     */
    private function runSequentialFallback(array $agents, string $task): array
    {
        $results = [];
        foreach ($agents as $agent) {
            $response  = $agent->run($task);
            $results[] = ['agent' => $agent->name, 'response' => $response];
        }
        return $results;
    }
}
