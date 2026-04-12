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

namespace MonkeysLegion\Apex\Contract;

use MonkeysLegion\Apex\DTO\EmbeddingVector;

/**
 * Embedding provider contract.
 */
interface EmbeddingInterface
{
    /**
     * Generate embeddings for given texts.
     *
     * @param list<string> $inputs
     * @return list<EmbeddingVector>
     */
    public function embed(array $inputs): array;
}
