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

namespace MonkeysLegion\Apex\DTO;

/**
 * Token usage statistics.
 */
final readonly class Usage
{
    public int $totalTokens;

    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
    ) {
        $this->totalTokens = $this->promptTokens + $this->completionTokens;
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'prompt_tokens'     => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens'      => $this->totalTokens,
        ];
    }
}
