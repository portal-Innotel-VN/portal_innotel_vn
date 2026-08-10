---
name: audit-agent-environment
description: Audits workspace Agent configuration, required directories, JSON files, executable launchers, and discovery paths without modifying them. Use when validating or troubleshooting this project's Agentic Development environment.
---

# Audit Agent Environment

Perform a read-only audit and report evidence. Do not repair files unless the user explicitly requests a fix.

## Audit workflow

1. Resolve the repository root with `git rev-parse --show-toplevel` when Git is available.
2. List `.agents`, `.Antigravity`, and `scripts/mcp-inspector.sh` with `find` or `ls`.
3. Confirm that `rules`, `skills`, `mcp`, `workflows`, and `plugins` each contain a `README.md` and one sample.
4. Parse every JSON file with an available JSON parser; do not install one.
5. Confirm `scripts/mcp-inspector.sh` is readable, executable, and passes `bash -n`.
6. Confirm `.agents/rules` is readable and report the exact rule files discovered.
7. Run network-dependent checks only after the user approves downloads or external access.
8. Report passed checks, failed checks, and any manual action separately.
