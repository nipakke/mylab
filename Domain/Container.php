<?php

declare(strict_types=1);

namespace MyLab\Domain;

final readonly class Container
{
    /**
     * @param  array<string, string>  $labels  Docker labels as key-value pairs
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
        public array $labels = [],
        public string $dockerStatus = '',
    ) {}

    /**
     * Create a Container from a raw Docker Engine API response.
     *
     * Parses the /containers/json JSON-decoded array into a typed domain object.
     *
     * @param  array<string, mixed>  $data  Raw container array from Docker API
     */
    public static function fromDockerApiResponse(array $data): self
    {
        $name = $data['Names'][0] ?? 'unknown';
        $name = ltrim($name, '/');

        $labels = isset($data['Labels']) && is_array($data['Labels'])
            ? $data['Labels']
            : [];

        $state = trim((string) ($data['State'] ?? 'unknown'));
        $dockerStatus = (string) ($data['Status'] ?? '');

        return new self(
            id: substr($data['Id'], 0, 12),
            name: $name,
            status: $state,
            labels: $labels,
            dockerStatus: $dockerStatus,
        );
    }

    /**
     * Convert to a template-safe array for Twig rendering.
     *
     * Keeps Twig templates free of object method calls — all data is
     * pre-formatted and ready for safe HTML output.
     *
     * @return array{id: string, name: string, status: string, badgeClass: string, statusClass: string}
     */
    public function toArray(): array
    {
        $state = ContainerState::tryFrom($this->status);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'badgeClass' => $state?->badgeClass() ?? 'badge-ghost',
            'statusClass' => ContainerState::resolveDotClass($this->status, $this->dockerStatus),
        ];
    }

    /**
     * Get a specific label value, or null if not set.
     */
    public function label(string $key): ?string
    {
        return $this->labels[$key] ?? null;
    }

    /**
     * Return a new Container with the given labels, keeping all other properties.
     *
     * Immutable — the original Container is unchanged.
     *
     * @param  array<string, string>  $labels
     */
    public function withLabels(array $labels): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            status: $this->status,
            labels: $labels,
            dockerStatus: $this->dockerStatus,
        );
    }
}
