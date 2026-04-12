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

namespace MonkeysLegion\Apex\Router;

use MonkeysLegion\Apex\DTO\Message;

/**
 * Conditional routing rule for the model router.
 */
final readonly class RoutingRule
{
    /**
     * @param callable(list<Message>, array<string, mixed>): bool $condition
     */
    public function __construct(
        private mixed  $condition,
        public  string $tier,
    ) {}

    /**
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     */
    public function matches(array $messages, array $options): bool
    {
        return ($this->condition)($messages, $options);
    }
}
