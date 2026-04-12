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

namespace MonkeysLegion\Apex\Guard\Validator;

use MonkeysLegion\Apex\Contract\GuardInterface;
use MonkeysLegion\Apex\DTO\GuardResult;
use MonkeysLegion\Apex\Enum\GuardAction;

/**
 * Custom callback validator — apply arbitrary validation logic.
 */
final class CustomValidator implements GuardInterface
{
    /** @var callable(string): GuardResult */
    private readonly \Closure $callback;

    /**
     * @param callable(string): GuardResult $callback
     */
    public function __construct(
        callable $callback,
        private readonly string      $validatorName = 'custom',
        private readonly GuardAction $onFail = GuardAction::Block,
    ) {
        $this->callback = $callback(...);
    }

    public function validate(string $text): GuardResult
    {
        return ($this->callback)($text);
    }

    public function defaultAction(): GuardAction
    {
        return $this->onFail;
    }

    public function name(): string
    {
        return $this->validatorName;
    }
}
