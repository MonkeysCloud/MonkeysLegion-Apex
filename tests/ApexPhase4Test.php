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

namespace MonkeysLegion\Apex\Tests;

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\Agent\Agent;
use MonkeysLegion\Apex\Agent\AgentBuilder;
use MonkeysLegion\Apex\Agent\Crew;
use MonkeysLegion\Apex\Agent\CrewBuilder;
use MonkeysLegion\Apex\Agent\Handoff;
use MonkeysLegion\Apex\Cost\BudgetManager;
use MonkeysLegion\Apex\Cost\CostAggregator;
use MonkeysLegion\Apex\Cost\CostReport;
use MonkeysLegion\Apex\Cost\CostTracker;
use MonkeysLegion\Apex\Cost\PricingRegistry;
use MonkeysLegion\Apex\DTO\Cost;
use MonkeysLegion\Apex\DTO\EmbeddingVector;
use MonkeysLegion\Apex\DTO\GuardResult;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\DTO\Usage;
use MonkeysLegion\Apex\Embedding\EmbeddingManager;
use MonkeysLegion\Apex\Embedding\InMemoryStore;
use MonkeysLegion\Apex\Embedding\Similarity;
use MonkeysLegion\Apex\Enum\AgentProcess;
use MonkeysLegion\Apex\Enum\GuardAction;
use MonkeysLegion\Apex\Enum\ModelTier;
use MonkeysLegion\Apex\Enum\Role;
use MonkeysLegion\Apex\Enum\RouterStrategy;
use MonkeysLegion\Apex\Exception\BudgetExceededException;
use MonkeysLegion\Apex\Guard\Validator\CustomValidator;
use MonkeysLegion\Apex\Guard\Validator\RegexValidator;
use MonkeysLegion\Apex\Guard\Validator\ToxicityValidator;
use MonkeysLegion\Apex\Guard\Validator\WordCountValidator;
use MonkeysLegion\Apex\Memory\ContextBuilder;
use MonkeysLegion\Apex\Memory\ConversationMemory;
use MonkeysLegion\Apex\Memory\SlidingWindowMemory;
use MonkeysLegion\Apex\Pipeline\Pipeline;
use MonkeysLegion\Apex\Pipeline\PipelineContext;
use MonkeysLegion\Apex\Pipeline\PipelineResult;
use MonkeysLegion\Apex\Pipeline\Step\ConditionalStep;
use MonkeysLegion\Apex\Pipeline\Step\LoopStep;
use MonkeysLegion\Apex\Pipeline\Step\TransformStep;
use MonkeysLegion\Apex\Router\ComplexityClassifier;
use MonkeysLegion\Apex\Router\FallbackChain;
use MonkeysLegion\Apex\Router\ModelRegistry;
use MonkeysLegion\Apex\Testing\FakeProvider;
use PHPUnit\Framework\TestCase;

/**
 * Phase 3+4 extended tests — middleware impls, guards, router, cost, pipeline, agents, memory, embeddings.
 */
final class ApexPhase4Test extends TestCase
{
    // ── Additional Guard Validators ───────────────────────

    public function test_toxicity_validator_clean(): void
    {
        $v = new ToxicityValidator();
        $this->assertTrue($v->validate('Hello world')->passed);
    }

    public function test_toxicity_validator_detects(): void
    {
        $v = new ToxicityValidator(threshold: 0.1);
        $result = $v->validate('I will harass you');
        $this->assertFalse($result->passed);
        $this->assertArrayHasKey('toxic_patterns', $result->violations);
    }

    public function test_toxicity_custom_patterns(): void
    {
        $v = new ToxicityValidator(customPatterns: ['/\bbadword\b/i'], threshold: 0.1);
        $result = $v->validate('This contains badword');
        $this->assertFalse($result->passed);
    }

    public function test_regex_validator_match(): void
    {
        $v = new RegexValidator([
            ['pattern' => '/\bpassword\b/i', 'label' => 'password'],
        ]);
        $result = $v->validate('My password is 12345');
        $this->assertFalse($result->passed);
        $this->assertArrayHasKey('password', $result->violations);
        $this->assertSame('My [FILTERED] is 12345', $result->redactedText);
    }

    public function test_regex_validator_no_match(): void
    {
        $v = new RegexValidator([
            ['pattern' => '/\bsecret\b/i', 'label' => 'secret'],
        ]);
        $this->assertTrue($v->validate('Hello world')->passed);
    }

    public function test_word_count_validator_pass(): void
    {
        $v = new WordCountValidator(minWords: 2, maxWords: 10);
        $this->assertTrue($v->validate('Hello world test')->passed);
    }

