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

use MonkeysLegion\Apex\Cost\CostReport;
use MonkeysLegion\Apex\Cost\CostTracker;

/**
 * CLI command to display AI cost reports.
 *
 * When MonkeysLegion CLI package is available, register this command via:
 *   #[Command('ai:costs', 'Display AI usage cost report')]
 *
 * Usage:
 *   php ml ai:costs
 *   php ml ai:costs --format=json
 */
final class CostReportCommand
{
    public function __construct(
        private readonly CostTracker $tracker,
    ) {}

    /**
     * Generate and print a cost report.
     *
     * @param resource $output Output stream.
     */
    public function execute($output = STDOUT): int
    {
        $costs  = $this->tracker->all();
        $report = CostReport::generate($costs);
        $data   = $report->toArray();

        fwrite($output, "=== MonkeysLegion Apex — Cost Report ===\n\n");
        fwrite($output, sprintf("Period: %s to %s\n", $data['period']['from'], $data['period']['to']));
        fwrite($output, sprintf("Total Cost:  \$%.6f\n", $data['summary']['total']));
        fwrite($output, sprintf("Input Cost:  \$%.6f\n", $data['summary']['input']));
        fwrite($output, sprintf("Output Cost: \$%.6f\n", $data['summary']['output']));
        fwrite($output, sprintf("Requests:    %d\n\n", $data['summary']['count']));

        if (!empty($data['by_model'])) {
            fwrite($output, "By Model:\n");
            foreach ($data['by_model'] as $model => $info) {
                fwrite($output, sprintf("  %-30s %3d calls  \$%.6f total  \$%.6f avg\n",
                    $model, $info['count'], $info['total'], $info['avg']));
            }
        }

        return 0;
    }
}
