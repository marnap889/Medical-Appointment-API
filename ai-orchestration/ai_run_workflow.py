from __future__ import annotations

import asyncio
import hashlib
import logging
import os
import subprocess
import sys
import time
from typing import Any
from pathlib import Path

from dotenv import load_dotenv
from agents import Agent, Runner, trace
from agents.exceptions import MaxTurnsExceeded
from agents.mcp import MCPServerStdio
from agents.items import MCPApprovalRequestItem, MCPApprovalResponseItem, MCPListToolsItem, ToolCallItem, ToolCallOutputItem
import mcp.client.session as mcp_client_session
import mcp.types as mcp_types

from ai_codex_mcp import build_codex_mcp_server
from ai_logging_utils import (
    append_text,
    render_decision_template,
    sync_tree_incremental,
    utc_now,
    write_jsonl,
    write_text,
)

PROJECT_ROOT = Path(__file__).resolve().parents[1]
AI_ROOT = PROJECT_ROOT / "ai-orchestration"
PROMPTS = AI_ROOT / "prompts"
LOGS = AI_ROOT / "runtime" / "logs"
RAW_TRANSCRIPTS = LOGS / "raw_transcripts"
AGENT_RUNS = LOGS / "agent_runs"
SUMMARIES = LOGS / "summaries"
DECISIONS = LOGS / "decisions"
DECISION_TEMPLATE = DECISIONS / "TEMPLATE.md"
DEFAULT_CODEX_SESSIONS_DIR = PROJECT_ROOT / ".codex" / "sessions"

ROLE_TO_PROMPT = {
    "architecture": "architecture.md",
    "implementation": "implementation.md",
    "review": "review.md",
    "testing": "testing.md",
    "security": "security.md",
    "synthesis": "synthesis.md",
}


class CodexEventValidationNoiseFilter(logging.Filter):
    def filter(self, record: logging.LogRecord) -> bool:
        message = record.getMessage()
        if "method='codex/event'" not in message:
            return True
        if "Failed to validate notification:" in message:
            return False
        if "Failed to validate request:" in message:
            return False
        return True


def configure_runtime_logging() -> None:
    mode = os.getenv("CODEX_MCP_VALIDATION_WARNINGS", "suppress").strip().lower()
    if mode in {"on", "true", "1", "show"}:
        return

    root_logger = logging.getLogger()
    if any(isinstance(existing_filter, CodexEventValidationNoiseFilter) for existing_filter in root_logger.filters):
        return
    root_logger.addFilter(CodexEventValidationNoiseFilter())


def configure_mcp_elicitation_fallback() -> None:
    mode = os.getenv("CODEX_MCP_ELICITATION_MODE", "fallback").strip().lower()
    if mode in {"off", "disabled", "error"}:
        return

    action = os.getenv("CODEX_MCP_ELICITATION_ACTION", "decline").strip().lower()
    if action not in {"accept", "decline", "cancel"}:
        action = "decline"

    def _pick_enum_value(options: list[Any], approve: bool) -> Any:
        if not options:
            return "approved" if approve else "denied"
        if not approve:
            for option in options:
                text = str(option).lower()
                if any(
                    marker in text
                    for marker in ("deny", "denied", "decline", "reject", "no", "false", "block")
                ):
                    return option
            return options[-1]
        for option in options:
            text = str(option).lower()
            if any(
                marker in text
                for marker in ("allow", "approve", "approved", "accept", "yes", "true", "grant")
            ):
                return option
        return options[0]

    def _value_for_schema(field_name: str, field_schema: dict[str, Any], approve: bool) -> Any:
        enum_values = field_schema.get("enum")
        if isinstance(enum_values, list):
            return _pick_enum_value(enum_values, approve)

        field_type = field_schema.get("type")
        if isinstance(field_type, list):
            field_type = field_type[0] if field_type else "string"

        if field_type == "boolean":
            return approve
        if field_type in {"integer", "number"}:
            return 1 if approve else 0
        if field_name.lower() == "decision":
            return "approved" if approve else "denied"
        return "approved" if approve else "denied"

    def _build_elicitation_content(params: mcp_types.ElicitRequestParams, approve: bool) -> dict[str, Any]:
        requested_schema = getattr(params, "requestedSchema", None)
        if not isinstance(requested_schema, dict):
            return {"decision": "approved" if approve else "denied"}

        properties = requested_schema.get("properties")
        if not isinstance(properties, dict):
            properties = {}

        required = requested_schema.get("required")
        required_fields: list[str] = required if isinstance(required, list) else []

        content: dict[str, Any] = {}
        for field_name in required_fields:
            field_schema = properties.get(field_name)
            if isinstance(field_schema, dict):
                content[field_name] = _value_for_schema(str(field_name), field_schema, approve)

        if "decision" in properties and "decision" not in content:
            decision_schema = properties.get("decision")
            if isinstance(decision_schema, dict):
                content["decision"] = _value_for_schema("decision", decision_schema, approve)
            else:
                content["decision"] = "approved" if approve else "denied"

        if "decision" not in content:
            content["decision"] = "approved" if approve else "denied"

        return content

    async def _noninteractive_elicitation_callback(
        context: mcp_client_session.RequestContext[mcp_client_session.ClientSession, object],
        params: mcp_types.ElicitRequestParams,
    ) -> mcp_types.ElicitResult | mcp_types.ErrorData:
        message = getattr(params, "message", "")
        approve = action == "accept"

        if mode in {"prompt", "interactive"} and sys.stdin.isatty():
            try:
                user_input = input(f"[MCP approval] {message} [y/N]: ").strip().lower()
                approve = user_input in {"y", "yes"}
            except EOFError:
                approve = False

        content = _build_elicitation_content(params, approve)
        logging.getLogger(__name__).warning(
            "MCP elicitation resolved in %s mode: approve=%s message=%s content=%s",
            mode,
            approve,
            message,
            content,
        )
        # Some Codex approval payloads expect fields (for example `decision`) at the top level.
        # Mirror submitted form content both in `content` and as extra top-level keys.
        return mcp_types.ElicitResult(action="accept", content=content, **content)

    mcp_client_session._default_elicitation_callback = _noninteractive_elicitation_callback


