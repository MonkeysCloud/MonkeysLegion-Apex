<?php



/**
 * MonkeysLegion Apex
 *
 * @package   MonkeysLegion\Apex
 * @author    MonkeysCloud <jorge@monkeys.cloud>
 * @license   MIT
 *
 * @requires  PHP 8.4
 */

declare(strict_types=1);

namespace MonkeysLegion\Apex\Agent;

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\Enum\AgentProcess;

/** Fluent builder for Crew. */
final class CrewBuilder
{
    /** @var list<Agent> */
    private array $agents = [];
    private string $name = 'crew';
    private AgentProcess $process = AgentProcess::Sequential;
    private int $maxIterations = 10;

    public function __construct(
        private readonly AI $ai,
    ) {}

    public function name(string $name): self { $this->name = $name; return $this; }
    public function process(AgentProcess $process): self { $this->process = $process; return $this; }
    public function maxIterations(int $max): self { $this->maxIterations = $max; return $this; }

    public function agent(Agent $agent): self
    {
        $this->agents[] = $agent;
        return $this;
    }

    public function build(): Crew
    {
        return new Crew(
            name:          $this->name,
            agents:        $this->agents,
            process:       $this->process,
            maxIterations: $this->maxIterations,
        );
    }
}
