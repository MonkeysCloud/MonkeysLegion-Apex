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
use MonkeysLegion\Apex\Contract\MemoryInterface;

/** Fluent builder for Agent. */
final class AgentBuilder
{
    private string $name = 'agent';
    private string $role = 'You are a helpful assistant.';
    /** @var list<object> */
    private array $tools = [];
    /** @var array<string, mixed> */
    private array $config = [];
    private ?MemoryInterface $memory = null;

    public function __construct(
        private readonly AI $ai,
    ) {}

    public function name(string $name): self { $this->name = $name; return $this; }
    public function role(string $role): self { $this->role = $role; return $this; }
    public function model(string $model): self { $this->config['model'] = $model; return $this; }
    public function tools(object ...$tools): self { $this->tools = $tools; return $this; }
    public function memory(MemoryInterface $memory): self { $this->memory = $memory; return $this; }
    public function config(string $key, mixed $value): self { $this->config[$key] = $value; return $this; }

    public function build(): Agent
    {
        return new Agent(
            name:   $this->name,
            role:   $this->role,
            ai:     $this->ai,
            tools:  $this->tools,
            config: $this->config,
            memory: $this->memory,
        );
    }
}
