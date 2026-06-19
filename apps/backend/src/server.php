<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use React\Http\HttpServer;
use React\Http\Message\Response;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Shipped\Backend\DTO\Container;

// =========================================================================
// 1. ENVIRONMENT & VIEW COMPILER BOOT
// =========================================================================
$isDev = getenv('APP_ENV') === 'local';

$loader = new FilesystemLoader(__DIR__ . '/views');
$twig = new Environment($loader, [
    'cache' => $isDev ? false : __DIR__ . '/../cache/twig',
    'strict_variables' => true, // Throws immediate runtime exceptions on invalid properties
]);

$twig->addGlobal('is_dev', $isDev);

// =========================================================================
// 2. MONOLITH ROUTING MATRIX
// =========================================================================
$dispatcher = FastRoute\simpleDispatcher(function (FastRoute\RouteCollector $r) use ($twig) {
    
    // Target: Root View (Full Structural Layout Render)
    $r->addRoute('GET', '/', function (ServerRequestInterface $request) use ($twig) {
        // Enforce strict type mapping at the system controller level
        $containers = [
            new Container('sha-01', 'reverse-proxy', 'running', 44040192),
            new Container('sha-02', 'db-postgres', 'running', 188743680),
            new Container('sha-03', 'cache-redis', 'stopped', 0),
        ];

        $html = $twig->render('home.twig', [
            'title' => 'Core Command Panel',
            'containers' => $containers
        ]);
        return Response::plaintext($html)->withHeader('Content-Type', 'text/html');
    });

    // Target: HTMX Partial Handler (Returns component fragments only)
    $r->addRoute('POST', '/containers/refresh', function (ServerRequestInterface $request) use ($twig) {
        // Simulate immediate non-blocking calculation update
        $freshContainers = [
            new Container('sha-01', 'reverse-proxy', 'running', 46137344),
            new Container('sha-02', 'db-postgres', 'running', 190840832),
            new Container('sha-03', 'cache-redis', 'running', 12582912), 
        ];

        $html = $twig->render('partials/container-rows.twig', ['containers' => $freshContainers]);
        return Response::plaintext($html)->withHeader('Content-Type', 'text/html');
    });

    // Target: Asynchronous Real-Time Telemetry Stream (Persistent SSE Pipe)
    $r->addRoute('GET', '/api/telemetry', function (ServerRequestInterface $request) {
        $stream = new React\Stream\ThroughStream();

        // Broadcast stats across the network pipeline every 2.0 seconds
        $timer = React\EventLoop\Loop::addPeriodicTimer(2.0, function () use ($stream) {
            $payload = json_encode([
                'time' => date('H:i:s'),
                'cpu' => rand(1, 12) . '%',
                'memory' => rand(38, 41) . '%'
            ]);
            $stream->write("data: {$payload}\n\n");
        });

        // Terminate timer execution cleanly when the client browser drops connection
        $stream->on('close', function () use ($timer) {
            React\EventLoop\Loop::cancelTimer($timer);
            echo "[" . date('H:i:s') . "] SSE Telemetry socket connection released safely.\n";
        });

        return new Response(
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
            ],
            $stream
        );
    });
});

// =========================================================================
// 3. HTTP EXECUTION KERNEL BIND
// =========================================================================
$http = new HttpServer(function (ServerRequestInterface $request) use ($dispatcher) {
    $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());
    
    if ($routeInfo[0] === FastRoute\Dispatcher::FOUND) {
        return $routeInfo[1]($request);
    }
    
    return new Response(404, ['Content-Type' => 'text/plain'], '404 - Engine Route Missing');
});

// =========================================================================
// 4. BACKGROUND SCHEDULED JOBS
// =========================================================================
React\EventLoop\Loop::addPeriodicTimer(5.0, function () {
    echo "[" . date('H:i:s') . "] Async heartbeat — worker alive, " . rand(1000, 9999) . " requests processed.\n";
});

$socket = new React\Socket\SocketServer('127.0.0.1:8080');
$http->listen($socket);

echo "ReactPHP Asynchronous Worker Engine listening on http://127.0.0.1:8080\n";
