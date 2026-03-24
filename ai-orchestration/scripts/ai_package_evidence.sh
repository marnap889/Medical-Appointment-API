#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ai_common.sh
source "${SCRIPT_DIR}/ai_common.sh"

TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
EXPORT_DIR="${AI_EVIDENCE_DIR}/export-${TIMESTAMP}"

ensure_runtime_dirs
mkdir -p "${EXPORT_DIR}"

cp -R "${AI_LOGS_DIR}" "${EXPORT_DIR}/logs"
cp -R "${AI_ROOT}/prompts" "${EXPORT_DIR}/prompts"
cp "${AI_ROOT}/README.md" "${EXPORT_DIR}/AI-README.md"
cp "${REPO_ROOT}/README.md" "${EXPORT_DIR}/README.md"
cp "${REPO_ROOT}/AGENTS.md" "${EXPORT_DIR}/AGENTS.md"
cp -R "${REPO_ROOT}/docs" "${EXPORT_DIR}/docs"
cp -R "${REPO_ROOT}/openapi" "${EXPORT_DIR}/openapi"

cat > "${EXPORT_DIR}/INDEX.md" <<INDEX
# Evidence Pack

Generated at: ${TIMESTAMP}

## Included materials
- logs/
- prompts/
- README.md
- AGENTS.md
- docs/
- openapi/

## Notes
- AI logs are generated under \`ai-orchestration/runtime/logs/\`.
- Session mirroring defaults to repo-local \`.codex/sessions\` (set \`CODEX_SESSION_MIRROR_MODE\` to override).
INDEX

tar -czf "${AI_EVIDENCE_DIR}/export-${TIMESTAMP}.tar.gz" -C "${AI_EVIDENCE_DIR}" "export-${TIMESTAMP}"

echo "Evidence packaged in ${EXPORT_DIR}"
echo "Archive created at ${AI_EVIDENCE_DIR}/export-${TIMESTAMP}.tar.gz"
