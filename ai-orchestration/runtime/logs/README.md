# AI workflow logs

This directory is intentionally versioned as part of the AI workflow skeleton.

Generated evidence is grouped by purpose:
- `raw_transcripts/` - mirrored Codex session artifacts (repo-local by default)
- `terminal/` - shell output captures from workflow scripts
- `agent_runs/` - structured JSONL records from the orchestration layer
- `git_history/` - repository snapshots taken after workflow runs
- `decisions/` - human-curated architectural/engineering decisions
- `summaries/` - compact milestone handoff summaries
- `tooling/` - helper script and orchestration metadata

Artifacts are packaged from `ai-orchestration/runtime/` so AI evidence remains isolated from Symfony runtime/application code.
