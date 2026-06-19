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
        $indexed = [];
        foreach ($containers as $container) {
            $indexed[$container->id] = $container;
        }
        $this->containers = $indexed;
    }

    /**
     * Add or replace a single container in the store.
     */
    public function add(Container $container): void
    {
        $this->containers[$container->id] = $container;
    }

    /**
     * Remove a container by its Docker ID.
     */
    public function remove(string $id): void
    {
        unset($this->containers[$id]);
    }
}
