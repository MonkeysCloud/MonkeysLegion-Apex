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

namespace MonkeysLegion\Apex\Middleware\Impl;

use MonkeysLegion\Apex\Contract\MiddlewareInterface;
use MonkeysLegion\Apex\Guard\Guard;
use MonkeysLegion\Apex\Middleware\MiddlewareContext;

/**
 * Validates user input through guardrails before the LLM call.
 */
final class InputGuardMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Guard $guard,
    ) {}

    public function handle(MiddlewareContext $context, callable $next): mixed
    {
        // Validate user messages
        foreach ($context->messages as $i => $message) {
            if ($message->role === \MonkeysLegion\Apex\Enum\Role::User) {
                $result = $this->guard->validateInput($message->content);
                if (!$result->passed) {
                    // Replace with redacted version
                    $context->messages[$i] = \MonkeysLegion\Apex\DTO\Message::user($result->text);
                }
                $context->metadata['input_guard_passed'] = $result->passed;
            }
        }

        return $next($context);
    }
}
