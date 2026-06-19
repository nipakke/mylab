<?php

declare(strict_types=1);

namespace Shipped\Backend\Job;

use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use Shipped\Backend\Service\ContainerStore;
use Shipped\Backend\Service\DockerServiceInterface;

/**
 * Periodic container collection job.
 *
 * Every 10 seconds queries the Docker API for all containers and updates
 * the in-memory ContainerStore so handlers always serve near-real-time data
 * without hitting the Docker daemon on every request.
 */
final class CollectContainers
{
  private const INTERVAL_SECONDS = 10.0;

  public function __construct(
    private readonly DockerServiceInterface $docker,
    private readonly ContainerStore $store,
  ) {
  }

  /**
   * Start the periodic collection loop.
   */
  public function start(): TimerInterface
  {
    return Loop::addPeriodicTimer(self::INTERVAL_SECONDS, function (): void {
      $this->docker->listAll()->then(
        function (array $containers): void {
          $this->store->update($containers);
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
