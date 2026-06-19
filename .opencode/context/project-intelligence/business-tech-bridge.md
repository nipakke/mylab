<!-- Context: project-intelligence/bridge | Priority: high | Version: 1.0 | Updated: 2026-06-19 -->

# Business ↔ Tech Bridge — Dash

> How business needs translate to technical solutions. This is the critical connection point.

## Quick Reference

- **Purpose**: Show how technical choices serve the goal of simple Docker monitoring
- **Update When**: New features, refactoring, business pivot

## Core Mapping

| Business Need | Technical Solution | Why This Mapping | Business Value |
|---------------|-------------------|------------------|----------------|
| See all Docker containers at a glance | Docker API → Container DTOs → Twig table | Direct socket connection, no agents needed | No setup, instant visibility |
| Real-time system resource monitoring | SSE stream from /api/telemetry | ReactPHP ThroughStream, 2s interval | Live CPU/memory without page refresh |
| Auto-refreshing container list | HTMX POST /containers/refresh | Returns partial HTML, HTMX swaps DOM | Always current without manual refresh |
| Simple deployment | Single Dockerfile (FrankenPHP + Caddy) | Multi-stage build, compiled Tailwind | One container to run |
| Zero-config setup | Docker socket access only | Unix socket connection via clue/docker-react | No API keys, no agent, no registration |

## Feature Mapping

### Feature: Container Dashboard (Home Page)

**Business Context**:
- User need: See all Docker containers with status, memory usage, and labels
- Business goal: Replace `docker ps` terminal command with a visual dashboard
- Priority: Core feature — the entire reason the project exists

**Technical Implementation**:
- Solution: GET `/` → HomeHandler → ListContainers → DockerService::listAll() → Twig renders home.twig
- Architecture: Handler (thin adapter) → Action (use-case) → Service (Docker API)
- Trade-offs: Server-rendered HTML (not SPA) — simpler but less interactive; acceptable for a dashboard

**Connection**:
Without this feature, the project has no purpose. The technical choice of server-rendered Twig templates means every page load gets fresh container data without client-side state management. HTMX handles partial refreshes — the user clicks "Refresh" and gets new rows without a full page reload.

### Feature: Real-Time Telemetry

**Business Context**:
- User need: Monitor host system CPU and memory usage in real time
- Business goal: Provide system health context alongside container status
- Priority: High — differentiates from basic `docker ps` output

**Technical Implementation**:
- Solution: GET `/api/telemetry` → TelemetryStreamHandler → StreamTelemetry → SSE stream (2s interval)
- Architecture: ReactPHP ThroughStream reads /proc/stat and /proc/meminfo, emits JSON every 2 seconds
- Trade-offs: SSE (unidirectional) vs WebSocket (bidirectional) — SSE is simpler and sufficient for telemetry

**Connection**:
SSE provides a persistent, low-overhead stream from server to browser. The browser's native EventSource API consumes it without any JavaScript library. Each 2-second snapshot includes CPU percentage and memory usage — enough granularity for monitoring without overwhelming the connection.

### Feature: Periodic Container Collection (Simplified)

**Business Context**:
- User need: Fast page loads without waiting for Docker API on every request
- Business goal: Responsive dashboard even with many containers
- Priority: Medium — performance optimization

**Technical Implementation**:
- Solution: Background CollectContainers job runs every 10s → stores latest list in ContainerStore
- Architecture: ReactPHP periodic timer → DockerService::listAll() → in-memory store → Handlers read from store
- Trade-offs: Up to 10s staleness for home page loads vs instant Docker API calls; manual refresh always fetches fresh

**Connection**:
Decoupling the read path from the Docker API means the home page loads instantly from memory. The 10s collection interval keeps data reasonably fresh without hammering the Docker daemon. Manual refresh (POST /containers/refresh) triggers a fresh Docker API call and updates the store, so the user always gets current data on demand.

## Trade-off Decisions

| Situation | Business Priority | Technical Priority | Decision Made | Rationale |
|-----------|-------------------|-------------------|---------------|-----------|
| Frontend framework | Simple, fast to build | Maintainable, scalable | No framework (HTMX + SSE) | Dashboard is simple enough; SPA complexity not justified |
| Real-time updates | Live telemetry visible | Efficient server resources | SSE (not WebSocket) | Unidirectional data flow; SSE is simpler and sufficient |
| Data freshness | Near real-time container status | Don't overload Docker daemon | 10s polling + in-memory store + fresh-on-refresh | Good balance of freshness and performance |
| Language choice | Fast iteration, easy maintenance | Async I/O for Docker/SSE | PHP + ReactPHP | PHP's iteration speed + ReactPHP's async event loop |

## Common Misalignments

| Misalignment | Warning Signs | Resolution Approach |
|--------------|---------------|---------------------|
| Adding SPA complexity | Someone proposes "let's add React for better UI" | Evaluate: does the feature actually need client-side state? HTMX + SSE already covers dynamic updates. |
| Over-engineering infrastructure | Proposing Redis, message queues, microservices | Ask: is this a single-user dashboard or a multi-tenant SaaS? Keep it simple. |
| Premature optimization | Optimizing for thousands of containers | Current target is solo devs with <100 containers. Profile before optimizing. |

## Stakeholder Communication

**For the Developer**:
- Every technical choice serves the goal of simplicity — zero-config, single binary, no JavaScript framework
- The hypermedia architecture (HTMX + SSE) is a deliberate pattern, not a limitation

**For Potential Contributors**:
- Read `decisions-log.md` to understand why things are the way they are
- The `Handler → Action → Service` layering is consistent across all features
- New features should follow the same pattern

## Onboarding Checklist

- [x] Understand the core business needs this project addresses
- [x] See how each major feature maps to business value
- [x] Know the key trade-offs and why decisions were made
- [x] Be able to explain why the hypermedia architecture was chosen

## Related Files

- `business-domain.md` — Business needs in detail
- `technical-domain.md` — Technical implementation in detail
- `decisions-log.md` — Decisions made with full context
- `living-notes.md` — Current open questions and issues