    public function test_word_count_validator_too_few(): void
    {
        $v = new WordCountValidator(minWords: 5);
        $result = $v->validate('Hello');
        $this->assertFalse($result->passed);
        $this->assertArrayHasKey('min_words', $result->violations);
    }

    public function test_word_count_validator_too_many(): void
    {
        $v = new WordCountValidator(maxWords: 3);
        $result = $v->validate('one two three four five');
        $this->assertFalse($result->passed);
        $this->assertArrayHasKey('max_words', $result->violations);
    }

    public function test_word_count_truncation(): void
    {
        $v = new WordCountValidator(maxWords: 2);
        $result = $v->validate('one two three four');
        $this->assertSame('one two', $result->redactedText);
    }

    public function test_custom_validator(): void
    {
        $v = new CustomValidator(
            fn(string $text) => new GuardResult(
                passed: !str_contains($text, 'forbidden'),
                text: $text,
                validator: 'custom_test',
            ),
            validatorName: 'custom_test',
        );

        $this->assertTrue($v->validate('ok text')->passed);
        $this->assertFalse($v->validate('this is forbidden')->passed);
        $this->assertSame('custom_test', $v->name());
    }

    // ── ComplexityClassifier ──────────────────────────────

    public function test_complexity_low(): void
    {
        $c = ComplexityClassifier::create();
        $result = $c->classify([Message::user('hi')]);
        $this->assertSame('low', $result['tier']);
        $this->assertSame(0, $result['score']);
    }

    public function test_complexity_medium(): void
    {
        $c = ComplexityClassifier::create();
        $result = $c->classify([
            Message::system('Be helpful'),
            Message::user('Tell me about PHP'),
            Message::assistant('PHP is...'),
            Message::user('More'),
            Message::assistant('Also...'),
        ]);
        // has_system + multi_turn = 2 signals → medium
        $this->assertSame('medium', $result['tier']);
        $this->assertContains('has_system', $result['signals']);
        $this->assertContains('multi_turn', $result['signals']);
    }

    public function test_complexity_high(): void
    {
        $c = ComplexityClassifier::create();
        $result = $c->classify(
            [
                Message::system('System'),
                Message::user(str_repeat('x', 3000)),
                Message::user('2'),
                Message::user('3'),
                Message::user('4'),
            ],
            ['tools' => []]
        );
        // long_input + has_system + multi_turn + has_tools = 4 → high
        $this->assertSame('high', $result['tier']);
    }

    public function test_complexity_custom_signal(): void
    {
        $c = ComplexityClassifier::create();
        $c->signal('is_code', fn(array $msgs) => str_contains($msgs[0]->content, '<?php'));
        $result = $c->classify([Message::user('<?php echo "hi";')]);
        $this->assertContains('is_code', $result['signals']);
    }

    // ── FallbackChain ─────────────────────────────────────

    public function test_fallback_chain_first_succeeds(): void
    {
        $fake1 = FakeProvider::create()->respondWith('from-fake1');
        $fake2 = FakeProvider::create()->respondWith('from-fake2');

        $chain = FallbackChain::create()
            ->add($fake1, 'model-a')
            ->add($fake2, 'model-b');

        $result = $chain->execute([Message::user('hi')]);
        $this->assertSame('from-fake1', $result['response']->content);
        $this->assertSame('fake', $result['provider']);
    }

    public function test_fallback_chain_first_fails(): void
    {
        $fake1 = FakeProvider::create()
            ->failWith(new \MonkeysLegion\Apex\Exception\ProviderException('fail', 'test'));
        $fake2 = FakeProvider::create()->respondWith('fallback-response');

        $chain = FallbackChain::create()
            ->add($fake1, 'model-a')
            ->add($fake2, 'model-b');

        $result = $chain->execute([Message::user('hi')]);
        $this->assertSame('fallback-response', $result['response']->content);
    }

    public function test_fallback_chain_count(): void
    {
        $chain = FallbackChain::create()
            ->add(FakeProvider::create(), 'a')
            ->add(FakeProvider::create(), 'b');
        $this->assertSame(2, $chain->count());
    }

    // ── ModelRegistry ─────────────────────────────────────

    public function test_model_registry_defaults(): void
    {
        $registry = new ModelRegistry();
        $info = $registry->get('claude-sonnet-4');
        $this->assertNotNull($info);
        $this->assertSame('anthropic', $info->provider);
        $this->assertSame(ModelTier::Balanced, $info->tier);
    }

