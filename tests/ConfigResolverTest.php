<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Tests;

use MonkeysLegion\Apex\Config\ConfigResolver;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ConfigResolver — dual config loading (MLC-first, PHP fallback).
 */
final class ConfigResolverTest extends TestCase
{
    // ─── Defaults ───────────────────────────────────────

    public function test_defaults_returns_complete_config(): void
    {
        $defaults = ConfigResolver::defaults();

        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('default_provider', $defaults);
        $this->assertArrayHasKey('providers', $defaults);
        $this->assertArrayHasKey('cost', $defaults);
        $this->assertArrayHasKey('rate_limit', $defaults);
        $this->assertArrayHasKey('guards', $defaults);
        $this->assertArrayHasKey('cache', $defaults);
        $this->assertArrayHasKey('retry', $defaults);
        $this->assertArrayHasKey('router', $defaults);
        $this->assertArrayHasKey('memory', $defaults);

        $this->assertSame('anthropic', $defaults['default_provider']);
    }

    public function test_defaults_includes_all_providers(): void
    {
        $defaults = ConfigResolver::defaults();

        $this->assertArrayHasKey('anthropic', $defaults['providers']);
        $this->assertArrayHasKey('openai', $defaults['providers']);
        $this->assertArrayHasKey('google', $defaults['providers']);
        $this->assertArrayHasKey('ollama', $defaults['providers']);
        $this->assertArrayHasKey('vertex', $defaults['providers']);
    }

    // ─── Resolve with no config files ───────────────────

    public function test_resolve_with_null_dir_returns_defaults(): void
    {
        $config = ConfigResolver::resolve(configDir: null, overrides: []);

        $this->assertSame('anthropic', $config['default_provider']);
        $this->assertIsArray($config['providers']);
    }

    public function test_resolve_with_nonexistent_dir_returns_defaults(): void
    {
        $config = ConfigResolver::resolve(configDir: '/nonexistent/path/config');

        $this->assertSame('anthropic', $config['default_provider']);
    }

    // ─── Override merging ───────────────────────────────

    public function test_overrides_take_highest_priority(): void
    {
        $config = ConfigResolver::resolve(
            configDir: null,
            overrides: ['default_provider' => 'openai'],
        );

        $this->assertSame('openai', $config['default_provider']);
    }

    public function test_nested_overrides_merge_correctly(): void
    {
        $config = ConfigResolver::resolve(
            configDir: null,
            overrides: [
                'providers' => [
                    'anthropic' => [
                        'api_key' => 'sk-test-123',
                    ],
                ],
            ],
        );

        // Override applied
        $this->assertSame('sk-test-123', $config['providers']['anthropic']['api_key']);

        // Other defaults preserved
        $this->assertSame('claude-sonnet-4', $config['providers']['anthropic']['model']);
        $this->assertArrayHasKey('openai', $config['providers']);
    }

    // ─── PHP config fallback ────────────────────────────

    public function test_resolve_loads_php_config(): void
    {
        // The package ships config/ai.php — resolve should find it
        $packageConfigDir = dirname(__DIR__) . '/config';

        if (!is_file($packageConfigDir . '/ai.php')) {
            $this->markTestSkipped('config/ai.php not found in package root');
        }

        $config = ConfigResolver::resolve(configDir: $packageConfigDir);

        // Should have values from the PHP config (which uses env() calls)
        $this->assertArrayHasKey('default_provider', $config);
        $this->assertArrayHasKey('providers', $config);
    }

    // ─── MLC config priority ────────────────────────────

    public function test_mlc_file_takes_priority_over_php(): void
    {
        // Create a temp directory with both MLC and PHP configs
        $tmpDir = sys_get_temp_dir() . '/apex_config_test_' . uniqid();
        mkdir($tmpDir, 0755, true);

        try {
            // Write a PHP config
            file_put_contents($tmpDir . '/ai.php', '<?php return ["default_provider" => "openai"];');

            // Write an MLC config
            file_put_contents($tmpDir . '/ai.mlc', <<<'MLC'
            ai {
                default_provider = "google"
            }
            MLC);

            // If MLC parser is NOT available, it should fall back to PHP
            // If MLC parser IS available, it should use MLC (google)
            $config = ConfigResolver::resolve(configDir: $tmpDir);

            if (class_exists(\MonkeysLegion\Mlc\Parsers\MlcParser::class, true)
                && class_exists(\MonkeysLegion\Env\EnvManager::class, true)
            ) {
                $this->assertSame('google', $config['default_provider']);
            } else {
                $this->assertSame('openai', $config['default_provider']);
            }
        } finally {
            @unlink($tmpDir . '/ai.php');
            @unlink($tmpDir . '/ai.mlc');
            @rmdir($tmpDir);
        }
    }

    // ─── AIServiceProvider integration ──────────────────

    public function test_ai_service_provider_uses_config_resolver(): void
    {
        $provider = new \MonkeysLegion\Apex\Http\AIServiceProvider(
            config: ['provider' => null, 'api_key' => 'test-key'],
        );

        // register() should work without errors
        $services = $provider->register();
        $this->assertArrayHasKey(\MonkeysLegion\Apex\AI::class, $services);
    }

    public function test_ai_service_provider_accepts_config_dir(): void
    {
        $provider = new \MonkeysLegion\Apex\Http\AIServiceProvider(
            config: ['api_key' => 'test-key'],
            configDir: '/nonexistent/path',
        );

        $services = $provider->register();
        $this->assertArrayHasKey(\MonkeysLegion\Apex\AI::class, $services);
    }
}
