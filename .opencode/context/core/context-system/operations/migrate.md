<!-- Context: core/migrate | Priority: medium | Version: 1.1 | Updated: 2026-06-19 -->

# Context Migrate Operation

**Purpose**: Context files are always project-local (`.opencode/context/`). Migration from external sources is not applicable — all context lives in the project and is committed to git.

**Last Updated**: 2026-06-19

---

## Core Concept

All context files live in `.opencode/context/` within the project root. Project intelligence is always local — committed to git and shared with your team.

## What Gets Created

| File | Location | Purpose |
|------|----------|---------|
| `core/standards/` | `.opencode/context/core/standards/` | Universal coding standards |
| `core/context-system/` | `.opencode/context/core/context-system/` | Context system operations |
| `core/workflows/` | `.opencode/context/core/workflows/` | Development workflows |
| `project-intelligence/` | `.opencode/context/project-intelligence/` | Project-specific patterns |

## Related

- `/add-context` — Create new project intelligence (interactive wizard)
- `/context harvest` — Extract knowledge from summaries
- Context path resolution: `.opencode/context/core/system/context-paths.md`
