<?php

declare(strict_types=1);

namespace MyLab\Handler;

use MyLab\Service\SseBroadcaster;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use React\Stream\ThroughStream;
use Twig\Environment;

use function React\Promise\resolve;

/**
 * SSE endpoint for container-update push events.
 *
 * Opens a persistent Server-Sent Events stream.  The SseBroadcaster
 * pushes structured JSON data from the ContainerSyncService; this
 * handler registers a per-stream Twig render transform so that
 * clients receive rendered HTML fragments — exactly as they did
 * before the architectural refactor.
 *
 * Rendering is the Handler's responsibility per patterns.md.
 * The Service layer never touches presentation.
 *
 * Heartbeats keep the connection alive; cleanup runs on close.
 */
final class ContainerStreamHandler
{
    private const HEARTBEAT_INTERVAL = 15.0;

    public function __construct(
        private readonly SseBroadcaster $broadcaster,
        private readonly Environment $twig,
    ) {}

    /**
     * Open an SSE stream that stays alive until the client disconnects.
     *
     * @return PromiseInterface<Response>
     */
    public function __invoke(ServerRequestInterface $request): PromiseInterface
    {
        $stream = new ThroughStream;

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        // Register the stream with a Twig render transform.
        // The SyncService broadcasts JSON arrays; we decode and
        // render an HTML fragment before it hits the wire.
        $this->broadcaster->attach($stream, function (string $event, string $jsonData): string {
            $groups = json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);

            return $this->twig->render('partials/link-cards.twig', [
                'groups' => $groups,
            ]);
        });

        $stream->write("event: connected\ndata: {}\n\n");

        $heartbeat = Loop::addPeriodicTimer(
            self::HEARTBEAT_INTERVAL,
            static function () use ($stream): void {
                if ($stream->isWritable()) {
                    $stream->write(": heartbeat\n\n");
                }
            },
        );

        $stream->on('close', function () use ($stream, $heartbeat): void {
            Loop::cancelTimer($heartbeat);
            $this->broadcaster->detach($stream);
        });

        return resolve(new Response(200, $headers, $stream));
    }
}
