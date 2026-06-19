<?php
declare(strict_types=1);

namespace Shipped\Backend\Action;

use React\Promise\PromiseInterface;
use Shipped\Backend\Service\DockerServiceInterface;
use Twig\Environment;

/**
 * Re-fetches containers from Docker and renders the HTMX table-row partial.
 *
 * Implements the async pipeline:
 *   DockerService.listAll() → Twig render(partials/container-rows.twig)
 *
 * Returns a PromiseInterface<string> so callers can attach their own
 * fulfilment/rejection handlers or feed the result directly into a ReactPHP
 * response body.
 */
final class RefreshContainers
{
    public function __construct(
        private readonly DockerServiceInterface $docker,
        private readonly Environment $twig,
    ) {}

    /**
     * Execute the refresh pipeline.
     *
     * @return PromiseInterface<string> Rendered HTML fragment
     */
    public function execute(): PromiseInterface
    {
        return $this->docker->listAll()->then(
            fn(array $containers): string =>
                $this->twig->render('partials/container-rows.twig', [
                    'containers' => $containers,
                ]),
        );
    }
}
