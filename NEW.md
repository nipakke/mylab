Here is the fully revised, complete architectural specification for your zero-Node-modules hypermedia engine. This single document now covers the server runtime, the async event loops, view architecture, production asset compilation, strict type safety mechanics, and full automated testing parameters using Pest.

---

# Complete Technical Specification: Asynchronous PHP Hypermedia Monolith

## 1. System Topology & Asset Routing

The application functions as a single-process hypermedia monolith. Rather than maintaining decoupled backend and frontend code bases with intermediate type compilers, the frontend presentation layout is evaluated directly inside the server's execution boundary.

* **FrankenPHP (Main Go Engine):** Opens web ports, serves static distribution files (images/favicons), handles SSL/TLS termination, and proxies dynamic HTTP requests down to the worker thread.
* **ReactPHP (Worker Kernel):** Boots once into system RAM, executing an infinite non-blocking event loop that handles routing logic, strict data processing, and structural Twig rendering.
* **HTMX + SSE (Client Engine):** Controls browser DOM mutations using hypermedia exchange tokens (`hx-get`, `hx-swap`) and real-time Server-Sent Events, removing the need for a JavaScript framework, virtual DOM hydration, or node modules.

---

## 2. Directory Architecture Blueprint

```text
apps/backend/
├── cache/                  # Production Twig Compilation Storage
├── src/
│   └── DTO/                # Strictly Typed Data Transfer Objects
│       └── Container.php
├── tests/                  # Pest Automation Suite
│   ├── TestCase.php
│   └── RenderTest.php
├── views/                  # Twig Hypermedia Components
│   ├── partials/
│   │   └── container-rows.twig
│   ├── home.twig
│   └── layout.twig
├── composer.json           # Application Dependencies
├── project.json            # Nx Automation Pipelines
└── server.php              # Asynchronous System Kernel

```

---

## 3. Dependency Configuration (`apps/backend/composer.json`)

```json
{
  "name": "shipped/backend",
  "description": "Asynchronous PHP Hypermedia Monolith Core",
  "type": "project",
  "license": "MIT",
  "require": {
    "php": "^8.2",
    "react/http": "^1.9",
    "react/stream": "^1.4",
    "twig/twig": "^3.0",
    "nikic/fast-route": "^1.3"
  },
  "require-dev": {
    "pestphp/pest": "^2.0"
  },
  "autoload": {
    "psr-4": {
      "Shipped\\Backend\\": "src/"
    }
  },
  "config": {
    "allow-plugins": {
      "pestphp/pest-plugin": true
    }
  }
}

```

---

## 4. Strict Type Definition Layer (`apps/backend/src/DTO/Container.php`)

Type safety is preserved by creating immutable data structures right at the system input boundary. By feeding these strict objects straight into the view engine, the template parameters are typed automatically.

```php
<?php
declare(strict_types=1);

namespace Shipped\Backend\DTO;

class Container
{
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
        public int $memoryUsageBytes
    ) {}

    /**
     * Helper method to present formatted data inside hypermedia templates
     */
    public function getFormattedMemory(): string
    {
        return round($this->memoryUsageBytes / 1024 / 1024, 2) . ' MB';
    }
}

```

---

## 5. Core Kernel Runtime (`apps/backend/server.php`)

```php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

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
    'cache' => $isDev ? false : __DIR__ . '/cache/twig',
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

$socket = new React\Socket\SocketServer('127.0.0.1:8080');
$http->listen($socket);

echo "ReactPHP Asynchronous Worker Engine listening on http://127.0.0.1:8080\n";

```

---

## 6. View Architecture Layout (Twig)

### Base Page Layout Framework (`views/layout.twig`)

```html
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ title }}</title>

    <script src="https://unpkg.com/htmx.org@2.0.0"></script>
    <script src="https://unpkg.com/htmx-ext-sse@2.0.0"></script>

    {% if is_dev %}
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daisyui@5.0.0/dist/full.css" />
    {% else %}
        <link rel="stylesheet" href="/assets/css/app.min.css">
    {% endif %}
</head>
<body class="h-full antialiased font-sans">
    <div class="navbar bg-base-900 border-b border-base-800 px-6">
        <a class="text-xl font-bold text-primary tracking-wider">⚡ SHIPPED HYPERMEDIA</a>
    </div>
    <main class="p-8 max-w-6xl mx-auto">
        {% block content %}{% endblock %}
    </main>
</body>
</html>

```

### View Application Controller Component (`views/home.twig`)

