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

namespace MonkeysLegion\Apex\Cost;

/**
 * Generated cost report.
 */
final readonly class CostReport
{
    /**
     * @param array<string, array{count: int, total: float, avg: float}> $byModel
     * @param array<string, float> $byPeriod
     * @param array{input: float, output: float, total: float, count: int} $summary
     */
    public function __construct(
        public array $byModel,
        public array $byPeriod,
        public array $summary,
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
    ) {}

    /**
     * Generate a cost report from tracked costs.
     *
     * @param list<\MonkeysLegion\Apex\DTO\Cost> $costs
     */
    public static function generate(array $costs, string $periodFormat = 'Y-m-d'): self
    {
        $aggregator = new CostAggregator();

        $timestamps = array_map(fn($c) => $c->timestamp, $costs);
        $from = !empty($timestamps) ? min($timestamps) : new \DateTimeImmutable();
        $to   = !empty($timestamps) ? max($timestamps) : new \DateTimeImmutable();

        return new self(
            byModel:  $aggregator->byModel($costs),
            byPeriod: $aggregator->byPeriod($costs, $periodFormat),
            summary:  $aggregator->summary($costs),
            from:     $from,
            to:       $to,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'summary'   => $this->summary,
            'by_model'  => $this->byModel,
            'by_period' => $this->byPeriod,
            'period'    => [
                'from' => $this->from->format('c'),
                'to'   => $this->to->format('c'),
            ],
        ];
    }
}
