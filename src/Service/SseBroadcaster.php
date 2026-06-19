<?php

declare(strict_types=1);

namespace Shipped\Backend\Service;

use React\Stream\ThroughStream;

/**
 * Manages connected SSE streams and broadcasts events to all clients.
 *
 * Thread-safe by design: ReactPHP runs on a single event-loop thread,
 * so the internal stream list requires no locking.
 */
final class SseBroadcaster
{
    /** @var ThroughStream[] */
    private array $streams = [];

    /**
     * Register a new SSE client stream.
     */
    public function attach(ThroughStream $stream): void
    {
        $this->streams[] = $stream;
    }

    /**
     * Remove a disconnected SSE client stream.
     */
    public function detach(ThroughStream $stream): void
    {
        $this->streams = array_filter(
            $this->streams,
            static fn (ThroughStream $s): bool => $s !== $stream,
        );
    }

    /**
     * Send an SSE-formatted event to every connected client.
     *
     * Multi-line data is automatically normalised so every line
     * carries the mandatory "data:" prefix required by the SSE
     * protocol (RFC 8895 § 6.2).
     *
     * Streams that are no longer writable are automatically pruned.
     */
    public function broadcast(string $event, string $data): void
    {
        $dataLines = implode("\n", array_map(
            static fn (string $line): string => "data: {$line}",
            explode("\n", $data),
        ));

        $message = "event: {$event}\n{$dataLines}\n\n";

        $active = [];

        foreach ($this->streams as $stream) {
            if ($stream->isWritable()) {
                $stream->write($message);
                $active[] = $stream;
            }
        }

        $this->streams = $active;
    }

    /**
     * Number of currently connected clients.
     */
    public function clientCount(): int
    {
        return count($this->streams);
    }
}
