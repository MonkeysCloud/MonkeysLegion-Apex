<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Tests;

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\Agent\Agent;
use MonkeysLegion\Apex\Agent\AgentBuilder;
use MonkeysLegion\Apex\Agent\Crew;
use MonkeysLegion\Apex\Agent\CrewBuilder;
use MonkeysLegion\Apex\Agent\Orchestration\ConversationalOrchestrator;
use MonkeysLegion\Apex\Agent\Orchestration\HierarchicalOrchestrator;
use MonkeysLegion\Apex\Agent\Orchestration\OrchestratorInterface;
use MonkeysLegion\Apex\Agent\Orchestration\ParallelOrchestrator;
use MonkeysLegion\Apex\Agent\Orchestration\SequentialOrchestrator;
use MonkeysLegion\Apex\Enum\AgentProcess;
use MonkeysLegion\Apex\Testing\FakeProvider;
use PHPUnit\Framework\TestCase;

final class Apex120OrchestratorTest extends TestCase
{
    private function ai(int $count = 10): AI
    {
        $fake = FakeProvider::create();
        for ($i = 0; $i < $count; $i++) {
            $fake->respondWith("response-{$i}");
        }
        return new AI($fake);
    }

    // ── Sequential ───────────────────────────────────────

    public function test_sequential_orchestrator_chains_output(): void
    {
        $ai = $this->ai(2);
        $a = new Agent('a', 'role-a', $ai);
        $b = new Agent('b', 'role-b', $ai);

        $orch = new SequentialOrchestrator();
        $results = $orch->run([$a, $b], 'task');

        $this->assertCount(2, $results);
        $this->assertSame('a', $results[0]['agent']);
        $this->assertSame('b', $results[1]['agent']);
    }

    public function test_sequential_empty_agents(): void
    {
        $orch = new SequentialOrchestrator();
        $this->assertSame([], $orch->run([], 'task'));
    }

    // ── Parallel ─────────────────────────────────────────

    public function test_parallel_orchestrator_all_get_same_input(): void
    {
        $ai = $this->ai(2);
        $a = new Agent('a', 'role-a', $ai);
        $b = new Agent('b', 'role-b', $ai);

        $orch = new ParallelOrchestrator();
        $results = $orch->run([$a, $b], 'task', ['parallel' => false]);

        $this->assertCount(2, $results);
        $this->assertSame('a', $results[0]['agent']);
        $this->assertSame('b', $results[1]['agent']);
    }

    public function test_parallel_empty(): void
    {
        $orch = new ParallelOrchestrator();
        $this->assertSame([], $orch->run([], 'task'));
    }

    // ── Hierarchical ─────────────────────────────────────

    public function test_hierarchical_manager_plus_workers(): void
    {
        $ai = $this->ai(5);
        $manager = new Agent('manager', 'coordinate', $ai);
        $w1 = new Agent('w1', 'worker', $ai);
        $w2 = new Agent('w2', 'worker', $ai);

        $orch = new HierarchicalOrchestrator();
        $results = $orch->run([$manager, $w1, $w2], 'build app');

        // manager plan + w1 + w2 + manager synthesis = 4
        $this->assertCount(4, $results);
        $this->assertSame('manager', $results[0]['agent']);
        $this->assertSame('w1', $results[1]['agent']);
        $this->assertSame('w2', $results[2]['agent']);
        $this->assertSame('manager', $results[3]['agent']);
    }

    public function test_hierarchical_empty(): void
    {
        $orch = new HierarchicalOrchestrator();
        $this->assertSame([], $orch->run([], 'task'));
    }

    // ── Conversational ───────────────────────────────────

    public function test_conversational_respects_max_iterations(): void
    {
        $ai = $this->ai(10);
        $a = new Agent('a', 'role-a', $ai);
        $b = new Agent('b', 'role-b', $ai);

        $orch = new ConversationalOrchestrator(maxIterations: 3);
        $results = $orch->run([$a, $b], 'debate');

        $this->assertCount(3, $results);
    }

    public function test_conversational_options_override(): void
    {
        $ai = $this->ai(10);
        $a = new Agent('a', 'r', $ai);

        $orch = new ConversationalOrchestrator(maxIterations: 10);
        $results = $orch->run([$a], 'x', ['maxIterations' => 2]);

        $this->assertCount(2, $results);
    }

    // ── Crew Delegation ──────────────────────────────────

    public function test_crew_delegates_to_orchestrator(): void
    {
        $ai = $this->ai(2);
        $a = new Agent('a', 'role', $ai);

        $crew = new Crew('test-crew', [$a], AgentProcess::Sequential);
        $this->assertInstanceOf(SequentialOrchestrator::class, $crew->orchestrator());
        $results = $crew->run('task');
        $this->assertCount(1, $results);
    }

    public function test_crew_custom_orchestrator(): void
    {
        $custom = new class implements OrchestratorInterface {
            public function run(array $agents, string $task, array $options = []): array {
                return [['agent' => 'custom', 'response' => null]];
            }
        };

        $crew = new Crew('c', [], orchestrator: $custom);
        $this->assertSame(1, count($crew->run('x')));
    }

    public function test_crew_resolves_all_process_types(): void
    {
        foreach (AgentProcess::cases() as $process) {
            $crew = new Crew('c', [], $process);
            $this->assertInstanceOf(OrchestratorInterface::class, $crew->orchestrator());
        }
    }

    public function test_crew_agents_accessor(): void
    {
        $ai = $this->ai();
        $a = new Agent('a', 'r', $ai);
        $crew = new Crew('c', [$a]);
        $this->assertCount(1, $crew->agents());
    }

    // ── AI Facade Methods ────────────────────────────────

    public function test_ai_agent_returns_builder(): void
    {
        $ai = new AI(FakeProvider::create()->respondWith('ok'));
        $this->assertInstanceOf(AgentBuilder::class, $ai->agent('test'));
    }

    public function test_ai_crew_returns_builder(): void
    {
        $ai = new AI(FakeProvider::create()->respondWith('ok'));
        $this->assertInstanceOf(CrewBuilder::class, $ai->crew('test'));
    }

    public function test_ai_pipeline_returns_pipeline(): void
    {
        $ai = new AI(FakeProvider::create()->respondWith('ok'));
        $this->assertInstanceOf(\MonkeysLegion\Apex\Pipeline\Pipeline::class, $ai->pipeline('p'));
    }

    public function test_ai_guard_returns_guard(): void
    {
        $ai = new AI(FakeProvider::create()->respondWith('ok'));
        $this->assertInstanceOf(\MonkeysLegion\Apex\Guard\Guard::class, $ai->guard());
    }

    public function test_ai_stats_null_without_tracker(): void
    {
        $ai = new AI(FakeProvider::create()->respondWith('ok'));
        $this->assertNull($ai->stats());
    }

    public function test_ai_stats_with_tracker(): void
    {
        $tracker = new \MonkeysLegion\Apex\Cost\CostTracker(
            new \MonkeysLegion\Apex\Cost\PricingRegistry()
        );
        $ai = new AI(FakeProvider::create()->respondWith('ok'), $tracker);
        $ai->generate('hi');
        $report = $ai->stats();
        $this->assertInstanceOf(\MonkeysLegion\Apex\Cost\CostReport::class, $report);
    }
}
