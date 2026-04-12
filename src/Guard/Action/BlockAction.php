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

namespace MonkeysLegion\Apex\Guard\Action;

use MonkeysLegion\Apex\DTO\GuardResult;

/**
 * Block action — throws GuardException.
 */
final class BlockAction
{
    public function execute(GuardResult $result): GuardResult
    {
        throw new \MonkeysLegion\Apex\Exception\GuardException(
            result: $result,
            message: "Guard blocked: [{$result->validator}] " . json_encode($result->violations),
        );
    }
}
