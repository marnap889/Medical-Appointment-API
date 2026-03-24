from __future__ import annotations

import json
import shutil
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat()


def write_jsonl(path: Path, event: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("a", encoding="utf-8") as handle:
        handle.write(json.dumps(event, ensure_ascii=False) + "\n")


def write_text(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


def append_text(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("a", encoding="utf-8") as handle:
        handle.write(content)


def sync_tree_incremental(
    source: Path,
    target: Path,
    manifest_path: Path,
    *,
    max_files: int = 2000,
) -> dict[str, int]:
    if max_files <= 0:
        max_files = 1

    if not source.exists():
        return {"total_files": 0, "scanned_files": 0, "copied_files": 0, "copied_bytes": 0}

    manifest: dict[str, str] = {}
    if manifest_path.exists():
        try:
            manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        except json.JSONDecodeError:
            manifest = {}

    all_files = [item for item in source.rglob("*") if item.is_file()]
    all_files.sort(key=lambda item: item.stat().st_mtime_ns, reverse=True)
    selected = all_files[:max_files]

    new_manifest: dict[str, str] = {}
    copied_files = 0
    copied_bytes = 0

    for item in selected:
        stat = item.stat()
        relative = item.relative_to(source)
        relative_key = relative.as_posix()
        signature = f"{stat.st_size}:{stat.st_mtime_ns}"
        new_manifest[relative_key] = signature

        destination = target / relative
        if manifest.get(relative_key) == signature and destination.exists():
            continue

        destination.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(item, destination)
        copied_files += 1
        copied_bytes += stat.st_size

    manifest_path.parent.mkdir(parents=True, exist_ok=True)
    manifest_path.write_text(json.dumps(new_manifest, ensure_ascii=False, indent=2), encoding="utf-8")

    return {
        "total_files": len(all_files),
        "scanned_files": len(selected),
        "copied_files": copied_files,
        "copied_bytes": copied_bytes,
    }
