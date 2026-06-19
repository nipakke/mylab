<?php

declare(strict_types=1);

namespace Shipped\Backend\Service;

use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use Shipped\Backend\Domain\Container;
use Shipped\Backend\Domain\DashboardLink;

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

    private const MAX_WAIT_SECONDS = 2.0;

    private bool $refreshing = false;

    private ?TimerInterface $debounce = null;

    private ?TimerInterface $maxWait = null;

    public function __construct(
        private readonly DockerServiceInterface $docker,
        private readonly ContainerStoreInterface $store,
        private readonly SseBroadcaster $broadcaster,
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

        $stream->on('data', $this->handleDockerEvent(...));

        $stream->on('error', $this->handleEventStreamError(...));

        // Phase 3 — periodic fallback (safety net against missed events)
        Loop::addPeriodicTimer(self::FALLBACK_INTERVAL, $this->refreshAll(...));
    }

    private function scheduleRefresh(): void
    {
        // Reset debounce — wait for quiet period
        if ($this->debounce !== null) {
            Loop::cancelTimer($this->debounce);
        }

        $this->debounce = Loop::addTimer(self::DEBOUNCE_SECONDS, $this->doRefresh(...));

        // Start max-wait on first event of a burst, never reset it
        if ($this->maxWait === null) {
            $this->maxWait = Loop::addTimer(self::MAX_WAIT_SECONDS, $this->doRefresh(...));
        }
    }

    private function doRefresh(): void
    {
        $this->clearTimers();
        $this->refreshAll();
    }

    private function clearTimers(): void
    {
        if ($this->debounce !== null) {
            Loop::cancelTimer($this->debounce);
            $this->debounce = null;
        }

        if ($this->maxWait !== null) {
            Loop::cancelTimer($this->maxWait);
            $this->maxWait = null;
        }
    }

    private function refreshAll(): void
    {
        if ($this->refreshing) {
            return;
        }

        $this->refreshing = true;

        $this->docker->listAll()->then(
            $this->applyContainerList(...),
            $this->logError('Periodic refresh'),
        )->then(
            $this->finishRefresh(...),
            $this->finishRefresh(...),
        );
    }

    /**
     * Push the current store state to all connected SSE clients
     * as structured JSON data. Presentation (Twig rendering) is
     * the responsibility of the Handler layer — this Service
     * broadcasts only domain data.
     */
    private function broadcastUpdate(): void
    {
        $groupedLinks = $this->store->getGroupedLinks(0.0);

        $data = [];
        foreach ($groupedLinks as $groupName => $links) {
            $data[$groupName] = array_map(
                static fn (DashboardLink $link): array => $link->toArray(),
                $links,
            );
        }

        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        echo "[SSE] Broadcasting update — ".count($data).' groups — '.json_encode(array_keys($data))."\n";
        $this->broadcaster->broadcast('container-update', $json);
    }

    /**
     * Handle a single Docker event from the real-time stream.
     *
     * @param  array<array-key, mixed>  $event
     */
    private function handleDockerEvent(array $event): void
    {
        $containerId = $event['Actor']['ID'] ?? null;
        $action = $event['Action'] ?? '';

        if ($containerId === null || $action === '') {
            return;
        }

        $shortId = substr($containerId, 0, 12);
        $name = $event['Actor']['Attributes']['name'] ?? $shortId;

        echo "[ContainerSyncService] {$action} → {$name} ({$shortId})\n";

        $this->scheduleRefresh();
    }

    /**
     * Handle an error from the Docker event stream.
     */
    private function handleEventStreamError(\Throwable $error): void
    {
        fwrite(STDERR, "[ContainerSyncService] Event stream error: {$error->getMessage()}\n");
    }

    /**
     * Apply a fresh container list to the store and push updates to clients.
     *
     * @param  Container[]  $containers
     */
    private function applyContainerList(array $containers): void
    {
        $this->store->replace($containers);
        $this->broadcastUpdate();
    }

    /**
     * Release the refresh lock so subsequent refreshes can proceed.
     */
    private function finishRefresh(): void
    {
        $this->refreshing = false;
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
