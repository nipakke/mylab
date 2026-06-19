<!-- Context: project-intelligence/technical | Priority: high | Version: 1.1 | Updated: 2026-06-19 -->

# Technical Domain — Dash

> PHP hypermedia monolith for real-time Docker container monitoring. Server-rendered HTML with HTMX and SSE — no client-side JavaScript framework.

## Quick Reference

- **Purpose**: Understand how the project works technically
- **Update When**: New features, refactoring, tech stack changes
- **Audience**: Developers, DevOps, technical stakeholders

## Primary Stack

| Layer | Technology | Version | Rationale |
|-------|-----------|---------|-----------|
| Language | PHP | ^8.2 | Strong typing, mature ecosystem, FrankenPHP support |
| Runtime | ReactPHP | ^1.9 (http), ^1.4 (stream) | Async event loop — non-blocking I/O, no nginx/FPM needed |
| Templates | Twig | ^3.0 | Server-side rendering, template inheritance, safe output |
| Routing | FastRoute | ^1.3 | Simple, fast regex-based router (by Nikita Popov) |
| Docker API | clue/docker-react | ^1.6 | Async Docker Engine API client on ReactPHP event loop |
| Real-time | SSE (Server-Sent Events) | Native | Push container updates to browser via HTMX SSE extension |
| Frontend | HTMX | CDN | Hypermedia-driven UI — partial swaps + SSE auto-refresh |
| CSS | Tailwind CSS + daisyUI | CDN (dev) / compiled (prod) | Utility-first CSS with component library |
| Server | FrankenPHP + Caddy | Dockerfile | Production-grade PHP app server with auto-SSL |
| Testing | Pest | ^3.0 | Elegant PHP testing with strict type assertions |
| Linting | Laravel Pint | ^1.29 | PHP CS Fixer preset, minimal config |
| Static Analysis | PHPStan | ^2.2 | Level 8 — strict types, return types, null safety |
| Syntax Check | Parallel-Lint | ^1.4 | Fast PHP syntax validation across all source files |

## Architecture Pattern

```
Type: Hypermedia Monolith
Pattern: Handler → ContainerStore (read path, direct)
         Handler → Action → Service → External API (write/refresh path)
         └── Twig renders HTML (HTMX + SSE for dynamic behavior)
```

```
Browser (HTMX + SSE)
       │
       ▼
FrankenPHP / Caddy (port 8000)
  ├── /assets/* → static files
  └── /* → reverse proxy → ReactPHP worker (127.0.0.1:8080)
              │
              ├── IpResolver (client IP, proxy-aware)
              ├── RateLimiter (token-bucket, per-IP)
              ├── FastRoute dispatcher
              ├── Handler (HTTP adapter, thin)
              │     ├── HomeHandler → ContainerStore → Twig (read)
              │     ├── ContainerRefreshHandler → RefreshContainers → DockerService (refresh)
              │     ├── ContainerStreamHandler → SseBroadcaster (SSE push)
              │     └── DevReloadHandler (SSE, dev-only)
              │
              ├── ContainerSyncService (background sync)
              │     ├── Docker events stream (real-time)
              │     ├── Periodic fallback (30s safety net)
              │     └── SseBroadcaster → SSE clients (push updates)
              │
              └── Twig templates (layout → home → partials)
```

### Why This Architecture?

The hypermedia monolith pattern was chosen to eliminate the complexity of a SPA frontend. Instead of maintaining a separate JavaScript application with API contracts, state management, and build tooling, all rendering happens server-side. HTMX handles partial page updates via declarative HTML attributes, while the SSE extension provides real-time push updates — without writing any JavaScript.

**Alternatives considered**: 
- SvelteKit + PHP API (monorepo) — rejected: unnecessary complexity, dual build pipelines, type sync overhead
- Go backend — rejected: slower iteration, less mature async Docker client
- Nx monorepo — rejected: orchestration overhead for what is essentially a single application

## Project Structure

