<?php

declare(strict_types=1);

namespace Shipped\Backend\Service;

use React\Promise;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\ThroughStream;
use Shipped\Backend\Domain\Container;

/**
 * Aggregates multiple DockerService instances behind a single
 * DockerServiceInterface, presenting a unified view of containers
 * across independent Docker daemons.
 *
 * listAll() merges container lists from every backend, deduplicating
 * by container ID (first-wins). Individual backend failures are logged
 * and skipped — the operation succeeds as long as at least one backend
 * contributes results.
 *
 * eventsStream() pipes events from every backend into a single merged
 * stream. Errors on individual streams are logged but never close the
 * merged stream; only when every source has closed is the merged
 * stream terminated.
 */
final class MultiDockerService implements DockerServiceInterface
{
    /** @param DockerService[] $services */
    public function __construct(
        private readonly array $services,
    ) {
        if ($this->services === []) {
            throw new \InvalidArgumentException(
                'MultiDockerService requires at least one DockerService',
            );
        }
    }

    /**
     * List containers from every backend, merge, and deduplicate.
     *
     * Each service is called independently. Failures are caught per-service
     * and logged to STDERR; the failed service simply contributes an empty
     * array. React\Promise\all() waits for every promise (success or
     * recovered) before merging.
     *
     * @return PromiseInterface<Container[]>
     */
    public function listAll(): PromiseInterface
    {
        $promises = [];

        foreach ($this->services as $service) {
            $promises[] = $service->listAll()->then(
                static fn (array $containers): array => $containers,
                static function (\Throwable $e): array {
                    fwrite(STDERR, "[MultiDockerService] Service failed: {$e->getMessage()}\n");

                    return [];
                },
            );
        }

        return Promise\all($promises)->then(
            fn (array $results): array => $this->deduplicate(array_merge(...$results)),
        );
    }

    /**
     * Merge event streams from every backend into a single ThroughStream.
     *
     * Data events are forwarded transparently. Error events are logged to
     * STDERR but do NOT close the merged stream — other backends may still
     * be producing events. The merged stream is only ended when all source
     * streams have closed.
     *
     * @param  array<string, array<array-key, string>>|null  $filters
     * @return ReadableStreamInterface
     */
    public function eventsStream(
        ?float $since = null,
        ?float $until = null,
        ?array $filters = null,
    ): ReadableStreamInterface {
        $merged = new ThroughStream();
        $open = count($this->services);

        foreach ($this->services as $service) {
            $source = $service->eventsStream($since, $until, $filters);

            $source->on('data', function (mixed $data) use ($merged): void {
                $merged->write($data);
            });

            $source->on('error', static function (\Throwable $e): void {
                fwrite(STDERR, "[MultiDockerService] Event stream error: {$e->getMessage()}\n");
            });

            $source->on('close', function () use ($merged, &$open): void {
                $open--;
                if ($open <= 0) {
                    $merged->end();
                }
            });
        }

        return $merged;
    }

    /**
     * Deduplicate containers by ID, keeping the first occurrence.
     *
     * Pure function — no side effects, no I/O.
     *
     * @param  Container[]  $containers
     * @return Container[]
     */
    private function deduplicate(array $containers): array
    {
        $seen = [];

        foreach ($containers as $container) {
            if (!isset($seen[$container->id])) {
                $seen[$container->id] = $container;
            }
        }

        return array_values($seen);
    }
}
