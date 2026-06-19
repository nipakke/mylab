<?php

declare(strict_types=1);

namespace Shipped\Backend\Action;

use React\EventLoop\Loop;
use React\Stream\ThroughStream;
use Shipped\Backend\Service\DockerServiceInterface;

final class StreamTelemetry
{
    public function __construct(
        private readonly DockerServiceInterface $docker,
    ) {}

    /**
     * Create an SSE telemetry stream that broadcasts system stats every 2 seconds.
     *
     * The returned ThroughStream produces SSE-formatted events ("data: {json}\n\n")
     * and can be piped directly into an HTTP response body for long-lived connections.
     */
    public function execute(): ThroughStream
    {
        $stream = new ThroughStream;

        $timer = Loop::addPeriodicTimer(2.0, function () use ($stream): void {
            $snapshot = $this->docker->getTelemetry();
            $payload = json_encode($snapshot->toArray());
            $stream->write("data: {$payload}\n\n");
        });

        $stream->on('close', function () use ($timer): void {
            Loop::cancelTimer($timer);
        });

        return $stream;
    }
}
