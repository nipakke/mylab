<?php

declare(strict_types=1);

namespace MyLab\Service;

use MyLab\Domain\Container;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;

interface DockerServiceInterface
{
    /**
     * List all containers via the Docker Engine API.
     *
     * @return PromiseInterface<Container[]> Resolves to an array of Container DTOs
     */
    public function listAll(): PromiseInterface;

    /**
     * List all Docker Swarm services via the Docker Engine API.
     *
     * Calls GET /services and returns the raw response arrays.  In standalone
     * (non-Swarm) mode this returns an empty list without erroring.
     *
     * @return PromiseInterface<list<array{ID?: string, Spec?: array{Labels?: array<string, string>}}>>
     */
    public function listServices(): PromiseInterface;

    /**
     * Stream Docker daemon events as a ReactPHP readable stream.
     *
     * Each `data` event emits a JSON-encoded Docker event object.
     * Filters can narrow by type (container, image, volume, etc.) and action.
     *
     * @param  float|null  $since  UNIX timestamp — only events after this time
     * @param  float|null  $until  UNIX timestamp — only events before this time
     * @param  array<string, array<array-key, string>>|null  $filters  e.g. ['type' => ['container'], 'event' => ['start', 'die']]
     */
    public function eventsStream(?float $since = null, ?float $until = null, ?array $filters = null): ReadableStreamInterface;
}
