<?php

declare(strict_types=1);

namespace MyLab\Service;

use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use MyLab\Domain\Container;

interface DockerServiceInterface
{
    /**
     * List all containers via the Docker Engine API.
     *
     * @return PromiseInterface<Container[]> Resolves to an array of Container DTOs
     */
    public function listAll(): PromiseInterface;

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
