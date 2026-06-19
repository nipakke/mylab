<?php

declare(strict_types=1);

namespace Shipped\Backend\Service;

use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;

/**
 * Orchestrates container data synchronisation between the Docker daemon
 * and the in-memory ContainerStore.
 *
 * Three-phase strategy:
 *   1. Initial snapshot  — listAll() populates the store at startup.
 *   2. Event-driven sync — Docker eventsStream() updates the store in real time.
 *   3. Periodic fallback  — safety-net refresh every 30 seconds.
 *
 * DockerService itself has no knowledge of ContainerStore — this service
 * is the sole bridge between the two.
 */
final class ContainerSyncService
{
    private const FALLBACK_INTERVAL = 30.0;

    private const DEBOUNCE_SECONDS = 0.5;

    private bool $refreshing = false;

    private ?TimerInterface $debounce = null;

    public function __construct(
        private readonly DockerServiceInterface $docker,
        private readonly ContainerStore $store,
    ) {}

    /**
     * Boot the sync pipeline and keep it running for the lifetime of the process.
     */
    public function start(): void
    {
        // Phase 1 — initial snapshot
        $this->refreshAll();

        // Phase 2 — real-time Docker events
        $stream = $this->docker->eventsStream(
            since: null,
            until: null,
            filters: [
                'type' => ['container'],
                'event' => ['create', 'start', 'die', 'destroy', 'pause', 'unpause', 'restart', 'kill', 'stop', 'oom', 'update', 'rename', 'health_status'],
            ],
        );

        $stream->on('data', function (array $event): void {
            $containerId = $event['Actor']['ID'] ?? null;
            $action = $event['Action'] ?? '';

            if ($containerId === null || $action === '') {
                return;
            }

            $shortId = substr($containerId, 0, 12);
            $name = $event['Actor']['Attributes']['name'] ?? $shortId;

            echo "[ContainerSyncService] {$action} → {$name} ({$shortId})\n";

            $this->scheduleRefresh();
        });

        $stream->on('error', function (\Throwable $error): void {
            fwrite(STDERR, "[ContainerSyncService] Event stream error: {$error->getMessage()}\n");
        });

        // Phase 3 — periodic fallback (safety net against missed events)
        Loop::addPeriodicTimer(self::FALLBACK_INTERVAL, fn () => $this->refreshAll());
    }

    private function scheduleRefresh(): void
    {
        if ($this->debounce !== null) {
            Loop::cancelTimer($this->debounce);
        }

        $this->debounce = Loop::addTimer(self::DEBOUNCE_SECONDS, function (): void {
            $this->debounce = null;
            $this->refreshAll();
        });
    }

    private function refreshAll(): void
    {
        if ($this->refreshing) {
            return;
        }

        $this->refreshing = true;

        $this->docker->listAll()->then(
            fn (array $containers) => $this->store->update($containers),
            $this->logError('Periodic refresh'),
        )->then(
            fn () => $this->refreshing = false,
            fn () => $this->refreshing = false,
        );
    }

    /**
     * Return a reusable error handler that logs to STDERR.
     */
    private function logError(string $context): callable
    {
        return function (\Throwable $error) use ($context): void {
            fwrite(STDERR, "[ContainerSyncService] {$context} failed: {$error->getMessage()}\n");
        };
    }
}
