<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Http;

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\DTO\Message;

/**
 * Base AI controller for HTTP endpoints.
 */
abstract class AIController
{
    public function __construct(
        protected readonly AI $ai,
    ) {}

    /**
     * Parse request body into messages.
     *
     * @param array<string, mixed> $body
     * @return list<Message>
     */
    protected function parseMessages(array $body): array
    {
        $messages = [];

        if (isset($body['system'])) {
            $messages[] = Message::system($body['system']);
        }

        if (isset($body['messages']) && is_array($body['messages'])) {
            foreach ($body['messages'] as $msg) {
                $messages[] = new Message(
                    role: \MonkeysLegion\Apex\Enum\Role::from($msg['role']),
                    content: $msg['content'],
                );
            }
        } elseif (isset($body['message'])) {
            $messages[] = Message::user($body['message']);
        }

        return $messages;
    }

    /**
     * Build JSON response array.
     *
     * @return array<string, mixed>
     */
    protected function responseArray(\MonkeysLegion\Apex\DTO\Response $response): array
    {
        return [
            'content'       => $response->content,
            'finish_reason' => $response->finishReason->value,
            'usage' => [
                'prompt_tokens'     => $response->usage->promptTokens,
                'completion_tokens' => $response->usage->completionTokens,
                'total_tokens'      => $response->usage->totalTokens,
            ],
            'model'    => $response->model,
            'provider' => $response->provider,
        ];
    }
}
