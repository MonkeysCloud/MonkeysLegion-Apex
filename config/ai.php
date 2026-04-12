<?php

declare(strict_types=1);

/**
 * MonkeysLegion Apex — Default Configuration
 *
 * @package   MonkeysLegion\Apex
 * @author    MonkeysCloud <jorge@monkeys.cloud>
 * @license   MIT
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    | The default AI provider to use: 'anthropic', 'openai', 'ollama'
    */
    'default_provider' => env('AI_PROVIDER', 'anthropic'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'anthropic' => [
            'api_key'  => env('ANTHROPIC_API_KEY', ''),
            'model'    => env('ANTHROPIC_MODEL', 'claude-sonnet-4'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'timeout'  => 30.0,
        ],
        'openai' => [
            'api_key'  => env('OPENAI_API_KEY', ''),
            'model'    => env('OPENAI_MODEL', 'gpt-4.1'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com'),
            'timeout'  => 30.0,
        ],
        'ollama' => [
            'model'    => env('OLLAMA_MODEL', 'llama3'),
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'timeout'  => 60.0,
        ],
        'google' => [
            'api_key'  => env('GOOGLE_API_KEY', ''),
            'model'    => env('GOOGLE_MODEL', 'gemini-2.5-flash'),
            'base_url' => env('GOOGLE_BASE_URL', 'https://generativelanguage.googleapis.com'),
            'timeout'  => 30.0,
        ],
        'vertex' => [
            'api_key'  => env('VERTEX_API_KEY', ''),
            'model'    => env('VERTEX_MODEL', 'gemini-2.5-flash'),
            'project'  => env('VERTEX_PROJECT', ''),
            'location' => env('VERTEX_LOCATION', 'us-central1'),
            'timeout'  => 30.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost & Budget
    |--------------------------------------------------------------------------
    */
    'cost' => [
        'tracking_enabled' => true,
        'max_budget'       => (float) env('AI_MAX_BUDGET', 100.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'enabled'         => true,
        'max_requests'    => (int) env('AI_RATE_LIMIT', 60),
        'window_seconds'  => 60.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Guards
    |--------------------------------------------------------------------------
    */
    'guards' => [
        'input' => [
            'pii_detection'      => true,
            'injection_detection'=> true,
            'toxicity_detection' => true,
        ],
        'output' => [
            'pii_detection' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => (bool) env('AI_CACHE_ENABLED', false),
        'ttl'     => (int) env('AI_CACHE_TTL', 3600),
        'prefix'  => 'apex_cache:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_retries' => 3,
        'base_delay'  => 1.0,
        'max_delay'   => 8.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Router
    |--------------------------------------------------------------------------
    */
    'router' => [
        'strategy' => env('AI_ROUTER_STRATEGY', 'cost_optimized'),
        'tiers' => [
            'fast'     => ['claude-haiku-4', 'gpt-4.1-nano'],
            'balanced' => ['claude-sonnet-4', 'gpt-4.1'],
            'power'    => ['claude-opus-4', 'o3'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory
    |--------------------------------------------------------------------------
    */
    'memory' => [
        'default'      => 'sliding_window',
        'max_tokens'   => 4096,
        'max_messages' => 50,
    ],
];
