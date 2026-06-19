<?php
declare(strict_types=1);

namespace Shipped\Backend\Domain;

class Container
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $status,
        public readonly int $memoryUsageBytes
    ) {}

    /**
     * Helper method to present formatted data inside hypermedia templates
     */
    public function getFormattedMemory(): string
    {
        return round($this->memoryUsageBytes / 1024 / 1024, 2) . ' MB';
    }
}
