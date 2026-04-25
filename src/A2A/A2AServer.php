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

namespace MonkeysLegion\Apex\A2A;

use MonkeysLegion\Apex\Agent\Agent;

/**
 * A2A Server — HTTP handler for Agent-to-Agent protocol.
 *
 * Exposes local Apex agents as A2A agents that remote clients can discover,
 * delegate tasks to, and receive results from.
 *
 * Uses JSON-RPC 2.0 over HTTP(S) as per the A2A v1.2 specification.
 */
final class A2AServer
{
    /** @var array<string, Agent> Name → Agent mapping */
    private array $agents = [];

    /** @var array<string, A2ATask> Task ID → Task mapping */
    private array $tasks = [];

    /**
     * Register a local agent for A2A exposure.
     */
    public function register(Agent $agent): self
    {
        $this->agents[$agent->name] = $agent;
        return $this;
    }

    /**
     * Process an A2A JSON-RPC request.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function handle(array $request): array
    {
        $method = $request['method'] ?? '';
        $id     = $request['id'] ?? null;
        $params = $request['params'] ?? [];

        return match ($method) {
            'agent/discover'    => $this->handleDiscover($id),
            'tasks/send'        => $this->handleTaskSend($id, $params),
            'tasks/get'         => $this->handleTaskGet($id, $params),
            'tasks/cancel'      => $this->handleTaskCancel($id, $params),
            'tasks/sendSubscribe' => $this->handleTaskSend($id, $params), // Same handler, SSE response handled externally
            default             => $this->errorResponse($id, -32601, "Method not found: {$method}"),
        };
    }

    /**
     * Get all registered agent cards.
     *
     * @return list<AgentCard>
     */
    public function agentCards(): array
    {
        $cards = [];
        foreach ($this->agents as $agent) {
            $cards[] = $this->buildAgentCard($agent);
        }
        return $cards;
    }

    /**
     * @return array<string, mixed>
     */
    private function handleDiscover(mixed $id): array
    {
        $agents = [];
        foreach ($this->agents as $agent) {
            $agents[] = $this->buildAgentCard($agent)->toArray();
        }

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => ['agents' => $agents],
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleTaskSend(mixed $id, array $params): array
    {
        $agentName = $params['agent'] ?? '';
        $input     = $params['message']['parts'][0]['text'] ?? $params['input'] ?? '';

        if (!isset($this->agents[$agentName])) {
            return $this->errorResponse($id, -32602, "Unknown agent: {$agentName}");
        }

        // Create and track the task
        $task = A2ATask::create($input, ['agent' => $agentName]);
        $this->tasks[$task->id] = $task;
        $task->working();

        // Execute the agent
        try {
            $response = $this->agents[$agentName]->run($input);
            $task->complete($response->content);
        } catch (\Throwable $e) {
            $task->fail($e->getMessage());
        }

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $task->toArray(),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleTaskGet(mixed $id, array $params): array
    {
        $taskId = $params['id'] ?? '';

        if (!isset($this->tasks[$taskId])) {
            return $this->errorResponse($id, -32602, "Task not found: {$taskId}");
        }

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $this->tasks[$taskId]->toArray(),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleTaskCancel(mixed $id, array $params): array
    {
        $taskId = $params['id'] ?? '';

        if (!isset($this->tasks[$taskId])) {
            return $this->errorResponse($id, -32602, "Task not found: {$taskId}");
        }

        $this->tasks[$taskId]->cancel();

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $this->tasks[$taskId]->toArray(),
        ];
    }

    /**
     * Build an agent card from a local Agent instance.
     */
    private function buildAgentCard(Agent $agent): AgentCard
    {
        return new AgentCard(
            name:        $agent->name,
            description: $agent->role,
            url:         '', // Set by the HTTP layer
            skills:      [$agent->role],
            provider:    'MonkeysLegion-Apex',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResponse(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => ['code' => $code, 'message' => $message],
        ];
    }
}