    public function test_model_registry_by_tier(): void
    {
        $registry = new ModelRegistry();
        $fast = $registry->byTier(ModelTier::Fast);
        $this->assertNotEmpty($fast);
        foreach ($fast as $m) {
            $this->assertSame(ModelTier::Fast, $m->tier);
        }
    }

    public function test_model_registry_by_provider(): void
    {
        $registry = new ModelRegistry();
        $openai = $registry->byProvider('openai');
        $this->assertNotEmpty($openai);
        foreach ($openai as $m) {
            $this->assertSame('openai', $m->provider);
        }
    }

    public function test_model_registry_cheapest(): void
    {
        $registry = new ModelRegistry();
        $cheapest = $registry->cheapest();
        $this->assertNotNull($cheapest);
        // Ollama is free
        $this->assertSame(0.0, $cheapest->inputPricePerMillion);
    }

    // ── CostAggregator ───────────────────────────────────

    public function test_cost_aggregator_by_model(): void
    {
        $agg = new CostAggregator();
        $costs = [
            new Cost(0.003, 0.015, 'sonnet'),
            new Cost(0.003, 0.015, 'sonnet'),
            new Cost(0.01, 0.05, 'opus'),
        ];

        $result = $agg->byModel($costs);
        $this->assertSame(2, $result['sonnet']['count']);
        $this->assertEqualsWithDelta(0.036, $result['sonnet']['total'], 0.001);
        $this->assertSame(1, $result['opus']['count']);
    }

    public function test_cost_aggregator_summary(): void
    {
        $agg = new CostAggregator();
        $costs = [
            new Cost(1.0, 2.0, 'model-a'),
            new Cost(3.0, 4.0, 'model-b'),
        ];

        $result = $agg->summary($costs);
        $this->assertSame(4.0, $result['input']);
        $this->assertSame(6.0, $result['output']);
        $this->assertSame(10.0, $result['total']);
        $this->assertSame(2, $result['count']);
    }

    // ── BudgetManager ─────────────────────────────────────

    public function test_budget_manager_within_budget(): void
    {
        $mgr = new BudgetManager();
        $mgr->setBudget('user:1', 10.0);
        $cost = $mgr->charge('user:1', 'claude-sonnet-4', new Usage(100_000, 50_000));
        $this->assertGreaterThan(0.0, $cost);
        $this->assertGreaterThan(0.0, $mgr->remaining('user:1'));
    }

    public function test_budget_manager_exceeds(): void
    {
        $mgr = new BudgetManager();
        $mgr->setBudget('user:1', 0.001);
        $this->expectException(BudgetExceededException::class);
        $mgr->charge('user:1', 'claude-sonnet-4', new Usage(10_000_000, 5_000_000));
    }

    public function test_budget_manager_reset(): void
    {
        $mgr = new BudgetManager();
        $mgr->setBudget('user:1', 100.0);
        $mgr->charge('user:1', 'claude-haiku-4', new Usage(1000, 500));
        $this->assertGreaterThan(0.0, $mgr->spent('user:1'));
        $mgr->reset('user:1');
        $this->assertSame(0.0, $mgr->spent('user:1'));
    }

    public function test_budget_manager_no_budget(): void
    {
        $mgr = new BudgetManager();
        $this->assertNull($mgr->remaining('no-scope'));
        // No budget = no limit
        $cost = $mgr->charge('no-scope', 'model', new Usage(100, 100));
        $this->assertGreaterThanOrEqual(0.0, $cost);
    }

    // ── CostReport ────────────────────────────────────────

    public function test_cost_report_generate(): void
    {
        $costs = [
            new Cost(1.0, 2.0, 'sonnet'),
            new Cost(0.5, 1.0, 'haiku'),
        ];

        $report = CostReport::generate($costs);
        $this->assertSame(2, $report->summary['count']);
        $this->assertEqualsWithDelta(4.5, $report->summary['total'], 0.01);
        $this->assertArrayHasKey('sonnet', $report->byModel);
    }

    public function test_cost_report_to_array(): void
    {
        $report = CostReport::generate([
            new Cost(1.0, 2.0, 'test-model'),
        ]);
        $array = $report->toArray();
        $this->assertArrayHasKey('summary', $array);
        $this->assertArrayHasKey('by_model', $array);
        $this->assertArrayHasKey('period', $array);
    }

    // ── Pipeline ──────────────────────────────────────────

    public function test_pipeline_basic(): void
    {
        $result = Pipeline::create('test')
            ->pipe(fn(PipelineContext $ctx) => strtoupper($ctx->input))
            ->pipe(fn(PipelineContext $ctx) => $ctx->get('last_output') . '!')
            ->run('hello');

        $this->assertTrue($result->success);
        $this->assertSame('HELLO!', $result->output);
        $this->assertCount(2, $result->trace);
    }

