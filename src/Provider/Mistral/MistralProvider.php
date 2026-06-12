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

namespace MonkeysLegion\Apex\Provider\Mistral;

use MonkeysLegion\Apex\DTO\ModelInfo;
use MonkeysLegion\Apex\Enum\ModelTier;
use MonkeysLegion\Apex\Provider\OpenAICompatible\GenericProvider;

/**
 * Mistral AI provider — Mistral and Mixtral models via OpenAI-compatible API.
 */
final class MistralProvider extends GenericProvider
{
    protected const string DEFAULT_BASE_URL = 'https://api.mistral.ai';

    public function __construct(
        string  $apiKey,
        string  $model = 'mistral-large-latest',
        ?string $baseUrl = null,
        ?float  $timeout = null,
        ?int    $maxRetries = null,
        ?string $embeddingModel = null,
    ) {
        parent::__construct(
            apiKey: $apiKey,
            model: $model,
            baseUrl: $baseUrl ?? static::DEFAULT_BASE_URL,
            timeout: $timeout,
            maxRetries: $maxRetries,
            providerName: 'mistral',
            embeddingModel: $embeddingModel,
        );
    }

    public function name(): string
    {
        return 'mistral';
    }

    public function modelInfo(string $model): ModelInfo
    {
        $catalog = $this->buildModelCatalog();
        return $catalog[$model] ?? parent::modelInfo($model);
    }

    /**
     * @return list<ModelInfo>
     */
    public function listModels(): array
    {
        return array_values($this->buildModelCatalog());
    }

    /**
     * @return array<string, ModelInfo>
     */
    private function buildModelCatalog(): array
    {
        return [
            'mistral-large-latest' => new ModelInfo(
                name: 'mistral-large-latest',
                provider: 'mistral',
                tier: ModelTier::Power,
                contextWindow: 128_000,
                maxOutputTokens: 32_000,
                inputPricePerMillion: 2.00,
                outputPricePerMillion: 6.00,
            ),
            'mistral-medium-latest' => new ModelInfo(
                name: 'mistral-medium-latest',
                provider: 'mistral',
                tier: ModelTier::Balanced,
                contextWindow: 128_000,
                maxOutputTokens: 32_000,
                inputPricePerMillion: 0.80,
                outputPricePerMillion: 2.40,
            ),
            'mistral-small-latest' => new ModelInfo(
                name: 'mistral-small-latest',
                provider: 'mistral',
                tier: ModelTier::Fast,
                contextWindow: 128_000,
                maxOutputTokens: 32_000,
                inputPricePerMillion: 0.20,
                outputPricePerMillion: 0.60,
            ),
            'codestral-latest' => new ModelInfo(
                name: 'codestral-latest',
                provider: 'mistral',
                tier: ModelTier::Balanced,
                contextWindow: 256_000,
                maxOutputTokens: 32_000,
                inputPricePerMillion: 0.30,
                outputPricePerMillion: 0.90,
            ),
        ];
    }
}
