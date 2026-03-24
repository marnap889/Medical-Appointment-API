# AI workflow operating model

## Roles

Specialist agents:
- Architecture Planner
- Implementation Agent
- Review Agent
- Test Agent
- Security Agent

Orchestration layer:
- Synthesis Orchestrator (conflict resolution + final execution plan)

## Workflow

1. Clarify scope and acceptance criteria
2. Select an active milestone from `docs/ROADMAP-MILESTONES.md`
3. Run one specialist role for deep work (`ai_run_workflow.sh <role>`)
4. Run 5 specialist agents in parallel (`ai_run_parallel_workflow.sh`)
5. Execute synthesis pass over all specialist outputs
6. Record decision log, summary and git snapshot
7. Package evidence for audit/review

## Conflict resolution policy (Synthesis)

Strict priority order:
1. security/privacy and data protection
2. correctness and regression risk
3. architecture consistency and maintainability
4. delivery speed and implementation effort

## Operational commands

- `./ai-orchestration/scripts/ai_bootstrap_runtime.sh`
- `./ai-orchestration/scripts/ai_run_workflow.sh <architecture|implementation|review|testing|security|synthesis> "<task>"`
- `./ai-orchestration/scripts/ai_run_parallel_workflow.sh "<task>"`
- `./ai-orchestration/scripts/ai_package_evidence.sh`

Compatibility alias:
- `./ai-orchestration/scripts/ai_run_parallel_review.sh "<task>"` (delegates to parallel workflow)

## Local tooling requirements

- Docker + Docker Compose plugin
- Python 3.11+ with `venv`
- Node.js 20+ (`npx codex mcp-server`)
- Codex CLI via `npx codex`
- OpenAI API key in `ai-orchestration/.env`

## Orchestration verification checklist

1. Copy env templates:
   - `cp .env.dist .env`
   - `cp ai-orchestration/.env.dist ai-orchestration/.env`
2. Bootstrap runtime:
   - `./ai-orchestration/scripts/ai_bootstrap_runtime.sh`
3. Validate wiring and syntax:
   - `make -n ai-bootstrap ai-workflow ROLE=synthesis TASK="test" ai-parallel ai-evidence`
   - `bash -n ai-orchestration/scripts/ai_*.sh`
   - `python3 -m py_compile ai-orchestration/ai_*.py`
4. Run one specialist flow:
   - `./ai-orchestration/scripts/ai_run_workflow.sh architecture "Design booking invariants"`
5. Run parallel 5-agent flow with synthesis:
   - `./ai-orchestration/scripts/ai_run_parallel_workflow.sh "Review booking flow and propose next steps"`
6. Validate generated artifacts:
   - `ls -la ai-orchestration/runtime/logs`
   - `./ai-orchestration/scripts/ai_package_evidence.sh`

## Logging

All AI workflow evidence should land inside `ai-orchestration/runtime/logs/`:
- `raw_transcripts/`
- `terminal/`
- `agent_runs/`
- `git_history/`
- `decisions/`
- `summaries/`
- `tooling/`

Session mirroring defaults:
- `CODEX_SESSION_MIRROR_MODE=repo-only`
- `CODEX_SESSION_MIRROR_MAX_FILES=2000`

## Human role

The human remains responsible for:
- choosing trade-offs
- accepting/rejecting AI suggestions
- protecting security/privacy boundaries
- ensuring final correctness

## Milestone-driven execution

- milestone definitions live in `docs/ROADMAP-MILESTONES.md`
- agents should execute one milestone at a time
- each completed milestone should end with one commit
