<!-- Context: project-intelligence/technical | Priority: high | Version: 1.0 | Updated: 2026-06-19 -->

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
| Frontend | HTMX + SSE | CDN | Hypermedia-driven UI — partial swaps, real-time streaming |
| CSS | Tailwind CSS + daisyUI | CDN (dev) / compiled (prod) | Utility-first CSS with component library |
| Server | FrankenPHP + Caddy | Dockerfile | Production-grade PHP app server with auto-SSL |
| Testing | Pest | ^3.0 | Elegant PHP testing with strict type assertions |
| Linting | Laravel Pint | ^1.29 | PHP CS Fixer preset, minimal config |

## Architecture Pattern

```
Type: Hypermedia Monolith
Pattern: Handler → Action → Service → External API
         └── Twig renders HTML (HTMX/SSE for dynamic behavior)
```

```
Browser (HTMX + SSE)
       │
       ▼
FrankenPHP / Caddy (port 8000)
  ├── /assets/* → static files
  └── /* → reverse proxy → ReactPHP worker (127.0.0.1:8080)
              │
              ├── FastRoute dispatcher
              ├── Handler (HTTP adapter, thin)
              │     └── Action (business logic, use-case)
              │           └── Service (Docker API client, snapshot store)
              └── Twig templates (layout → home → partials)
```

### Why This Architecture?

The hypermedia monolith pattern was chosen to eliminate the complexity of a SPA frontend. Instead of maintaining a separate JavaScript application with API contracts, state management, and build tooling, all rendering happens server-side. HTMX handles partial page updates via declarative HTML attributes, and SSE provides real-time streaming — both without writing any JavaScript.

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
│   ├── ListContainers.php      # Fetches containers → renders home.twig (async Promise)
│   ├── RefreshContainers.php   # Re-fetches → renders container-rows.twig for HTMX swap
│   └── StreamTelemetry.php     # SSE stream — emits CPU/memory telemetry every 2s
│
├── Domain/                     # Immutable Data Transfer Objects
│   ├── Container.php           # id, name, status, memoryUsageBytes, labels
│   └── TelemetrySnapshot.php   # time, cpu, memory (for SSE JSON)
│
├── Handler/                    # HTTP adapter layer (thin — delegates to Action)
│   ├── HomeHandler.php         # GET / → ListContainers → HTML Response
│   ├── ContainerRefreshHandler.php  # POST /containers/refresh → HTML partial
│   └── TelemetryStreamHandler.php   # GET /api/telemetry → SSE Response
│
├── Job/                        # Background recurring tasks
│   └── CollectContainers.php   # Periodic (10s) → ContainerStore
│
├── Service/                    # Service / infrastructure layer
│   ├── ContainerStore.php            # In-memory store — latest container list
│   ├── DockerServiceInterface.php   # Contract: listAll(), getTelemetry()
│   └── DockerService.php            # clue/docker-react + system telemetry
│
└── views/                      # Twig templates
    ├── layout.twig             # Base HTML (HTMX, SSE, Tailwind, daisyUI)
    ├── home.twig               # Main page: telemetry card + container table
    └── partials/
        └── container-rows.twig # Table rows for HTMX swap
```

**Key Directories**:
- `src/Action/` — Business logic. Each action is a single use-case. Returns Promises for async operations.
- `src/Domain/` — Immutable DTOs. No logic, just typed data carriers.
- `src/Handler/` — Thin HTTP adapters. Parses request, delegates to Action, formats Response.
- `src/Service/` — Infrastructure concerns. Docker API client, in-memory storage.
- `src/views/` — Twig templates. Layout inheritance: `layout.twig` → `home.twig` → partials.

## Route Table

| Method | Path | Handler | Action | Description |
|--------|------|---------|--------|-------------|
| GET | `/` | HomeHandler | ListContainers | Full page render |
| POST | `/containers/refresh` | ContainerRefreshHandler | RefreshContainers | HTMX partial swap |
| GET | `/api/telemetry` | TelemetryStreamHandler | StreamTelemetry | SSE stream (2s interval) |

## Key Technical Decisions

| Decision | Rationale | Impact |
|----------|-----------|--------|
| ReactPHP event loop | Single-process async — no nginx, no FPM, no process managers | Simpler deployment, lower resource usage |
| Server-rendered HTML (no SPA) | Eliminates frontend build step, API contracts, state sync | Faster development, fewer moving parts |
| HTMX for dynamic UI | Declarative partial updates in HTML attributes | No JavaScript to write for interactivity |
| SSE for real-time data | Unidirectional server→client stream, native browser support | No WebSocket complexity needed |
| Immutable domain DTOs | Thread-safe, predictable, easy to test | Safer concurrent access in async context |
| In-memory container store | Latest container list only — no history | Fast reads, simple, no persistence needed for ephemeral data |

## Integration Points

| System | Purpose | Protocol | Direction |
|--------|---------|----------|-----------|
| Docker Engine API | Container listing, inspection | HTTP (Unix socket) | Outbound |
| System (/proc) | CPU/memory telemetry | Filesystem read | Internal |

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
Monitoring: SSE telemetry stream (same as UI)
```

## Onboarding Checklist

- [x] Know the primary tech stack
- [x] Understand the architecture pattern and why it was chosen
- [x] Know the key project directories and their purpose
- [x] Understand major technical decisions and rationale
- [x] Know integration points and dependencies
- [x] Be able to set up local development environment
- [x] Know how to run tests and deploy

## Related Files

- `business-domain.md` — Why this technical foundation exists
- `business-tech-bridge.md` — How business needs map to technical solutions
- `decisions-log.md` — Full decision history with context
