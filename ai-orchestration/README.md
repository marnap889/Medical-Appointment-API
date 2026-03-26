# AI orchestration layer

This folder contains the orchestration layer for an AI-first engineering workflow:

- runs specialist agents (architecture, implementation, review, testing, security)
- runs a synthesis orchestrator that resolves conflicts into one plan
- uses Codex CLI through MCP (`npx codex mcp-server`)
- stores structured logs under `ai-orchestration/runtime/logs/`
- packages reproducible evidence under `ai-orchestration/runtime/evidence/`

## Local prerequisites

- Python 3.11+ with `venv`
- Node.js 20+ and `npx`
- Codex CLI available as `npx codex`
- OpenAI API key with model access

## Setup

```bash
cp .env.dist .env
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

Defaults:
- Codex MCP runs non-interactive by default: `CODEX_MCP_APPROVAL_POLICY=never`
- Codex MCP sandbox mode defaults to: `CODEX_MCP_SANDBOX_MODE=workspace-write`
- noisy MCP validation warnings are suppressed by default: `CODEX_MCP_VALIDATION_WARNINGS=suppress`
- MCP elicitation requests are handled in fallback mode to avoid run crashes:
  `CODEX_MCP_ELICITATION_MODE=fallback`, `CODEX_MCP_ELICITATION_ACTION=decline`
- implementation role uses retries and higher turn budget by default:
  `CODEX_AGENT_MAX_TURNS=10`, `CODEX_IMPLEMENTATION_MAX_TURNS=20`, `CODEX_IMPLEMENTATION_MAX_ATTEMPTS=2`
- workflow progress heartbeat is printed during long runs:
  `CODEX_PROGRESS_INTERVAL_SEC=20` (minimum 5 seconds)
- session mirroring stays repo-local by default: `CODEX_SESSION_MIRROR_MODE=repo-only`
- mirrored transcript scope is bounded with `CODEX_SESSION_MIRROR_MAX_FILES=2000`

Elicitation modes:
- `CODEX_MCP_ELICITATION_MODE=prompt` asks in terminal (`[y/N]`) for each approval
- `CODEX_MCP_ELICITATION_MODE=fallback` uses automatic decision from `CODEX_MCP_ELICITATION_ACTION`
- set `CODEX_MCP_ELICITATION_ACTION=accept` for full auto-approve local runs

Approval payload contract:
- Codex MCP expects decision variants aligned with current protocol: `approved`, `approved_for_session`,
  `approved_execpolicy_amendment`, `network_policy_amendment`, `denied`, `abort`
- this project normalizes fallback answers to `approved`/`denied` (never `approve`/`deny`)

## Main entry points (from repository root)

```bash
./ai-orchestration/scripts/ai_bootstrap_runtime.sh
./ai-orchestration/scripts/ai_run_workflow.sh architecture "Design the next booking increment"
./ai-orchestration/scripts/ai_run_parallel_workflow.sh "Review current state and propose next steps"
./ai-orchestration/scripts/ai_package_evidence.sh
```

Compatibility alias:
- `./ai-orchestration/scripts/ai_run_parallel_review.sh` delegates to `ai_run_parallel_workflow.sh`

## Smoke test workflow orchestration

1. Initialize runtime folders:
   - `./ai-orchestration/scripts/ai_bootstrap_runtime.sh`
2. Verify command wiring (no API key/network needed):
   - `make -n ai-bootstrap ai-workflow ROLE=synthesis TASK="test" ai-parallel ai-evidence`
3. Verify shell script syntax:
   - `bash -n ai-orchestration/scripts/ai_*.sh`
4. Verify Python orchestration modules compile:
   - `python3 -m py_compile ai-orchestration/ai_*.py`
5. Run full orchestration (requires configured `.env` and network):
   - `./ai-orchestration/scripts/ai_run_workflow.sh implementation "Implement a minimal endpoint"`
   - `./ai-orchestration/scripts/ai_run_parallel_workflow.sh "Review implementation quality"`
6. Confirm artifacts were generated:
   - `ls -la ai-orchestration/runtime/logs`
   - `./ai-orchestration/scripts/ai_package_evidence.sh`