```
src/
├── server.php                  # Entry point — boots autoloader, creates ServerKernel
├── ServerKernel.php            # DI wiring, route registration, ReactPHP HTTP server
│
├── Action/                     # Business logic (use-case layer)
│   └── RefreshContainers.php   # Re-fetches containers → updates store → renders partial
│
├── Domain/                     # Immutable Data Transfer Objects
│   ├── Container.php           # id, name, status, memoryUsageBytes, labels
│   ├── DashboardLink.php       # Parsed service link from Docker container labels
│   └── label-parser.php        # Docker label parsing logic (pure functions)
│
├── Handler/                    # HTTP adapter layer (thin — delegates to Store or Action)
│   ├── HomeHandler.php              # GET / → reads ContainerStore → renders home.twig
│   ├── ContainerRefreshHandler.php  # POST /containers/refresh → HTMX partial reload
│   ├── ContainerStreamHandler.php   # GET /containers/stream → SSE endpoint for push updates
│   └── DevReloadHandler.php         # GET /dev/reload → SSE dev-mode live reload
│
├── Service/                    # Service / infrastructure layer
│   ├── ContainerStore.php            # In-memory store — latest container list + grouped links
│   ├── ContainerSyncService.php      # Three-phase sync: snapshot → events → periodic fallback
│   ├── DockerServiceInterface.php    # Contract: listAll(), eventsStream()
│   ├── DockerService.php             # clue/docker-react Docker API client
│   ├── SseBroadcaster.php            # Manages connected SSE streams, broadcasts events
│   ├── RateLimiter.php               # In-memory token-bucket rate limiter (per-IP)
│   └── IpResolver.php                # Resolves real client IP (reverse-proxy aware)
│
└── views/                      # Twig templates
    ├── layout.twig             # Base HTML (HTMX, Tailwind, daisyUI)
    ├── home.twig               # Main page: link cards
    └── partials/
        ├── container-rows.twig # Table rows for HTMX swap
        └── link-cards.twig     # Dashboard navigation links
```

**Key Directories**:
- `src/Action/` — Business logic for write/refresh use-cases. Each action is a single use-case. Returns Promises for async operations.
- `src/Domain/` — Immutable DTOs. No logic, just typed data carriers. All classes are `final readonly`.
- `src/Handler/` — Thin HTTP adapters. Parses request, delegates to Action or reads from Store directly, formats Response.
- `src/Service/` — Infrastructure concerns. Docker API client, in-memory storage, SSE broadcasting, rate limiting, IP resolution.
- `src/views/` — Twig templates. Layout inheritance: `layout.twig` → `home.twig` → partials.

## Route Table

| Method | Path | Handler | Action | Description |
|--------|------|---------|--------|-------------|
| GET | `/` | HomeHandler | — (reads ContainerStore directly) | Full page render |
| POST | `/containers/refresh` | ContainerRefreshHandler::handle | RefreshContainers | HTMX partial swap |
| GET | `/containers/stream` | ContainerStreamHandler | — (SSE broadcast consumer) | SSE push endpoint for container updates |
| GET | `/dev/reload` | DevReloadHandler | — (SSE heartbeat) | SSE dev-mode live reload (APP_ENV=local only) |

## Key Technical Decisions

| Decision | Rationale | Impact |
|----------|-----------|--------|
| ReactPHP event loop | Single-process async — no nginx, no FPM, no process managers | Simpler deployment, lower resource usage |
| Server-rendered HTML (no SPA) | Eliminates frontend build step, API contracts, state sync | Faster development, fewer moving parts |
| HTMX + SSE for dynamic UI | Declarative partial updates + real-time push in HTML attributes | No JavaScript to write for interactivity |
| Immutable domain DTOs | Thread-safe, predictable, easy to test | Safer concurrent access in async context |
| In-memory container store | Latest container list only — no history | Fast reads, simple, no persistence needed for ephemeral data |
| Three-phase container sync | Snapshot → event stream → periodic fallback prevents stale data | Near-real-time updates with safety net |
| Token-bucket rate limiter | Per-IP request throttling with configurable burst/window | Prevents abuse, no external service needed |
| Reverse-proxy-aware IP resolver | Trust modes: none, always, auto, Cloudflare, Traefik | Correct client IP behind proxies and CDNs |

## Integration Points

| System | Purpose | Protocol | Direction |
|--------|---------|----------|-----------|
| Docker Engine API | Container listing, inspection, event stream | HTTP (Unix socket) | Outbound |
| Browser (SSE) | Real-time container update push | SSE (HTTP long-lived) | Outbound |

## Development Environment

```
Setup: npm install (runs composer install via postinstall)
Requirements: PHP 8.2+, Composer, Node.js, Docker Engine
Local Dev: npm run dev (nodemon watches src/ for .php/.twig changes)
Testing: ./vendor/bin/pest (or make test)
Linting: ./vendor/bin/pint (or make lint)
```

## Deployment

```
Environment: Production Docker container
Platform: FrankenPHP + Caddy (Dockerfile)
CI/CD: Not yet configured
Monitoring: Your monitoring tool of choice
```

## Onboarding Checklist

- [x] Know the primary tech stack
- [x] Understand the architecture pattern and why it was chosen
- [x] Know the key project directories and their purpose
- [x] Understand major technical decisions and rationale
- [x] Know integration points and dependencies
- [x] Be able to set up local development environment
- [x] Know how to run tests and deploy
