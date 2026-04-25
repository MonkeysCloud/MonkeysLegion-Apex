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

use MonkeysLegion\Apex\DTO\ModelInfo;
use MonkeysLegion\Apex\Enum\ModelTier;

/**
 * Registry of known models and their metadata.
 */
final class ModelRegistry
{
    /** @var array<string, ModelInfo> */
    private array $models = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    /**
     * Register a model.
     */
    public function register(ModelInfo $model): void
    {
        $this->models[$model->name] = $model;
    }

    /**
     * Get model info by name.
     */
    public function get(string $name): ?ModelInfo
    {
        return $this->models[$name] ?? null;
    }

    /**
     * List all models.
     *
     * @return list<ModelInfo>
     */
    public function all(): array
    {
        return array_values($this->models);
    }

    /**
     * List models by tier.
     *
     * @return list<ModelInfo>
     */
    public function byTier(ModelTier $tier): array
    {
        return array_values(array_filter(
            $this->models,
            fn(ModelInfo $m) => $m->tier === $tier,
        ));
    }

    /**
     * List models by provider.
     *
     * @return list<ModelInfo>
     */
    public function byProvider(string $provider): array
    {
        return array_values(array_filter(
            $this->models,
            fn(ModelInfo $m) => $m->provider === $provider,
        ));
    }

    /**
     * Find cheapest model that supports given features.
     */
    public function cheapest(
        bool $streaming = false,
        bool $toolCalls = false,
        bool $vision = false,
    ): ?ModelInfo {
        $candidates = array_filter($this->models, function (ModelInfo $m) use ($streaming, $toolCalls, $vision) {
            if ($streaming && !$m->supportsStreaming) return false;
            if ($toolCalls && !$m->supportsToolCalls) return false;
            if ($vision && !$m->supportsVision) return false;
            return true;
        });

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn(ModelInfo $a, ModelInfo $b) =>
            ($a->inputPricePerMillion + $a->outputPricePerMillion)
            <=> ($b->inputPricePerMillion + $b->outputPricePerMillion)
        );

        return $candidates[0];
    }

    private function registerDefaults(): void
    {
        $defaults = [
            new ModelInfo('claude-opus-4', 'anthropic', ModelTier::Power, 200_000, 32_000, 15.0, 75.0, true, true, true),
            new ModelInfo('claude-sonnet-4', 'anthropic', ModelTier::Balanced, 200_000, 64_000, 3.0, 15.0, true, true, true),
            new ModelInfo('claude-haiku-4', 'anthropic', ModelTier::Fast, 200_000, 64_000, 0.8, 4.0, true, true, true),
            new ModelInfo('gpt-4.1', 'openai', ModelTier::Balanced, 1_000_000, 32_000, 2.0, 8.0, true, true, true),
            new ModelInfo('gpt-4.1-mini', 'openai', ModelTier::Fast, 1_000_000, 32_000, 0.4, 1.6),
            new ModelInfo('gpt-4.1-nano', 'openai', ModelTier::Fast, 1_000_000, 32_000, 0.1, 0.4),
            new ModelInfo('o3', 'openai', ModelTier::Power, 200_000, 100_000, 10.0, 40.0, true, true, true),
            new ModelInfo('o4-mini', 'openai', ModelTier::Balanced, 200_000, 100_000, 1.1, 4.4),
            new ModelInfo('gemini-2.5-pro', 'google', ModelTier::Power, 1_000_000, 65_000, 1.25, 10.0, true, true, true),
            new ModelInfo('gemini-2.5-flash', 'google', ModelTier::Fast, 1_000_000, 65_000, 0.15, 0.6),

            // DeepSeek
            new ModelInfo('deepseek-chat', 'deepseek', ModelTier::Balanced, 128_000, 8_192, 0.27, 1.1),
            new ModelInfo('deepseek-reasoner', 'deepseek', ModelTier::Power, 128_000, 16_384, 0.55, 2.19),

            // Mistral
            new ModelInfo('mistral-large-latest', 'mistral', ModelTier::Power, 128_000, 32_000, 2.0, 6.0, true, true),
            new ModelInfo('mistral-medium-latest', 'mistral', ModelTier::Balanced, 128_000, 32_000, 0.8, 2.4),
            new ModelInfo('mistral-small-latest', 'mistral', ModelTier::Fast, 128_000, 32_000, 0.2, 0.6),
            new ModelInfo('codestral-latest', 'mistral', ModelTier::Balanced, 256_000, 32_000, 0.3, 0.9),

            // Groq
            new ModelInfo('llama-3.3-70b-versatile', 'groq', ModelTier::Balanced, 128_000, 32_768, 0.59, 0.79),
            new ModelInfo('llama-3.1-8b-instant', 'groq', ModelTier::Fast, 128_000, 8_192, 0.05, 0.08),
            new ModelInfo('mixtral-8x7b-32768', 'groq', ModelTier::Fast, 32_768, 32_768, 0.24, 0.24),
            new ModelInfo('gemma2-9b-it', 'groq', ModelTier::Fast, 8_192, 8_192, 0.2, 0.2),

            // Ollama (local)
            new ModelInfo('llama3', 'ollama', ModelTier::Fast, 8_192, 4_096, 0.0, 0.0),
        ];

        foreach ($defaults as $model) {
            $this->register($model);
        }
    }
}
