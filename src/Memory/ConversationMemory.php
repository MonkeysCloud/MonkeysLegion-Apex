<?php



/**
 * MonkeysLegion Apex
 *
 * @package   MonkeysLegion\Apex
 * @author    MonkeysCloud <jorge@monkeys.cloud>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

declare(strict_types=1);

namespace MonkeysLegion\Apex\Memory;

use MonkeysLegion\Apex\Contract\MemoryInterface;
use MonkeysLegion\Apex\DTO\Message;

/** Simple conversation memory — stores all messages, no trimming. */
final class ConversationMemory implements MemoryInterface
{
    /** @var list<Message> */
    private array $messages = [];

    public function add(Message $message): void
    {
        $this->messages[] = $message;
    }

    /** @return list<Message> */
    public function messages(): array
    {
        return $this->messages;
    }

    public function clear(): void
    {
        $this->messages = [];
    }
}
