<!-- Context: core/context-paths | Priority: low | Version: 1.1 | Updated: 2026-06-19 -->

---
id: context-paths
name: Context File Path Resolution
---

# Context File Path Resolution

## Resolution Order

Context files are loaded from the project's local `.opencode/context/` directory.

## What Goes Where

| Content Type | Recommended Location | Why |
|---|---|---|
| **Project Intelligence** (tech stack, patterns, naming) | `.opencode/context/project-intelligence/` | Project-specific, committed to git, shared with team |
| **Core Standards** (code-quality, docs, tests) | `.opencode/context/core/` | Universal standards, same across projects |

## Path Configuration

```json
{
  "paths": {
    "local": ".opencode/context"
  }
}
```

## Environment Variable Override

The installer supports `OPENCODE_INSTALL_DIR` to override the install location:

```bash
export OPENCODE_INSTALL_DIR=~/custom/path
bash install.sh developer
```

OpenCode itself supports `OPENCODE_CONFIG_DIR` for a custom config directory (see [OpenCode docs](https://opencode.ai/docs/config/)).

## Common Scenarios

### Scenario 1: Local Install (Development / Repo Maintainer)
- OAC installed locally via `bash install.sh developer`
- All context in `.opencode/context/`
- Committed to git, team shares everything

### Scenario 2: Adding Project Intelligence
- Run `/add-context` in project → creates `.opencode/context/project-intelligence/` locally
- Project intelligence committed to git, shared with team
