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

namespace MonkeysLegion\Apex\A2A;

/**
 * A2A Message — inter-agent communication payload.
 */
final readonly class A2AMessage
{
    /**
     * @param array<string, mixed> $parts Content parts (text, data, file references)
     */
    public function __construct(
        public string $role,
        public array  $parts,
        public string $taskId = '',
    ) {}

    /**
     * Create a user/requester message.
     */
    public static function from(string $text, string $taskId = ''): self
    {
        return new self('user', [['type' => 'text', 'text' => $text]], $taskId);
    }

    /**
     * Create an agent response message.
     */
    public static function response(string $text, string $taskId = ''): self
    {
        return new self('agent', [['type' => 'text', 'text' => $text]], $taskId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role'   => $this->role,
            'parts'  => $this->parts,
            'taskId' => $this->taskId,
        ];
    }
}
