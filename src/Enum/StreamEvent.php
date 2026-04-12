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
 * Streaming event types.
 */
enum StreamEvent: string
{
    case TextDelta     = 'text_delta';
    case ObjectPartial = 'object_partial';
    case ToolCall      = 'tool_call';
    case Done          = 'done';
    case Error         = 'error';
}
