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
 * Model performance tiers for smart routing.
 */
enum ModelTier: string
{
    case Fast     = 'fast';
    case Balanced = 'balanced';
    case Power    = 'power';
}
