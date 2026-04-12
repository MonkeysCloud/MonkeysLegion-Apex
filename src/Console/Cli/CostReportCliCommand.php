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

use MonkeysLegion\Apex\Console\CostReportCommand;
use MonkeysLegion\Apex\Cost\CostTracker;
use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttribute;
use MonkeysLegion\Cli\Console\Command;

/**
 * CLI adapter — registers CostReportCommand with the MonkeysLegion CLI framework.
 */
#[CommandAttribute('ai:costs', 'Display AI usage cost report')]
final class CostReportCliCommand extends Command
{
    public function __construct(
        private readonly CostTracker $tracker,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $cmd = new CostReportCommand($this->tracker);
        return $cmd->execute(STDOUT);
    }
}
