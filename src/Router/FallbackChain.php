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

use MonkeysLegion\Apex\Contract\ProviderInterface;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\Exception\ProviderException;

/**
 * Fallback chain — tries providers in order until one succeeds.
 */
final class FallbackChain
{
    /** @var list<array{provider: ProviderInterface, model: string}> */
    private array $chain = [];

    public static function create(): self
    {
        return new self();
    }

    /**
     * Add a provider/model pair to the fallback chain.
     */
    public function add(ProviderInterface $provider, string $model): self
    {
        $this->chain[] = ['provider' => $provider, 'model' => $model];
        return $this;
    }

    /**
     * Execute the chain — returns first successful response.
     *
     * @param list<Message>        $messages
     * @param array<string, mixed> $options
     * @return array{response: \MonkeysLegion\Apex\DTO\Response, provider: string, model: string}
     * @throws ProviderException When all providers fail.
     */
    public function execute(array $messages, array $options = []): array
    {
        $errors = [];

        foreach ($this->chain as ['provider' => $provider, 'model' => $model]) {
            try {
                $opts = array_merge($options, ['model' => $model]);
                $response = $provider->chat($messages, $opts);

                return [
                    'response' => $response,
                    'provider' => $provider->name(),
                    'model'    => $model,
                ];
            } catch (\Throwable $e) {
                $errors[] = "{$provider->name()}/{$model}: {$e->getMessage()}";
                continue;
            }
        }

        throw new ProviderException(
            'All providers in fallback chain failed: ' . implode('; ', $errors),
            'fallback_chain',
        );
    }

    /**
     * Get chain length.
     */
    public function count(): int
    {
        return count($this->chain);
    }
}
