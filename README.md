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
- **🌊 Streaming** — Real-time SSE streaming with `TextStream` + HTTP response wrapper
- **🛡️ Guardrails** — PII, prompt injection, toxicity, regex, word count, custom validators
- **🧭 Smart Router** — Complexity-based model selection with 4 strategies + custom rules
- **💰 Cost Tracking** — Per-request cost, budget management, cost reports, aggregation
- **🧠 Memory** — 5 memory types: Conversation, SlidingWindow, Summary, Vector, Persistent
- **🔗 Middleware Pipeline** — 8 built-in middlewares: RateLimit, CostBudget, InputGuard, OutputGuard, Cache, Retry, Fallback, Telemetry
- **🔀 Pipelines** — Declarative workflows with Generate, Extract, Classify, Summarize, Translate, Guard, Conditional, Loop steps
- **🤝 Multi-Agent** — Agent, Crew, Handoff with 4 orchestration modes (Sequential, Parallel, Hierarchical, Conversational)
- **📊 Embeddings** — EmbeddingManager, InMemoryStore, Similarity (cosine, euclidean, dot)
- **🏗️ Framework** — AIServiceProvider, AIStreamResponse, AIMiddleware, AIController
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
use MonkeysLegion\Apex\Http\AIStreamResponse;
(new AIStreamResponse($stream))->send();
```

### Declarative Pipelines

```php
use MonkeysLegion\Apex\Pipeline\Pipeline;
use MonkeysLegion\Apex\Pipeline\Step\{GenerateStep, ExtractStep, GuardStep};

$result = Pipeline::create('content-pipeline')
    ->pipe(new GuardStep($guard, isInput: true))
    ->pipe(new GenerateStep($ai, system: 'You are a writer'))
    ->pipe(new SummarizeStep($ai, maxWords: 100))
    ->transform('word_count', fn($ctx) => str_word_count($ctx->get('summary')))
    ->when(
        fn($ctx) => $ctx->get('word_count') > 50,
        new TranslateStep($ai, 'Spanish'),
    )
    ->run('Write about PHP 8.4');

echo $result->output;
echo "Steps: " . count($result->trace);
```

### Multi-Agent Crews

```php
use MonkeysLegion\Apex\Agent\{Agent, Crew};
use MonkeysLegion\Apex\Enum\AgentProcess;

$crew = new Crew('content-team', [
    new Agent('researcher', 'Research topics thoroughly', $ai),
    new Agent('writer', 'Write clear, engaging content', $ai),
    new Agent('editor', 'Edit for grammar and clarity', $ai),
], AgentProcess::Sequential);

$results = $crew->run('Create an article about PHP 8.4 property hooks');
// Sequential: researcher → writer → editor, each building on previous output
```

### Guardrails

```php
use MonkeysLegion\Apex\Guard\Guard;
use MonkeysLegion\Apex\Guard\Validator\{
    PIIDetectorValidator,
    PromptInjectionValidator,
    ToxicityValidator,
    WordCountValidator,
};

$guard = Guard::create()
    ->input(new PromptInjectionValidator())
    ->input(new ToxicityValidator())
    ->output(new PIIDetectorValidator())
    ->output(new WordCountValidator(maxWords: 500));

$guard->validateInput($userPrompt);             // throws GuardException if blocked
$result = $guard->validateOutput($llmResponse); // returns GuardResult with redacted text
```

### Smart Router

```php
use MonkeysLegion\Apex\Router\{ModelRouter, ComplexityClassifier, ModelRegistry};
use MonkeysLegion\Apex\Enum\RouterStrategy;

$router = ModelRouter::create()
    ->tier('fast',     ['claude-haiku-4', 'gpt-4.1-nano'])
    ->tier('balanced', ['claude-sonnet-4', 'gpt-4.1'])
    ->tier('power',    ['claude-opus-4', 'o3'])
    ->strategy(RouterStrategy::CostOptimized);

$model = $router->select($messages); // Auto-selects based on complexity
```

### Cost Management

```php
use MonkeysLegion\Apex\Cost\{BudgetManager, CostReport, CostTracker, PricingRegistry};

// Track costs
$tracker = new CostTracker(new PricingRegistry());
$ai = new AI($provider, $tracker);

// Per-user budgets
$budget = new BudgetManager();
$budget->setBudget('user:123', 10.0);
$budget->charge('user:123', 'claude-sonnet-4', $response->usage);

// Generate reports
$report = CostReport::generate($tracker->costs());
echo "Total: $" . number_format($report->summary['total'], 4);
```

### Middleware Stack

```php
use MonkeysLegion\Apex\Middleware\{MiddlewarePipeline, Impl\*};

