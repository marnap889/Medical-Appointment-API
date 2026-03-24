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
- session mirroring stays repo-local by default: `CODEX_SESSION_MIRROR_MODE=repo-only`
- mirrored transcript scope is bounded with `CODEX_SESSION_MIRROR_MAX_FILES=2000`

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
   - `bash -n ai-orchestration/scripts/ai_*.sh scripts/ai_*.sh`
4. Verify Python orchestration modules compile:
   - `python3 -m py_compile ai-orchestration/ai_*.py`
5. Run full orchestration (requires configured `.env` and network):
   - `./ai-orchestration/scripts/ai_run_workflow.sh implementation "Implement a minimal endpoint"`
   - `./ai-orchestration/scripts/ai_run_parallel_workflow.sh "Review implementation quality"`
6. Confirm artifacts were generated:
   - `ls -la ai-orchestration/runtime/logs`
   - `./ai-orchestration/scripts/ai_package_evidence.sh`
