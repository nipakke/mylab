<?php

declare(strict_types=1);

namespace MyLab\Domain;

/**
 * Pure functions for extracting DashboardLink config from Docker container labels.
 *
 * A container is only displayed if it has at least one mylab.* label
 * and mylab.enable is not explicitly "false".
 * Unlabeled containers are invisible — not shown at all.
 */
const DEFAULT_GROUP = 'Other';
const DEFAULT_WEIGHT = 50;

/**
 * Parse all containers, returning only those that are mylab-enabled.
 *
 * @param  Container[]  $containers
 * @return DashboardLink[]
 */
function parseLabels(array $containers): array
{
    return array_values(array_filter(
        array_map(__NAMESPACE__.'\parseLabel', $containers),
    ));
}

/**
 * Parse a single container into a DashboardLink, or null if not enabled.
 */
function parseLabel(Container $container): ?DashboardLink
{
    $labels = $container->labels;

    if (shouldSkip($labels)) {
        return null;
    }

    $name = ! empty($labels['mylab.name']) ? $labels['mylab.name'] : $container->name;
    $url = $labels['mylab.url'] ?? null;
    $icon = $labels['mylab.icon'] ?? null;
    $description = $labels['mylab.description'] ?? null;
    $group = $labels['mylab.group'] ?? DEFAULT_GROUP;
    $weight = isset($labels['mylab.weight']) ? (int) $labels['mylab.weight'] : DEFAULT_WEIGHT;

    return new DashboardLink(
        containerId: $container->id,
        containerName: $container->name,
        status: $container->status,
        name: $name,
        url: $url !== null && $url !== '' ? $url : null,
        icon: $icon !== null && $icon !== '' ? $icon : null,
        description: $description !== null && $description !== '' ? $description : null,
        group: $group !== '' ? $group : DEFAULT_GROUP,
        weight: $weight,
        dockerStatus: $container->dockerStatus,
    );
}

/**
 * Group links by group name, sorted alphabetically by group,
 * then by weight within each group (lower = first).
 *
 * @param  DashboardLink[]  $links
 * @return array<string, DashboardLink[]>
 */
function groupAndSort(array $links): array
{
    $grouped = [];
    foreach ($links as $link) {
        $grouped[$link->group][] = $link;
    }

    ksort($grouped, SORT_NATURAL);

    foreach ($grouped as &$groupLinks) {
        usort($groupLinks, static fn (DashboardLink $a, DashboardLink $b): int => $a->weight <=> $b->weight);
    }
    unset($groupLinks);

    return $grouped;
}

/**
 * Skip containers that are either explicitly disabled or have no mylab labels at all.
 *
 * @param  array<string, string>  $labels
 */
function shouldSkip(array $labels): bool
{
    if (! hasMyLabLabels($labels)) {
        return true;
    }

    return isset($labels['mylab.enable']) && $labels['mylab.enable'] === 'false';
}

/**
 * Check if the labels array contains any key starting with "mylab.".
 *
 * @param  array<string, string>  $labels
 */
function hasMyLabLabels(array $labels): bool
{
    foreach ($labels as $key => $_) {
        if (str_starts_with($key, 'mylab.')) {
            return true;
        }
    }

    return false;
}

/**
 * Enrich containers with service-level labels from Docker Swarm services.
 *
 * In Swarm mode, `deploy.labels` are set on the service object, not on
 * individual containers.  This function merges those service labels into
 * matching containers (matched via the `com.docker.swarm.service.id`
 * label) so the existing parseLabels() pipeline picks them up unchanged.
 *
 * Container-level labels take priority over service-level labels when both
 * define the same key.
 *
 * For services that carry `mylab.*` labels, only one container per service
 * is kept (preferring running containers) to avoid duplicate cards from
 * multi-replica services.  Containers belonging to services without
 * `mylab.*` labels are passed through as-is.
 *
 * Pure function — no side effects, no I/O.
 *
 * @param  Container[]  $containers
 * @param  list<array{ID?: string, Spec?: array{Labels?: array<string, string>}}>  $services  Raw /services API response
 * @return Container[]
 */
function enrichContainerLabelsWithServiceLabels(array $containers, array $services): array
{
    // ── Build service-id → labels map ──────────────────────────────
    $serviceLabels = [];
    $servicesWithMyLab = [];

    foreach ($services as $service) {
        $id = $service['ID'] ?? null;
        $labels = $service['Spec']['Labels'] ?? [];

        if ($id === null || $labels === []) {
            continue;
        }

        $serviceLabels[$id] = $labels;

        if (hasMyLabLabels($labels)) {
            $servicesWithMyLab[$id] = true;
        }
    }

    // Fast path — no swarm services with labels at all
    if ($serviceLabels === []) {
        return $containers;
    }

    // ── Enrich and deduplicate in a single pass ────────────────────
    $bestPerService = [];
    $result = [];

    foreach ($containers as $container) {
        $swarmServiceId = $container->labels['com.docker.swarm.service.id'] ?? null;

        // Not a swarm container, or service has no labels — pass through
        if ($swarmServiceId === null || ! isset($serviceLabels[$swarmServiceId])) {
            $result[] = $container;
            continue;
        }

        // Merge service labels under container labels
        $merged = array_merge($serviceLabels[$swarmServiceId], $container->labels);
        $container = $merged !== $container->labels
            ? $container->withLabels($merged)
            : $container;

        // Service without mylab labels — keep every container
        if (! isset($servicesWithMyLab[$swarmServiceId])) {
            $result[] = $container;
            continue;
        }

        // Service HAS mylab labels — deduplicate, keep best (prefer running)
        if (! isset($bestPerService[$swarmServiceId])) {
            $bestPerService[$swarmServiceId] = $container;
        } elseif (
            $container->status === 'running'
            && $bestPerService[$swarmServiceId]->status !== 'running'
        ) {
            $bestPerService[$swarmServiceId] = $container;
        }
    }

    // Append the best container for each deduplicated service
    foreach ($bestPerService as $container) {
        $result[] = $container;
    }

    return $result;
}
