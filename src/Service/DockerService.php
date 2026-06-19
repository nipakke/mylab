<?php

declare(strict_types=1);

namespace Shipped\Backend\Service;

use Clue\React\Docker\Client;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use Shipped\Backend\Domain\Container;

final class DockerService implements DockerServiceInterface
{
    private readonly Client $client;

    public function __construct(
        private readonly ?string $dsn = null,
    ) {
        $this->client = new Client(null, $this->dsn);
    }

    public function listAll(): PromiseInterface
    {
        return $this->client->containerList(true, true)->then(
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
        return $this->client->eventsStream($since, $until, $filters ?? []);
    }
}