def load_prompt(name: str) -> str:
    return (PROMPTS / name).read_text(encoding="utf-8")


def collect_run_activity(result: object) -> dict[str, object]:
    new_items = getattr(result, "new_items", [])
    counts: dict[str, int] = {}
    for item in new_items:
        item_name = type(item).__name__
        counts[item_name] = counts.get(item_name, 0) + 1

    mcp_items = (
        counts.get(ToolCallItem.__name__, 0)
        + counts.get(ToolCallOutputItem.__name__, 0)
        + counts.get(MCPListToolsItem.__name__, 0)
        + counts.get(MCPApprovalRequestItem.__name__, 0)
        + counts.get(MCPApprovalResponseItem.__name__, 0)
    )
    return {
        "item_counts": counts,
        "mcp_activity_count": mcp_items,
        "used_mcp_tools": mcp_items > 0,
    }


def build_implementation_retry_task(task: str, previous_output: str, activity: dict[str, object]) -> str:
    return (
        f"{task}\n\n"
        "Recovery constraints for this retry:\n"
        "- Previous attempt produced no repository changes.\n"
        f"- Previous MCP activity summary: {activity}\n"
        "- You must use MCP tools to apply concrete file edits in this repository before responding.\n"
        "- Before final response, verify real changes using repository state (git status / git diff).\n"
        "- If changes still cannot be applied, output BLOCKED and provide concrete blocker evidence.\n\n"
        "Previous output to correct:\n"
        f"{previous_output}"
    )


def build_testing_retry_task(
    task: str,
    previous_output: str,
    activity: dict[str, object],
    changed_paths: set[str],
    invalid_paths: list[str],
) -> str:
    changed_sample = sorted(changed_paths)[:20]
    invalid_sample = invalid_paths[:20]
    return (
        f"{task}\n\n"
        "Recovery constraints for this retry:\n"
        "- Previous attempt failed testing workflow write-policy validation.\n"
        f"- Previous MCP activity summary: {activity}\n"
        f"- Previous changed paths sample: {changed_sample}\n"
        f"- Previous invalid paths sample (outside allowed testing paths): {invalid_sample}\n"
        "- You must use MCP tools to apply concrete file edits in this repository before responding.\n"
        "- You may modify only files under tests/ and src/DataFixtures/ unless the task explicitly requests another path.\n"
        "- Before final response, verify changed files using repository state (git status / git diff).\n"
        "- If no compliant test changes can be applied, output BLOCKED with concrete blocker evidence.\n\n"
        "Previous output to correct:\n"
        f"{previous_output}"
    )


