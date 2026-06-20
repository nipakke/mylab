<?php

declare(strict_types=1);

namespace MyLab\Service;

use Clue\React\Docker\Client;
use MyLab\Domain\Container;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use React\Socket\FixedUriConnector;
use React\Socket\UnixConnector;
use React\Stream\ReadableStreamInterface;

final class DockerService implements DockerServiceInterface
{
    private readonly Client $client;

    private readonly Browser $browser;

    public function __construct(
        private readonly ?string $dsn = null,
    ) {
        $this->client = new Client(null, $this->dsn);

        // Build a standalone Browser for API calls not covered by clue/docker-react
        // (e.g. /services for Docker Swarm).  Replicates the Client's connector logic.
        $url = $this->dsn ?? 'unix:///var/run/docker.sock';
        $connector = null;

        if (str_starts_with($url, 'unix://')) {
            $connector = new FixedUriConnector($url, new UnixConnector);
            $url = 'http://localhost/';
        }

        $this->browser = (new Browser($connector))->withBase($url);
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
     * @return PromiseInterface<list<array{ID?: string, Spec?: array{Labels?: array<string, string>}}>>
     */
    public function listServices(): PromiseInterface
    {
        return $this->browser->get(
            'services',
            ['Accept' => 'application/json'],
        )->then(
            static function (ResponseInterface $response): array {
                $body = (string) $response->getBody();
                $data = json_decode($body, true);

                /** @var list<array{ID?: string, Spec?: array{Labels?: array<string, string>}}> */
                return is_array($data) ? $data : [];
            },
            static function (\Throwable $error): array {
                fwrite(STDERR, "[DockerService] listServices failed: {$error->getMessage()}\n");

                /** @var list<array{ID?: string, Spec?: array{Labels?: array<string, string>}}> */
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
