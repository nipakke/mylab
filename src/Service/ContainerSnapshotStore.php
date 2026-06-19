<?php
declare(strict_types=1);

namespace Shipped\Backend\Service;

use Shipped\Backend\Domain\Container;

/**
 * In-memory store for periodic container collection snapshots.
 *
 * Holds the latest snapshot plus a bounded history buffer (last 60 snapshots
 * = 10 minutes of history at the default 10-second collection interval).
 */
final class ContainerSnapshotStore
{
    /** @var array<int, array{timestamp: float, containers: Container[]}> */
    private array $snapshots = [];

    private const MAX_HISTORY = 60;

    /**
     * Record a new collection snapshot.
     *
     * @param Container[] $containers
     */
    public function record(array $containers): void
    {
        $this->snapshots[] = [
            'timestamp'  => microtime(true),
            'containers' => $containers,
        ];

        if (\count($this->snapshots) > self::MAX_HISTORY) {
            array_shift($this->snapshots);
        }
    }

    /**
     * Return the most recent snapshot, or null if none recorded yet.
     *
     * @return array{timestamp: float, containers: Container[]}|null
     */
    public function latest(): ?array
    {
        if (\count($this->snapshots) === 0) {
            return null;
        }

        return $this->snapshots[array_key_last($this->snapshots)];
    }

    /**
     * Return the full history buffer (up to MAX_HISTORY snapshots).
     *
     * @return array<int, array{timestamp: float, containers: Container[]}>
     */
    public function history(): array
    {
        return $this->snapshots;
    }

    /**
     * Return only containers that are currently "running" from the latest snapshot.
     *
     * @return Container[]
     */
    public function runningContainers(): array
    {
        $latest = $this->latest();
        if ($latest === null) {
            return [];
        }

        return array_values(
            array_filter(
                $latest['containers'],
                static fn(Container $c): bool => $c->status === 'running',
            ),
        );
    }
}
