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

namespace MonkeysLegion\Apex\Contract;

use MonkeysLegion\Apex\Pipeline\PipelineContext;
use MonkeysLegion\Apex\Pipeline\PipelineResult;

/**
 * Declarative AI pipeline contract.
 */
interface PipelineInterface
{
    /**
     * Execute the pipeline with input.
     */
    public function run(string $input, string $model = ''): PipelineResult;
}
