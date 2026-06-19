<?php

declare(strict_types=1);

namespace Shipped\Backend\Action;

use React\Promise\PromiseInterface;
use Shipped\Backend\Domain\DashboardLink;
use Shipped\Backend\Service\ContainerStoreInterface;
use Shipped\Backend\Service\DockerServiceInterface;

/**
 * Fetches fresh container data from Docker, updates the store
 * (which rebuilds the link cache), and returns the grouped link data.
 */
final class RefreshContainers
{
    public function __construct(
        private readonly DockerServiceInterface $docker,
        private readonly ContainerStoreInterface $store,
    ) {}

    /**
     * Execute the refresh pipeline.
     *
     * @return PromiseInterface<array<string, DashboardLink[]>> Grouped link data keyed by group name
     */
    public function execute(): PromiseInterface
    {
        return $this->docker->listAll()->then(
            function (array $containers): array {
                $this->store->replace($containers);

                return $this->store->getGroupedLinks(0.0);
            },
        );
    }
}
