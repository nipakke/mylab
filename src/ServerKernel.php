<?php
declare(strict_types=1);

namespace Shipped\Backend;

use Clue\React\Docker\Client;
use FastRoute;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;
use Shipped\Backend\Handler\ContainerRefreshHandler;
use Shipped\Backend\Handler\HomeHandler;
use Shipped\Backend\Handler\TelemetryStreamHandler;
use Shipped\Backend\Service\DockerService;
use Shipped\Backend\Service\DockerServiceInterface;
use Shipped\Backend\Workflow\ListContainersWorkflow;
use Shipped\Backend\Workflow\RefreshContainersWorkflow;
use Shipped\Backend\Workflow\StreamTelemetryWorkflow;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class ServerKernel
{
    private readonly DockerServiceInterface $dockerService;
    private readonly Environment $twig;

    public function __construct()
    {
        $isDev = getenv('APP_ENV') === 'local';

        $loader = new FilesystemLoader(__DIR__ . '/views');
        $this->twig = new Environment($loader, [
            'cache'            => $isDev ? false : __DIR__ . '/../cache/twig',
            'strict_variables' => true,
        ]);
        $this->twig->addGlobal('is_dev', $isDev);

        $this->dockerService = new DockerService(new Client());
    }

    public function run(): void
    {
        $homeHandler = new HomeHandler(
            new ListContainersWorkflow($this->dockerService, $this->twig),
        );

        $refreshHandler = new ContainerRefreshHandler(
            new RefreshContainersWorkflow($this->dockerService, $this->twig),
        );

        $telemetryHandler = new TelemetryStreamHandler(
            new StreamTelemetryWorkflow($this->dockerService),
        );

        $dispatcher = FastRoute\simpleDispatcher(
            function (FastRoute\RouteCollector $r) use (
                $homeHandler,
                $refreshHandler,
                $telemetryHandler,
            ): void {
                $r->addRoute('GET', '/', $homeHandler);
                $r->addRoute('POST', '/containers/refresh', $refreshHandler->handle(...));
                $r->addRoute('GET', '/api/telemetry', $telemetryHandler->handle(...));
            }
        );

        $http = new HttpServer(function (ServerRequestInterface $request) use ($dispatcher) {
            $routeInfo = $dispatcher->dispatch(
                $request->getMethod(),
                $request->getUri()->getPath(),
            );

            if ($routeInfo[0] === FastRoute\Dispatcher::FOUND) {
                return $routeInfo[1]($request);
            }

            return new Response(404, ['Content-Type' => 'text/plain'], '404 - Engine Route Missing');
        });

        Loop::addPeriodicTimer(5.0, function (): void {
            echo '[' . date('H:i:s') . "] Async heartbeat — worker alive\n";
        });

        $socket = new SocketServer('127.0.0.1:8080');
        $http->listen($socket);

        echo "ReactPHP Asynchronous Worker Engine listening on http://127.0.0.1:8080\n";
    }
}
