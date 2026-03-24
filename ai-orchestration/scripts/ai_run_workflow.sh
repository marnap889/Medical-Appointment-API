#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -lt 2 ]; then
  echo "Usage: ./ai-orchestration/scripts/ai_run_workflow.sh <architecture|implementation|review|testing|security|synthesis> <task...>"
  exit 1
fi

ROLE="$1"
shift
TASK="$*"

case "${ROLE}" in
  architecture|implementation|review|testing|security|synthesis) ;;
  *)
    echo "Unsupported role: ${ROLE}"
    exit 1
    ;;
esac

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ai_common.sh
source "${SCRIPT_DIR}/ai_common.sh"

ensure_runtime_dirs
activate_or_fail

TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_FILE="${AI_LOGS_TERMINAL}/${TIMESTAMP}-${ROLE}.log"

(
  cd "${AI_ROOT}"
  PYTHONUNBUFFERED=1 python3 ai_run_workflow.py "${ROLE}" "${TASK}"
) 2>&1 | tee "${LOG_FILE}"

capture_git_state "${TIMESTAMP}" "${ROLE}"
