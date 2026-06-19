<?php

declare(strict_types=1);

use MyLab\Domain\Container;
use MyLab\Domain\DashboardLink;

use function MyLab\Domain\groupAndSort;
use function MyLab\Domain\hasMyLabLabels;
use function MyLab\Domain\parseLabel;
use function MyLab\Domain\parseLabels;

/**
 * Helper to create a Container with specific labels.
 *
 * @param  array<string, string>  $labels
 */
function makeContainer(array $labels = [], string $name = 'test-service', string $status = 'running'): Container
{
    return new Container(
        id: 'abc123',
        name: $name,
        status: $status,
        memoryUsageBytes: 1048576,
        labels: $labels,
    );
}

// ─── parseLabel ──────────────────────────────────────────────────────────

test('container with no mylab labels returns null', function () {
    $link = parseLabel(makeContainer([]));

    expect($link)->toBeNull();
});

test('container with a mylab label is enabled by default', function () {
    $link = parseLabel(makeContainer(['mylab.name' => 'My App']));

    expect($link)->toBeInstanceOf(DashboardLink::class)
        ->and($link->name)->toBe('My App')
        ->and($link->group)->toBe('Other')
        ->and($link->weight)->toBe(50)
        ->and($link->url)->toBeNull()
        ->and($link->icon)->toBeNull()
        ->and($link->description)->toBeNull()
        ->and($link->status)->toBe('running')
        ->and($link->containerId)->toBe('abc123')
        ->and($link->containerName)->toBe('test-service');
});

test('mylab.enable=false returns null', function () {
    $link = parseLabel(makeContainer(['mylab.enable' => 'false']));

    expect($link)->toBeNull();
});

test('mylab.enable=true is enabled', function () {
    $link = parseLabel(makeContainer(['mylab.enable' => 'true']));

    expect($link)->not->toBeNull();
});

test('mylab.enable with any value other than false is enabled', function () {
    $link = parseLabel(makeContainer(['mylab.enable' => '1']));

    expect($link)->not->toBeNull();
});

test('parses all optional label values', function () {
    $link = parseLabel(makeContainer([
        'mylab.name' => 'Uptime Kuma',
        'mylab.url' => 'https://status.local',
        'mylab.icon' => 'uptimekuma',
        'mylab.group' => 'Monitoring',
        'mylab.description' => 'Status page for all services',
        'mylab.weight' => '10',
    ]));

    expect($link->name)->toBe('Uptime Kuma')
        ->and($link->url)->toBe('https://status.local')
        ->and($link->icon)->toBe('uptimekuma')
        ->and($link->group)->toBe('Monitoring')
        ->and($link->description)->toBe('Status page for all services')
        ->and($link->weight)->toBe(10);
});

test('name falls back to container name when mylab.name is empty', function () {
    $link = parseLabel(makeContainer(['mylab.name' => ''], 'nginx-proxy'));

    expect($link->name)->toBe('nginx-proxy');
});

test('empty url, icon, description become null', function () {
    $link = parseLabel(makeContainer([
        'mylab.url' => '',
        'mylab.icon' => '',
        'mylab.description' => '',
    ]));

    expect($link->url)->toBeNull()
        ->and($link->icon)->toBeNull()
        ->and($link->description)->toBeNull();
});

test('empty group falls back to Other', function () {
    $link = parseLabel(makeContainer(['mylab.group' => '']));

    expect($link->group)->toBe('Other');
});

test('captures container status correctly', function () {
    $running = parseLabel(makeContainer(['mylab.name' => 'app'], 'app', 'running'));
    $exited = parseLabel(makeContainer(['mylab.name' => 'app'], 'app', 'exited'));

    expect($running->status)->toBe('running')
        ->and($exited->status)->toBe('exited');
});

// ─── parseLabels ─────────────────────────────────────────────────────────

test('parseLabels filters disabled containers', function () {
    $containers = [
        makeContainer(['mylab.name' => 'App A']),
        makeContainer(['mylab.enable' => 'false', 'mylab.name' => 'App B']),
        makeContainer(['mylab.name' => 'App C']),
    ];

    $links = parseLabels($containers);

    expect($links)->toHaveCount(2)
        ->and($links[0]->name)->toBe('App A')
        ->and($links[1]->name)->toBe('App C');
});

test('parseLabels filters out containers with no mylab labels', function () {
    $containers = [
        makeContainer(['mylab.name' => 'App A']),
        makeContainer([]),                       // no mylab labels at all
        makeContainer(['some.other.label' => 'x']), // non-mylab labels
        makeContainer(['mylab.url' => 'https://b.local', 'mylab.name' => 'App B']),
    ];

    $links = parseLabels($containers);

    expect($links)->toHaveCount(2)
        ->and($links[0]->name)->toBe('App A')
        ->and($links[1]->name)->toBe('App B');
});

test('parseLabels returns empty array when all disabled', function () {
    $containers = [
        makeContainer(['mylab.enable' => 'false']),
        makeContainer(['mylab.enable' => 'false']),
    ];

    $links = parseLabels($containers);

    expect($links)->toBeEmpty();
});

test('parseLabels returns empty array when no containers have mylab labels', function () {
    $containers = [
        makeContainer([]),
        makeContainer(['traefik.enable' => 'true']),
    ];

    $links = parseLabels($containers);

    expect($links)->toBeEmpty();
});

// ─── hasMyLabLabels ──────────────────────────────────────────────────────

test('hasMyLabLabels returns true when mylab labels exist', function () {
    expect(hasMyLabLabels(['mylab.name' => 'X']))->toBeTrue();
    expect(hasMyLabLabels(['other.label' => 'x', 'mylab.url' => 'y']))->toBeTrue();
});

test('hasMyLabLabels returns false when no mylab labels', function () {
    expect(hasMyLabLabels([]))->toBeFalse();
    expect(hasMyLabLabels(['traefik.enable' => 'true']))->toBeFalse();
    expect(hasMyLabLabels(['com.docker.compose.project' => 'foo']))->toBeFalse();
});

// ─── groupAndSort ────────────────────────────────────────────────────────

test('groupAndSort groups by group name alphabetically', function () {
    $links = [
        new DashboardLink('1', 'c', 'running', 'C', null, null, null, 'Monitoring', 50),
        new DashboardLink('2', 'a', 'running', 'A', null, null, null, 'Analytics', 50),
        new DashboardLink('3', 'b', 'running', 'B', null, null, null, 'Other', 50),
    ];

    $groups = groupAndSort($links);

    expect(array_keys($groups))->toBe(['Analytics', 'Monitoring', 'Other']);
});

test('groupAndSort sorts by weight within groups ascending', function () {
    $links = [
        new DashboardLink('1', 'c', 'running', 'C', null, null, null, 'Media', 30),
        new DashboardLink('2', 'a', 'running', 'A', null, null, null, 'Media', 10),
        new DashboardLink('3', 'b', 'running', 'B', null, null, null, 'Media', 20),
    ];

    $groups = groupAndSort($links);

    expect($groups['Media'])->toHaveCount(3)
        ->and($groups['Media'][0]->name)->toBe('A')
        ->and($groups['Media'][1]->name)->toBe('B')
        ->and($groups['Media'][2]->name)->toBe('C');
});

test('groupAndSort handles empty array', function () {
    $groups = groupAndSort([]);

    expect($groups)->toBeEmpty();
});
