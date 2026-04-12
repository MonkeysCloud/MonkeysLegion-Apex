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

use MonkeysLegion\Apex\Enum\ModelTier;

/**
 * Model metadata — name, tier, pricing, context window.
 */
final readonly class ModelInfo
{
    public function __construct(
        public string    $name,
        public string    $provider,
        public ModelTier $tier,
        public int       $contextWindow,
        public int       $maxOutputTokens,
        public float     $inputPricePerMillion,
        public float     $outputPricePerMillion,
        public bool      $supportsStreaming = true,
        public bool      $supportsToolCalls = true,
        public bool      $supportsVision = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'              => $this->name,
            'provider'          => $this->provider,
            'tier'              => $this->tier->value,
            'context_window'    => $this->contextWindow,
            'max_output_tokens' => $this->maxOutputTokens,
            'pricing'           => [
                'input_per_million'  => $this->inputPricePerMillion,
                'output_per_million' => $this->outputPricePerMillion,
            ],
        ];
    }
}
