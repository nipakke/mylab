<?php

declare(strict_types=1);

namespace Shipped\Backend\Service;

use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use Twig\Environment;

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
        private readonly ContainerStore $store,
        private readonly Environment $twig,
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
            function (array $containers): void {
                $this->store->update($containers);
                $this->broadcastUpdate();
            },
            $this->logError('Periodic refresh'),
        )->then(
            fn () => $this->refreshing = false,
            fn () => $this->refreshing = false,
        );
    }

    /**
     * Render the current store state and push it to all
     * connected SSE clients as an HTML fragment.
     */
    private function broadcastUpdate(): void
    {
        $html = $this->twig->render('partials/link-cards.twig', [
            'groups' => $this->store->getGroupedLinks(),
        ]);

        $this->broadcaster->broadcast('container-update', $html);
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
