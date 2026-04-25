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

/**
 * A2A Client — discovers and invokes remote A2A agents.
 *
 * Implements the client side of the Agent-to-Agent (A2A) protocol.
 */
final class A2AClient
{
    public function __construct(
        private readonly float $timeout = 30.0,
    ) {}

    /**
     * Discover agents from a remote A2A server.
     *
     * @return list<AgentCard>
     */
    public function discover(string $serverUrl): array
    {
        $result = $this->send($serverUrl, 'agent/discover');
        $cards  = [];

        foreach ($result['agents'] ?? [] as $agentData) {
            $cards[] = new AgentCard(
                name:        $agentData['name'] ?? '',
                description: $agentData['description'] ?? '',
                url:         $agentData['url'] ?? $serverUrl,
                version:     $agentData['version'] ?? '1.0.0',
                skills:      $agentData['skills'] ?? [],
                protocols:   $agentData['protocols'] ?? ['a2a/1.2'],
            );
        }

        return $cards;
    }

    /**
     * Send a task to a remote agent.
     */
    public function sendTask(string $serverUrl, string $agentName, string $input): A2ATask
    {
        $result = $this->send($serverUrl, 'tasks/send', [
            'agent'   => $agentName,
            'message' => A2AMessage::from($input)->toArray(),
        ]);

        return new A2ATask(
            id:        $result['id'] ?? '',
            status:    $result['status'] ?? 'unknown',
            input:     $result['input'] ?? $input,
            output:    $result['output'] ?? null,
            error:     $result['error'] ?? null,
            createdAt: $result['createdAt'] ?? '',
            updatedAt: $result['updatedAt'] ?? null,
            metadata:  $result['metadata'] ?? [],
        );
    }

    /**
     * Get task status from a remote server.
     */
    public function getTask(string $serverUrl, string $taskId): A2ATask
    {
        $result = $this->send($serverUrl, 'tasks/get', ['id' => $taskId]);

        return new A2ATask(
            id:        $result['id'] ?? $taskId,
            status:    $result['status'] ?? 'unknown',
            input:     $result['input'] ?? null,
            output:    $result['output'] ?? null,
            error:     $result['error'] ?? null,
            createdAt: $result['createdAt'] ?? '',
            updatedAt: $result['updatedAt'] ?? null,
            metadata:  $result['metadata'] ?? [],
        );
    }

    /**
     * Cancel a task on a remote server.
     */
    public function cancelTask(string $serverUrl, string $taskId): A2ATask
    {
        $result = $this->send($serverUrl, 'tasks/cancel', ['id' => $taskId]);

        return new A2ATask(
            id:        $result['id'] ?? $taskId,
            status:    $result['status'] ?? 'canceled',
        );
    }

    /**
     * Send a JSON-RPC request to the A2A server.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function send(string $serverUrl, string $method, array $params = []): array
    {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id'      => bin2hex(random_bytes(8)),
            'method'  => $method,
            'params'  => (object) $params,
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init($serverUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            throw new \RuntimeException("A2A request failed: {$err} (HTTP {$code})");
        }

        $response = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
        if (isset($response['error'])) {
            throw new \RuntimeException("A2A error: {$response['error']['message']}");
        }

        return $response['result'] ?? [];
    }
}
