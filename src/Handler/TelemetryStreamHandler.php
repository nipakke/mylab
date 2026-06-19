<?php
declare(strict_types=1);

namespace Shipped\Backend\Handler;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use Shipped\Backend\Workflow\StreamTelemetryWorkflow;

/**
 * HTTP handler for GET /api/telemetry — establishes an SSE stream connection.
 *
 * This handler is synchronous: it calls the workflow to obtain a ThroughStream,
 * wraps it in a Response with SSE headers, and returns immediately. The async
 * data flow (periodic telemetry writes) is managed internally by the ThroughStream
 * created inside StreamTelemetryWorkflow.
 *
 * Thin by design — only HTTP concerns live here.
 */
final class TelemetryStreamHandler
{
    public function __construct(
        private readonly StreamTelemetryWorkflow $workflow,
    ) {}

    /**
     * Handle the SSE telemetry request.
     *
     * Calls StreamTelemetryWorkflow::execute() to obtain a ThroughStream,
     * wraps it in a Response with the appropriate SSE headers, and returns
     * the Response directly (synchronous path — the ThroughStream owns the
     * async lifecycle).
     */
    public function handle(ServerRequestInterface $request): Response
    {
        $stream = $this->workflow->execute();

        return new Response(
            200,
            [
                'Content-Type'  => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection'    => 'keep-alive',
            ],
            $stream,
        );
    }
}
