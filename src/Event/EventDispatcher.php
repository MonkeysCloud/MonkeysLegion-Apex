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

namespace MonkeysLegion\Apex\Event;

/**
 * Dispatches and listens for AI events.
 */
final class EventDispatcher
{
    /** @var array<string, list<callable(AIEvent): void>> */
    private array $listeners = [];

    /**
     * Register a listener for an event.
     *
     * @param callable(AIEvent): void $listener
     */
    public function listen(string $eventName, callable $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    /**
     * Dispatch an event to all listeners.
     */
    public function dispatch(AIEvent $event): void
    {
        foreach ($this->listeners[$event->name()] ?? [] as $listener) {
            $listener($event);
        }

        // Also dispatch to wildcard listeners
        foreach ($this->listeners['*'] ?? [] as $listener) {
            $listener($event);
        }
    }

    /**
     * Check if there are listeners for an event.
     */
    public function hasListeners(string $eventName): bool
    {
        return !empty($this->listeners[$eventName]);
    }
}
