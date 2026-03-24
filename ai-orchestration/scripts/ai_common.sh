#!/usr/bin/env bash
set -euo pipefail

AI_SCRIPTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
AI_ROOT="$(cd "${AI_SCRIPTS_DIR}/.." && pwd)"
REPO_ROOT="$(cd "${AI_ROOT}/.." && pwd)"
AI_RUNTIME_ROOT="${AI_ROOT}/runtime"
AI_LOGS_DIR="${AI_RUNTIME_ROOT}/logs"
AI_EVIDENCE_DIR="${AI_RUNTIME_ROOT}/evidence"

AI_LOGS_RAW_TRANSCRIPTS="${AI_LOGS_DIR}/raw_transcripts"
AI_LOGS_TERMINAL="${AI_LOGS_DIR}/terminal"
AI_LOGS_AGENT_RUNS="${AI_LOGS_DIR}/agent_runs"
AI_LOGS_GIT_HISTORY="${AI_LOGS_DIR}/git_history"
AI_LOGS_DECISIONS="${AI_LOGS_DIR}/decisions"
AI_LOGS_SUMMARIES="${AI_LOGS_DIR}/summaries"
AI_LOGS_TOOLING="${AI_LOGS_DIR}/tooling"

ensure_runtime_dirs() {
  mkdir -p \
    "${AI_LOGS_RAW_TRANSCRIPTS}" \
    "${AI_LOGS_TERMINAL}" \
    "${AI_LOGS_AGENT_RUNS}" \
    "${AI_LOGS_GIT_HISTORY}" \
    "${AI_LOGS_DECISIONS}" \
    "${AI_LOGS_SUMMARIES}" \
    "${AI_LOGS_TOOLING}" \
    "${AI_EVIDENCE_DIR}"
}

activate_or_fail() {
  if [ ! -d "${AI_ROOT}/.venv" ]; then
    echo "Missing ${AI_ROOT}/.venv"
    echo "Run: cd ${AI_ROOT} && python3 -m venv .venv && source .venv/bin/activate && pip install -r requirements.txt"
    exit 1
  fi

  # shellcheck source=/dev/null
  source "${AI_ROOT}/.venv/bin/activate"
}

capture_git_state() {
  local timestamp="$1"
  local suffix="$2"

  if command -v git >/dev/null 2>&1 && git -C "${REPO_ROOT}" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    git -C "${REPO_ROOT}" status --short > "${AI_LOGS_GIT_HISTORY}/${timestamp}-${suffix}-status.txt" || true
    git -C "${REPO_ROOT}" diff > "${AI_LOGS_GIT_HISTORY}/${timestamp}-${suffix}.patch" || true
  fi
}
