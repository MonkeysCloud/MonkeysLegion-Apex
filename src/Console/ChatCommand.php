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

namespace MonkeysLegion\Apex\Console;

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\DTO\Message;

/**
 * Interactive AI chat CLI command.
 *
 * When MonkeysLegion CLI package is available, register this command via:
 *   #[Command('ai:chat', 'Start an interactive AI chat session')]
 *
 * Usage:
 *   php ml ai:chat
 *   php ml ai:chat --model=claude-sonnet-4
 */
final class ChatCommand
{
    public function __construct(
        private readonly AI $ai,
    ) {}

    /**
     * Execute the interactive chat loop.
     *
     * @param resource $input  Input stream (default: STDIN).
     * @param resource $output Output stream (default: STDOUT).
     */
    public function execute($input = STDIN, $output = STDOUT): int
    {
        fwrite($output, "=== MonkeysLegion Apex — Interactive Chat ===\n");
        fwrite($output, "Type \"exit\" or \"quit\" to end the session.\n\n");

        /** @var list<Message> $history */
        $history = [];

        while (($line = fgets($input)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (in_array(strtolower($line), ['exit', 'quit'], true)) {
                fwrite($output, "Goodbye! 👋\n");
                return 0;
            }

            $history[] = Message::user($line);

            try {
                $response = $this->ai->generate($history);
                $history[] = Message::assistant($response->content);
                fwrite($output, "\nAI: {$response->content}\n\n");
            } catch (\Throwable $e) {
                fwrite($output, "Error: {$e->getMessage()}\n");
            }
        }

        return 0;
    }
}
