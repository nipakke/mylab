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
 * Labels are parsed once when containers change (replace/remove) and
 * served from cache on every request — zero parsing overhead per page load.
 */
final class ContainerStore implements ContainerStoreInterface
{
    /** @var array<string, Container> */
    private array $containers = [];

    /** @var DashboardLink[] */
    private array $links = [];

    /** @var array<string, DashboardLink[]> */
    private array $groupedLinks = [];

    /**
     * @return Container[]
     */
    public function all(): array
    {
        return array_values($this->containers);
    }

    public function find(string $id): ?Container
    {
        return $this->containers[$id] ?? null;
    }

    public function count(): int
    {
        return count($this->containers);
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
    public function getGroupedLinks(float $memoryThreshold): array
    {
        if ($memoryThreshold <= 0.0) {
            return $this->groupedLinks;
        }

        $thresholdBytes = (int) ($memoryThreshold * 1024 * 1024);

        $filtered = [];
        foreach ($this->groupedLinks as $group => $links) {
            $passing = array_filter(
                $links,
                function (DashboardLink $link) use ($thresholdBytes): bool {
                    $container = $this->containers[$link->containerId] ?? null;

                    return $container !== null && $container->memoryUsageBytes >= $thresholdBytes;
                },
            );

            if ($passing !== []) {
                $filtered[$group] = array_values($passing);
            }
        }

        return $filtered;
    }

    /**
     * Replace the stored container list with a fresh snapshot
     * and rebuild the link cache.
     *
     * @param Container[] $containers
     */
    public function replace(array $containers): void
    {
        $indexed = [];
        foreach ($containers as $container) {
            $indexed[$container->id] = $container;
        }
        $this->containers = $indexed;
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