async def execute_specialist(role: str, task: str, codex_server: MCPServerStdio) -> tuple[str, dict[str, object]]:
    instructions = load_prompt(ROLE_TO_PROMPT[role])

    agent = Agent(
        name=role.title() + " Agent",
        instructions=instructions,
        mcp_servers=[codex_server],
    )

    max_turns_default = 10
    max_turns_override = os.getenv("CODEX_AGENT_MAX_TURNS", "").strip()
    if max_turns_override.isdigit():
        max_turns_default = int(max_turns_override)
    if role == "implementation":
        implementation_override = os.getenv("CODEX_IMPLEMENTATION_MAX_TURNS", "").strip()
        if implementation_override.isdigit():
            max_turns_default = int(implementation_override)
        else:
            max_turns_default = max(max_turns_default, 20)
    if role == "testing":
        testing_override = os.getenv("CODEX_TESTING_MAX_TURNS", "").strip()
        if testing_override.isdigit():
            max_turns_default = int(testing_override)
        else:
            max_turns_default = max(max_turns_default, 20)

    progress_interval_raw = os.getenv("CODEX_PROGRESS_INTERVAL_SEC", "20").strip()
    progress_interval = int(progress_interval_raw) if progress_interval_raw.isdigit() else 20
    progress_interval = max(5, progress_interval)

    start = time.monotonic()
    print(
        f"[progress] role={role} status=started max_turns={max_turns_default}",
        flush=True,
    )

    run_task = asyncio.create_task(Runner.run(agent, task, max_turns=max_turns_default))
    while not run_task.done():
        await asyncio.sleep(progress_interval)
        if run_task.done():
            break
        elapsed = int(time.monotonic() - start)
        print(
            f"[progress] role={role} status=running elapsed_sec={elapsed}",
            flush=True,
        )

    try:
        result = await run_task
    except MaxTurnsExceeded as exc:
        elapsed_total = int(time.monotonic() - start)
        print(
            f"[progress] role={role} status=max_turns_exceeded elapsed_sec={elapsed_total}",
            flush=True,
        )
        return (
            (
                "BLOCKED: agent exceeded max turns before finishing. "
                f"Details: {exc}"
            ),
            {"item_counts": {}, "mcp_activity_count": 0, "used_mcp_tools": False},
        )
    elapsed_total = int(time.monotonic() - start)
    print(
        f"[progress] role={role} status=finished elapsed_sec={elapsed_total}",
        flush=True,
    )
    return str(result.final_output), collect_run_activity(result)


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


def get_repo_file_snapshot() -> dict[str, str] | None:
    if not (PROJECT_ROOT / ".git").exists():
        return None

    result = subprocess.run(
        ["git", "-C", str(PROJECT_ROOT), "ls-files", "-z", "--cached", "--others", "--exclude-standard"],
        check=False,
        capture_output=True,
    )
    if result.returncode != 0:
        return None

    files = [path for path in result.stdout.decode("utf-8", errors="ignore").split("\0") if path]
    snapshot: dict[str, str] = {}
    for file_path in files:
        absolute_path = PROJECT_ROOT / file_path
        if not absolute_path.is_file():
            continue
        try:
            digest = hashlib.sha256(absolute_path.read_bytes()).hexdigest()
        except OSError:
            continue
        snapshot[file_path] = digest
    return snapshot


def get_changed_paths(
    before: dict[str, str] | None,
    after: dict[str, str] | None,
) -> set[str]:
    if before is None or after is None:
        return set()

    changed: set[str] = set()
    for path in set(before) | set(after):
        if before.get(path) != after.get(path):
            changed.add(path)
    return changed


def is_allowed_testing_change(path: str) -> bool:
    return path.startswith("tests/") or path.startswith("src/DataFixtures/")


