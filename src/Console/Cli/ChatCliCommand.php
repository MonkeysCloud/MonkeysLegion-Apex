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

namespace MonkeysLegion\Apex\Console\Cli;

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\Console\ChatCommand;
use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttribute;
use MonkeysLegion\Cli\Console\Command;

/**
 * CLI adapter — registers ChatCommand with the MonkeysLegion CLI framework.
 */
#[CommandAttribute('ai:chat', 'Start an interactive AI chat session')]
final class ChatCliCommand extends Command
{
    public function __construct(
        private readonly AI $ai,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $cmd = new ChatCommand($this->ai);
        return $cmd->execute(STDIN, STDOUT);
    }
}
