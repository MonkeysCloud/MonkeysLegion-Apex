# MonkeysLegion-Apex 🐵⚡

> AI Orchestration Engine for the MonkeysLegion Framework

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4+-blue.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

## Overview

MonkeysLegion-Apex is a **provider-agnostic AI abstraction layer** that unifies LLM interactions across Anthropic, OpenAI, Google, and Ollama behind a single, type-safe PHP 8.4 API.

## Features

- **🤖 Provider-Agnostic** — Swap between Anthropic, OpenAI, Ollama without code changes
- **📐 Structured Output** — PHP class → JSON Schema → typed LLM output via `extract()`
- **🔧 Tool Calling** — `#[Tool]` attributes auto-register callable functions for multi-step agent loops
- **🌊 Streaming** — Real-time SSE streaming with `TextStream`
- **🛡️ Guardrails** — PII detection, prompt injection detection, composable input/output validation
- **🧭 Smart Router** — Complexity-based model selection (cost-optimized, quality-first, latency-first, round-robin)
- **💰 Cost Tracking** — Per-request cost calculation with configurable pricing registry
- **🧠 Memory** — Sliding window context management with token/message limits
- **🔗 Middleware Pipeline** — Onion-model middleware for logging, caching, rate limiting
- **🧪 FakeProvider** — Zero-API-calls testing infrastructure

## Installation

```bash
composer require monkeyscloud/monkeyslegion-apex
```

## Quick Start

### Text Generation

```php
use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\Provider\Anthropic\AnthropicProvider;

$ai = new AI(new AnthropicProvider(
    apiKey: $_ENV['ANTHROPIC_API_KEY'],
    model:  'claude-sonnet-4',
));

$response = $ai->generate(
    'Explain PHP 8.4 property hooks in 3 sentences',
    system: 'You are a senior PHP developer.',
);

echo $response->content;
echo "Tokens: {$response->usage->totalTokens}";
```

### Structured Output

```php
use MonkeysLegion\Apex\Schema\Schema;
use MonkeysLegion\Apex\Schema\Attribute\{Description, Constrain, Optional};

final class SentimentResult extends Schema
{
    public function __construct(
        #[Description('The detected sentiment')]
        #[Constrain(enum: ['positive', 'negative', 'neutral'])]
        public readonly string $sentiment,

        #[Description('Confidence score from 0 to 1')]
        #[Constrain(min: 0.0, max: 1.0)]
        public readonly float $confidence,

        #[Description('Optional explanation')]
        #[Optional]
        public readonly ?string $explanation = null,
    ) {}
}

$result = $ai->extract(SentimentResult::class, 'This product is amazing!');
// $result->sentiment === 'positive'
// $result->confidence === 0.95
```

### Tool Calling

```php
use MonkeysLegion\Apex\Tool\Attribute\{Tool, ToolParam};

final class WeatherTools
{
    #[Tool(name: 'get_weather', description: 'Get current weather')]
    public function getWeather(
        #[ToolParam(description: 'City name')]
        string $city,
    ): array {
        return ['city' => $city, 'temp' => 22, 'unit' => 'C'];
    }
}

$response = $ai->generate(
    "What's the weather in Tokyo?",
    options: ['tools' => [new WeatherTools()]],
);
```

### Streaming

```php
$stream = $ai->stream('Write a poem about PHP');

// Option 1: Iterate chunks
foreach ($stream as $chunk) {
    echo $chunk->delta;
    flush();
}

// Option 2: Get full text after streaming
$text = $stream->text();

// Option 3: SSE for HTTP responses
foreach ($stream->toSSE() as $event) {
    echo $event;
}
```

### Guardrails

```php
use MonkeysLegion\Apex\Guard\Guard;
use MonkeysLegion\Apex\Guard\Validator\{PIIDetectorValidator, PromptInjectionValidator};

$guard = Guard::create()
    ->input(new PromptInjectionValidator())     // Block injection attempts
    ->output(new PIIDetectorValidator());       // Redact PII in output

$guard->validateInput($userPrompt);             // throws GuardException if blocked
$result = $guard->validateOutput($llmResponse); // returns GuardResult with redacted text
```

### Smart Router

```php
use MonkeysLegion\Apex\Router\ModelRouter;
use MonkeysLegion\Apex\Enum\RouterStrategy;

$router = ModelRouter::create()
    ->tier('fast',     ['claude-haiku-4', 'gpt-4.1-nano'])
    ->tier('balanced', ['claude-sonnet-4', 'gpt-4.1'])
    ->tier('power',    ['claude-opus-4', 'o3'])
    ->strategy(RouterStrategy::CostOptimized);

$model = $router->select($messages); // Auto-selects based on complexity
```

### Cost Tracking

```php
use MonkeysLegion\Apex\Cost\{CostTracker, PricingRegistry};

$tracker = new CostTracker(new PricingRegistry());
$ai = new AI($provider, $tracker);

$ai->generate('Hello');
echo "Total cost: $" . number_format($tracker->totalCost(), 4);
```

### Testing with FakeProvider

```php
use MonkeysLegion\Apex\Testing\FakeProvider;

$fake = FakeProvider::create()
    ->respondWith('Mocked response')
    ->respondWith('Second response');

$ai = new AI($fake);
$response = $ai->generate('test');
assert($response->content === 'Mocked response');
assert($fake->calledTimes() === 1);
```

## Architecture

```
src/
├── AI.php                        # Main facade
├── Contract/                     # Interfaces
│   ├── ProviderInterface.php
│   ├── GuardInterface.php
│   ├── RouterInterface.php
│   ├── MiddlewareInterface.php
│   ├── MemoryInterface.php
│   ├── EmbeddingInterface.php
│   └── CostTrackerInterface.php
├── DTO/                          # Immutable value objects
│   ├── Message.php, Response.php, Usage.php, Cost.php
│   ├── StreamChunk.php, ToolCall.php, ToolResult.php
│   ├── GuardResult.php, EmbeddingVector.php, ModelInfo.php
├── Enum/                         # Backed string enums
├── Exception/                    # Exception hierarchy
├── Provider/                     # LLM providers
│   ├── AbstractProvider.php
│   ├── Anthropic/AnthropicProvider.php
│   ├── OpenAI/OpenAIProvider.php
│   └── Ollama/OllamaProvider.php
├── Schema/                       # Structured output engine
│   ├── Schema.php, SchemaCompiler.php, SchemaValidator.php
│   └── Attribute/  (#[Description], #[Constrain], #[Optional], etc.)
├── Tool/                         # Tool calling system
│   ├── ToolRegistry.php, ToolExecutor.php
│   └── Attribute/  (#[Tool], #[ToolParam])
├── Streaming/TextStream.php
├── Guard/                        # Guardrails engine
│   ├── Guard.php
│   └── Validator/ (PIIDetector, PromptInjection)
├── Router/ModelRouter.php
├── Memory/SlidingWindowMemory.php
├── Middleware/                    # Pipeline system
├── Cost/                         # Cost tracking
└── Testing/FakeProvider.php
```

## Requirements

- PHP 8.4+
- ext-curl
- ext-json
- ext-mbstring

## License

MIT
