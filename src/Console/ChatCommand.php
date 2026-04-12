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

/**
 * Interactive AI chat console.
 */
final class ChatCommand
{
    public function __construct(
        private readonly AI     $ai,
        private readonly string $system = 'You are a helpful assistant.',
    ) {}

    /**
     * Run interactive chat loop.
     *
     * @param resource $input  Input stream (default: STDIN).
     * @param resource $output Output stream (default: STDOUT).
     */
    public function execute($input = STDIN, $output = STDOUT): int
    {
        fwrite($output, "MonkeysLegion Apex — Interactive Chat\n");
        fwrite($output, "Type 'exit' or 'quit' to end.\n\n");

        while (true) {
            fwrite($output, '> ');
            $line = fgets($input);

            if ($line === false || in_array(trim($line), ['exit', 'quit', ''], true)) {
                fwrite($output, "\nGoodbye!\n");
                break;
            }

            try {
                $response = $this->ai->generate(trim($line), system: $this->system);
                fwrite($output, "\n{$response->content}\n\n");
                fwrite($output, "[tokens: {$response->usage->totalTokens} | model: {$response->model}]\n\n");
            } catch (\Throwable $e) {
                fwrite($output, "\nError: {$e->getMessage()}\n\n");
            }
        }

        return 0;
    }
}
