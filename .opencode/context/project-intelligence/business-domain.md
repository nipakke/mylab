<!-- Context: project-intelligence/business | Priority: high | Version: 1.0 | Updated: 2026-06-19 -->

# Business Domain — Dash

> Dash is a real-time Docker container dashboard that lets developers monitor their containers at a glance — no CLI needed.

## Quick Reference

- **Purpose**: Understand why this project exists
- **Update When**: Business direction changes, new features shipped, pivot
- **Audience**: Developers needing context, stakeholders, product team

## Project Identity

- **Project Name**: Dash
- **Tagline**: Real-time Docker container monitoring, zero JavaScript required
- **Problem Statement**: Developers running Docker containers need a quick visual overview of container status, resource usage, and health — without switching to the terminal or setting up complex monitoring stacks.
- **Solution**: A lightweight, self-hosted web dashboard that connects to the Docker socket and displays live container data via HTMX partials. Runs in a single binary with no external dependencies.

## Target Users

| User Segment | Who They Are | What They Need | Pain Points |
|--------------|--------------|----------------|-------------|
| Primary: Solo developers | Running Docker on their dev machine | Quick visual check of all containers | Switching to terminal, remembering docker ps flags |
| Secondary: Small teams | Running shared dev/staging environments | Shared visibility into container health | Complex monitoring tools (Grafana, Prometheus) are overkill |
| Tertiary: Homelab operators | Self-hosting services on a home server | Lightweight dashboard for personal infra | Want something simpler than Portainer |

## Value Proposition

**For Developers**:
- One-page view of all Docker containers with status, memory, and labels
- Auto-refreshes container list via HTMX partials
- Zero configuration — just point it at the Docker socket

**For the Project**:
- Demonstrates the hypermedia architecture pattern (HTMX + server-rendered HTML)
- Serves as a reference implementation for ReactPHP + Twig + Docker API
- Lightweight enough to run alongside development containers

## Success Metrics

| Metric | Definition | Target | Current |
|--------|------------|--------|---------|
| Works with Docker | Lists all running containers correctly | 100% | Works |
| Real-time updates | Container refresh via HTMX partials | <2s | Sub-second with caching |
| Single binary deploy | Runs via Docker with no external services | Yes | Dockerfile exists |
| Zero JS framework | No React/Vue/Svelte on the frontend | Yes | HTMX only |

## Key Stakeholders

| Role | Responsibility |
|------|----------------|
| Developer | Full-stack ownership — PHP backend, Twig templates, Docker integration |
| AI Agents (OAC) | Assisted development via .opencode/ context system |

## Roadmap Context

**Current Focus**: Stable container listing
**Next Milestone**: Container log viewing, start/stop/restart actions
**Long-term Vision**: Full Docker management dashboard — images, volumes, networks, compose stacks

## Business Constraints

- Must run with minimal resources (alongside dev containers) — hence the async event-loop architecture
- Must work with Docker socket access (no agent, no remote daemon required)

## Onboarding Checklist

- [x] Understand the problem statement
- [x] Identify target users and their needs
- [x] Know the key value proposition
- [x] Understand success metrics
- [x] Know who the stakeholders are

## Related Files

- `technical-domain.md` — How this business need is solved technically
- `business-tech-bridge.md` — Mapping between business and technical
- `decisions-log.md` — Business decisions with context
