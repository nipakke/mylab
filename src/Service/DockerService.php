<?php

declare(strict_types=1);

namespace Shipped\Backend\Service;

use Clue\React\Docker\Client;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use Shipped\Backend\Domain\Container;

final class DockerService implements DockerServiceInterface
{
    public function __construct(
        private readonly ?Client $client = null,
    ) {}

    public function listAll(): PromiseInterface
    {
        $client = $this->client ?? new Client;

        return $client->containerList(true, true)->then(
            fn (array $rawContainers): array => array_map(
                $this->hydrateContainer(...),
                $rawContainers,
            ),
            function (\Throwable $error): array {
                fwrite(STDERR, "[DockerService] listAll failed: {$error->getMessage()}\n");

                return [];
            },
        );
    }

    public function eventsStream(?float $since = null, ?float $until = null, ?array $filters = null): ReadableStreamInterface
    {
        $client = $this->client ?? new Client;

        return $client->eventsStream($since, $until, $filters ?? []);
    }

    /**
     * Map a raw Docker API container array to a Container domain object.
     *
     * @param  array<string, mixed>  $raw
     */
    private function hydrateContainer(array $raw): Container
    {
        $name = $raw['Names'][0] ?? 'unknown';
        $name = ltrim($name, '/');

        $labels = isset($raw['Labels']) && is_array($raw['Labels'])
            ? $raw['Labels']
            : [];

        return new Container(
            id: substr($raw['Id'], 0, 12),
            name: $name,
            status: $raw['State'] ?? 'unknown',
            memoryUsageBytes: $raw['SizeRw'] ?? 0,
            labels: $labels,
        );
    }
}
