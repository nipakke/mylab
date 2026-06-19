<?php

declare(strict_types=1);

namespace Shipped\Backend\Handler;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use Shipped\Backend\Workflow\ListContainersWorkflow;

/**
 * HTTP handler for the GET / route.
 *
 * Thin adapter: accepts the incoming request, delegates all business
 * logic to ListContainersWorkflow, and wraps the rendered HTML in
 * a PSR-7 Response with the appropriate Content-Type header.
 */
final class HomeHandler
{
    public function __construct(
        private readonly ListContainersWorkflow $workflow,
    ) {}

    /**
     * Handle an incoming HTTP request.
     *
     * @return PromiseInterface<Response> Resolves to a 200 HTML response
     */
    public function __invoke(ServerRequestInterface $request): PromiseInterface
    {
        return $this->workflow->handle()->then(
            fn(string $html): Response => new Response(
                200,
                ['Content-Type' => 'text/html'],
                $html,
            ),
        );
    }
}
