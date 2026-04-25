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

namespace MonkeysLegion\Apex;

use MonkeysLegion\Apex\Agent\AgentBuilder;
use MonkeysLegion\Apex\Agent\CrewBuilder;
use MonkeysLegion\Apex\Contract\MiddlewareInterface;
use MonkeysLegion\Apex\Contract\ProviderInterface;
use MonkeysLegion\Apex\Cost\CostReport;
use MonkeysLegion\Apex\Cost\CostTracker;
use MonkeysLegion\Apex\DTO\EmbeddingVector;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\Enum\Role;
use MonkeysLegion\Apex\Exception\SchemaValidationException;
use MonkeysLegion\Apex\Guard\Guard;
use MonkeysLegion\Apex\Pipeline\Pipeline;
use MonkeysLegion\Apex\Schema\Schema;
use MonkeysLegion\Apex\Schema\SchemaCompiler;
use MonkeysLegion\Apex\Schema\SchemaValidator;
use MonkeysLegion\Apex\Streaming\TextStream;
use MonkeysLegion\Apex\Tool\ToolExecutor;
use MonkeysLegion\Apex\Tool\ToolRegistry;

/**
 * Main AI orchestration facade.
 *
 * Provides a unified, provider-agnostic interface for:
 *  - Text generation (generate, stream)
 *  - Structured output (extract)
 *  - Embeddings (embed)
 *  - Tool calling (built into generate/stream)
 */