$pipeline = new MiddlewarePipeline();
$pipeline->pipe(new RateLimitMiddleware(maxRequests: 60));
$pipeline->pipe(new RetryMiddleware(maxRetries: 3));
$pipeline->pipe(new CacheMiddleware($cache, ttl: 3600));
$pipeline->pipe(new InputGuardMiddleware($guard));
$pipeline->pipe(new OutputGuardMiddleware($guard));
$pipeline->pipe(new CostBudgetMiddleware($tracker, maxBudget: 100.0));
$pipeline->pipe(new TelemetryMiddleware($logger));
$pipeline->pipe(new FallbackMiddleware($backupProvider));
```

### Memory & Context

```php
use MonkeysLegion\Apex\Memory\{
    ConversationMemory,
    SlidingWindowMemory,
    SummaryMemory,
    VectorMemory,
    PersistentMemory,
    ContextBuilder,
};

// Sliding window — keeps last N messages/tokens
$memory = new SlidingWindowMemory(maxMessages: 50, maxTokens: 4096);

// Summary — auto-summarizes older messages
$memory = new SummaryMemory($ai, summarizeEvery: 10);

// Vector — retrieves relevant past messages via embeddings
$memory = new VectorMemory($embeddingManager, topK: 5);

// Persistent — survives between requests via PSR-16 cache
$memory = new PersistentMemory($cache, key: 'session:abc');

// Build context from multiple sources
$messages = ContextBuilder::create()
    ->system('You are a helpful assistant')
    ->addMessages($memory->messages())
    ->addContext($vectorMemory->recall($query), 'Relevant context')
    ->build();
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
├── AI.php                          # Main facade
├── Contract/                       # 7 interfaces
├── DTO/                            # 10 immutable value objects
├── Enum/                           # 8 backed string enums
├── Exception/                      # 9 exception classes
├── Schema/                         # Structured output engine (3 + 5 attributes)
├── Provider/                       # LLM providers
│   ├── AbstractProvider.php        # cURL, retries, SSE
│   ├── Anthropic/                  # Claude models
│   ├── OpenAI/                     # GPT models
│   └── Ollama/                     # Local models
├── Tool/                           # Tool calling (#[Tool], #[ToolParam])
├── Streaming/                      # TextStream (iterate, SSE, pipe)
├── Guard/                          # Guardrails engine
│   ├── Guard.php                   # Composable validator pipeline
│   └── Validator/                  # 6 validators (PII, Injection, Toxicity, Regex, WordCount, Custom)
├── Router/                         # Smart routing
│   ├── ModelRouter.php             # 4 strategies
│   ├── ComplexityClassifier.php    # Heuristic classification
│   ├── FallbackChain.php           # Ordered failover
│   ├── ModelRegistry.php           # Known model catalog
│   └── RoutingRule.php             # Custom rules
├── Cost/                           # Cost management
│   ├── CostTracker.php, PricingRegistry.php
│   ├── BudgetManager.php           # Per-scope budgets
│   ├── CostAggregator.php          # Group costs
│   └── CostReport.php             # Analytics
├── Middleware/                      # Onion-model pipeline
│   ├── MiddlewarePipeline.php
│   └── Impl/                       # 8 built-in middlewares
├── Pipeline/                       # Declarative workflows
│   ├── Pipeline.php, PipelineContext.php, PipelineResult.php
│   └── Step/                       # 9 step types
├── Agent/                          # Multi-agent system
│   ├── Agent.php, AgentBuilder.php
│   ├── Crew.php, CrewBuilder.php   # 4 orchestration modes
│   └── Handoff.php                 # Agent context transfer
├── Memory/                         # Context management
│   ├── ConversationMemory.php      # Unbounded
│   ├── SlidingWindowMemory.php     # Token/message limited
│   ├── SummaryMemory.php           # Auto-summarizing
│   ├── VectorMemory.php            # Embedding-based retrieval
│   ├── PersistentMemory.php        # PSR-16 backed
│   └── ContextBuilder.php          # Multi-source assembly
├── Embedding/                      # Vector operations
│   ├── EmbeddingManager.php
│   ├── InMemoryStore.php
│   └── Similarity.php
├── Http/                           # Framework integration
│   ├── AIServiceProvider.php
│   ├── AIStreamResponse.php
│   ├── AIMiddleware.php
│   └── AIController.php
├── Testing/FakeProvider.php
└── config/ai.php                   # Default configuration
```

## Requirements

- PHP 8.4+
- ext-curl
- ext-json
- ext-mbstring
- `psr/simple-cache` (optional, for CacheMiddleware/PersistentMemory)
- `psr/log` (optional, for TelemetryMiddleware)

## License

MIT
