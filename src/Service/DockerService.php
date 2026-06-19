<?php

declare(strict_types=1);

namespace Shipped\Backend\Service;

use Clue\React\Docker\Client;
use React\Promise\PromiseInterface;
use Shipped\Backend\Domain\Container;
use Shipped\Backend\Domain\TelemetrySnapshot;

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

    public function getTelemetry(): TelemetrySnapshot
    {
        // Placeholder — real telemetry collection to be added later.
        // Currently matches the SSE payload format consumed by home.twig frontend.
        $load = sys_getloadavg();
        $cpuPercent = round($load[0] * 10, 1);

        return new TelemetrySnapshot(
            time: date('H:i:s'),
            cpu: $cpuPercent.'%',
            memory: $this->getMemoryUsagePercent(),
        );
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

    private function getMemoryUsagePercent(): string
    {
        if (! is_readable('/proc/meminfo')) {
            return 'N/A';
        }

        $meminfo = file_get_contents('/proc/meminfo');
        if ($meminfo === false) {
            return 'N/A';
        }

        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);

        if (! $total || ! $available) {
            return 'N/A';
        }

        $used = (int) $total[1] - (int) $available[1];
        $percent = round(($used / (int) $total[1]) * 100, 1);

        return $percent.'%';
    }
}
