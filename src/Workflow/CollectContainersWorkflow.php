<?php
declare(strict_types=1);

namespace Shipped\Backend\Workflow;

use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use Shipped\Backend\Service\ContainerSnapshotStore;
use Shipped\Backend\Service\DockerServiceInterface;

/**
 * Periodic container collection workflow.
 *
 * Every 10 seconds, queries the Docker API for all containers and records
 * the results into the in-memory ContainerSnapshotStore.
 *
 * Follows the same ReactPHP periodic-timer pattern as StreamTelemetryWorkflow.
 */
final class CollectContainersWorkflow
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
                        "[CollectContainersWorkflow] Collection failed: {$error->getMessage()}\n",
                    );
                },
            );
        });
    }
}
