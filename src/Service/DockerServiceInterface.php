<?php

declare(strict_types=1);

namespace Shipped\Backend\Service;

use React\Promise\PromiseInterface;
use Shipped\Backend\Domain\Container;
use Shipped\Backend\Domain\TelemetrySnapshot;

interface DockerServiceInterface
{
    /**
     * List all containers via the Docker Engine API.
     *
     * @return PromiseInterface<Container[]> Resolves to an array of Container DTOs
     */
    public function listAll(): PromiseInterface;

    /**
     * Collect current system telemetry snapshot.
     */
    public function getTelemetry(): TelemetrySnapshot;
}
