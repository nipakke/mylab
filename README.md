# MyLab — Docker Container Dashboard

An asynchronous PHP hypermedia monolith for real-time Docker container monitoring. Built with ReactPHP, Twig, and HTMX — no client-side JavaScript framework required.

## Architecture

```
Browser (HTMX + SSE)
       │
       ▼
ReactPHP HTTP Server (port 8080)
  ├── /assets/* → static files from public/
  ├── /containers/stream → SSE push
  ├── / → HomeHandler (Twig)
  └── /* → 404
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

Visit `http://localhost:8080`.

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

Runs `composer install --no-dev --optimize-autoloader`. For containerized deployment, use the provided `Dockerfile` which builds a PHP 8.3 CLI Alpine image with compiled Tailwind CSS.

## Routes

| Method | Path | Description |
|--------|------|-------------|
| GET | `/` | Dashboard homepage — lists all containers |
| GET | `/containers/stream` | SSE endpoint — push container updates to client |
| GET | `/dev/reload` | (local only) Triggers nodemon reload |

## Configuration

All configuration is via environment variables (no `.env` file). See `.env.example` for the full list.

| Env Var | Default | Description |
|---------|---------|-------------|
| `APP_ENV` | — | `local` or `production`. `local` disables Twig cache and enables the `/dev/reload` endpoint. |
| `RATE_LIMIT` | `60` | Max requests per window per IP (token bucket burst size). |
| `RATE_LIMIT_WINDOW` | `60` | Window duration in seconds for rate limiting. |
| `TRUST_PROXY` | `0` | Proxy trust mode — `0`, `1`, `auto`, `cloudflare`, or `traefik`. |
| `PROXY_IP_HEADER` | `X-Forwarded-For` | Header to read for the real client IP when proxy trust is enabled. |

### Trust Proxy Modes

| Mode | Behaviour |
|------|-----------|
| `0` | Never trust proxies — always use `REMOTE_ADDR`. |
| `1` | Always trust — read real IP from `PROXY_IP_HEADER`. |
| `auto` | Auto-detect: trusts proxy only when `REMOTE_ADDR` is a private IP (Docker/LAN) **or a known Cloudflare IP**. |
| `cloudflare` | Validates `REMOTE_ADDR` is a **Cloudflare edge IP** before trusting `CF-Connecting-IP`. |
| `traefik` | Trusts `X-Forwarded-For` only when `REMOTE_ADDR` is on a Docker/local network. |

### Rate Limiting Behaviour

Each IP gets a token bucket that refills steadily. Up to `RATE_LIMIT` requests are allowed in a burst, then the client is throttled to `RATE_LIMIT` / `RATE_LIMIT_WINDOW` requests per second. When rate-limited, the server returns **429 Too Many Requests** with a `Retry-After` header. Stale client buckets are pruned automatically.

**Recommended settings**:

```bash
# Behind Traefik or nginx on the same Docker network
RATE_LIMIT=60 TRUST_PROXY=auto make run

# Behind Cloudflare
RATE_LIMIT=120 TRUST_PROXY=cloudflare make run

# Directly exposed (no reverse proxy)
RATE_LIMIT=30 TRUST_PROXY=0 make run
```

## Project Structure

```
├── bin/
│   └── server                   # Entry point — boots autoloader, runs kernel
├── Kernel/
│   └── ServerKernel.php         # DI wiring, routing, static files, HTTP server
├── Action/                      # Business logic (use cases)
│   └── RefreshContainers.php
├── Domain/                      # Immutable DTOs
│   ├── Container.php
│   └── DashboardLink.php
├── Handler/                     # HTTP adapters
│   ├── HomeHandler.php
│   ├── ContainerStreamHandler.php
│   └── DevReloadHandler.php
├── Service/                     # Service layer
│   ├── ContainerStore.php
│   ├── ContainerSyncService.php
│   ├── DockerServiceInterface.php
│   ├── DockerService.php
│   ├── SseBroadcaster.php
│   ├── IpResolver.php
│   └── RateLimiter.php
├── views/                       # Twig templates
│   ├── layout.twig
│   ├── home.twig
│   └── partials/
│       ├── container-rows.twig
│       └── link-cards.twig
├── public/
│   └── assets/
│       └── css/
│           └── app.min.css      # Compiled Tailwind CSS
└── docker/
    └── php.ini                  # Production OPcache + JIT config
```
