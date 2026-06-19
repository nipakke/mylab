<?php

declare(strict_types=1);

namespace Shipped\Backend\Handler;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use Shipped\Backend\Service\ContainerStoreInterface;
use Twig\Environment;

use function React\Promise\resolve;

/**
 * HTTP handler for the GET / route.
 *
 * Reads pre-parsed grouped links from the ContainerStore cache
 * and renders the full page via Twig. No label parsing on request.
 */
final class HomeHandler
{
    public function __construct(
        private readonly ContainerStoreInterface $store,
        private readonly Environment $twig,
    ) {}

    /**
     * Handle an incoming HTTP request.
     *
     * @return PromiseInterface<Response> Resolves to a 200 HTML response
     */
    public function __invoke(ServerRequestInterface $request): PromiseInterface
    {
        return resolve($this->twig->render('home.twig', [
            'title' => 'Core Command Panel',
            'groups' => $this->store->getGroupedLinks(0.0),
        ]))->then(
            fn (string $html): Response => new Response(
                200,
                ['Content-Type' => 'text/html'],
                $html,
            ),
        );
    }
}
