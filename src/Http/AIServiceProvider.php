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

namespace MonkeysLegion\Apex\Http;

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\Config\ConfigResolver;
use MonkeysLegion\Apex\Cost\CostTracker;
use MonkeysLegion\Apex\Cost\PricingRegistry;

/**
 * Service provider — wires AI services into the framework DI container.
 *
 * Configuration is resolved with the following priority:
 *   1. Manual overrides passed via `$config` constructor argument
 *   2. MLC file (`ai.mlc`) — if MonkeysLegion MLC parser is installed
 *   3. PHP file (`config/ai.php`) — traditional PHP array
 *   4. Hardcoded sensible defaults
 *
 * Usage in MonkeysLegion:
 *   $container->register(new AIServiceProvider($config));
 */
final class AIServiceProvider
{
    /** @var list<string> Allowed provider class names */
    private const array ALLOWED_PROVIDERS = [
        \MonkeysLegion\Apex\Provider\Anthropic\AnthropicProvider::class,
        \MonkeysLegion\Apex\Provider\OpenAI\OpenAIProvider::class,
        \MonkeysLegion\Apex\Provider\Google\GoogleProvider::class,
        \MonkeysLegion\Apex\Provider\Ollama\OllamaProvider::class,
    ];

    /** @var array<string, mixed> */
    private readonly array $config;

    /**
     * @param array<string, mixed> $config     Manual overrides (highest priority).
     * @param string|null          $configDir  Path to config directory. When null,
     *                                         auto-discovered from the package root.
     */
    public function __construct(array $config = [], ?string $configDir = null)
    {
        $this->config = ConfigResolver::resolve(
            configDir: $configDir,
            overrides: $config,
        );
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

                if ($providerClass !== null
                    && class_exists($providerClass)
                    && in_array($providerClass, self::ALLOWED_PROVIDERS, true)
                ) {
                    $provider = new $providerClass(
                        apiKey: $this->config['api_key'] ?? '',
                        model:  $this->config['model'] ?? 'claude-sonnet-4',
                        embeddingModel: $this->config['embedding_model'] ?? null,
                    );
                }

                return new AI(
                    provider: $provider,
                    costTracker: new CostTracker(new PricingRegistry()),
                );
            },
        ];
    }

}
