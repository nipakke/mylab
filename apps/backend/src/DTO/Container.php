<?php
declare(strict_types=1);

namespace Shipped\Backend\DTO;

class Container
{
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
        public int $memoryUsageBytes
    ) {}

    /**
     * Helper method to present formatted data inside hypermedia templates
     */
    public function getFormattedMemory(): string
    {
        return round($this->memoryUsageBytes / 1024 / 1024, 2) . ' MB';
    }
}
