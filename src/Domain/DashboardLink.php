<?php

declare(strict_types=1);

namespace Shipped\Backend\Domain;

/**
 * Immutable DTO representing a parsed service link from Docker container labels.
 *
 * Built by LabelParser from a Container's labels — only containers with
 * dash.enable != "false" produce a DashboardLink.
 */
final readonly class DashboardLink
{
    public function __construct(
        public string $containerId,
        public string $containerName,
        public string $status,
        public string $name,
        public ?string $url,
        public ?string $icon,
        public ?string $description,
        public string $group,
        public int $weight,
    ) {}
}
