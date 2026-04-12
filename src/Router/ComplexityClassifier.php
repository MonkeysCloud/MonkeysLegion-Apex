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
use MonkeysLegion\Apex\Enum\Role;

/**
 * Classifies message complexity into tiers for routing decisions.
 *
 * Heuristics:
 *  - Token count (estimated)
 *  - System prompt presence
 *  - Attachment/vision content
 *  - Multi-turn conversation depth
 *  - Tool usage in options
 */
final class ComplexityClassifier
{
    /** @var array<string, callable(list<Message>, array<string, mixed>): bool> */
    private array $signals = [];

    public static function create(): self
    {
        $classifier = new self();
        return $classifier->withDefaults();
    }

    /**
     * Register default complexity signals.
     */
    public function withDefaults(): self
    {
        return $this
            ->signal('long_input', fn(array $msgs) => $this->totalLength($msgs) > 2000)
            ->signal('has_system', fn(array $msgs) => $this->hasRole($msgs, Role::System))
            ->signal('has_attachments', fn(array $msgs) => $this->hasAttachments($msgs))
            ->signal('multi_turn', fn(array $msgs) => count($msgs) > 4)
            ->signal('has_tools', fn(array $msgs, array $opts) => isset($opts['tools']));
    }

    /**
     * Register a custom complexity signal.
     *
     * @param callable(list<Message>, array<string, mixed>): bool $detector
     */
    public function signal(string $name, callable $detector): self
    {
        $this->signals[$name] = $detector;
        return $this;
    }

    /**
     * Classify complexity: low (0-1 signals), medium (2-3), high (4+).
     *
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     * @return array{tier: string, score: int, signals: list<string>}
     */
    public function classify(array $messages, array $options = []): array
    {
        $active = [];
        $score  = 0;

        foreach ($this->signals as $name => $detector) {
            if ($detector($messages, $options)) {
                $active[] = $name;
                $score++;
            }
        }

        $tier = match (true) {
            $score >= 4 => 'high',
            $score >= 2 => 'medium',
            default     => 'low',
        };

        return [
            'tier'    => $tier,
            'score'   => $score,
            'signals' => $active,
        ];
    }

    /**
     * @param list<Message> $messages
     */
    private function totalLength(array $messages): int
    {
        return array_sum(array_map(fn(Message $m) => strlen($m->content), $messages));
    }

    /**
     * @param list<Message> $messages
     */
    private function hasRole(array $messages, Role $role): bool
    {
        return !empty(array_filter($messages, fn(Message $m) => $m->role === $role));
    }

    /**
     * @param list<Message> $messages
     */
    private function hasAttachments(array $messages): bool
    {
        return !empty(array_filter($messages, fn(Message $m) => !empty($m->attachments)));
    }
}
