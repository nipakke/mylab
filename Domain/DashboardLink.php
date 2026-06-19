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
        public string $dockerStatus = '',
    ) {}

    /**
     * Return all public properties as an associative array.
     *
     * @return array{
     *     containerId: string,
     *     containerName: string,
     *     status: string,
     *     badgeClass: string,
     *     statusClass: string,
     *     name: string,
     *     url: ?string,
     *     icon: ?string,
     *     description: ?string,
     *     group: string,
     *     weight: int
     * }
     */
    public function toArray(): array
    {
        $state = ContainerState::tryFrom($this->status);

        return [
            'containerId' => $this->containerId,
            'containerName' => $this->containerName,
            'status' => $this->status,
            'badgeClass' => $state?->badgeClass() ?? 'badge-ghost',
            'statusClass' => ContainerState::resolveDotClass($this->status, $this->dockerStatus),
            'dockerStatus' => $this->dockerStatus,
            'name' => $this->name,
            'url' => $this->url,
            'icon' => $this->icon,
            'description' => $this->description,
            'group' => $this->group,
            'weight' => $this->weight,
        ];
    }
}
