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
 * Action to take when a guardrail detects a violation.
 */
enum GuardAction: string
{
    case Block    = 'block';
    case Redact   = 'redact';
    case Retry    = 'retry';
    case Warn     = 'warn';
    case Truncate = 'truncate';
    case Replace  = 'replace';
    case Pass     = 'pass';
}
