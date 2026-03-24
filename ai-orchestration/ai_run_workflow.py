from __future__ import annotations

import asyncio
import os
import sys
from pathlib import Path

from dotenv import load_dotenv
from agents import Agent, Runner, trace
from agents.mcp import MCPServerStdio

from ai_codex_mcp import build_codex_mcp_server
from ai_logging_utils import append_text, sync_tree_incremental, utc_now, write_jsonl, write_text

PROJECT_ROOT = Path(__file__).resolve().parents[1]
AI_ROOT = PROJECT_ROOT / "ai-orchestration"
PROMPTS = AI_ROOT / "prompts"
LOGS = AI_ROOT / "runtime" / "logs"
RAW_TRANSCRIPTS = LOGS / "raw_transcripts"
AGENT_RUNS = LOGS / "agent_runs"
SUMMARIES = LOGS / "summaries"
DECISIONS = LOGS / "decisions"
DEFAULT_CODEX_SESSIONS_DIR = PROJECT_ROOT / ".codex" / "sessions"

ROLE_TO_PROMPT = {
    "architecture": "architecture.md",
    "implementation": "implementation.md",
    "review": "review.md",
    "testing": "testing.md",
    "security": "security.md",
    "synthesis": "synthesis.md",
}


def load_prompt(name: str) -> str:
    return (PROMPTS / name).read_text(encoding="utf-8")


async def execute_specialist(role: str, task: str, codex_server: MCPServerStdio) -> str:
    instructions = load_prompt(ROLE_TO_PROMPT[role])

    agent = Agent(
        name=role.title() + " Agent",
        instructions=instructions,
        mcp_servers=[codex_server],
    )

    result = await Runner.run(agent, task)
    return str(result.final_output)


def path_is_within(path: Path, parent: Path) -> bool:
    try:
        path.resolve().relative_to(parent.resolve())
    except ValueError:
        return False
    return True


def mirror_sessions_if_enabled() -> None:
    mirror_mode = os.getenv("CODEX_SESSION_MIRROR_MODE", "repo-only").strip().lower()
    if mirror_mode in {"off", "disabled", "0", "false", "no"}:
        return

    sessions_setting = os.getenv("CODEX_SESSIONS_DIR", "").strip()
    sessions_dir = Path(os.path.expanduser(sessions_setting)) if sessions_setting else DEFAULT_CODEX_SESSIONS_DIR
    max_files_value = os.getenv("CODEX_SESSION_MIRROR_MAX_FILES", "2000")
    try:
        max_files = int(max_files_value)
    except ValueError:
        max_files = 2000
    manifest_path = LOGS / "tooling" / "session_mirror_manifest.json"

    if mirror_mode == "repo-only" and not path_is_within(sessions_dir, PROJECT_ROOT):
        append_text(
            LOGS / "tooling" / "runs.log",
            f"{utc_now()} session_mirror_skipped dir={sessions_dir} reason=outside_repo\n",
        )
        return

    stats = sync_tree_incremental(sessions_dir, RAW_TRANSCRIPTS, manifest_path, max_files=max_files)
    append_text(
        LOGS / "tooling" / "runs.log",
        (
            f"{utc_now()} session_mirror_stats dir={sessions_dir} total={stats['total_files']} "
            f"scanned={stats['scanned_files']} copied={stats['copied_files']} bytes={stats['copied_bytes']}\n"
        ),
    )


def ensure_openai_api_key() -> None:
    if not os.getenv("OPENAI_API_KEY", "").strip():
        raise SystemExit("Missing OPENAI_API_KEY. Set it in ai-orchestration/.env before running workflows.")


async def main() -> None:
    load_dotenv(AI_ROOT / ".env")
    ensure_openai_api_key()
    if len(sys.argv) < 3:
        raise SystemExit("Usage: python ai_run_workflow.py <architecture|implementation|review|testing|security|synthesis> <task>")

    workflow = sys.argv[1]
    task = " ".join(sys.argv[2:])

    if workflow not in ROLE_TO_PROMPT:
        raise SystemExit(f"Unknown workflow '{workflow}'")

    run_id = utc_now().replace(":", "-")
    jsonl_path = AGENT_RUNS / f"{run_id}-{workflow}.jsonl"
    summary_path = SUMMARIES / f"{run_id}-{workflow}.md"
    decisions_path = DECISIONS / f"{run_id}-{workflow}.md"

    write_jsonl(jsonl_path, {
        "ts": utc_now(),
        "event": "workflow_started",
        "workflow": workflow,
        "task": task,
    })

    async with build_codex_mcp_server() as codex_server:
        with trace(workflow):
            output = await execute_specialist(workflow, task, codex_server)

    write_jsonl(jsonl_path, {
        "ts": utc_now(),
        "event": "workflow_finished",
        "workflow": workflow,
        "task": task,
        "summary_length": len(output),
    })

    write_text(summary_path, f"# {workflow.title()} summary\n\n## Task\n{task}\n\n## Output\n{output}\n")
    write_text(decisions_path, f"# Decision log\n\n- Workflow: {workflow}\n- Task: {task}\n- Timestamp: {utc_now()}\n\n## Human review\n- Fill in what you accepted, rejected, or changed after reviewing the AI output.\n")

    mirror_sessions_if_enabled()

    append_text(LOGS / "tooling" / "runs.log", f"{utc_now()} workflow={workflow} task={task}\n")

    print(output)

if __name__ == "__main__":
    asyncio.run(main())