async def main() -> None:
    load_dotenv(AI_ROOT / ".env")
    configure_runtime_logging()
    configure_mcp_elicitation_fallback()
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
    repo_snapshot_before = get_repo_file_snapshot() if workflow in {"implementation", "testing"} else None

    write_jsonl(jsonl_path, {
        "ts": utc_now(),
        "event": "workflow_started",
        "workflow": workflow,
        "task": task,
    })

    blocked_reason: str | None = None
    activity: dict[str, object] = {"item_counts": {}, "mcp_activity_count": 0, "used_mcp_tools": False}

    async with build_codex_mcp_server() as codex_server:
        with trace(workflow):
            if workflow not in {"implementation", "testing"}:
                output, activity = await execute_specialist(workflow, task, codex_server)
            else:
                attempts_env_name = (
                    "CODEX_IMPLEMENTATION_MAX_ATTEMPTS"
                    if workflow == "implementation"
                    else "CODEX_TESTING_MAX_ATTEMPTS"
                )
                max_attempts = os.getenv(attempts_env_name, "2").strip()
                attempts = int(max_attempts) if max_attempts.isdigit() else 2
                attempts = max(1, attempts)
                retry_task = task
                output = ""

                for attempt in range(1, attempts + 1):
                    print(
                        f"[progress] role={workflow} attempt={attempt}/{attempts} status=started",
                        flush=True,
                    )
                    output, activity = await execute_specialist(workflow, retry_task, codex_server)
                    repo_snapshot_after = get_repo_file_snapshot()
                    changed_paths = get_changed_paths(repo_snapshot_before, repo_snapshot_after)
                    changed = bool(changed_paths)
                    invalid_paths = (
                        [path for path in sorted(changed_paths) if not is_allowed_testing_change(path)]
                        if workflow == "testing"
                        else []
                    )
                    used_tools = bool(activity.get("used_mcp_tools", False))
                    print(
                        (
                            f"[progress] role={workflow} attempt={attempt}/{attempts} "
                            f"status=finished changed={changed} invalid_paths={len(invalid_paths)} "
                            f"used_mcp_tools={used_tools}"
                        ),
                        flush=True,
                    )

                    write_jsonl(jsonl_path, {
                        "ts": utc_now(),
                        "event": f"{workflow}_attempt",
                        "attempt": attempt,
                        "changed": changed,
                        "changed_paths_count": len(changed_paths),
                        "changed_paths_sample": sorted(changed_paths)[:20],
                        "invalid_paths_count": len(invalid_paths),
                        "invalid_paths_sample": invalid_paths[:20],
                        "used_mcp_tools": used_tools,
                        "activity": activity,
                    })

                    run_is_valid = changed and not invalid_paths
                    if run_is_valid:
                        break

                    if attempt < attempts:
                        print(
                            f"[progress] role={workflow} attempt={attempt}/{attempts} status=retrying",
                            flush=True,
                        )
                        if workflow == "implementation":
                            retry_task = build_implementation_retry_task(task, output, activity)
                        else:
                            retry_task = build_testing_retry_task(
                                task=task,
                                previous_output=output,
                                activity=activity,
                                changed_paths=changed_paths,
                                invalid_paths=invalid_paths,
                            )
                        continue

                    if workflow == "implementation":
                        blocked_reason = (
                            "No repository changes detected after implementation workflow run. "
                            "Treat this run as blocked and re-run with enforced concrete edits."
                        )
                    elif not changed:
                        blocked_reason = (
                            "No repository changes detected after testing workflow run. "
                            "Treat this run as blocked and re-run with enforced test edits."
                        )
                    else:
                        blocked_reason = (
                            "Testing workflow modified files outside allowed paths (tests/ and src/DataFixtures/). "
                            f"Invalid paths: {', '.join(invalid_paths[:20])}"
                        )
                    if not used_tools:
                        blocked_reason += " No MCP tool activity detected."
                    output = f"{output}\n\n## Execution status\n- blocked: {blocked_reason}\n"
                    write_jsonl(jsonl_path, {
                        "ts": utc_now(),
                        "event": f"{workflow}_blocked_policy_violation",
                        "workflow": workflow,
                        "task": task,
                        "changed_paths_count": len(changed_paths),
                        "changed_paths_sample": sorted(changed_paths)[:20],
                        "invalid_paths_count": len(invalid_paths),
                        "invalid_paths_sample": invalid_paths[:20],
                        "activity": activity,
                    })

    write_jsonl(jsonl_path, {
        "ts": utc_now(),
        "event": "workflow_finished",
        "workflow": workflow,
        "task": task,
        "summary_length": len(output),
        "activity": activity,
    })

    write_text(summary_path, f"# {workflow.title()} summary\n\n## Task\n{task}\n\n## Output\n{output}\n")
    decision_content = render_decision_template(
        DECISION_TEMPLATE,
        date=utc_now(),
        workflow=workflow,
        task=task,
        summary=f"See {summary_path.relative_to(PROJECT_ROOT).as_posix()}",
    )
    write_text(decisions_path, decision_content)

    mirror_sessions_if_enabled()

    append_text(LOGS / "tooling" / "runs.log", f"{utc_now()} workflow={workflow} task={task}\n")

    print(output)
    if blocked_reason is not None:
        raise SystemExit(blocked_reason)

if __name__ == "__main__":
    asyncio.run(main())
