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
 * Chat message roles.
 */
enum Role: string
{
    case System    = 'system';
    case User      = 'user';
    case Assistant = 'assistant';
    case Tool      = 'tool';
}
