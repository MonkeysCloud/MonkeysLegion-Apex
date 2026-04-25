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

namespace MonkeysLegion\Apex\A2A;

/**
 * A2A Agent Card — discovery manifest for agent capabilities.
 *
 * Implements the Agent-to-Agent (A2A) protocol specification.
 * Agent Cards are served at `/.well-known/agent.json` for discovery.
 */
final readonly class AgentCard
{
    /**
     * @param list<string> $skills    Human-readable list of agent capabilities
     * @param list<string> $protocols Supported protocols (e.g., ['a2a/1.2'])
     * @param array<string, mixed> $authentication Authentication requirements
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $url,
        public string $version = '1.0.0',
        public array  $skills = [],
        public array  $protocols = ['a2a/1.2'],
        public array  $authentication = [],
        public ?string $provider = null,
    ) {}

    /**
     * Serialize to A2A-compatible JSON format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $card = [
            'name'        => $this->name,
            'description' => $this->description,
            'url'         => $this->url,
            'version'     => $this->version,
            'skills'      => $this->skills,
            'protocols'   => $this->protocols,
        ];

        if (!empty($this->authentication)) {
            $card['authentication'] = $this->authentication;
        }

        if ($this->provider !== null) {
            $card['provider'] = $this->provider;
        }

        return $card;
    }

    /**
     * Serialize to JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