    public function test_pipeline_transform(): void
    {
        $result = Pipeline::create()
            ->transform('word_count', fn(PipelineContext $ctx) => str_word_count($ctx->input))
            ->pipe(fn(PipelineContext $ctx) => "Words: {$ctx->get('word_count')}")
            ->run('hello world test');

        $this->assertSame('Words: 3', $result->output);
    }

    public function test_pipeline_conditional(): void
    {
        $result = Pipeline::create()
            ->pipe(fn(PipelineContext $ctx) => strlen($ctx->input))
            ->when(
                fn(PipelineContext $ctx) => $ctx->get('last_output') > 5,
                fn(PipelineContext $ctx) => 'long string',
            )
            ->run('hello world');

        $this->assertSame('long string', $result->output);
    }

    public function test_pipeline_conditional_skipped(): void
    {
        $result = Pipeline::create()
            ->pipe(fn(PipelineContext $ctx) => strlen($ctx->input))
            ->when(
                fn(PipelineContext $ctx) => $ctx->get('last_output') > 100,
                fn() => 'should not run',
            )
            ->run('hi');

        // Conditional should return last_output when skipped
        $this->assertSame(2, $result->output);
    }

    public function test_pipeline_loop(): void
    {
        $result = Pipeline::create()
            ->transform('counter', fn() => 0)
            ->loop(
                fn(PipelineContext $ctx) => $ctx->get('counter') < 3,
                fn(PipelineContext $ctx) => $ctx->set('counter', $ctx->get('counter') + 1),
                maxIterations: 10,
            )
            ->run('test');

        $this->assertTrue($result->success);
        $this->assertSame(3, $result->data['counter']);
    }

    public function test_pipeline_error_handling(): void
    {
        $result = Pipeline::create()
            ->pipe(fn() => throw new \RuntimeException('oops'))
            ->run('test');

        $this->assertFalse($result->success);
        $this->assertSame('oops', $result->error);
    }

    public function test_pipeline_result_to_array(): void
    {
        $result = Pipeline::create()
            ->pipe(fn() => 'done')
            ->run('test');

        $array = $result->toArray();
        $this->assertArrayHasKey('output', $array);
        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('duration_ms', $array);
    }

    // ── Agent ─────────────────────────────────────────────

    public function test_agent_run(): void
    {
        $fake = FakeProvider::create()->respondWith('Agent response');
        $ai = new AI($fake);

        $agent = new Agent('researcher', 'You are a researcher.', $ai);
        $response = $agent->run('Find information about PHP');

        $this->assertSame('Agent response', $response->content);
        $this->assertCount(2, $agent->memory()->messages()); // user + assistant
    }

    public function test_agent_builder(): void
    {
        $fake = FakeProvider::create()->respondWith('Built agent response');
        $ai = new AI($fake);

        $agent = (new AgentBuilder($ai))
            ->name('writer')
            ->role('You are a writer.')
            ->model('claude-sonnet-4')
            ->build();

        $response = $agent->run('Write a poem');
        $this->assertSame('Built agent response', $response->content);
        $this->assertSame('writer', $agent->name);
    }

    public function test_agent_handoff(): void
    {
        $fake = FakeProvider::create()
            ->respondWith('Research done')
            ->respondWith('Editing done');
        $ai = new AI($fake);

        $researcher = new Agent('researcher', 'Research things', $ai);
        $editor = new Agent('editor', 'Edit things', $ai);

        $researcher->run('Research PHP 8.4');
        $handoff = $researcher->handoff($editor, 'Passing research');

        $this->assertSame('researcher', $handoff->from);
        $this->assertSame('editor', $handoff->to);
        $this->assertSame('Passing research', $handoff->summary);
        $this->assertNotEmpty($handoff->context);
    }

    // ── Crew ──────────────────────────────────────────────

    public function test_crew_sequential(): void
    {
        $fake = FakeProvider::create()
            ->respondWith('Research done')
            ->respondWith('Editing done');
        $ai = new AI($fake);

        $crew = new Crew('team', [
            new Agent('researcher', 'Research', $ai),
            new Agent('editor', 'Edit', $ai),
        ], AgentProcess::Sequential);

        $results = $crew->run('Write about PHP');
        $this->assertCount(2, $results);
        $this->assertSame('researcher', $results[0]['agent']);
        $this->assertSame('editor', $results[1]['agent']);
    }

