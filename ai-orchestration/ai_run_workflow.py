from __future__ import annotations

import asyncio
import logging
import os
import subprocess
import sys
from typing import Any
from pathlib import Path

from dotenv import load_dotenv
from agents import Agent, Runner, trace
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

    result = await Runner.run(agent, task, max_turns=max_turns_default)
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


def get_repo_state_signature() -> str | None:
    if not (PROJECT_ROOT / ".git").exists():
        return None

    def git_output(*args: str) -> str:
        result = subprocess.run(
            ["git", "-C", str(PROJECT_ROOT), *args],
            check=False,
            capture_output=True,
            text=True,
        )
        if result.returncode != 0:
            return ""
        return result.stdout

    status = git_output("status", "--porcelain=v1", "--untracked-files=all")
    diff_unstaged = git_output("diff", "--no-ext-diff")
    diff_staged = git_output("diff", "--cached", "--no-ext-diff")
    return f"STATUS\n{status}\nDIFF\n{diff_unstaged}\nCACHED\n{diff_staged}"


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
    repo_state_before = get_repo_state_signature() if workflow == "implementation" else None

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
            if workflow != "implementation":
                output, activity = await execute_specialist(workflow, task, codex_server)
            else:
                max_attempts = os.getenv("CODEX_IMPLEMENTATION_MAX_ATTEMPTS", "2").strip()
                attempts = int(max_attempts) if max_attempts.isdigit() else 2
                attempts = max(1, attempts)
                retry_task = task
                output = ""

                for attempt in range(1, attempts + 1):
                    output, activity = await execute_specialist(workflow, retry_task, codex_server)
                    repo_state_after = get_repo_state_signature()
                    changed = repo_state_before is not None and repo_state_after != repo_state_before
                    used_tools = bool(activity.get("used_mcp_tools", False))

                    write_jsonl(jsonl_path, {
                        "ts": utc_now(),
                        "event": "implementation_attempt",
                        "attempt": attempt,
                        "changed": changed,
                        "used_mcp_tools": used_tools,
                        "activity": activity,
                    })

                    if changed:
                        break

                    if attempt < attempts:
                        retry_task = build_implementation_retry_task(task, output, activity)
                        continue

                    blocked_reason = (
                        "No repository changes detected after implementation workflow run. "
                        "Treat this run as blocked and re-run with enforced concrete edits."
                    )
                    if not used_tools:
                        blocked_reason += " No MCP tool activity detected."
                    output = f"{output}\n\n## Execution status\n- blocked: {blocked_reason}\n"
                    write_jsonl(jsonl_path, {
                        "ts": utc_now(),
                        "event": "implementation_blocked_no_repo_changes",
                        "workflow": workflow,
                        "task": task,
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
