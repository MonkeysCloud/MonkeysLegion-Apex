<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Http;

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\Contract\ProviderInterface;
use MonkeysLegion\Apex\Cost\CostTracker;
use MonkeysLegion\Apex\Cost\PricingRegistry;

/**
 * Service provider — wires AI services into the framework DI container.
 *
 * Usage in MonkeysLegion:
 *   $container->register(new AIServiceProvider($config));
 */
final class AIServiceProvider
{
    /** @var array<string, mixed> */
    private readonly array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->defaults(), $config);
    }

    /**
     * Register services — call from your DI container configuration.
     *
     * @return array<string, callable>
     */
    public function register(): array
    {
        return [
            PricingRegistry::class => fn() => new PricingRegistry(),

            CostTracker::class => fn() => new CostTracker(
                new PricingRegistry(),
            ),

            AI::class => function (): AI {
                $providerClass = $this->config['provider'] ?? null;
                $provider = null;

                if ($providerClass !== null && class_exists($providerClass)) {
                    $provider = new $providerClass(
                        apiKey: $this->config['api_key'] ?? '',
                        model:  $this->config['model'] ?? 'claude-sonnet-4',
                    );
                }

                return new AI(
                    provider: $provider,
                    costTracker: new CostTracker(new PricingRegistry()),
                );
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'provider'   => null,
            'api_key'    => '',
            'model'      => 'claude-sonnet-4',
            'max_budget' => 100.0,
            'rate_limit' => 60,
        ];
    }
}
