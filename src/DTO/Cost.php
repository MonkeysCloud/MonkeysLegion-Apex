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

namespace MonkeysLegion\Apex\DTO;

/**
 * Cost calculation result for a single AI request.
 */
final readonly class Cost
{
    public float $total;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public float               $inputCost,
        public float               $outputCost,
        public string              $model,
        public \DateTimeImmutable   $timestamp = new \DateTimeImmutable(),
        public array               $metadata = [],
    ) {
        $this->total = $this->inputCost + $this->outputCost;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'input_cost'  => round($this->inputCost, 6),
            'output_cost' => round($this->outputCost, 6),
            'total'       => round($this->total, 6),
            'model'       => $this->model,
            'timestamp'   => $this->timestamp->format('c'),
        ];
    }
}
