<!-- Context: project-intelligence/decisions | Priority: high | Version: 1.0 | Updated: 2026-06-19 -->

# Decisions Log — Dash

> Major architectural and technical decisions with full context. Prevents "why was this done?" debates.

## Quick Reference

- **Purpose**: Document decisions so future maintainers understand context
- **Format**: Each decision as a separate entry
- **Status**: Decided | Pending | Under Review | Deprecated

---

## Decision: PHP + ReactPHP as the backend runtime

**Date**: 2026-06-19
**Status**: Decided
**Owner**: Developer

### Context
The project started as a Go backend (single binary, fast, low memory). After experimenting with a SvelteKit + PHP API monorepo on Nx, the complexity of dual build pipelines and type synchronization became a burden. The project needed a simpler stack that could still handle async I/O for Docker API calls and SSE streaming.

### Decision
Migrate from Go to PHP 8.2 with ReactPHP as the async event-loop runtime. Use Twig for server-side templates and HTMX/SSE for dynamic frontend behavior — no SPA framework.

### Rationale
PHP with ReactPHP provides an async event loop similar to Node.js but with PHP's mature ecosystem. The clue/docker-react library offers a first-class async Docker API client. Twig provides secure, performant server-side templating. HTMX eliminates the need for a JavaScript framework entirely. The result is a single-language stack (PHP) with no frontend build step.

### Alternatives Considered
| Alternative | Pros | Cons | Why Rejected? |
|-------------|------|------|---------------|
| Go + templates | Fast, single binary, low memory | Immature Docker API client, slower iteration | PHP iteration speed + better async Docker client |
| SvelteKit + PHP API | Modern SPA, type-safe contracts | Dual build pipelines, Nx orchestration, type sync overhead | Complexity not justified for a dashboard |
| Laravel + Inertia | Full-featured framework | Heavy, convention-heavy, FPM-based (not async) | Too much framework for a focused tool |

### Impact
- **Positive**: Single language, no frontend build step, fast iteration, simpler deployment
- **Negative**: PHP has a reputation problem; ReactPHP ecosystem is smaller than Node.js
- **Risk**: ReactPHP maturity — some libraries may be less maintained than Node.js equivalents

### Related
- Git commits: `611eec7` (flatten Nx monorepo), `6dde154` (Go → PHP migration)

---

## Decision: HTMX + SSE instead of a JavaScript framework

**Date**: 2026-06-19
**Status**: Decided
**Owner**: Developer

### Context
The dashboard needs dynamic UI updates (container list refresh, real-time telemetry) but doesn't need complex client-side state management. A SPA framework (React, Vue, Svelte) would require a separate build pipeline, API contracts, and state synchronization.

### Decision
Use HTMX for partial page updates (container table refresh via POST /containers/refresh) and Server-Sent Events for real-time telemetry streaming (GET /api/telemetry). All rendering stays server-side in Twig.

### Rationale
HTMX extends HTML with declarative attributes for AJAX requests and DOM swaps. SSE is a native browser API for unidirectional server→client streams. Together they cover 100% of the dashboard's dynamic UI needs without a single line of custom JavaScript.

### Alternatives Considered
| Alternative | Pros | Cons | Why Rejected? |
|-------------|------|------|---------------|
| React/Vue/Svelte SPA | Rich interactivity, ecosystem | Build step, API layer, state management, dual language | Overkill for a dashboard |
| WebSockets | Bidirectional, low latency | More complex server implementation, connection management | SSE is simpler for unidirectional telemetry |
| Alpine.js + polling | Lightweight, no build step | Polling is wasteful, less elegant | HTMX partials + SSE is cleaner |

### Impact
- **Positive**: Zero JavaScript to write/maintain, server controls all rendering, simpler deployment
- **Negative**: Less flexibility for complex client-side interactions (not needed currently)
- **Risk**: HTMX may be unfamiliar to new contributors

---

## Decision: Flat PHP project (no monorepo)

**Date**: 2026-06-19
**Status**: Decided
**Owner**: Developer

### Context
Previous iterations used Nx monorepo with SvelteKit frontend + PHP backend. The monorepo orchestration added complexity without proportional benefit for a single-application project.

### Decision
Flatten to a single PHP project directory with standard Composer autoloading. No workspace configuration, no task orchestration, no type generation between languages.

### Rationale
A monorepo makes sense for multiple applications sharing code. Dash is a single application. The orchestration overhead (Nx config, dual package.json, type sync scripts) was pure friction.

### Impact
- **Positive**: Simpler setup (`npm install` does everything), faster onboarding, fewer config files
- **Negative**: If a separate frontend is ever needed, it would require restructuring
- **Risk**: None — the hypermedia architecture makes a separate frontend unnecessary

### Related
- Git commit: `611eec7`

---

## Decision: In-memory container snapshot store with periodic collection

**Date**: 2026-06-19
**Status**: Decided
**Owner**: Developer

### Context
The home page needs to display container data. Fetching from Docker API on every request would be slow and could overload the Docker daemon. The data is ephemeral by nature.

### Decision
Use a background job (CollectContainers) that polls Docker API every 10 seconds and stores results in an in-memory circular buffer (ContainerSnapshotStore, 60 snapshots = 10 minutes of history). Handlers read from the store instead of hitting Docker directly.

### Rationale
Decouples read path from Docker API calls. Provides instant response times for page loads. Circular buffer prevents memory leaks. No persistence needed — container state is inherently ephemeral.

### Alternatives Considered
| Alternative | Pros | Cons | Why Rejected? |
|-------------|------|------|---------------|
| Direct Docker API on each request | Always fresh data | Slow, thundering herd on refresh | Unacceptable latency |
| Redis/memcached cache | Shared across processes, TTL | External dependency, overkill for single-process | Added complexity, no benefit |
| SQLite | Persistent, queryable | Overkill for ephemeral data, write amplification | Not needed |

### Impact
- **Positive**: Fast reads, decoupled from Docker API, memory-efficient circular buffer
- **Negative**: Up to 10s staleness (acceptable for a dashboard)
- **Risk**: Memory growth if snapshot size increases — mitigated by circular buffer cap

---

## Deprecated Decisions

Decisions that were later overturned:

| Decision | Date | Replaced By | Why |
|----------|------|-------------|-----|
| Go backend | 2026-04 | PHP + ReactPHP | Slower iteration, less mature Docker client |
| Nx monorepo | 2026-06 | Flat PHP project | Orchestration overhead for single app |
| SvelteKit frontend | 2026-06 | HTMX + SSE | Eliminated dual-language complexity |

## Onboarding Checklist

- [x] Understand the philosophy behind major architectural choices
- [x] Know why certain technologies were chosen over alternatives
- [x] Understand trade-offs that were made
- [x] Know where to find decision context when questions arise

## Related Files

- `technical-domain.md` — Technical implementation affected by these decisions
- `business-tech-bridge.md` — How decisions connect business and technical
- `living-notes.md` — Current open questions that may become decisions
