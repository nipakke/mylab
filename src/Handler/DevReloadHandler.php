<?php

declare(strict_types=1);

namespace Shipped\Backend\Handler;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use React\Stream\ThroughStream;

use function React\Promise\resolve;

/**
 * SSE endpoint for dev-mode browser live reload.
 *
 * Keeps an open Server-Sent Events stream with periodic heartbeats.
 * When the server restarts (nodemon kills the process), the browser
 * EventSource detects the connection drop and polls until the new
 * server responds — then reloads the page.
 *
 * Dev-only: registered only when APP_ENV=local.
 */
final class DevReloadHandler
{
    private const HEARTBEAT_INTERVAL = 15.0;

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

        $stream->write("event: connected\ndata: {}\n\n");

        /** @var TimerInterface|null */
        $heartbeat = Loop::addPeriodicTimer(
            self::HEARTBEAT_INTERVAL,
            static function () use ($stream): void {
                if ($stream->isWritable()) {
                    $stream->write(": heartbeat\n\n");
                }
            },
        );

        $stream->on('close', static function () use ($heartbeat): void {
            Loop::cancelTimer($heartbeat);
        });

        return resolve(new Response(200, $headers, $stream));
    }
}