final class AI
{
    private ToolRegistry $toolRegistry;
    private ToolExecutor $toolExecutor;

    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly ?CostTracker $costTracker = null,
    ) {
        $this->toolRegistry = new ToolRegistry();
        $this->toolExecutor = new ToolExecutor($this->toolRegistry);
    }

    // ─── Text Generation ─────────────────────────────────

    /**
     * Generate a text response.
     *
     * @param string|list<Message> $input
     * @param array<string, mixed> $options
     */
    public function generate(
        string|array $input,
        ?string      $system = null,
        ?string      $model = null,
        array        $options = [],
    ): Response {
        $messages = $this->normalizeInput($input, $system);

        // Register tool objects if provided
        if (isset($options['tools'])) {
            $this->toolRegistry->register($options['tools']);
            $options['tools'] = $this->toolRegistry->compile();
        }

        if ($model !== null) {
            $options['model'] = $model;
        }

        $response = $this->provider->chat($messages, $options);

        // Multi-step tool calling
        $maxSteps = min($options['maxSteps'] ?? 10, 50);
        $step = 0;
        $allMessages = $messages;

        while ($response->hasToolCalls() && $step < $maxSteps) {
            // Add assistant response
            $allMessages[] = Message::assistant($response->content, $response->toolCalls);

            // Execute tools and add results
            $results = $this->toolExecutor->executeAll($response->toolCalls);
            foreach ($results as $result) {
                $encoded = is_string($result->output)
                    ? $result->output
                    : json_encode($result->output, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
                // Enforce maximum tool output size to prevent context injection
                if (mb_strlen($encoded) > 100_000) {
                    $encoded = mb_substr($encoded, 0, 100_000) . '... [truncated]';
                }
                $allMessages[] = Message::tool(
                    $encoded,
                    $result->toolCallId,
                );
            }

            // Call LLM again with tool results
            $response = $this->provider->chat($allMessages, $options);
            $step++;
        }

        // Track cost
        $this->costTracker?->record(
            $response->model ?: ($model ?? 'unknown'),
            $response->usage,
        );

        return $response;
    }

    // ─── Streaming ───────────────────────────────────────

    /**
     * Stream a text response as Server-Sent Events.
     *
     * @param string|list<Message> $input
     * @param array<string, mixed> $options
     */
    public function stream(
        string|array $input,
        ?string      $system = null,
        ?string      $model = null,
        array        $options = [],
    ): TextStream {
        $messages = $this->normalizeInput($input, $system);

        if ($model !== null) {
            $options['model'] = $model;
        }

        $generator = $this->provider->streamChat($messages, $options);
        return new TextStream($generator);
    }

    // ─── Structured Output ───────────────────────────────

    /**
     * Extract structured data from text using a Schema class.
     *
     * @template T of Schema
     * @param class-string<T>      $schema
     * @param string|list<Message> $input
     * @param array<string, mixed> $options
     * @return T
     */
    public function extract(
        string       $schema,
        string|array $input,
        ?string      $model = null,
        int          $retries = 3,
        array        $options = [],
    ): Schema {
        $jsonSchema = SchemaCompiler::compile($schema);
        $schemaJson = json_encode($jsonSchema, JSON_PRETTY_PRINT);

        $systemPrompt = "You must respond with a valid JSON object matching this schema:\n{$schemaJson}\n"
            . "Respond ONLY with the JSON object. No markdown, no explanation.";

        $messages = $this->normalizeInput($input, $systemPrompt);

        if ($model !== null) {
            $options['model'] = $model;
        }

        // Add response_format for OpenAI provider
        $options['response_format'] = ['type' => 'json_object'];

        $lastError = null;

        for ($attempt = 0; $attempt < $retries; $attempt++) {
            $response = $this->provider->chat($messages, $options);

            $this->costTracker?->record(
                $response->model ?: ($model ?? 'unknown'),
                $response->usage,
            );

            try {
                $json = json_decode($response->content, true, 512, JSON_THROW_ON_ERROR);
                return SchemaValidator::validate($schema, $json);
            } catch (\JsonException $e) {
                $lastError = "JSON parse error: {$e->getMessage()}";
            } catch (SchemaValidationException $e) {
                $lastError = $e->getMessage();
            }

            // Retry with validation feedback
            $messages[] = Message::assistant($response->content);
            $messages[] = Message::user(
                "The response failed validation: {$lastError}\n"
                . "Please fix the errors and try again. Respond ONLY with valid JSON.",
            );
        }

        throw new SchemaValidationException(
            "Failed to extract valid schema after {$retries} retries: {$lastError}",
        );
    }

    // ─── Embeddings ──────────────────────────────────────

    /**
     * Generate embeddings for text(s).
     *
     * @param string|list<string> $input
     * @return list<EmbeddingVector>
     */
    public function embed(string|array $input): array
    {
        $inputs = is_string($input) ? [$input] : $input;
        return $this->provider->embed($inputs);
    }

    // ─── Utilities ───────────────────────────────────────

    /**
     * Get the underlying provider.
     */
    public function provider(): ProviderInterface
    {
        return $this->provider;
    }

    /**
     * Get the cost tracker.
     */
    public function costs(): ?CostTracker
    {
        return $this->costTracker;
    }

    /**
     * Normalize string or message array input into messages.
     *
     * @param string|list<Message> $input
     * @return list<Message>
     */
    private function normalizeInput(string|array $input, ?string $system): array
    {
        $messages = [];
        if ($system !== null) {
            $messages[] = Message::system($system);
        }
        if (is_string($input)) {
            $messages[] = Message::user($input);
        } else {
            $messages = array_merge($messages, $input);
        }
        return $messages;
    }

    // ─── Facade Methods (v1.2.0) ────────────────────────

    /**
     * Create a new pipeline builder.
     */
    public function pipeline(?string $name = null): Pipeline
    {
        return Pipeline::create($name);
    }

    /**
     * Create a new agent builder.
     */
    public function agent(string $name = 'agent'): AgentBuilder
    {
        return (new AgentBuilder($this))->name($name);
    }

    /**
     * Create a new crew builder.
     */
    public function crew(string $name = 'crew'): CrewBuilder
    {
        return (new CrewBuilder($this))->name($name);
    }

    /**
     * Create a new guard builder.
     */
    public function guard(): Guard
    {
        return Guard::create();
    }

    /**
     * Get a cost report for the current session.
     */
    public function stats(): ?CostReport
    {
        return $this->costTracker?->report();
    }
}
