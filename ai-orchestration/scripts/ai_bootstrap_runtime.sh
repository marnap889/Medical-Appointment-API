#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ai_common.sh
source "${SCRIPT_DIR}/ai_common.sh"

ensure_runtime_dirs

echo "AI runtime folders prepared under ${AI_RUNTIME_ROOT}."
echo "Session mirroring defaults to repo-only mode (.codex/sessions)."
echo "Set CODEX_SESSION_MIRROR_MODE=all if you intentionally want to mirror an external sessions directory."
echo "Use CODEX_SESSION_MIRROR_MAX_FILES to bound mirrored transcript scope (default: 2000 files)."
