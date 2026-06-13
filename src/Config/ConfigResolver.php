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

namespace MonkeysLegion\Apex\Config;

/**
 * ConfigResolver — dual-format configuration loader for Apex.
 *
 * Resolution priority:
 *   1. Manual overrides (passed via `$overrides`)
 *   2. MLC file (`ai.mlc`) — if MonkeysLegion MLC parser is available
 *   3. PHP file (`ai.php`) — traditional PHP array return
 *   4. Hardcoded sensible defaults
 *
 * This design keeps Apex lightweight for non-MonkeysLegion environments
 * (e.g., Drupal) where the MLC parser is not installed. The `monkeyslegion-mlc`
 * package is a `suggest` dependency, not a `require`.
 */
final class ConfigResolver
{
    /**
     * Resolve the full Apex configuration array.
     *
     * @param string|null          $configDir  Path to config directory (e.g., basePath . '/config').
     *                                         When null, attempts to discover from the package root.
     * @param array<string, mixed> $overrides  Manual overrides — highest priority.
     *
     * @return array<string, mixed>  Merged configuration.
     */
    public static function resolve(?string $configDir = null, array $overrides = []): array
    {
        // Discover config directory if not provided
        if ($configDir === null) {
            $configDir = self::discoverConfigDir();
        }

        // Try MLC first (prioritized)
        $mlcConfig = self::tryMlc($configDir);

        if ($mlcConfig !== null) {
            return array_replace_recursive(self::defaults(), $mlcConfig, $overrides);
        }

        // Fall back to PHP config
        $phpConfig = self::tryPhp($configDir);

        if ($phpConfig !== null) {
            return array_replace_recursive(self::defaults(), $phpConfig, $overrides);
        }

        // Last resort: defaults + overrides
        return array_replace_recursive(self::defaults(), $overrides);
    }

    /**
     * Load config from an MLC file if the MLC parser is available.
     *
     * Returns null if:
     *  - The MLC parser class is not installed
     *  - The MLC env bootstrapper class is not installed
     *  - The `ai.mlc` file does not exist
     *  - Parsing fails (silently skipped)
     *
     * @param string|null $configDir
     *
     * @return array<string, mixed>|null
     */
    private static function tryMlc(?string $configDir): ?array
    {
        if ($configDir === null) {
            return null;
        }

        $mlcFile = rtrim($configDir, '/') . '/ai.mlc';

        if (!is_file($mlcFile)) {
            return null;
        }

        // Check if the MLC parser and Env bootstrapper are available
        if (!class_exists(\MonkeysLegion\Mlc\Parsers\MlcParser::class, true)) {
            return null;
        }

        if (!class_exists(\MonkeysLegion\Env\EnvManager::class, true)) {
            return null;
        }

        try {
            // Bootstrap env + parser (same flow as the framework's ConfigLoader)
            $envManager = new \MonkeysLegion\Env\EnvManager(
                loader: new \MonkeysLegion\Env\Loaders\DotenvLoader(),
                repository: new \MonkeysLegion\Env\Repositories\NativeEnvRepository(),
            );

            // Boot from project root (one level up from config/)
            $rootPath = dirname($configDir);
            if (!$envManager->isBooted()) {
                $envManager->boot($rootPath);
            }

            $parser = new \MonkeysLegion\Mlc\Parsers\MlcParser(
                envBootstrapper: $envManager,
                root: $rootPath,
            );

            $parsed = $parser->parseFile($mlcFile);

            // The MLC file wraps config under an `ai { }` block — flatten it
            return $parsed['ai'] ?? $parsed;
        } catch (\Throwable) {
            // Silently skip if parsing fails — fall through to PHP config
            return null;
        }
    }

    /**
     * Load config from a PHP file.
     *
     * Returns null if the file does not exist or does not return an array.
     *
     * @param string|null $configDir
     *
     * @return array<string, mixed>|null
     */
    private static function tryPhp(?string $configDir): ?array
    {
        if ($configDir === null) {
            return null;
        }

        $phpFile = rtrim($configDir, '/') . '/ai.php';

        if (!is_file($phpFile)) {
            return null;
        }

        try {
            $config = require $phpFile;

            return is_array($config) ? $config : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Hardcoded sensible defaults.
     *
     * These match the existing values from config/ai.php and ensure Apex
     * always has a complete configuration tree even if no config file exists.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'default_provider' => 'anthropic',

            'providers' => [
                'anthropic' => [
                    'api_key'  => '',
                    'model'    => 'claude-sonnet-4',
                    'base_url' => 'https://api.anthropic.com',
                    'timeout'  => 30.0,
                ],
                'openai' => [
                    'api_key'  => '',
                    'model'    => 'gpt-4.1',
                    'embedding_model' => 'text-embedding-3-small',
                    'base_url' => 'https://api.openai.com',
                    'timeout'  => 30.0,
                ],
                'ollama' => [
                    'model'    => 'llama3',
                    'embedding_model' => 'llama3',
                    'base_url' => 'http://localhost:11434',
                    'timeout'  => 60.0,
                ],
                'google' => [
                    'api_key'  => '',
                    'model'    => 'gemini-2.5-flash',
                    'embedding_model' => 'text-embedding-004',
                    'base_url' => 'https://generativelanguage.googleapis.com',
                    'timeout'  => 30.0,
                ],
                'vertex' => [
                    'api_key'  => '',
                    'model'    => 'gemini-2.5-flash',
                    'embedding_model' => 'text-embedding-004',
                    'project'  => '',
                    'location' => 'us-central1',
                    'timeout'  => 30.0,
                ],
            ],

            'cost' => [
                'tracking_enabled' => true,
                'max_budget'       => 100.0,
            ],

            'rate_limit' => [
                'enabled'        => true,
                'max_requests'   => 60,
                'window_seconds' => 60.0,
            ],

            'guards' => [
                'input' => [
                    'pii_detection'       => true,
                    'injection_detection' => true,
                    'toxicity_detection'  => true,
                ],
                'output' => [
                    'pii_detection' => true,
                ],
            ],

            'cache' => [
                'enabled' => false,
                'ttl'     => 3600,
                'prefix'  => 'apex_cache:',
            ],

            'retry' => [
                'max_retries' => 3,
                'base_delay'  => 1.0,
                'max_delay'   => 8.0,
            ],

            'router' => [
                'strategy' => 'cost_optimized',
                'tiers' => [
                    'fast'     => ['claude-haiku-4', 'gpt-4.1-nano'],
                    'balanced' => ['claude-sonnet-4', 'gpt-4.1'],
                    'power'    => ['claude-opus-4', 'o3'],
                ],
            ],

            'memory' => [
                'default'      => 'sliding_window',
                'max_tokens'   => 4096,
                'max_messages' => 50,
            ],
        ];
    }

    /**
     * Attempt to discover the config directory from the package root.
     *
     * Walks up from __DIR__ looking for a `config/` directory.
     *
     * @return string|null
     */
    private static function discoverConfigDir(): ?string
    {
        // From src/Config/ → package root is ../../
        $packageRoot = dirname(__DIR__, 2);
        $configDir = $packageRoot . '/config';

        if (is_dir($configDir)) {
            return $configDir;
        }

        return null;
    }
}
