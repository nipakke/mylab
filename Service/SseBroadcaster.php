<?php

declare(strict_types=1);

namespace Shipped\Backend\Service;

use React\Stream\ThroughStream;

/**
 * Manages connected SSE streams and broadcasts events to all clients.
 *
 * Thread-safe by design: ReactPHP runs on a single event-loop thread,
 * so the internal stream list requires no locking.
 *
 * Each attached stream may carry an optional transform callable.
 * Transforms are applied per-stream before the message is formatted
 * as an SSE event, allowing handlers to inject presentation logic
 * (e.g. Twig rendering) without the broadcast source knowing about it.
 */
final class SseBroadcaster
{
    /** @var ThroughStream[] */
    private array $streams = [];

    /** @var array<int, callable(string, string): string> */
    private array $transforms = [];

    /**
     * Register a new SSE client stream.
     *
     * @param  ?callable(string, string): string  $transform  Optional per-stream data transform
     *                                                        (receives event name and raw data,
     *                                                        returns transformed data string)
     */
    public function attach(ThroughStream $stream, ?callable $transform = null): void
    {
        $this->streams[] = $stream;

        if ($transform !== null) {
            $this->transforms[\spl_object_id($stream)] = $transform;
        }
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

        unset($this->transforms[\spl_object_id($stream)]);
    }

    /**
     * Send an SSE-formatted event to every connected client.
     *
     * Multi-line data is automatically normalised so every line
     * carries the mandatory "data:" prefix required by the SSE
     * protocol (RFC 8895 § 6.2).
     *
     * Per-stream transforms are applied before SSE formatting,
     * so the transform receives the raw $data string and must
     * return a string (which is then wrapped in SSE framing).
     *
     * Streams that are no longer writable are automatically pruned.
     */
    public function broadcast(string $event, string $data): void
    {
        $active = [];

        foreach ($this->streams as $stream) {
            if ($stream->isWritable()) {
                $streamData = $data;

                $transform = $this->transforms[\spl_object_id($stream)] ?? null;
                if ($transform !== null) {
                    $streamData = ($transform)($event, $data);
                }

                $dataLines = implode("\n", array_map(
                    static fn (string $line): string => "data: {$line}",
                    explode("\n", $streamData),
                ));

                $message = "event: {$event}\n{$dataLines}\n\n";

                $stream->write($message);
                $active[] = $stream;
            }
        }

        // Prune transforms for streams that were removed
        $activeIds = array_map(\spl_object_id(...), $active);
        $this->transforms = array_intersect_key(
            $this->transforms,
            array_flip($activeIds),
        );

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
