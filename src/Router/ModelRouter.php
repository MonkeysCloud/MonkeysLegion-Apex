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

namespace MonkeysLegion\Apex\Router;

use MonkeysLegion\Apex\Contract\RouterInterface;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\Enum\ModelTier;
use MonkeysLegion\Apex\Enum\Role;
use MonkeysLegion\Apex\Enum\RouterStrategy;

/**
 * Smart model router — selects optimal model based on complexity.
 */
final class ModelRouter implements RouterInterface
{
    /** @var array<string, list<string>> */
    private array $tiers = [];

    /** @var list<RoutingRule> */
    private array $rules = [];

    private RouterStrategy $strategy = RouterStrategy::CostOptimized;
    private ?string $fallback = null;
    private int $roundRobinIndex = 0;

    public static function create(): self
    {
        return new self();
    }

    /**
     * Define a model tier.
     *
     * @param list<string> $models
     */
    public function tier(string|ModelTier $tier, array $models): self
    {
        $name = $tier instanceof ModelTier ? $tier->value : $tier;
        $this->tiers[$name] = $models;
        return $this;
    }

    /**
     * Set the routing strategy.
     */
    public function strategy(RouterStrategy|string $strategy): self
    {
        $this->strategy = $strategy instanceof RouterStrategy
            ? $strategy
            : RouterStrategy::from($strategy);
        return $this;
    }

    /**
     * Set fallback model.
     */
    public function fallback(string $model): self
    {
        $this->fallback = $model;
        return $this;
    }

    /**
     * Add a routing rule.
     *
     * @param callable(list<Message>, array<string, mixed>): bool $condition
     */
    public function rule(callable $condition, string $tier): self
    {
        $this->rules[] = new RoutingRule($condition, $tier);
        return $this;
    }

    /**
     * Select the best model for this request.
     *
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     */
    public function select(array $messages, array $options = []): string
    {
        // 1. Explicit model override
        if (isset($options['model'])) {
            return $options['model'];
        }

        // 2. Check declarative rules
        foreach ($this->rules as $rule) {
            if ($rule->matches($messages, $options)) {
                return $this->pickFromTier($rule->tier);
            }
        }

        // 3. Auto-classify and route
        $complexity = $this->classifyComplexity($messages);

        return match ($this->strategy) {
            RouterStrategy::CostOptimized => $this->routeCostOptimized($complexity),
            RouterStrategy::QualityFirst  => $this->routeQualityFirst($complexity),
            RouterStrategy::LatencyFirst  => $this->routeLatencyFirst($complexity),
            RouterStrategy::RoundRobin    => $this->routeRoundRobin(),
        };
    }

    /**
     * Classify input complexity: low, medium, high.
     *
     * Heuristics: total content length, system message presence, attachments.
     *
     * @param list<Message> $messages
     */
    private function classifyComplexity(array $messages): string
    {
        $totalLength = array_sum(array_map(
            fn(Message $m) => strlen($m->content),
            $messages,
        ));
        $hasSystem = !empty(array_filter($messages, fn(Message $m) => $m->role === Role::System));
        $hasAttach = !empty(array_filter($messages, fn(Message $m) => !empty($m->attachments)));

        if ($totalLength < 200 && !$hasSystem && !$hasAttach) {
            return 'low';
        }
        if ($totalLength > 2000 || $hasAttach) {
            return 'high';
        }
        return 'medium';
    }

    private function routeCostOptimized(string $complexity): string
    {
        return match ($complexity) {
            'low'    => $this->pickFromTier('fast'),
            'medium' => $this->pickFromTier('balanced'),
            'high'   => $this->pickFromTier('power'),
        };
    }

    private function routeQualityFirst(string $complexity): string
    {
        return match ($complexity) {
            'low'    => $this->pickFromTier('balanced'),
            'medium' => $this->pickFromTier('power'),
            'high'   => $this->pickFromTier('power'),
        };
    }

    private function routeLatencyFirst(string $complexity): string
    {
        return $this->pickFromTier('fast');
    }

    private function routeRoundRobin(): string
    {
        $allModels = array_merge(...array_values($this->tiers));
        if (empty($allModels)) {
            return $this->fallback ?? 'claude-haiku-4';
        }
        $model = $allModels[$this->roundRobinIndex % count($allModels)];
        $this->roundRobinIndex++;
        return $model;
    }

    private function pickFromTier(string $tierName): string
    {
        $models = $this->tiers[$tierName] ?? [];
        if (empty($models)) {
            return $this->fallback ?? 'claude-haiku-4';
        }
        return $models[0];
    }
}
