<?php

declare(strict_types=1);

use MyLab\Domain\Container;

use function MyLab\Domain\enrichContainerLabelsWithServiceLabels;

/**
 * Helper to create a Container with specific labels, optionally
 * identifying it as a Swarm task via com.docker.swarm.service.id.
 *
 * @param  array<string, string>  $labels
 */
function makeSwarmContainer(
    array $labels = [],
    string $name = 'test-service',
    string $status = 'running',
    string $swarmServiceId = 'svc-abc123',
): Container {
    $labels['com.docker.swarm.service.id'] = $swarmServiceId;

    return new Container(
        id: substr(md5($name), 0, 12),
        name: $name,
        status: $status,
        memoryUsageBytes: 1048576,
        labels: $labels,
    );
}

/**
 * Helper to create a non-Swarm Container (no com.docker.swarm.service.id).
 */
function makeStandaloneContainer(string $name = 'standalone', array $labels = []): Container
{
    return new Container(
        id: substr(md5($name), 0, 12),
        name: $name,
        status: 'running',
        memoryUsageBytes: 1048576,
        labels: $labels,
    );
}

/**
 * Helper to create a raw service array as returned by GET /services.
 *
 * @param  array<string, string>  $labels
 * @return array{ID: string, Spec: array{Labels: array<string, string>}}
 */
function makeService(string $id, array $labels = []): array
{
    return [
        'ID' => $id,
        'Spec' => [
            'Labels' => $labels,
        ],
    ];
}

// ─── enrichContainerLabelsWithServiceLabels ─────────────────────────────────

test('empty services returns containers unchanged', function () {
    $containers = [makeSwarmContainer(['mylab.name' => 'App'], 'app')];

    $result = enrichContainerLabelsWithServiceLabels($containers, []);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('app')
        ->and($result[0]->labels['mylab.name'])->toBe('App');
});

test('service labels are injected into matching swarm containers', function () {
    $containers = [
        makeSwarmContainer([], 'grafana', 'running', 'svc-1'),
    ];

    $services = [
        makeService('svc-1', [
            'mylab.name' => 'Grafana',
            'mylab.url' => 'https://grafana.local',
            'mylab.group' => 'Monitoring',
        ]),
    ];

    $result = enrichContainerLabelsWithServiceLabels($containers, $services);

    expect($result)->toHaveCount(1)
        ->and($result[0]->labels['mylab.name'])->toBe('Grafana')
        ->and($result[0]->labels['mylab.url'])->toBe('https://grafana.local')
        ->and($result[0]->labels['mylab.group'])->toBe('Monitoring');
});

test('container labels override service labels', function () {
    $containers = [
        makeSwarmContainer(['mylab.name' => 'Custom Name'], 'grafana', 'running', 'svc-1'),
    ];

    $services = [
        makeService('svc-1', [
            'mylab.name' => 'Service Name',
            'mylab.url' => 'https://svc.local',
        ]),
    ];

    $result = enrichContainerLabelsWithServiceLabels($containers, $services);

    expect($result)->toHaveCount(1)
        ->and($result[0]->labels['mylab.name'])->toBe('Custom Name')   // container wins
        ->and($result[0]->labels['mylab.url'])->toBe('https://svc.local'); // service-only
});

test('non-swarm containers pass through unchanged', function () {
    $containers = [
        makeStandaloneContainer('standalone', ['mylab.name' => 'Standalone']),
    ];

    $services = [
        makeService('svc-1', ['mylab.name' => 'ShouldNotAppear']),
    ];

    $result = enrichContainerLabelsWithServiceLabels($containers, $services);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('standalone')
        ->and($result[0]->labels['mylab.name'])->toBe('Standalone');
});

test('services with mylab labels deduplicate replicas to one card', function () {
    $containers = [
        makeSwarmContainer([], 'grafana-1', 'running', 'svc-1'),
        makeSwarmContainer([], 'grafana-2', 'running', 'svc-1'),
        makeSwarmContainer([], 'grafana-3', 'running', 'svc-1'),
    ];

    $services = [
        makeService('svc-1', ['mylab.name' => 'Grafana']),
    ];

    $result = enrichContainerLabelsWithServiceLabels($containers, $services);

    // Only one container per service when service has mylab labels
    expect($result)->toHaveCount(1)
        ->and($result[0]->labels['mylab.name'])->toBe('Grafana');
});

