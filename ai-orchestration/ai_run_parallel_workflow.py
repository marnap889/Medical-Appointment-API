from __future__ import annotations

import asyncio
import json
import os
from pathlib import Path

from dotenv import load_dotenv

from ai_codex_mcp import build_codex_mcp_server
from ai_logging_utils import append_text, utc_now, write_jsonl, write_text
from ai_run_workflow import ensure_openai_api_key, execute_specialist, mirror_sessions_if_enabled

PROJECT_ROOT = Path(__file__).resolve().parents[1]
AI_ROOT = PROJECT_ROOT / "ai-orchestration"
LOGS = AI_ROOT / "runtime" / "logs"
AGENT_RUNS = LOGS / "agent_runs"
SUMMARIES = LOGS / "summaries"
DECISIONS = LOGS / "decisions"
TOOLING = LOGS / "tooling"
SPECIALIST_ROLES = ["architecture", "implementation", "review", "testing", "security"]


async def execute_isolated_role(role: str, task: str) -> str:
    async with build_codex_mcp_server() as codex_server:
        return await execute_specialist(role, task, codex_server)


def build_synthesis_task(task: str, role_results: dict[str, dict[str, str]]) -> str:
    payload = {
        "task": task,
        "specialist_results": role_results,
        "orchestration_expectation": (
            "Resolve conflicts with strict priority: security/privacy > correctness > architecture > speed."
        ),
    }
    return (
        "Synthesize the specialist outputs into one executable plan.\n"
        "Use the provided JSON payload as the single source of truth.\n\n"
        f"{json.dumps(payload, ensure_ascii=False, indent=2)}"
    )


async def main() -> None:
    load_dotenv(AI_ROOT / ".env")
    ensure_openai_api_key()
    task = os.getenv("PARALLEL_TASK", "Review the current booking flow and propose the next best iteration.")
    run_id = utc_now().replace(":", "-")
    jsonl_path = AGENT_RUNS / f"{run_id}-parallel.jsonl"

    write_jsonl(jsonl_path, {"ts": utc_now(), "event": "parallel_started", "task": task, "roles": SPECIALIST_ROLES})

    coroutines = [execute_isolated_role(role, task) for role in SPECIALIST_ROLES]
    raw_results = await asyncio.gather(*coroutines, return_exceptions=True)

    role_results: dict[str, dict[str, str]] = {}
    for role, result in zip(SPECIALIST_ROLES, raw_results, strict=True):
        if isinstance(result, Exception):
            role_results[role] = {"status": "error", "output": f"{type(result).__name__}: {result}"}
            write_jsonl(
                jsonl_path,
                {"ts": utc_now(), "event": "role_failed", "role": role, "error": f"{type(result).__name__}: {result}"},
            )
            continue

        role_results[role] = {"status": "ok", "output": result}
        write_jsonl(
            jsonl_path,
            {"ts": utc_now(), "event": "role_finished", "role": role, "result_length": len(result)},
        )

    synthesis_input = build_synthesis_task(task, role_results)
    try:
        synthesis_output = await execute_isolated_role("synthesis", synthesis_input)
        synthesis_status = "ok"
        write_jsonl(
            jsonl_path,
            {"ts": utc_now(), "event": "synthesis_finished", "result_length": len(synthesis_output)},
        )
    except Exception as exc:
        synthesis_status = "error"
        synthesis_output = f"SynthesisError: {type(exc).__name__}: {exc}"
        write_jsonl(
            jsonl_path,
            {"ts": utc_now(), "event": "synthesis_failed", "error": synthesis_output},
        )

    markdown = "# Parallel workflow summary\n\n"
    markdown += f"## Task\n{task}\n\n"
    markdown += "## Specialist outputs\n\n"
    for role in SPECIALIST_ROLES:
        result = role_results[role]
        markdown += f"### {role.title()} ({result['status']})\n{result['output']}\n\n"

    markdown += f"## Synthesis ({synthesis_status})\n{synthesis_output}\n"

    write_text(SUMMARIES / f"{run_id}-parallel.md", markdown)
    write_text(
        DECISIONS / f"{run_id}-parallel.md",
        (
            "# Parallel decision log\n\n"
            f"- Task: {task}\n"
            f"- Timestamp: {utc_now()}\n"
            f"- Specialist roles: {', '.join(SPECIALIST_ROLES)}\n"
            f"- Synthesis status: {synthesis_status}\n\n"
            "## Human acceptance\n"
            "- Mark accepted recommendations\n"
            "- Mark rejected/deferred recommendations and rationale\n"
        ),
    )

    mirror_sessions_if_enabled()
    append_text(TOOLING / "runs.log", f"{utc_now()} workflow=parallel task={task}\n")
    print(markdown)


if __name__ == "__main__":
    asyncio.run(main())
