<?php

declare(strict_types=1);

namespace Shipped\Backend;

use FastRoute;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;
use Shipped\Backend\Handler\ContainerStreamHandler;
use Shipped\Backend\Handler\DevReloadHandler;
use Shipped\Backend\Handler\HomeHandler;
use Shipped\Backend\Service\ContainerStore;
use Shipped\Backend\Service\ContainerSyncService;
use Shipped\Backend\Service\DockerService;
use Shipped\Backend\Service\DockerServiceInterface;
use Shipped\Backend\Service\MultiDockerService;
use Shipped\Backend\Service\IpResolver;
use Shipped\Backend\Service\RateLimiter;
use Shipped\Backend\Service\SseBroadcaster;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class ServerKernel
{
    private readonly bool $isDev;

    private readonly DockerServiceInterface $dockerService;

    private readonly Environment $twig;

    private readonly IpResolver $ipResolver;

    private readonly RateLimiter $rateLimiter;

    public function __construct()
    {
        $this->isDev = getenv('APP_ENV') === 'local';

        $loader = new FilesystemLoader(__DIR__.'/views');
        $this->twig = new Environment($loader, [
            'cache' => $this->isDev ? false : __DIR__.'/../cache/twig',
            'strict_variables' => true,
        ]);
        $this->twig->addGlobal('is_dev', $this->isDev);

        // Priority: DASH_DOCKER_HOSTS > DOCKER_HOST > default
        $hosts = getenv('DASH_DOCKER_HOSTS');
        if ($hosts !== false && $hosts !== '') {
            $dsns = array_values(array_filter(array_map('trim', explode(',', $hosts)), fn (string $s): bool => $s !== ''));
            if (count($dsns) === 1) {
                $this->dockerService = new DockerService(dsn: $dsns[0]);
            } else {
                $services = array_map(fn (string $dsn): DockerService => new DockerService(dsn: $dsn), $dsns);
                $this->dockerService = new MultiDockerService($services);
            }
        } else {
            $host = getenv('DOCKER_HOST');
            if ($host !== false && $host !== '') {
                $this->dockerService = new DockerService(dsn: $host);
            } else {
                $this->dockerService = new DockerService();
            }
        }

        $this->ipResolver = new IpResolver;
        $this->rateLimiter = new RateLimiter;
    }

    public function run(): void
    {
        $containerStore = new ContainerStore;

        $homeHandler = new HomeHandler($containerStore, $this->twig);

        $broadcaster = new SseBroadcaster;
        $streamHandler = new ContainerStreamHandler($broadcaster, $this->twig);

        $devReloadHandler = $this->isDev ? new DevReloadHandler : null;

        $dispatcher = FastRoute\simpleDispatcher(
            function (FastRoute\RouteCollector $r) use (
                $homeHandler,
                $streamHandler,
                $devReloadHandler,
            ): void {
                $r->addRoute('GET', '/', $homeHandler);
                $r->addRoute('GET', '/containers/stream', $streamHandler);

                if ($devReloadHandler !== null) {
                    $r->addRoute('GET', '/dev/reload', $devReloadHandler);
                }
            }
        );

        $http = new HttpServer(function (ServerRequestInterface $request) use ($dispatcher) {
            // ── Rate limiting ─────────────────────────────────────────
            $clientIp = $this->ipResolver->resolve($request);

            if (! $this->rateLimiter->isAllowed($clientIp)) {
                $retryAfter = $this->rateLimiter->getRetryAfterSeconds($clientIp);

                return new Response(429, [
                    'Content-Type' => 'text/plain',
                    'Retry-After' => (string) $retryAfter,
                ], 'Too Many Requests');
            }

            // ── Routing ───────────────────────────────────────────────
            $routeInfo = $dispatcher->dispatch(
                $request->getMethod(),
                $request->getUri()->getPath(),
            );

            if ($routeInfo[0] === FastRoute\Dispatcher::FOUND) {
                return $routeInfo[1]($request);
            }

            return new Response(404, ['Content-Type' => 'text/plain'], '404 - Engine Route Missing');
        });

        $syncService = new ContainerSyncService(
            $this->dockerService,
            $containerStore,
            $broadcaster,
        );
        $syncService->start();

        Loop::addPeriodicTimer(5.0, function (): void {
            echo '['.date('H:i:s')."] Async heartbeat — worker alive\n";
        });

        $socket = new SocketServer('127.0.0.1:8080');
        $http->listen($socket);

        echo "ReactPHP Asynchronous Worker Engine listening on http://127.0.0.1:8080\n";
    }
}
