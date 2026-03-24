from __future__ import annotations

import os
from pathlib import Path

from agents.mcp import MCPServerStdio

PROJECT_ROOT = Path(__file__).resolve().parents[1]


def build_codex_mcp_server() -> MCPServerStdio:
    approval_policy = os.getenv("CODEX_MCP_APPROVAL_POLICY", "never").strip() or "never"
    sandbox_mode = os.getenv("CODEX_MCP_SANDBOX_MODE", "workspace-write").strip() or "workspace-write"

    return MCPServerStdio(
        name="Codex CLI",
        params={
            "command": "npx",
            "args": [
                "-y",
                "codex",
                "-a",
                approval_policy,
                "-s",
                sandbox_mode,
                "-C",
                str(PROJECT_ROOT),
                "mcp-server",
                "-c",
                f'approval_policy="{approval_policy}"',
            ],
            "cwd": str(PROJECT_ROOT),
        },
        client_session_timeout_seconds=360000,
    )
