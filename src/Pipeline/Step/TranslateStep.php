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

namespace MonkeysLegion\Apex\Pipeline\Step;

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\Pipeline\PipelineContext;
use MonkeysLegion\Apex\Pipeline\StepInterface;

/** Translate text to a target language. */
final class TranslateStep implements StepInterface
{
    public function __construct(
        private readonly AI     $ai,
        private readonly string $targetLanguage,
        private readonly string $outputKey = 'translated',
    ) {}

    public function execute(PipelineContext $context): mixed
    {
        $input    = (string) ($context->get('last_output') ?? $context->input);
        $response = $this->ai->generate(
            "Translate the following text to {$this->targetLanguage}. Output ONLY the translation:\n\n{$input}",
        );
        $context->set($this->outputKey, $response->content);
        return $response->content;
    }

    public function name(): string { return 'translate'; }
}
