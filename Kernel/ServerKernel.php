<?php

declare(strict_types=1);

namespace MyLab\Kernel;

use FastRoute;
use MyLab\Handler\ContainerStreamHandler;
use MyLab\Handler\DevReloadHandler;
use MyLab\Handler\HomeHandler;
use MyLab\Service\ContainerStore;
use MyLab\Service\ContainerSyncService;
use MyLab\Service\DockerService;
use MyLab\Service\DockerServiceInterface;
use MyLab\Service\IpResolver;
use MyLab\Service\MultiDockerService;
use MyLab\Service\RateLimiter;
use MyLab\Service\SseBroadcaster;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class ServerKernel
{
    private readonly bool $isDev;

    private readonly DockerServiceInterface $dockerService;

    private readonly Environment $twig;

    private readonly IpResolver $ipResolver;

    private readonly RateLimiter $rateLimiter;

    private readonly string $publicDir;

    /** @var array<string, string> */
    private static array $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'html' => 'text/html',
        'txt' => 'text/plain',
        'xml' => 'application/xml',
    ];

    public function __construct()
    {
        $this->isDev = getenv('APP_ENV') === 'local';
        $this->publicDir = __DIR__.'/../public';

        $loader = new FilesystemLoader(__DIR__.'/../views');
        $this->twig = new Environment($loader, [
            'cache' => $this->isDev ? false : __DIR__.'/../cache/twig',
            'strict_variables' => true,
        ]);
        $this->twig->addGlobal('is_dev', $this->isDev);

        // Priority: MYLAB_DOCKER_HOSTS > DOCKER_HOST > default
        $hosts = getenv('MYLAB_DOCKER_HOSTS');
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
                $this->dockerService = new DockerService;
            }
        }

        $this->ipResolver = new IpResolver;
        $this->rateLimiter = new RateLimiter;
    }

    private function resolveMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::$mimeTypes[$ext] ?? 'application/octet-stream';
    }

    /**
     * Serve a static file from the public/ directory with path-traversal protection.
     */
    private function serveStatic(string $requestPath): ?Response
    {
        $filePath = $this->publicDir.$requestPath;
        $realFile = realpath($filePath);

        if ($realFile === false) {
            return null;
        }

        $realPublic = realpath($this->publicDir);
        if ($realPublic === false || ! str_starts_with($realFile, $realPublic)) {
            return null;
        }

        if (! is_file($realFile) || ! is_readable($realFile)) {
            return null;
        }

        $content = file_get_contents($realFile);
        if ($content === false) {
            return null;
        }

        $mime = $this->resolveMime($realFile);

        $headers = [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($content),
        ];

        if (! $this->isDev) {
            $headers['Cache-Control'] = 'public, max-age=604800, immutable';
        }

        return new Response(200, $headers, $content);
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

            // ── Static file fallback ──────────────────────────────────
            if ($request->getMethod() === 'GET') {
                $staticResponse = $this->serveStatic($request->getUri()->getPath());
                if ($staticResponse !== null) {
                    return $staticResponse;
                }
            }

            return new Response(404, ['Content-Type' => 'text/plain'], '404 - Engine Route Missing');
        });

        $syncService = new ContainerSyncService(
            $this->dockerService,
            $containerStore,
            $broadcaster,
        );
        $syncService->start();

        $port = (int) (getenv('PORT') ?: 8080);
        $socket = new SocketServer("0.0.0.0:{$port}");
        $http->listen($socket);

        echo "ReactPHP Async Worker Engine listening on http://0.0.0.0:{$port}\n";
    }
}
