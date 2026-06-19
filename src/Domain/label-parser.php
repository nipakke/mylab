<?php

declare(strict_types=1);

namespace Shipped\Backend\Domain;

/**
 * Pure functions for extracting DashboardLink config from Docker container labels.
 *
 * A container is only displayed if it has at least one dash.* label
 * and dash.enable is not explicitly "false".
 * Unlabeled containers are invisible — not shown at all.
 */
const DEFAULT_GROUP = 'Other';
const DEFAULT_WEIGHT = 50;

/**
 * Parse all containers, returning only those that are dash-enabled.
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

    $name = ! empty($labels['dash.name']) ? $labels['dash.name'] : $container->name;
    $url = $labels['dash.url'] ?? null;
    $icon = $labels['dash.icon'] ?? null;
    $description = $labels['dash.description'] ?? null;
    $group = $labels['dash.group'] ?? DEFAULT_GROUP;
    $weight = isset($labels['dash.weight']) ? (int) $labels['dash.weight'] : DEFAULT_WEIGHT;

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
 * Skip containers that are either explicitly disabled or have no dash labels at all.
 *
 * @param  array<string, string>  $labels
 */
function shouldSkip(array $labels): bool
{
    if (! hasDashLabels($labels)) {
        return true;
    }

    return isset($labels['dash.enable']) && $labels['dash.enable'] === 'false';
}

/**
 * Check if the labels array contains any key starting with "dash.".
 *
 * @param  array<string, string>  $labels
 */
function hasDashLabels(array $labels): bool
{
    foreach ($labels as $key => $_) {
        if (str_starts_with($key, 'dash.')) {
            return true;
        }
    }

    return false;
}
