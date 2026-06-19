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
