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

use MonkeysLegion\Apex\DTO\GuardResult;
use MonkeysLegion\Apex\Enum\GuardAction;

/**
 * Guardrail validator contract.
 */
interface GuardInterface
{
    /**
     * Validate text and return result.
     */
    public function validate(string $text): GuardResult;

    /**
     * Get the default action when validation fails.
     */
    public function defaultAction(): GuardAction;

    /**
     * Get validator name for logging.
     */
    public function name(): string;
}
