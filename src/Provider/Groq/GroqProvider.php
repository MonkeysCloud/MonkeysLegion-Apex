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

namespace MonkeysLegion\Apex\Provider\Groq;

use MonkeysLegion\Apex\DTO\ModelInfo;
use MonkeysLegion\Apex\Enum\ModelTier;
use MonkeysLegion\Apex\Provider\OpenAICompatible\GenericProvider;

/**
 * Groq provider — ultra-fast LPU inference via OpenAI-compatible API.
 */
final class GroqProvider extends GenericProvider
{
    protected const string DEFAULT_BASE_URL = 'https://api.groq.com/openai';

    public function __construct(
        string  $apiKey,
        string  $model = 'llama-3.3-70b-versatile',
        ?string $baseUrl = null,
        ?float  $timeout = null,
        ?int    $maxRetries = null,
    ) {
        parent::__construct($apiKey, $model, $baseUrl ?? static::DEFAULT_BASE_URL, $timeout, $maxRetries, 'groq');
    }

    public function name(): string
    {
        return 'groq';
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
            'llama-3.3-70b-versatile' => new ModelInfo(
                name: 'llama-3.3-70b-versatile',
                provider: 'groq',
                tier: ModelTier::Balanced,
                contextWindow: 128_000,
                maxOutputTokens: 32_768,
                inputPricePerMillion: 0.59,
                outputPricePerMillion: 0.79,
            ),
            'llama-3.1-8b-instant' => new ModelInfo(
                name: 'llama-3.1-8b-instant',
                provider: 'groq',
                tier: ModelTier::Fast,
                contextWindow: 128_000,
                maxOutputTokens: 8_192,
                inputPricePerMillion: 0.05,
                outputPricePerMillion: 0.08,
            ),
            'mixtral-8x7b-32768' => new ModelInfo(
                name: 'mixtral-8x7b-32768',
                provider: 'groq',
                tier: ModelTier::Fast,
                contextWindow: 32_768,
                maxOutputTokens: 32_768,
                inputPricePerMillion: 0.24,
                outputPricePerMillion: 0.24,
            ),
            'gemma2-9b-it' => new ModelInfo(
                name: 'gemma2-9b-it',
                provider: 'groq',
                tier: ModelTier::Fast,
                contextWindow: 8_192,
                maxOutputTokens: 8_192,
                inputPricePerMillion: 0.20,
                outputPricePerMillion: 0.20,
            ),
        ];
    }
}
