<?php

declare(strict_types=1);

namespace Shipped\Backend\Service;

use Shipped\Backend\Domain\Container;
use Shipped\Backend\Domain\DashboardLink;

use function Shipped\Backend\Domain\groupAndSort;
use function Shipped\Backend\Domain\parseLabels;

/**
 * In-memory store holding the latest container list and pre-parsed link cache.
 *
 * Labels are parsed once when containers change (update/add/remove) and
 * served from cache on every request — zero parsing overhead per page load.
 */
final class ContainerStore
{
    /** @var Container[] */
    private array $containers = [];

    /** @var DashboardLink[] */
    private array $links = [];

    /** @var array<string, DashboardLink[]> */
    private array $groupedLinks = [];

    public function getAll(): array
    {
        return $this->containers;
    }

    /**
     * @return DashboardLink[]
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * @return array<string, DashboardLink[]>
     */
    public function getGroupedLinks(): array
    {
        return $this->groupedLinks;
    }

    /**
     * Replace the stored container list with a fresh snapshot
     * and rebuild the link cache.
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
        $this->rebuildCache();
    }

    /**
     * Add or replace a single container and rebuild the link cache.
     */
    public function add(Container $container): void
    {
        $this->containers[$container->id] = $container;
        $this->rebuildCache();
    }

    /**
     * Remove a container by its Docker ID and rebuild the link cache.
     */
    public function remove(string $id): void
    {
        unset($this->containers[$id]);
        $this->rebuildCache();
    }

    private function rebuildCache(): void
    {
        $this->links = parseLabels($this->containers);
        $this->groupedLinks = groupAndSort($this->links);
    }
}
