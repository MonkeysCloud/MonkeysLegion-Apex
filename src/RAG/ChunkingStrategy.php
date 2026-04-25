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

namespace MonkeysLegion\Apex\RAG;

/**
 * Contract for document chunking strategies.
 */
interface ChunkingStrategy
{
    /**
     * Split text into chunks.
     *
     * @return list<string>
     */
    public function chunk(string $text): array;
}
