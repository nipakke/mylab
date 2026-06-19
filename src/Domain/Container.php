<?php
declare(strict_types=1);

namespace Shipped\Backend\Domain;

class Container
{
    /**
     * @param array<string, string> $labels Docker labels as key-value pairs
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $status,
        public readonly int $memoryUsageBytes,
        public readonly array $labels = [],
    ) {}

    /**
     * Helper method to present formatted data inside hypermedia templates
     */
    public function getFormattedMemory(): string
    {
        return round($this->memoryUsageBytes / 1024 / 1024, 2) . ' MB';
    }

    /**
     * Get a specific label value, or null if not set.
     */
    public function label(string $key): ?string
    {
        return $this->labels[$key] ?? null;
    }
}
