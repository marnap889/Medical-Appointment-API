from __future__ import annotations

from agents.mcp import MCPServerStdio


def build_codex_mcp_server() -> MCPServerStdio:
    return MCPServerStdio(
        name="Codex CLI",
        params={
            "command": "npx",
            "args": ["-y", "codex", "mcp-server"],
        },
        client_session_timeout_seconds=360000,
    )
