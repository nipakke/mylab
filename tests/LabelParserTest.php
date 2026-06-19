<?php

declare(strict_types=1);

use Shipped\Backend\Domain\Container;
use Shipped\Backend\Domain\DashboardLink;

use function Shipped\Backend\Domain\groupAndSort;
use function Shipped\Backend\Domain\hasDashLabels;
use function Shipped\Backend\Domain\parseLabel;
use function Shipped\Backend\Domain\parseLabels;

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

test('container with no dash labels returns null', function () {
    $link = parseLabel(makeContainer([]));

    expect($link)->toBeNull();
});

test('container with a dash label is enabled by default', function () {
    $link = parseLabel(makeContainer(['dash.name' => 'My App']));

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

test('dash.enable=false returns null', function () {
    $link = parseLabel(makeContainer(['dash.enable' => 'false']));

    expect($link)->toBeNull();
});

test('dash.enable=true is enabled', function () {
    $link = parseLabel(makeContainer(['dash.enable' => 'true']));

    expect($link)->not->toBeNull();
});

test('dash.enable with any value other than false is enabled', function () {
    $link = parseLabel(makeContainer(['dash.enable' => '1']));

    expect($link)->not->toBeNull();
});

test('parses all optional label values', function () {
    $link = parseLabel(makeContainer([
        'dash.name' => 'Uptime Kuma',
        'dash.url' => 'https://status.local',
        'dash.icon' => 'uptimekuma',
        'dash.group' => 'Monitoring',
        'dash.description' => 'Status page for all services',
        'dash.weight' => '10',
    ]));

    expect($link->name)->toBe('Uptime Kuma')
        ->and($link->url)->toBe('https://status.local')
        ->and($link->icon)->toBe('uptimekuma')
        ->and($link->group)->toBe('Monitoring')
        ->and($link->description)->toBe('Status page for all services')
        ->and($link->weight)->toBe(10);
});

test('name falls back to container name when dash.name is empty', function () {
    $link = parseLabel(makeContainer(['dash.name' => ''], 'nginx-proxy'));

    expect($link->name)->toBe('nginx-proxy');
});

test('empty url, icon, description become null', function () {
    $link = parseLabel(makeContainer([
        'dash.url' => '',
        'dash.icon' => '',
        'dash.description' => '',
    ]));

    expect($link->url)->toBeNull()
        ->and($link->icon)->toBeNull()
        ->and($link->description)->toBeNull();
});

test('empty group falls back to Other', function () {
    $link = parseLabel(makeContainer(['dash.group' => '']));

    expect($link->group)->toBe('Other');
});

test('captures container status correctly', function () {
    $running = parseLabel(makeContainer(['dash.name' => 'app'], 'app', 'running'));
    $exited = parseLabel(makeContainer(['dash.name' => 'app'], 'app', 'exited'));

    expect($running->status)->toBe('running')
        ->and($exited->status)->toBe('exited');
});

// ─── parseLabels ─────────────────────────────────────────────────────────

test('parseLabels filters disabled containers', function () {
    $containers = [
        makeContainer(['dash.name' => 'App A']),
        makeContainer(['dash.enable' => 'false', 'dash.name' => 'App B']),
        makeContainer(['dash.name' => 'App C']),
    ];

    $links = parseLabels($containers);

    expect($links)->toHaveCount(2)
        ->and($links[0]->name)->toBe('App A')
        ->and($links[1]->name)->toBe('App C');
});

test('parseLabels filters out containers with no dash labels', function () {
    $containers = [
        makeContainer(['dash.name' => 'App A']),
        makeContainer([]),                       // no dash labels at all
        makeContainer(['some.other.label' => 'x']), // non-dash labels
        makeContainer(['dash.url' => 'https://b.local', 'dash.name' => 'App B']),
    ];

    $links = parseLabels($containers);

    expect($links)->toHaveCount(2)
        ->and($links[0]->name)->toBe('App A')
        ->and($links[1]->name)->toBe('App B');
});

test('parseLabels returns empty array when all disabled', function () {
    $containers = [
        makeContainer(['dash.enable' => 'false']),
        makeContainer(['dash.enable' => 'false']),
    ];

    $links = parseLabels($containers);

    expect($links)->toBeEmpty();
});

test('parseLabels returns empty array when no containers have dash labels', function () {
    $containers = [
        makeContainer([]),
        makeContainer(['traefik.enable' => 'true']),
    ];

    $links = parseLabels($containers);

    expect($links)->toBeEmpty();
});

// ─── hasDashLabels ───────────────────────────────────────────────────────

test('hasDashLabels returns true when dash labels exist', function () {
    expect(hasDashLabels(['dash.name' => 'X']))->toBeTrue();
    expect(hasDashLabels(['other.label' => 'x', 'dash.url' => 'y']))->toBeTrue();
});

test('hasDashLabels returns false when no dash labels', function () {
    expect(hasDashLabels([]))->toBeFalse();
    expect(hasDashLabels(['traefik.enable' => 'true']))->toBeFalse();
    expect(hasDashLabels(['com.docker.compose.project' => 'foo']))->toBeFalse();
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
