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
 * Reason the model stopped generating.
 */
enum FinishReason: string
{
    case Stop          = 'stop';
    case Length         = 'length';
    case ToolCall      = 'tool_call';
    case ContentFilter = 'content_filter';
}
