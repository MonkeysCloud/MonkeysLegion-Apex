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
 * Memory/context management contract.
 */
interface MemoryInterface
{
    /**
     * Add a message to memory.
     */
    public function add(Message $message): void;

    /**
     * Get all messages in the current context.
     *
     * @return list<Message>
     */
    public function messages(): array;

    /**
     * Clear all messages.
     */
    public function clear(): void;
}
