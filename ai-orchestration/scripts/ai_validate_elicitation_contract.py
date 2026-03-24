#!/usr/bin/env python3
from __future__ import annotations

import asyncio
import os
import sys
from pathlib import Path

import mcp.client.session as mcp_client_session
import mcp.types as mcp_types


AI_ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(AI_ROOT))

import ai_run_workflow  # noqa: E402


async def resolve_decision(auto_action: str, schema: dict[str, object]) -> str:
    os.environ["CODEX_MCP_ELICITATION_MODE"] = "fallback"
    os.environ["CODEX_MCP_ELICITATION_ACTION"] = auto_action
    ai_run_workflow.configure_mcp_elicitation_fallback()

    callback = mcp_client_session._default_elicitation_callback
    params = mcp_types.ElicitRequestFormParams(
        message="contract-check",
        mode="form",
        requestedSchema=schema,
    )
    result = await callback(None, params)
    payload = result.model_dump()
    decision = payload.get("decision")
    if isinstance(decision, str):
        return decision
    content = payload.get("content")
    if isinstance(content, dict):
        nested = content.get("decision")
        if isinstance(nested, str):
            return nested
    raise RuntimeError(f"Missing decision in elicitation response payload: {payload}")


async def main() -> int:
    strict_schema: dict[str, object] = {
        "type": "object",
        "properties": {
            "decision": {
                "type": "string",
                "enum": [
                    "approved",
                    "approved_for_session",
                    "approved_execpolicy_amendment",
                    "network_policy_amendment",
                    "denied",
                    "abort",
                ],
            }
        },
        "required": ["decision"],
    }
    loose_schema: dict[str, object] = {
        "type": "object",
        "properties": {"decision": {"type": "string"}},
        "required": ["decision"],
    }

    approved = await resolve_decision("accept", strict_schema)
    if approved not in {
        "approved",
        "approved_for_session",
        "approved_execpolicy_amendment",
        "network_policy_amendment",
    }:
        raise RuntimeError(f"Invalid approve decision variant: {approved}")

    denied_strict = await resolve_decision("decline", strict_schema)
    if denied_strict not in {"denied", "abort"}:
        raise RuntimeError(f"Invalid deny decision variant for strict schema: {denied_strict}")

    denied_loose = await resolve_decision("decline", loose_schema)
    if denied_loose != "denied":
        raise RuntimeError(f"Invalid deny decision variant for loose schema: {denied_loose}")

    return 0


if __name__ == "__main__":
    raise SystemExit(asyncio.run(main()))
