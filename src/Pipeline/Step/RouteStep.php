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

namespace MonkeysLegion\Apex\Pipeline\Step;

use MonkeysLegion\Apex\Pipeline\PipelineContext;
use MonkeysLegion\Apex\Pipeline\StepInterface;
use MonkeysLegion\Apex\Router\ModelRouter;

/**
 * Route step — selects the model via ModelRouter before generation.
 */
final class RouteStep implements StepInterface
{
    public function __construct(
        private readonly ModelRouter $router,
    ) {}

    public function execute(PipelineContext $context): mixed
    {
        $messages = $context->get('messages') ?? [];
        $model    = $this->router->select($messages);

        $context->set('routed_model', $model);
        return $model;
    }

    public function name(): string { return 'route'; }
}
