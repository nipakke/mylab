<!-- Context: project-intelligence/notes | Priority: high | Version: 1.0 | Updated: 2026-06-19 -->

# Living Notes — Dash

> Active issues, technical debt, open questions, and insights. Keep this alive.

## Quick Reference

- **Purpose**: Capture current state, problems, and open questions
- **Update**: Weekly or when status changes
- **Archive**: Move resolved items to bottom with status

## Technical Debt

| Item | Impact | Priority | Mitigation |
|------|--------|----------|------------|
| No error handling for Docker socket unavailable | Dashboard shows empty/error state | Medium | Add graceful fallback UI + retry logic |
| Twig CDN in dev mode (Tailwind/daisyUI) | No offline dev, version pinning | Low | Acceptable — prod uses compiled CSS |
| No authentication | Anyone with network access can see containers | Medium | Add basic auth or Tailscale requirement |
| Makefile lint only checks server.php | Rest of src/ not syntax-checked | Low | ✅ **Resolved 2026-06-19** — `composer lint` runs pint --test + parallel-lint + phpstan (level 8) |
| Stale `.vscode/settings.json` | Configured for Go, not PHP | Low | **Removed 2026-06-19** |

### Technical Debt Details

**No error handling for Docker socket unavailable**
- *Priority*: Medium
- *Impact*: If Docker daemon is stopped or socket permissions wrong, dashboard shows broken state
- *Root Cause*: Early-stage project — error handling not yet prioritized
- *Proposed Solution*: Catch DockerService exceptions, render error partial with troubleshooting tips
- *Effort*: Small
- *Status*: Acknowledged

## Open Questions

| Question | Status | Next Action |
|----------|--------|-------------|
| Should we add container log streaming? | Open | Evaluate SSE vs chunked transfer for log tailing |
| Should we add container start/stop/restart actions? | Open | Add action buttons with HTMX POST + confirmation |

## Known Issues

| Issue | Severity | Workaround | Status |
|-------|----------|------------|--------|
| None reported | — | — | — |

## Insights & Lessons Learned

### What Works Well
- **HTMX partials for table refresh** — Zero JavaScript, clean separation. The `/containers/refresh` endpoint returns exactly the rows needed, and HTMX swaps them in.
- **Immutable DTOs** — `Container` as a readonly class prevents accidental mutation in async context.
- **Handler → Action → Service layering** — Thin handlers, focused actions, swappable services. Easy to test each layer independently.

### What Could Be Better
- **Error handling** — No try/catch around Docker API calls. A Docker daemon restart breaks the dashboard silently.
- **Testing coverage** — Only 3 tests in `RenderTest.php`. No tests for Action or Service layers.
- **Configuration** — Docker socket path is hardcoded. Should be configurable via env var.
- **Rate limiting / DDoS** — Now handled via `RateLimiter` (token bucket per IP) + `IpResolver` (trusted proxy resolution). Configurable via `RATE_LIMIT`, `RATE_LIMIT_WINDOW`, `TRUST_PROXY`, `PROXY_IP_HEADER` env vars. See `.env.example`.

### Patterns & Conventions

### Code Patterns Worth Preserving
- **Action as use-case** — Each Action class does one thing (ListContainers, RefreshContainers). Returns a ReactPHP Promise for async operations.
- **Handler as thin adapter** — Handlers only parse the request, delegate to Action, and format the Response. No business logic in handlers.
- **Immutable DTOs with readonly properties** — PHP 8.2 readonly classes prevent accidental mutation.
- **Twig layout inheritance** — `layout.twig` defines base HTML shell; `home.twig` extends it; `container-rows.twig` is a reusable partial.

### Gotchas for Maintainers
- **ReactPHP is event-loop based** — Blocking I/O (file_get_contents, PDO) will stall the entire server. Always use async I/O.
- **Twig templates are recompiled in dev** — Set `APP_ENV=prod` to use cached templates. Cache directory is `cache/twig/`.
- **ContainerStore is in-memory only** — Restarting the server loses state. This is by design — Docker state is inherently ephemeral.


## Active Work

| Area | Goal | Status |
|------|------|--------|
| Core dashboard | Container listing | Done |
| Background collection | Periodic Docker polling every 10s → ContainerStore | Done |
| Rate limiting & DDoS protection | Token-bucket per IP via RateLimiter + IpResolver | **Done 2026-06-19** |
| Error handling | Graceful failure states | Pending |
| Container actions | Start/stop/restart via HTMX | Planned |
| Testing | Expand test coverage to Action/Service layers | Planned |

## Archive (Resolved Items)

### Resolved: Go → PHP migration
- **Resolved**: 2026-06-19
- **Resolution**: Fully migrated to PHP 8.2 + ReactPHP
- **Learnings**: PHP with ReactPHP is a viable async runtime. The clue/docker-react library is production-quality.

### Resolved: Monorepo → flat project
- **Resolved**: 2026-06-19
- **Resolution**: Flattened from Nx monorepo to single PHP project
- **Learnings**: Monorepo overhead isn't justified for single-application projects. HTMX eliminates the need for a separate frontend.

### Resolved: Project cruft cleanup
- **Resolved**: 2026-06-19
- **Resolution**: Removed Go binary, Go VS Code config, stale .tmp sessions (sveltekit, turborepo, laravel-inertia), PHPUnit cache. Updated README and all project-intelligence context files.
- **Learnings**: Project evolved through 4 architectures — regular cleanup prevents confusion.

## Onboarding Checklist

- [x] Review known technical debt and understand impact
- [x] Know what open questions exist
- [x] Understand current issues and workarounds
- [x] Be aware of patterns and gotchas
- [x] Know active projects and timelines

## Related Files

- `decisions-log.md` — Past decisions that inform current state
- `business-domain.md` — Business context for current priorities
- `technical-domain.md` — Technical context for current state
- `business-tech-bridge.md` — Context for current trade-offs
