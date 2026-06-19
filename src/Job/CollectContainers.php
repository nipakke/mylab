<?php
declare(strict_types=1);

namespace Shipped\Backend\Job;

use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use Shipped\Backend\Service\ContainerSnapshotStore;
use Shipped\Backend\Service\DockerServiceInterface;

/**
 * Periodic container collection job.
 *
 * Every 10 seconds, queries the Docker API for all containers and records
 * the results into the in-memory ContainerSnapshotStore.
 */
final class CollectContainers
{
    private const INTERVAL_SECONDS = 10.0;

    public function __construct(
        private readonly DockerServiceInterface $docker,
        private readonly ContainerSnapshotStore $store,
    ) {}

    /**
     * Start the periodic collection loop.
     *
     * The returned TimerInterface can be cancelled via Loop::cancelTimer()
     * to stop collection at any time.
     */
    public function start(): TimerInterface
    {
        return Loop::addPeriodicTimer(self::INTERVAL_SECONDS, function (): void {
            $this->docker->listAll()->then(
                function (array $containers): void {
                    $this->store->record($containers);
                },
                function (\Throwable $error): void {
                    fwrite(
                        STDERR,
                        "[CollectContainers] Collection failed: {$error->getMessage()}\n",
                    );
                },
            );
        });
    }
}
