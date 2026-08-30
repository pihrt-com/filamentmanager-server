#!/usr/bin/env python3
"""PrusaSlicer post-processing helper for FilamentManager Server.

Configure FILAMENTMANAGER_URL, FILAMENTMANAGER_TOKEN and
FILAMENTMANAGER_PRINTER, then add this script under Print Settings > Output
options > Post-processing scripts. It reads metadata only and never modifies
the generated G-code. A server/network error does not block G-code export.
"""

from __future__ import annotations

import hashlib
import json
import os
import pathlib
import re
import sys
import urllib.error
import urllib.request


def split_values(value: str) -> list[str]:
    return [item.strip().strip('"') for item in re.split(r"[;,]", value) if item.strip()]


def parse(path: pathlib.Path) -> dict:
    metadata: dict[str, list[str]] = {}
    usage: list[float] = []
    keys = ("filament_type", "filament_colour", "filament_vendor", "filament_settings_id", "printer_model", "printer_settings_id", "estimated printing time (normal mode)")
    digest = hashlib.sha256()
    with path.open("rb") as source:
        for block in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(block)
    with path.open("r", encoding="utf-8", errors="replace") as source:
        for raw_line in source:
            line = raw_line.strip()
            match = re.match(r"^;\s*filament used \[g\]\s*=\s*(.+)$", line, re.I)
            if match:
                usage = [float(item) for item in re.split(r"[,;]", match.group(1)) if item.strip()]
            for key in keys:
                match = re.match(r"^;\s*" + re.escape(key) + r"\s*=\s*(.*)$", line, re.I)
                if match:
                    metadata[key] = split_values(match.group(1))
    if not usage:
        raise ValueError("G-code does not contain '; filament used [g] = ...' metadata")
    types = metadata.get("filament_type", [])
    colors = metadata.get("filament_colour", [])
    consumptions = []
    for index, grams in enumerate(usage):
        if grams <= 0:
            continue
        color = colors[index].upper() if index < len(colors) and re.fullmatch(r"#[0-9A-Fa-f]{6}", colors[index]) else None
        consumptions.append({"extruderIndex": index, "estimatedWeightG": round(grams, 2), "materialType": types[index] if index < len(types) else None, "colorHex": color})
    return {"fileName": path.name, "sha256": digest.hexdigest(), "metadata": metadata, "consumptions": consumptions}


def main() -> int:
    if len(sys.argv) < 2:
        print("Usage: filamentmanager-prusaslicer.py FILE.gcode", file=sys.stderr)
        return 0
    server = os.environ.get("FILAMENTMANAGER_URL", "").rstrip("/")
    token = os.environ.get("FILAMENTMANAGER_TOKEN", "")
    printer = os.environ.get("FILAMENTMANAGER_PRINTER", "")
    if not server or not token or not printer:
        print("FilamentManager: set FILAMENTMANAGER_URL, FILAMENTMANAGER_TOKEN and FILAMENTMANAGER_PRINTER", file=sys.stderr)
        return 0
    try:
        payload = parse(pathlib.Path(sys.argv[-1]))
        payload["printerName"] = printer
        request = urllib.request.Request(server + "/api/v1/print-jobs/import", data=json.dumps(payload).encode("utf-8"), headers={"Authorization": "Bearer " + token, "Content-Type": "application/json", "User-Agent": "FilamentManager-PrusaSlicer/1.0"}, method="POST")
        with urllib.request.urlopen(request, timeout=20) as response:
            result = json.load(response)
        print("FilamentManager print job: " + str(result.get("url", result.get("id", "created"))))
    except (OSError, ValueError, urllib.error.URLError, json.JSONDecodeError) as error:
        print("FilamentManager import failed (G-code export continues): " + str(error), file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
