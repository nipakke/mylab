<?php

declare(strict_types=1);

namespace Shipped\Backend\Handler;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use Shipped\Backend\Action\RefreshContainers;

/**
 * HTTP handler for the POST /containers/refresh route (HTMX partial reload).
 *
 * Thin adapter: accepts the incoming request, delegates all business
 * logic to RefreshContainers, and wraps the rendered HTML
 * fragment in a PSR-7 Response with the appropriate Content-Type header.
 */
final class ContainerRefreshHandler
{
    public function __construct(
        private readonly RefreshContainers $action,
    ) {}

    /**
     * Handle an incoming HTTP request for container refresh.
     *
     * Delegates to RefreshContainers::execute() which re-fetches
     * containers from Docker and renders the table-row partial via Twig.
     * The resulting HTML string is wrapped in a 200 Response with
     * Content-Type: text/html for HTMX consumption.
     *
     * @return PromiseInterface<Response> Resolves to a 200 HTML response
     */
    public function handle(ServerRequestInterface $request): PromiseInterface
    {
        return $this->action->execute()->then(
            fn(string $html): Response => new Response(
                200,
                ['Content-Type' => 'text/html'],
                $html,
            ),
        );
    }
}
