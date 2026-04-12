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

use MonkeysLegion\Apex\DTO\Message;

/**
 * Smart model router contract.
 */
interface RouterInterface
{
    /**
     * Select the best model for this request.
     *
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     * @return string Model identifier.
     */
    public function select(array $messages, array $options = []): string;
}