test('services with mylab labels prefer running over exited replicas', function () {
    $containers = [
        makeSwarmContainer([], 'grafana-1', 'exited', 'svc-1'),
        makeSwarmContainer([], 'grafana-2', 'running', 'svc-1'),
    ];

    $services = [
        makeService('svc-1', ['mylab.name' => 'Grafana']),
    ];

    $result = enrichContainerLabelsWithServiceLabels($containers, $services);

    expect($result)->toHaveCount(1)
        ->and($result[0]->status)->toBe('running')
        ->and($result[0]->name)->toBe('grafana-2');
});

test('services without mylab labels keep all containers', function () {
    $containers = [
        makeSwarmContainer(['traefik.enable' => 'true'], 'traefik-1', 'running', 'svc-2'),
        makeSwarmContainer(['traefik.enable' => 'true'], 'traefik-2', 'running', 'svc-2'),
    ];

    $services = [
        makeService('svc-2', ['traefik.enable' => 'true']),
    ];

    $result = enrichContainerLabelsWithServiceLabels($containers, $services);

    // Service has no mylab labels, so all containers are kept
    expect($result)->toHaveCount(2);
});

test('swarm container with no matching service passes through', function () {
    $containers = [
        makeSwarmContainer(['mylab.name' => 'Orphan'], 'orphan', 'running', 'svc-missing'),
    ];

    $services = [
        makeService('svc-other', ['mylab.name' => 'Other']),
    ];

    $result = enrichContainerLabelsWithServiceLabels($containers, $services);

    expect($result)->toHaveCount(1)
        ->and($result[0]->labels['mylab.name'])->toBe('Orphan');
});

test('mixed swarm and standalone containers work together', function () {
    $containers = [
        makeSwarmContainer([], 'grafana', 'running', 'svc-1'),
        makeStandaloneContainer('standalone', ['mylab.name' => 'Standalone App']),
    ];

    $services = [
        makeService('svc-1', ['mylab.name' => 'Grafana']),
    ];

    $result = enrichContainerLabelsWithServiceLabels($containers, $services);

    expect($result)->toHaveCount(2);

    $names = array_map(fn (Container $c): string => $c->labels['mylab.name'] ?? '', $result);
    expect($names)->toContain('Grafana');
    expect($names)->toContain('Standalone App');
});

test('service with empty labels does not affect containers', function () {
    $containers = [
        makeSwarmContainer(['mylab.name' => 'App'], 'app', 'running', 'svc-1'),
    ];

    $services = [
        makeService('svc-1', []),  // empty labels
    ];

    $result = enrichContainerLabelsWithServiceLabels($containers, $services);

    expect($result)->toHaveCount(1)
        ->and($result[0]->labels['mylab.name'])->toBe('App');
});

test('service with mylab labels but only exited replicas deduplicates to one', function () {
    $containers = [
        makeSwarmContainer([], 'grafana-1', 'exited', 'svc-1'),
        makeSwarmContainer([], 'grafana-2', 'exited', 'svc-1'),
    ];

    $services = [
        makeService('svc-1', ['mylab.name' => 'Grafana']),
    ];

    $result = enrichContainerLabelsWithServiceLabels($containers, $services);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('grafana-1');  // first one kept
});

test('immutability: original containers are not modified', function () {
    $originalLabels = ['com.docker.swarm.service.id' => 'svc-1'];
    $containers = [
        new Container(
            id: 'abc123',
            name: 'app',
            status: 'running',
            memoryUsageBytes: 1048576,
            labels: $originalLabels,
        ),
    ];

    $services = [
        makeService('svc-1', ['mylab.name' => 'Grafana']),
    ];

    $result = enrichContainerLabelsWithServiceLabels($containers, $services);

    // Original container labels unchanged
    expect($containers[0]->labels)->toBe($originalLabels);

    // Result container has merged labels
    expect($result[0]->labels['mylab.name'])->toBe('Grafana');
    expect($result[0]->labels['com.docker.swarm.service.id'])->toBe('svc-1');
});
