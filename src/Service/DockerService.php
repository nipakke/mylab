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
                Container::fromDockerApiResponse(...),
                $rawContainers,
            ),
            function (\Throwable $error): array {
                fwrite(STDERR, "[DockerService] listAll failed: {$error->getMessage()}\n");

                return [];
            },
        );
    }

    /**
     * @param  array<string, array<array-key, string>>|null  $filters
     */
    public function eventsStream(?float $since = null, ?float $until = null, ?array $filters = null): ReadableStreamInterface
    {
        $client = $this->client ?? new Client;

        return $client->eventsStream($since, $until, $filters ?? []);
    }
}
