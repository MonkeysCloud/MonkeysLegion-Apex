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

use MonkeysLegion\Apex\DTO\StreamChunk;

/**
 * Stream contract — iterable stream of chunks.
 */
interface StreamInterface extends \IteratorAggregate
{
    /**
     * Get full concatenated text after consuming the stream.
     */
    public function text(): string;
}