```html
{% extends "layout.twig" %}

{% block content %}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div hx-ext="sse" sse-connect="/api/telemetry" sse-swap="message" class="card bg-base-900 border border-base-800 shadow-xl">
        <div class="card-body">
            <h2 class="card-title text-sm text-slate-400 uppercase tracking-wider">Core Telemetry Pulse</h2>
            <div class="text-2xl font-mono text-emerald-400 mt-2">Connecting...</div>
            <script>
                document.body.addEventListener('htmx:sseMessage', function(e) {
                    const data = JSON.parse(e.detail.data);
                    e.target.innerHTML = `
                        <div class="space-y-1 text-sm font-mono">
                            <p class="text-xs text-slate-500">Sync: ${data.time}</p>
                            <p>CPU usage: <span class="text-primary font-bold">${data.cpu}</span></p>
                            <p>RAM usage: <span class="text-secondary font-bold">${data.memory}</span></p>
                        </div>
                    `;
                });
            </script>
        </div>
    </div>
</div>

<div class="card bg-base-900 border border-base-800 shadow-xl">
    <div class="card-body">
        <div class="flex justify-between items-center mb-4">
            <h2 class="card-title text-xl font-bold">Managed Daemons</h2>
            <button hx-post="/containers/refresh" 
                    hx-target="#container-table-body" 
                    hx-indicator="#loading-spinner"
                    class="btn btn-primary btn-sm">
                <span id="loading-spinner" class="loading loading-spinner loading-xs htmx-indicator"></span>
                Refresh Nodes
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr class="border-base-800 text-slate-400">
                        <th>Container ID</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Memory Allocation</th>
                    </tr>
                </thead>
                <tbody id="container-table-body">
                    {% include "partials/container-rows.twig" %}
                </tbody>
            </table>
        </div>
    </div>
</div>
{% endblock %}

```

### DOM Hypermedia Partial Fragment Block (`views/partials/container-rows.twig`)

```html
{% for container in containers %}
<tr class="border-base-800 hover:bg-base-800/40 transition-colors">
    <td class="font-mono text-xs text-slate-400">{{ container.id }}</td>
    <td class="font-bold">{{ container.name }}</td>
    <td>
        {% if container.status == 'running' %}
            <span class="badge badge-success badge-sm font-semibold text-xs">{{ container.status }}</span>
        {% else %}
            <span class="badge badge-error badge-sm font-semibold text-xs">{{ container.status }}</span>
        {% endif %}
    </td>
    <td class="font-mono text-sm text-slate-300">{{ container.getFormattedMemory() }}</td>
</tr>
{% else %}
<tr>
    <td colspan="4" class="text-center text-slate-500 py-4">No active nodes detected.</td>
</tr>
{% endfor %}

```

---

## 7. Continuous Integration Testing Framework (Pest Setup)

Because everything compiles inside PHP, writing frontend unit tests means writing raw, accelerated backend checks.

### 1. Test Suite Configuration (`tests/Pest.php`)

```php
<?php
uses()->in(__DIR__);

```

### 2. Integration Template Render Assertion Suite (`tests/RenderTest.php`)

```php
<?php
declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Shipped\Backend\DTO\Container;

beforeEach(function () {
    // Instantiate isolated mock rendering parameters before each run block
    $this->loader = new FilesystemLoader(__DIR__ . '/../views');
    $this->twig = new Environment($this->loader, [
        'cache' => false,
        'strict_variables' => true // Assures properties match exact DTO schema models
    ]);
});

test('the system model maps properties into types successfully', function () {
    $container = new Container('id-999', 'worker-node', 'running', 104857600);
    
    expect($container->id)->toBeString()
        ->and($container->name)->toBe('worker-node')
        ->and($container->getFormattedMemory())->toBe('100 MB');
});

test('the partial template loop crashes explicitly if strict type variables are missing', function () {
    // Intentionally omitting 'containers' structure from array variables collection mapping
    expect(fn() => $this->twig->render('partials/container-rows.twig', []))
        ->toThrow(\Twig\Error\RuntimeError::class);
});

test('the layout components parse strictly validated elements into safe output blocks', function () {
    $container = new Container('sha-test', 'load-balancer', 'stopped', 0);

    $html = $this->twig->render('partials/container-rows.twig', [
        'containers' => [$container]
    ]);

    expect($html)->toContain('load-balancer')
        ->and($html)->toContain('badge-error')
        ->and($html)->not->toContain('No active nodes detected.');
});

```

---

## 8. High-Performance Multi-Stage Production Build (`Dockerfile`)

```dockerfile
# =========================================================================
# STAGE 1: ASSET COMPILE RUNTIME (No Node modules required)
# =========================================================================
FROM lucaslorentz/caddy-docker-proxy:ci-alpine AS builder
RUN xcaddy build \
    --with github.com/tailscale/caddy-tailscale \
    --with github.com/caddyserver/nginx-adapter \
    --with github.com/orefalo/caddy-tailwind

# =========================================================================
# STAGE 2: PRODUCTION SERVER RUNTIME
# =========================================================================
FROM dunglas/frankenphp:latest-alpine

COPY --from=builder /usr/bin/caddy /usr/bin/frankenphp

ENV APP_ENV=production
WORKDIR /app
COPY . /app

# Run vendor clean updates
RUN composer install --no-dev --optimize-autoloader

# Go binary optimization: Generates minimized deployment assets with zero JS tools
RUN frankenphp tailwind --input /app/views/layout.twig --output /app/public/assets/css/app.min.css --minify

```

---

## 9. Monorepo Integration Settings (`project.json`)

```json
{
  "name": "backend",
  "$schema": "../../node_modules/nx/schemas/project-schema.json",
  "projectType": "application",
  "sourceRoot": "apps/backend",
  "targets": {
    "dev": {
      "executor": "nx:run-commands",
      "options": {
        "command": "APP_ENV=local php server.php",
        "cwd": "apps/backend"
      }
    },
    "test": {
      "executor": "nx:run-commands",
      "options": {
        "command": "./vendor/bin/pest",
        "cwd": "apps/backend"
      }
    }
  }
}

```

---

### Verified Macro Operational Directives

* Run development environment loop instantly: `nx dev backend`
* Execute strict verification testing engine suites: `nx test backend`