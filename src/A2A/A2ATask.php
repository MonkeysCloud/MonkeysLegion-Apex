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
 * A2A Task — represents a unit of work delegated between agents.
 *
 * Lifecycle: submitted → working → input-required → completed | failed | canceled
 */
final class A2ATask
{
    public function __construct(
        public readonly string $id,
        public string          $status = 'submitted',
        public ?string         $input = null,
        public ?string         $output = null,
        public ?string         $error = null,
        public readonly string $createdAt = '',
        public ?string         $updatedAt = null,
        /** @var array<string, mixed> */
        public array           $metadata = [],
    ) {}

    /**
     * Transition to 'working' status.
     */
    public function working(): self
    {
        $this->status = 'working';
        $this->updatedAt = (new \DateTimeImmutable())->format('c');
        return $this;
    }

    /**
     * Transition to 'input-required' status.
     */
    public function inputRequired(): self
    {
        $this->status = 'input-required';
        $this->updatedAt = (new \DateTimeImmutable())->format('c');
        return $this;
    }

    /**
     * Complete the task with output.
     */
    public function complete(string $output): self
    {
        $this->status = 'completed';
        $this->output = $output;
        $this->updatedAt = (new \DateTimeImmutable())->format('c');
        return $this;
    }

    /**
     * Fail the task with an error message.
     */
    public function fail(string $error): self
    {
        $this->status = 'failed';
        $this->error = $error;
        $this->updatedAt = (new \DateTimeImmutable())->format('c');
        return $this;
    }

    /**
     * Cancel the task.
     */
    public function cancel(): self
    {
        $this->status = 'canceled';
        $this->updatedAt = (new \DateTimeImmutable())->format('c');
        return $this;
    }

    /**
     * Check if the task is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'canceled'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'status'    => $this->status,
            'input'     => $this->input,
            'output'    => $this->output,
            'error'     => $this->error,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'metadata'  => $this->metadata,
        ];
    }

    /**
     * Create a new task from an input.
     */
    public static function create(string $input, array $metadata = []): self
    {
        return new self(
            id:        bin2hex(random_bytes(16)),
            input:     $input,
            createdAt: (new \DateTimeImmutable())->format('c'),
            metadata:  $metadata,
        );
    }
}
