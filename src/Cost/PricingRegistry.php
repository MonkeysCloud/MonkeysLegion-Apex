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

namespace MonkeysLegion\Apex\Cost;

/**
 * Registry of model pricing (per 1M tokens).
 */
final class PricingRegistry
{
    /** @var array<string, ModelPricing> */
    private array $pricing;

    public function __construct()
    {
        $this->pricing = self::defaults();
    }

    /**
     * Get pricing for a model. Falls back to zero-cost for unknown models.
     */
    public function get(string $model): ModelPricing
    {
        return $this->pricing[$model]
            ?? $this->pricing[$this->resolveAlias($model)]
            ?? new ModelPricing(0.0, 0.0);
    }

    /**
     * Register custom model pricing.
     */
    public function register(string $model, float $inputPerMillion, float $outputPerMillion): void
    {
        $this->pricing[$model] = new ModelPricing($inputPerMillion, $outputPerMillion);
    }

    /**
     * Default pricing table — kept up to date with major providers.
     *
     * @return array<string, ModelPricing>
     */
    private static function defaults(): array
    {
        return [
            // Anthropic
            'claude-opus-4'       => new ModelPricing(15.00, 75.00),
            'claude-sonnet-4'     => new ModelPricing(3.00,  15.00),
            'claude-haiku-4'      => new ModelPricing(0.80,  4.00),

            // OpenAI
            'gpt-4.1'            => new ModelPricing(2.00,  8.00),
            'gpt-4.1-mini'       => new ModelPricing(0.40,  1.60),
            'gpt-4.1-nano'       => new ModelPricing(0.10,  0.40),
            'o3'                 => new ModelPricing(10.00, 40.00),
            'o4-mini'            => new ModelPricing(1.10,  4.40),

            // Google
            'gemini-2.5-pro'     => new ModelPricing(1.25,  10.00),
            'gemini-2.5-flash'   => new ModelPricing(0.15,  0.60),

            // DeepSeek
            'deepseek-v3'        => new ModelPricing(0.27,  1.10),
            'deepseek-r1'        => new ModelPricing(0.55,  2.19),

            // Local (Ollama — free)
            'llama3'             => new ModelPricing(0.00, 0.00),
            'mistral'            => new ModelPricing(0.00, 0.00),
        ];
    }

    /**
     * Resolve short alias → full model name.
     */
    private function resolveAlias(string $model): string
    {
        return match ($model) {
            'sonnet'   => 'claude-sonnet-4',
            'opus'     => 'claude-opus-4',
            'haiku'    => 'claude-haiku-4',
            default    => $model,
        };
    }
}
