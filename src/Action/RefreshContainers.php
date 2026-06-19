<?php

declare(strict_types=1);

namespace Shipped\Backend\Action;

use React\Promise\PromiseInterface;
use Shipped\Backend\Service\ContainerStore;
use Shipped\Backend\Service\DockerServiceInterface;
use Twig\Environment;

/**
 * Fetches fresh container data from Docker, updates the store
 * (which rebuilds the link cache), and renders the HTMX partial.
 */
final class RefreshContainers
{
    public function __construct(
        private readonly DockerServiceInterface $docker,
        private readonly ContainerStore $store,
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
            function (array $containers): string {
                $this->store->update($containers);

                return $this->twig->render('partials/link-cards.twig', [
                    'groups' => $this->store->getGroupedLinks(),
                ]);
            },
        );
    }
}
