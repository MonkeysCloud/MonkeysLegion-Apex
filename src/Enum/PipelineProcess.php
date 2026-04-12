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

namespace MonkeysLegion\Apex\Enum;

/**
 * Pipeline execution process type.
 */
enum PipelineProcess: string
{
    case Sequential  = 'sequential';
    case Parallel    = 'parallel';
    case Conditional = 'conditional';
}
