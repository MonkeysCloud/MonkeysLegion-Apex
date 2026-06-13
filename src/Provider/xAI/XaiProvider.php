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

namespace MonkeysLegion\Apex\Provider\xAI;

use MonkeysLegion\Apex\DTO\ModelInfo;
use MonkeysLegion\Apex\Enum\ModelTier;
use MonkeysLegion\Apex\Provider\OpenAICompatible\GenericProvider;

/**
 * xAI provider — Grok models via OpenAI-compatible API.
 */
final class XaiProvider extends GenericProvider
{
    protected const string DEFAULT_BASE_URL = 'https://api.x.ai';

    public function __construct(
        string  $apiKey,
        string  $model = 'grok-3',
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
            providerName: 'xai',
            embeddingModel: $embeddingModel,
        );
    }

    public function name(): string
    {
        return 'xai';
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
            'grok-3' => new ModelInfo(
                name: 'grok-3',
                provider: 'xai',
                tier: ModelTier::Power,
                contextWindow: 131_072,
                maxOutputTokens: 131_072,
                inputPricePerMillion: 3.00,
                outputPricePerMillion: 15.00,
            ),
            'grok-3-mini' => new ModelInfo(
                name: 'grok-3-mini',
                provider: 'xai',
                tier: ModelTier::Balanced,
                contextWindow: 131_072,
                maxOutputTokens: 131_072,
                inputPricePerMillion: 0.30,
                outputPricePerMillion: 0.50,
            ),
            'grok-3-fast' => new ModelInfo(
                name: 'grok-3-fast',
                provider: 'xai',
                tier: ModelTier::Fast,
                contextWindow: 131_072,
                maxOutputTokens: 131_072,
                inputPricePerMillion: 5.00,
                outputPricePerMillion: 25.00,
            ),
        ];
    }
}
