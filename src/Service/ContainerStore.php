<?php

declare(strict_types=1);

namespace Shipped\Backend\Service;

use Shipped\Backend\Domain\Container;

/**
 * In-memory store holding the latest container list.
 *
 * Populated by the periodic CollectContainers background job and
 * updated on-demand by RefreshContainers when the user requests a
 * manual refresh.  Handlers read from this store instead of hitting
 * the Docker API directly — no snapshot history, just the latest state.
 */
final class ContainerStore
{
    /** @var Container[] */
    private array $containers = [];

    /**
     * Return the latest container list.
     *
     * @return Container[]
     */
    public function getAll(): array
    {
        return $this->containers;
    }

    /**
     * Replace the stored container list with a fresh snapshot.
     *
     * @param  Container[]  $containers
     */
    public function update(array $containers): void
    {
        $this->containers = $containers;
    }
}
