<?php

declare(strict_types=1);

namespace Shipped\Backend\Handler;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use Shipped\Backend\Action\RefreshContainers;
use Twig\Environment;

/**
 * HTTP handler for the POST /containers/refresh route (HTMX partial reload).
 *
 * Thin adapter: accepts the incoming request, delegates business
 * logic to RefreshContainers, renders the response via Twig,
 * and wraps the result in a PSR-7 Response.
 */
final class ContainerRefreshHandler
{
    public function __construct(
        private readonly RefreshContainers $action,
        private readonly Environment $twig,
    ) {}

    /**
     * Handle an incoming HTTP request for container refresh.
     *
     * Delegates to RefreshContainers::execute() which re-fetches
     * containers from Docker and returns grouped link data.
     * The domain data is rendered through Twig and wrapped in a
     * 200 Response with Content-Type: text/html for HTMX consumption.
     *
     * @return PromiseInterface<Response> Resolves to a 200 HTML response
     */
    public function handle(ServerRequestInterface $request): PromiseInterface
    {
        return $this->action->execute()->then(
            function (array $groups): Response {
                $html = $this->twig->render('partials/link-cards.twig', [
                    'groups' => $groups,
                ]);

                return new Response(
                    200,
                    ['Content-Type' => 'text/html'],
                    $html,
                );
            },
        );
    }
}
