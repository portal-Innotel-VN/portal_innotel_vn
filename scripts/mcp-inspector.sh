#!/usr/bin/env bash

set -euo pipefail

readonly inspector_package="@modelcontextprotocol/inspector"
allow_download=false

usage() {
  cat <<'EOF'
Usage: scripts/mcp-inspector.sh [--allow-download] [--] [inspector arguments...]

By default, the launcher only uses an already cached/local package.
Pass --allow-download to let npx download the Inspector when it is missing.

Examples:
  scripts/mcp-inspector.sh
  scripts/mcp-inspector.sh --allow-download
  scripts/mcp-inspector.sh --allow-download node /absolute/path/to/server.js
EOF
}

case "${1:-}" in
  --allow-download)
    allow_download=true
    shift
    ;;
  -h|--help)
    usage
    exit 0
    ;;
  --)
    shift
    ;;
esac

if ! command -v npx >/dev/null 2>&1; then
  echo "Error: npx is required but was not found in PATH." >&2
  exit 127
fi

if [[ "$allow_download" == "true" ]]; then
  exec npx --yes "$inspector_package" "$@"
fi

if npm_config_offline=true npx --yes "$inspector_package" "$@"; then
  exit 0
else
  status=$?
fi

cat >&2 <<'EOF'

MCP Inspector is not available in the local npm cache.
No package was downloaded. After explicitly approving a temporary npx download, run:

  ./scripts/mcp-inspector.sh --allow-download
EOF

exit "$status"