    public function test_crew_parallel(): void
    {
        $fake = FakeProvider::create()
            ->respondWith('Agent A result')
            ->respondWith('Agent B result');
        $ai = new AI($fake);

        $crew = new Crew('team', [
            new Agent('a', 'Role A', $ai),
            new Agent('b', 'Role B', $ai),
        ], AgentProcess::Parallel);

        $results = $crew->run('Task');
        $this->assertCount(2, $results);
    }

    public function test_crew_builder(): void
    {
        $fake = FakeProvider::create()
            ->respondWith('r1')
            ->respondWith('r2');
        $ai = new AI($fake);

        $crew = (new CrewBuilder($ai))
            ->name('my-crew')
            ->process(AgentProcess::Sequential)
            ->agent(new Agent('a', 'Role', $ai))
            ->agent(new Agent('b', 'Role', $ai))
            ->build();

        $results = $crew->run('go');
        $this->assertCount(2, $results);
        $this->assertSame('my-crew', $crew->name);
    }

    // ── Memory Types ──────────────────────────────────────

    public function test_conversation_memory(): void
    {
        $memory = new ConversationMemory();
        $memory->add(Message::user('hi'));
        $memory->add(Message::assistant('hello'));
        $this->assertCount(2, $memory->messages());
        $memory->clear();
        $this->assertCount(0, $memory->messages());
    }

    public function test_context_builder(): void
    {
        $ctx = ContextBuilder::create()
            ->system('You are helpful')
            ->addMessages([Message::user('Q1'), Message::assistant('A1')])
            ->addContext([Message::user('Relevant fact')], 'Context')
            ->build();

        $this->assertSame(Role::System, $ctx[0]->role);
        $this->assertSame('You are helpful', $ctx[0]->content);
        $this->assertGreaterThanOrEqual(4, count($ctx));
    }

    public function test_context_builder_empty_context(): void
    {
        $ctx = ContextBuilder::create()
            ->system('System')
            ->addContext([], 'Empty')
            ->build();

        // Should not add empty context message
        $this->assertCount(1, $ctx);
    }

    // ── Embedding System ──────────────────────────────────

    public function test_similarity_cosine(): void
    {
        $this->assertEqualsWithDelta(1.0, Similarity::cosine([1, 0], [1, 0]), 0.001);
        $this->assertEqualsWithDelta(0.0, Similarity::cosine([1, 0], [0, 1]), 0.001);
    }

    public function test_similarity_euclidean(): void
    {
        $this->assertEqualsWithDelta(0.0, Similarity::euclidean([1, 0], [1, 0]), 0.001);
        $this->assertEqualsWithDelta(1.414, Similarity::euclidean([1, 0], [0, 1]), 0.01);
    }

    public function test_similarity_dot_product(): void
    {
        $this->assertSame(0.0, Similarity::dotProduct([1, 0], [0, 1]));
        $this->assertSame(5.0, Similarity::dotProduct([1, 2], [1, 2]));
    }

    public function test_in_memory_store(): void
    {
        $store = new InMemoryStore();
        $store->add(new EmbeddingVector('cat', [1, 0, 0], 3, 'test'), ['label' => 'cat']);
        $store->add(new EmbeddingVector('dog', [0.9, 0.1, 0], 3, 'test'), ['label' => 'dog']);
        $store->add(new EmbeddingVector('car', [0, 1, 0], 3, 'test'), ['label' => 'car']);

        $this->assertSame(3, $store->count());

        $query = new EmbeddingVector('query', [1, 0, 0], 3, 'test');
        $results = $store->search($query, 2);

        $this->assertCount(2, $results);
        $this->assertSame('cat', $results[0]['metadata']['label']);
    }

    public function test_in_memory_store_clear(): void
    {
        $store = new InMemoryStore();
        $store->add(new EmbeddingVector('a', [1], 1, 'test'));
        $store->clear();
        $this->assertSame(0, $store->count());
    }

    public function test_embedding_manager(): void
    {
        $fake = FakeProvider::create();
        $mgr = new EmbeddingManager($fake);
        $vectors = $mgr->embed(['hello', 'world']);
        $this->assertCount(2, $vectors);
    }

    public function test_embedding_manager_single(): void
    {
        $fake = FakeProvider::create();
        $mgr = new EmbeddingManager($fake);
        $vector = $mgr->embedOne('hello');
        $this->assertSame('hello', $vector->input);
    }

    // ── Config ────────────────────────────────────────────

    public function test_config_file_parseable(): void
    {
        // Just verify the config file is valid PHP
        $configPath = __DIR__ . '/../config/ai.php';
        $this->assertFileExists($configPath);
    }
}
