# MonkeysLegion-Apex 🐵⚡

> AI Orchestration Engine for the MonkeysLegion Framework

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4+-blue.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-363%20✓-brightgreen.svg)](#testing)

## Overview

MonkeysLegion-Apex is a **provider-agnostic AI abstraction layer** that unifies LLM interactions across Anthropic, OpenAI, Google (AI Studio + Vertex AI), and Ollama behind a single, type-safe PHP 8.4 API.

## Features

- **🤖 Provider-Agnostic** — Swap between Anthropic, OpenAI, Google, Vertex AI, Ollama without code changes
- **📐 Structured Output** — PHP class → JSON Schema → typed LLM output via `extract()`
- **🔧 Tool Calling** — `#[Tool]` attributes auto-register callable functions for multi-step agent loops
- **🌊 Streaming** — Real-time SSE streaming with `TextStream` + HTTP response wrapper
- **🛡️ Guardrails** — PII, prompt injection, toxicity, regex, word count, custom validators + 6 guard actions
- **🧭 Smart Router** — Complexity-based model selection with 4 strategies + custom rules
- **💰 Cost Tracking** — Per-request cost, budget management, cost reports, aggregation
- **🧠 Memory** — 5 memory types + agent-scoped memory: Conversation, SlidingWindow, Summary, Vector, Persistent, AgentMemory
- **🔗 Middleware Pipeline** — 8 built-in middlewares: RateLimit, CostBudget, InputGuard, OutputGuard, Cache, Retry, Fallback, Telemetry
- **🔀 Pipelines** — Declarative workflows with Generate, Extract, Classify, Summarize, Translate, Guard, Conditional, Loop steps
- **🤝 Multi-Agent** — Agent, Crew, Handoff with 4 orchestration modes (Sequential, Parallel, Hierarchical, Conversational)
- **📊 Embeddings** — EmbeddingManager, InMemoryStore, Similarity (cosine, euclidean, dot)
- **🔌 MCP Server** — Model Context Protocol support for tool/resource serving via JSON-RPC
- **📡 Events** — Event dispatcher with `RequestCompleted`, `RequestFailed` events + wildcard listeners
- **🖥️ Console** — Interactive `ai:chat` and `ai:costs` CLI commands (MonkeysLegion CLI compatible)
- **📈 Telemetry** — Integrates with MonkeysLegion Telemetry for distributed tracing, metrics, and structured logging
- **🏗️ Framework** — AIServiceProvider, AIStreamResponse, AIMiddleware, AIController
- **🧪 FakeProvider** — Zero-API-calls testing infrastructure

## Installation

```bash
composer require monkeyscloud/monkeyslegion-apex
```

### Optional Dependencies

```bash
# CLI commands (ai:chat, ai:costs)
composer require monkeyscloud/monkeyslegion-cli

# Distributed tracing, metrics, and logging
composer require monkeyscloud/monkeyslegion-telemetry

# Semantic caching middleware
composer require monkeyscloud/monkeyslegion-cache
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

### Provider Examples

```php
// Anthropic (Claude)
$provider = new AnthropicProvider(apiKey: $_ENV['ANTHROPIC_API_KEY'], model: 'claude-sonnet-4');

// OpenAI (GPT)
$provider = new OpenAIProvider(apiKey: $_ENV['OPENAI_API_KEY'], model: 'gpt-4.1');

// Google AI Studio (Gemini)
$provider = new GoogleProvider(
    apiKey:  $_ENV['GOOGLE_API_KEY'],
    model:   'gemini-2.5-flash',
    baseUrl: 'https://generativelanguage.googleapis.com/v1beta',
);

// Google Vertex AI
$provider = new GoogleProvider(
    apiKey:  $_ENV['VERTEX_API_KEY'],
    model:   'gemini-2.5-pro',
    baseUrl: "https://{$location}-aiplatform.googleapis.com/v1/projects/{$project}/locations/{$location}/publishers/google/models",
);

// Ollama (Local)
$provider = new OllamaProvider(model: 'llama3', baseUrl: 'http://localhost:11434');
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

### Multi-Step Tool Loops

```php
use MonkeysLegion\Apex\Tool\{ToolRegistry, ToolExecutor, MultiStepRunner};

$registry = new ToolRegistry();
$registry->register([new WeatherTools(), new CalendarTools()]);

$runner = new MultiStepRunner($ai, new ToolExecutor($registry), maxSteps: 10);
$response = $runner->run(
    'Check the weather in Paris and add a reminder if it will rain',
    system: 'You are a helpful assistant with access to tools.',
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
use MonkeysLegion\Apex\Pipeline\Step\{GenerateStep, SummarizeStep, GuardStep};

$result = Pipeline::create('content-pipeline')
    ->pipe(new GuardStep($guard, isInput: true))
    ->pipe(new GenerateStep($ai, system: 'You are a writer'))
    ->pipe(new SummarizeStep($ai, maxWords: 100))
    ->transform('word_count', fn($ctx) => str_word_count($ctx->get('summary')))
    ->when(
        fn($ctx) => $ctx->get('word_count') > 50,
        new TranslateStep($ai, 'Spanish'),
    )
    ->loop(
        fn($ctx) => $ctx->get('quality_score') < 0.8,
        new GenerateStep($ai, system: 'Improve this text'),
        maxIterations: 3,
    )
    ->run('Write about PHP 8.4');

echo $result->output;
echo "Steps: " . count($result->trace);
echo "Duration: {$result->durationMs}ms";
```

### Multi-Agent Crews

```php
use MonkeysLegion\Apex\Agent\{Agent, Crew, AgentBuilder, CrewBuilder};
use MonkeysLegion\Apex\Enum\AgentProcess;

// Direct construction
$crew = new Crew('content-team', [
    new Agent('researcher', 'Research topics thoroughly', $ai),
    new Agent('writer', 'Write clear, engaging content', $ai),
    new Agent('editor', 'Edit for grammar and clarity', $ai),
], AgentProcess::Sequential);

$results = $crew->run('Create an article about PHP 8.4 property hooks');
// Sequential: researcher → writer → editor, each building on previous output

// Builder pattern
$crew = (new CrewBuilder($ai))
    ->name('analysis-team')
    ->agent((new AgentBuilder($ai))->name('analyst')->role('Data analyst'))
    ->agent((new AgentBuilder($ai))->name('reporter')->role('Technical writer'))
    ->process(AgentProcess::Hierarchical)
    ->build();
```

### Agent-Scoped Memory

```php
use MonkeysLegion\Apex\Agent\Memory\{AgentMemory, AgentMemoryManager};
use MonkeysLegion\Apex\Memory\ConversationMemory;

// Scoped memory with automatic system prompt injection
$agentMemory = new AgentMemory(
    new ConversationMemory(),
    agentName: 'researcher',
    systemPrompt: 'You are a thorough research analyst.',
);

// Manager for multi-agent memory isolation
$memoryManager = new AgentMemoryManager(
    fn(string $agentName) => new ConversationMemory(),
);

$memoryManager->forAgent('researcher')->add(Message::user('Find data on AI'));
$memoryManager->forAgent('writer')->add(Message::user('Write about AI'));
$memoryManager->clearAll(); // Reset all agent memories
```

### Guardrails

```php
use MonkeysLegion\Apex\Guard\Guard;
use MonkeysLegion\Apex\Guard\GuardPipeline;
use MonkeysLegion\Apex\Guard\Validator\{
    PIIDetectorValidator,
    PromptInjectionValidator,
    ToxicityValidator,
    WordCountValidator,
    RegexValidator,
    CustomValidator,
};
use MonkeysLegion\Apex\Enum\GuardAction;

// Simple guard
$guard = Guard::create()
    ->input(new PromptInjectionValidator())
    ->input(new ToxicityValidator())
    ->output(new PIIDetectorValidator())
    ->output(new WordCountValidator(maxWords: 500));

$guard->validateInput($userPrompt);             // throws GuardException if blocked
$result = $guard->validateOutput($llmResponse); // returns GuardResult with redacted text

// Guard pipeline with configurable actions
$pipeline = GuardPipeline::create()
    ->add(new PromptInjectionValidator(), GuardAction::Block)
    ->add(new PIIDetectorValidator(), GuardAction::Redact)
    ->add(new ToxicityValidator(), GuardAction::Warn)
    ->add(new RegexValidator([
        ['pattern' => '/\bconfidential\b/i', 'label' => 'confidential'],
    ]), GuardAction::Replace);
```

### Smart Router

```php
use MonkeysLegion\Apex\Router\{ModelRouter, ComplexityClassifier, ModelRegistry, FallbackChain};
use MonkeysLegion\Apex\Enum\RouterStrategy;

$router = ModelRouter::create()
    ->tier('fast',     ['claude-haiku-4', 'gpt-4.1-nano', 'gemini-2.5-flash'])
    ->tier('balanced', ['claude-sonnet-4', 'gpt-4.1', 'gemini-2.5-pro'])
    ->tier('power',    ['claude-opus-4', 'o3'])
    ->strategy(RouterStrategy::CostOptimized);

$model = $router->select($messages); // Auto-selects based on complexity

// Fallback chain — tries providers in order
$chain = FallbackChain::create()
    ->add($anthropicProvider, 'claude-sonnet-4')
    ->add($openaiProvider, 'gpt-4.1')
    ->add($googleProvider, 'gemini-2.5-pro');

$result = $chain->execute($messages); // Returns first successful response
```

### Cost Management

```php
use MonkeysLegion\Apex\Cost\{BudgetManager, CostReport, CostTracker, PricingRegistry};

// Track costs
$tracker = new CostTracker(new PricingRegistry());
$tracker->record('claude-sonnet-4', $response->usage);
$tracker->record('gemini-2.5-flash', $response2->usage);

// Per-user budgets
$budget = new BudgetManager();
$budget->setBudget('user:123', 10.0);
$budget->charge('user:123', 'claude-sonnet-4', $response->usage);
echo "Remaining: $" . $budget->remaining('user:123');

// Generate reports
$report = CostReport::generate($tracker->all());
echo "Total: $" . number_format($report->summary['total'], 4);
echo "By model: " . json_encode($report->byModel);
```

### Middleware Stack

```php
use MonkeysLegion\Apex\Middleware\MiddlewarePipeline;
use MonkeysLegion\Apex\Middleware\Impl\{
    RateLimitMiddleware, RetryMiddleware, CacheMiddleware,
    InputGuardMiddleware, OutputGuardMiddleware,
    CostBudgetMiddleware, TelemetryMiddleware, FallbackMiddleware,
};

$pipeline = new MiddlewarePipeline();
$pipeline->push(new RateLimitMiddleware(maxRequests: 60));
$pipeline->push(new RetryMiddleware(maxRetries: 3, baseDelay: 0.5));
$pipeline->push(new CacheMiddleware($cache, ttl: 3600));
$pipeline->push(new InputGuardMiddleware($guard));
$pipeline->push(new OutputGuardMiddleware($guard));
$pipeline->push(new CostBudgetMiddleware($tracker, maxBudget: 100.0));
$pipeline->push(new TelemetryMiddleware($logger));     // Integrates with MonkeysLegion Telemetry
$pipeline->push(new FallbackMiddleware($backupProvider));
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

### MCP Server (Model Context Protocol)

```php
use MonkeysLegion\Apex\MCP\MCPServer;

$server = new MCPServer();

// Register tools
$server->tool('calculate', 'Perform calculations', ['type' => 'object'], function (array $args) {
    return eval("return {$args['expression']};");
});

// Register resources
$server->resource('config', 'file:///config.json', json_encode($config), 'application/json');

// Handle JSON-RPC requests
$response = $server->handle($request);
// Supports: tools/list, tools/call, resources/list, resources/read
```

### Event System

```php
use MonkeysLegion\Apex\Event\{EventDispatcher, RequestCompletedEvent, RequestFailedEvent};

$dispatcher = new EventDispatcher();

// Listen for completed requests
$dispatcher->listen('ai.request.completed', function (RequestCompletedEvent $event) {
    log("Model: {$event->model}, Latency: {$event->latencyMs}ms");
    log("Tokens: {$event->response->usage->totalTokens}");
});

// Listen for errors
$dispatcher->listen('ai.request.failed', function (RequestFailedEvent $event) {
    alert("AI error on {$event->provider}: {$event->error->getMessage()}");
});

// Wildcard listeners
$dispatcher->listen('ai.*', function ($event) {
    metrics_record($event->name(), $event->timestamp);
});
```

### Console Commands

```php
// Interactive chat session
// $ php ml ai:chat
// $ php ml ai:chat --model=claude-sonnet-4
use MonkeysLegion\Apex\Console\ChatCommand;

$cmd = new ChatCommand($ai);
$cmd->execute(STDIN, STDOUT);

// Cost report
// $ php ml ai:costs
// $ php ml ai:costs --format=json
use MonkeysLegion\Apex\Console\CostReportCommand;

$cmd = new CostReportCommand($tracker);
$cmd->execute(STDOUT);
```

### Testing with FakeProvider

```php
use MonkeysLegion\Apex\Testing\FakeProvider;

$fake = FakeProvider::create()
    ->respondWith('Mocked response')
    ->respondWith('Second response')
    ->failWith(new ProviderException('API down', 'test'));

$ai = new AI($fake);
$response = $ai->generate('test');
assert($response->content === 'Mocked response');
assert($fake->calledTimes() === 1);

// Inspect calls
$lastCall = $fake->lastCall();
$allCalls = $fake->getCalls();

// Fake embeddings
$vectors = (new AI($fake))->embed(['hello', 'world']);
assert(count($vectors) === 2);
```

## Architecture

```
src/
├── AI.php                          # Main facade
├── Contract/                       # 12 interfaces
├── DTO/                            # 10 immutable value objects
├── Enum/                           # 8 backed string enums
├── Exception/                      # 9 exception classes
├── Schema/                         # Structured output engine (3 + 5 attributes)
├── Provider/                       # LLM providers
│   ├── AbstractProvider.php        # cURL, retries, SSE
│   ├── Anthropic/                  # Claude models
│   ├── OpenAI/                     # GPT models
│   ├── Google/                     # Gemini (AI Studio + Vertex AI)
│   └── Ollama/                     # Local models
├── Tool/                           # Tool calling
│   ├── ToolRegistry.php            # #[Tool] + #[ToolParam] discovery
│   ├── ToolExecutor.php            # ToolCall → ToolResult execution
│   ├── ToolSchemaCompiler.php      # OpenAI, Anthropic, Google formats
│   └── MultiStepRunner.php         # Autonomous tool loops
├── Streaming/                      # Real-time streaming
│   ├── TextStream.php              # Iterable text stream
│   ├── ObjectStream.php            # Structured streaming
│   ├── SSEStream.php               # Server-Sent Events parser
│   └── StreamBuffer.php            # Buffered chunk window
├── Guard/                          # Guardrails engine
│   ├── Guard.php                   # Input/output validator pipeline
│   ├── GuardPipeline.php           # Configurable action pipeline
│   ├── Validator/                  # 6 validators (PII, Injection, Toxicity, Regex, WordCount, Custom)
│   └── Action/                     # 6 actions (Block, Redact, Warn, Truncate, Replace, Retry)
├── Router/                         # Smart routing
│   ├── ModelRouter.php             # 4 strategies
│   ├── ComplexityClassifier.php    # Heuristic classification
│   ├── FallbackChain.php           # Ordered failover
│   ├── ModelRegistry.php           # Known model catalog (incl. Google/Gemini)
│   └── RoutingRule.php             # Custom rules
├── Cost/                           # Cost management
│   ├── CostTracker.php             # Per-request cost tracking
│   ├── PricingRegistry.php         # Model pricing (incl. Gemini, DeepSeek)
│   ├── BudgetManager.php           # Per-scope budgets
│   ├── CostAggregator.php          # Group by model/period
│   └── CostReport.php             # Analytics
├── Middleware/                      # Onion-model pipeline
│   ├── MiddlewarePipeline.php      # push() + execute()
│   ├── MiddlewareContext.php       # Shared context + metadata bag
│   └── Impl/                       # 8 built-in middlewares
│       ├── RateLimitMiddleware     # Token bucket rate limiting
│       ├── RetryMiddleware         # Exponential backoff + jitter
│       ├── CacheMiddleware         # PSR-16 semantic caching
│       ├── InputGuardMiddleware    # Pre-request guardrails
│       ├── OutputGuardMiddleware   # Post-response guardrails
│       ├── CostBudgetMiddleware    # Budget enforcement
│       ├── TelemetryMiddleware     # Telemetry package integration
│       └── FallbackMiddleware      # Provider failover
├── Pipeline/                       # Declarative workflows
│   ├── Pipeline.php                # Fluent builder
│   ├── PipelineContext.php         # Step data sharing
│   ├── PipelineResult.php          # Output + trace + timing
│   ├── PipelineRunner.php          # Named pipeline registry
│   └── Step/                       # 12 step types
├── Agent/                          # Multi-agent system
│   ├── Agent.php                   # Single agent with memory + tools
│   ├── AgentBuilder.php            # Fluent agent construction
│   ├── AgentRunner.php             # Lifecycle hooks (onStep, onHandoff)
│   ├── Crew.php                    # 4 orchestration modes
│   ├── CrewBuilder.php             # Fluent crew construction
│   ├── Handoff.php                 # Agent context transfer
│   └── Memory/                     # Agent-scoped memory
│       ├── AgentMemory.php         # Isolated memory with system prompt
│       └── AgentMemoryManager.php  # Per-agent memory factory
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
│   └── Similarity.php              # Cosine, Euclidean, Dot Product
├── MCP/                            # Model Context Protocol
│   ├── MCPServer.php               # JSON-RPC tool/resource server
│   └── MCPClient.php               # MCP client connector
├── Event/                          # Event system
│   ├── EventDispatcher.php         # listen() + dispatch() + wildcards
│   ├── AIEvent.php                 # Base event interface
│   ├── RequestCompletedEvent.php   # Success event
│   └── RequestFailedEvent.php      # Failure event
├── Console/                        # CLI commands
│   ├── ChatCommand.php             # Interactive ai:chat
│   ├── CostReportCommand.php       # Cost reporting ai:costs
│   └── Cli/                        # MonkeysLegion CLI adapters
│       ├── ChatCliCommand.php      # #[Command('ai:chat')]
│       └── CostReportCliCommand.php# #[Command('ai:costs')]
├── Http/                           # Framework integration
│   ├── AIServiceProvider.php
│   ├── AIStreamResponse.php
│   ├── AIMiddleware.php
│   └── AIController.php
├── Testing/FakeProvider.php        # Zero-API testing with respondWith/failWith
└── config/ai.php                   # Default configuration
```

## Configuration

```php
// config/ai.php
return [
    'default'   => env('AI_PROVIDER', 'anthropic'),
    'providers' => [
        'anthropic' => [
            'api_key'  => env('ANTHROPIC_API_KEY'),
            'model'    => env('ANTHROPIC_MODEL', 'claude-sonnet-4'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        ],
        'openai' => [
            'api_key'  => env('OPENAI_API_KEY'),
            'model'    => env('OPENAI_MODEL', 'gpt-4.1'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
        'google' => [
            'api_key'  => env('GOOGLE_API_KEY'),
            'model'    => env('GOOGLE_MODEL', 'gemini-2.5-flash'),
            'base_url' => env('GOOGLE_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
        'vertex' => [
            'api_key'  => env('VERTEX_API_KEY'),
            'model'    => env('VERTEX_MODEL', 'gemini-2.5-pro'),
            'project'  => env('VERTEX_PROJECT'),
            'location' => env('VERTEX_LOCATION', 'us-central1'),
        ],
        'ollama' => [
            'model'    => env('OLLAMA_MODEL', 'llama3'),
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        ],
    ],
];
```

## Testing

363 tests, 705 assertions — validated across DTOs, Enums, Exceptions, Schema, Providers, Guards, Router, Cost, Memory, Embeddings, Pipelines, Agents, Streaming, Tools, Console, MCP, Events, and AI facade.

```bash
php vendor/bin/phpunit           # Run all tests
php vendor/bin/phpunit --testdox # Verbose output
```

## Requirements

- PHP 8.4+
- ext-curl
- ext-json
- ext-mbstring
- `psr/simple-cache` ^3.0
- `psr/log` ^3.0

### Optional

- `monkeyscloud/monkeyslegion-cli` — Required for `ai:chat` and `ai:costs` console commands
- `monkeyscloud/monkeyslegion-telemetry` — Distributed tracing, metrics, and structured logging
- `monkeyscloud/monkeyslegion-cache` — Semantic caching middleware
- `ext-pcntl` — Parallel tool execution

## License

MIT
