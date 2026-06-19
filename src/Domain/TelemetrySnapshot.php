<?php

declare(strict_types=1);

namespace Shipped\Backend\Domain;

/**
 * Immutable DTO representing a snapshot of system telemetry
 * for SSE (Server-Sent Events) streaming payloads.
 *
 * Fields mirror the SSE payload format expected by frontend consumers:
 * time (H:i:s), cpu (percentage), memory (percentage).
 */
final readonly class TelemetrySnapshot
{
    public function __construct(
        public string $time,
        public string $cpu,
        public string $memory,
    ) {}

    /**
     * Convert to array for seamless JSON serialization in SSE payloads.
     *
     * @return array{time: string, cpu: string, memory: string}
     */
    public function toArray(): array
    {
        return [
            'time' => $this->time,
            'cpu' => $this->cpu,
            'memory' => $this->memory,
        ];
    }
}
