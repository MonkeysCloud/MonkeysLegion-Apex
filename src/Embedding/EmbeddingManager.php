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

namespace MonkeysLegion\Apex\Embedding;

use MonkeysLegion\Apex\Contract\ProviderInterface;
use MonkeysLegion\Apex\DTO\EmbeddingVector;

/**
 * Manages embedding generation via a provider.
 */
final class EmbeddingManager
{
    public function __construct(
        private readonly ProviderInterface $provider,
    ) {}

    /**
     * Embed one or more strings.
     *
     * @param list<string> $inputs
     * @return list<EmbeddingVector>
     */
    public function embed(array $inputs): array
    {
        return $this->provider->embed($inputs);
    }

    /**
     * Embed a single string.
     */
    public function embedOne(string $input): EmbeddingVector
    {
        $results = $this->embed([$input]);
        return $results[0];
    }

    /**
     * Compute similarity between two strings.
     */
    public function similarity(string $a, string $b): float
    {
        $va = $this->embedOne($a);
        $vb = $this->embedOne($b);
        return $va->cosineSimilarity($vb);
    }
}
