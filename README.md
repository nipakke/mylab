# Dash — Docker Container Dashboard

An asynchronous PHP hypermedia monolith for real-time Docker container monitoring. Built with ReactPHP, Twig, and HTMX — no client-side JavaScript framework required.

## Architecture

```
Browser (HTMX + SSE)
       │
       ▼
FrankenPHP / Caddy (port 8000)
  ├── /assets/* → static files
  └── /* → ReactPHP worker (127.0.0.1:8080)
              │
              ├── FastRoute dispatcher
              ├── Handler → Action → DockerService (clue/docker-react)
              └── Twig templates (server-rendered HTML)
```

The frontend uses **HTMX** for partial page updates. All rendering happens server-side via Twig — no build step, no SPA.

## Prerequisites

- PHP 8.2+
- Composer
- Node.js (for `nodemon` in development)
- Docker Engine (with API accessible)

## Quick Start

```bash
# Install all dependencies
npm install

# Start the dev server (auto-reloads on changes)
npm run dev
```

Visit `http://localhost:8000`.

## Development Commands

| Task | Command | Description |
|------|---------|-------------|
| Start dev server | `npm run dev` | Nodemon watches `src/` for `.php/.twig` changes |
| Run server directly | `make run` | Starts ReactPHP without auto-reload |
| Run tests | `make test` | Runs Pest test suite |
| Lint | `make lint` | PHP syntax check |
| Clean cache | `make clean` | Removes Twig template cache |

## Production Build

```bash
make build
```

Runs `composer install --no-dev --optimize-autoloader`. For containerized deployment, use the provided `Dockerfile` which builds a FrankenPHP/Caddy image with compiled Tailwind CSS.

## Routes

| Method | Path | Description |
|--------|------|-------------|
| GET | `/` | Dashboard homepage — lists all containers |
| POST | `/containers/refresh` | HTMX partial — refreshes container table |

## Project Structure

```
src/
├── server.php                  # Entry point
├── ServerKernel.php            # DI, routing, HTTP server setup
├── Action/                     # Business logic (use cases)
│   ├── ListContainers.php
│   └── RefreshContainers.php
├── Domain/                     # Immutable DTOs
│   └── Container.php
├── Handler/                    # HTTP adapters
│   ├── HomeHandler.php
│   └── ContainerRefreshHandler.php
├── Job/                        # Background jobs
│   └── CollectContainers.php
├── Service/                    # Service layer
│   ├── ContainerStore.php
│   ├── DockerServiceInterface.php
│   └── DockerService.php
└── views/                      # Twig templates
    ├── layout.twig
    ├── home.twig
    └── partials/
        └── container-rows.twig
```
