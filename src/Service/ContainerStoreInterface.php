<?php

declare(strict_types=1);

namespace Shipped\Backend\Service;

use Shipped\Backend\Domain\Container;
use Shipped\Backend\Domain\DashboardLink;

interface ContainerStoreInterface
{
    /**
     * Return all stored containers.
     *
     * @return Container[]
     */
    public function all(): array;

    /**
     * Find a single container by its Docker ID.
     */
    public function find(string $id): ?Container;

    /**
     * Replace the entire container list with a fresh snapshot.
     *
     * Rebuilds the link cache after replacement.
     *
     * @param Container[] $containers
     */
    public function replace(array $containers): void;

    /**
     * Remove a container by its Docker ID.
     *
     * Rebuilds the link cache after removal.
     */
    public function remove(string $id): void;

    /**
     * Return the number of stored containers.
     */
    public function count(): int;

    /**
     * Return all parsed dashboard links.
     *
     * @return DashboardLink[]
     */
    public function getLinks(): array;

    /**
     * Return dashboard links grouped by group name, filtered by memory threshold.
     *
     * Only links whose associated container uses at least $memoryThreshold
     * megabytes of memory are included. Pass 0.0 to include all links.
     *
     * @return array<string, DashboardLink[]>
     */
    public function getGroupedLinks(float $memoryThreshold): array;
}
