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

namespace MonkeysLegion\Apex\Provider\DeepSeek;

use MonkeysLegion\Apex\DTO\ModelInfo;
use MonkeysLegion\Apex\Enum\ModelTier;
use MonkeysLegion\Apex\Provider\OpenAICompatible\GenericProvider;

/**
 * DeepSeek provider — DeepSeek V3 and R1 via OpenAI-compatible API.
 */
final class DeepSeekProvider extends GenericProvider
{
    protected const string DEFAULT_BASE_URL = 'https://api.deepseek.com';

    public function __construct(
        string  $apiKey,
        string  $model = 'deepseek-chat',
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
            providerName: 'deepseek',
            embeddingModel: $embeddingModel,
        );
    }

    public function name(): string
    {
        return 'deepseek';
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
            'deepseek-chat' => new ModelInfo(
                name: 'deepseek-chat',
                provider: 'deepseek',
                tier: ModelTier::Balanced,
                contextWindow: 128_000,
                maxOutputTokens: 8_192,
                inputPricePerMillion: 0.27,
                outputPricePerMillion: 1.10,
            ),
            'deepseek-reasoner' => new ModelInfo(
                name: 'deepseek-reasoner',
                provider: 'deepseek',
                tier: ModelTier::Power,
                contextWindow: 128_000,
                maxOutputTokens: 16_384,
                inputPricePerMillion: 0.55,
                outputPricePerMillion: 2.19,
            ),
        ];
    }
}
