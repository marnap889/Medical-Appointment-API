#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ai_common.sh
source "${SCRIPT_DIR}/ai_common.sh"

TASK="${*:-Review the booking module and propose next steps}"
TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_FILE="${AI_LOGS_TERMINAL}/${TIMESTAMP}-parallel.log"

ensure_runtime_dirs
activate_or_fail

(
  cd "${AI_ROOT}"
  PARALLEL_TASK="${TASK}" PYTHONUNBUFFERED=1 python3 ai_run_parallel_workflow.py
) 2>&1 | tee "${LOG_FILE}"

capture_git_state "${TIMESTAMP}" "parallel"
