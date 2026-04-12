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
use Psr\Log\LoggerInterface;

/**
 * Warn action — logs warning but allows through.
 */
final class WarnAction
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function execute(GuardResult $result): GuardResult
    {
        $this->logger?->warning('Guard warning', [
            'validator'  => $result->validator,
            'violations' => $result->violations,
        ]);

        return $result;
    }
}
