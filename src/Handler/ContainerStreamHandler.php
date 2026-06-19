<?php

declare(strict_types=1);

namespace Shipped\Backend\Handler;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use React\Stream\ThroughStream;
use Shipped\Backend\Service\SseBroadcaster;

use function React\Promise\resolve;

/**
 * SSE endpoint for container-update push events.
 *
 * Opens a persistent Server-Sent Events stream that the
 * SseBroadcaster writes to whenever the container store is
 * refreshed.  The client (HTMX SSE extension) receives
 * rendered HTML fragments and swaps them into the DOM.
 *
 * Heartbeats keep the connection alive; cleanup runs on close.
 */
final class ContainerStreamHandler
{
    private const HEARTBEAT_INTERVAL = 15.0;

    public function __construct(
        private readonly SseBroadcaster $broadcaster,
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

        $this->broadcaster->attach($stream);

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
