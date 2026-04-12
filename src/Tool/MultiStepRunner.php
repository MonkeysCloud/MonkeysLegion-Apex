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

namespace MonkeysLegion\Apex\Tool;

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\DTO\ToolResult;
use MonkeysLegion\Apex\Enum\FinishReason;

/**
 * Runs multi-step tool call loops until the LLM stops requesting tools.
 */
final class MultiStepRunner
{
    public function __construct(
        private readonly AI           $ai,
        private readonly ToolExecutor $executor,
        private readonly int          $maxSteps = 10,
    ) {}

    /**
     * Run a conversation with tool calls until completion.
     *
     * @param string|list<Message>   $input
     * @param array<string, mixed>   $options
     */
    public function run(string|array $input, string $system = '', array $options = []): Response
    {
        $messages = is_string($input)
            ? [Message::user($input)]
            : $input;

        if ($system !== '') {
            array_unshift($messages, Message::system($system));
        }

        $steps = 0;

        while ($steps < $this->maxSteps) {
            $response = $this->ai->generate($messages, options: $options);
            $steps++;

            if (!$response->hasToolCalls()) {
                return $response;
            }

            // Add assistant message with tool calls
            $messages[] = Message::assistant($response->content, toolCalls: $response->toolCalls);

            // Execute each tool call
            foreach ($response->toolCalls as $tc) {
                $result = $this->executor->execute($tc);
                $messages[] = Message::tool(
                    $result->success
                        ? (is_string($result->output) ? $result->output : json_encode($result->output))
                        : "Error: {$result->error}",
                    $tc->id,
                );
            }
        }

        // Max steps reached — return last response
        return $this->ai->generate($messages, options: $options);
    }
}
