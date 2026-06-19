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
        public int $memoryUsageBytes,
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
            memoryUsageBytes: $data['SizeRw'] ?? 0,
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
     * @return array{id: string, name: string, status: string, badgeClass: string, statusClass: string, memory: string}
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
            'memory' => format_memory_bytes($this->memoryUsageBytes),
        ];
    }

    /**
     * Format memory usage as a human-readable string for Twig templates.
     *
     * Delegates to the pure function format_memory_bytes() so the formatting
     * logic is shareable and independently testable.
     */
    public function getFormattedMemory(): string
    {
        return format_memory_bytes($this->memoryUsageBytes);
    }

    /**
     * Get a specific label value, or null if not set.
     */
    public function label(string $key): ?string
    {
        return $this->labels[$key] ?? null;
    }
}

/**
 * Format a byte count into a human-readable memory string.
 *
 * Pure function — no side effects, no I/O, trivially testable.
 *
 * @param  int  $bytes  Raw byte count (e.g. 104857600)
 * @return string Human-readable string (e.g. "100 MB")
 */
function format_memory_bytes(int $bytes): string
{
    return round($bytes / 1024 / 1024, 2).' MB';
}
