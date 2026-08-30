#!/usr/bin/env python3
"""PrusaSlicer post-processing helper for FilamentManager Server.

Configure FILAMENTMANAGER_URL, FILAMENTMANAGER_TOKEN and
FILAMENTMANAGER_PRINTER, then add this script under Print Settings > Output
options > Post-processing scripts. It reads metadata only and never modifies
the generated G-code. A server/network error does not block G-code export.
"""

from __future__ import annotations

import hashlib
import datetime
import json
import os
import pathlib
import re
import ssl
import struct
import sys
import urllib.error
import urllib.request
import zlib

try:
    import certifi
except ImportError:
    certifi = None


def split_values(value: str) -> list[str]:
    return [item.strip().strip('"') for item in re.split(r"[;,]", value) if item.strip()]


METADATA_KEYS = ("filament_type", "filament_colour", "extruder_colour", "filament_vendor", "filament_settings_id", "printer_model", "printer_settings_id", "estimated printing time (normal mode)")
MAX_METADATA_SIZE = 16 * 1024 * 1024


def parse_text(path: pathlib.Path) -> tuple[dict[str, list[str]], list[float]]:
    metadata: dict[str, list[str]] = {}
    usage: list[float] = []
    with path.open("r", encoding="utf-8", errors="replace") as source:
        for raw_line in source:
            line = raw_line.strip()
            match = re.match(r"^;\s*filament used \[g\]\s*=\s*(.+)$", line, re.I)
            if match:
                usage = [float(item) for item in re.split(r"[,;]", match.group(1)) if item.strip()]
            for key in METADATA_KEYS:
                match = re.match(r"^;\s*" + re.escape(key) + r"\s*=\s*(.*)$", line, re.I)
                if match:
                    metadata[key] = split_values(match.group(1))
    return metadata, usage


def read_exact(source, length: int) -> bytes:
    data = source.read(length)
    if len(data) != length:
        raise ValueError("Truncated BGcode file")
    return data


def parse_bgcode(path: pathlib.Path) -> tuple[dict[str, list[str]], list[float]]:
    metadata: dict[str, list[str]] = {}
    usage: list[float] = []
    with path.open("rb") as source:
        magic, version, checksum_type = struct.unpack("<IIH", read_exact(source, 10))
        if magic != 0x45444347 or version != 1 or checksum_type not in (0, 1):
            raise ValueError("Invalid or unsupported BGcode header")
        while block_start := source.read(8):
            if len(block_start) != 8:
                raise ValueError("Truncated BGcode block header")
            block_type, compression, uncompressed_size = struct.unpack("<HHI", block_start)
            if compression not in (0, 1, 2, 3) or block_type not in (0, 1, 2, 3, 4, 5):
                raise ValueError("Unsupported BGcode block")
            header = block_start
            compressed_size = uncompressed_size
            if compression:
                extra = read_exact(source, 4)
                header += extra
                compressed_size = struct.unpack("<I", extra)[0]
            parameter_size = 6 if block_type == 5 else 2
            parameters = read_exact(source, parameter_size)
            data_size = compressed_size if compression else uncompressed_size
            is_metadata = block_type in (2, 3, 4)
            if is_metadata:
                if data_size > MAX_METADATA_SIZE or uncompressed_size > MAX_METADATA_SIZE:
                    raise ValueError("BGcode metadata is too large")
                encoded = read_exact(source, data_size)
            else:
                source.seek(data_size, os.SEEK_CUR)
                encoded = b""
            if checksum_type == 1:
                stored_checksum = struct.unpack("<I", read_exact(source, 4))[0]
                if is_metadata and stored_checksum != (zlib.crc32(header + parameters + encoded) & 0xFFFFFFFF):
                    raise ValueError("BGcode metadata checksum is invalid")
            if not is_metadata:
                continue
            encoding = struct.unpack("<H", parameters)[0]
            if encoding != 0:
                raise ValueError("Unsupported BGcode metadata encoding")
            if compression == 0:
                decoded = encoded
            elif compression == 1:
                decoded = zlib.decompress(encoded)
            else:
                raise ValueError("Heatshrink-compressed BGcode metadata is not supported; export with uncompressed or Deflate metadata")
            if len(decoded) != uncompressed_size:
                raise ValueError("Invalid BGcode metadata size")
            for raw_line in decoded.decode("utf-8", errors="replace").splitlines():
                key, separator, value = raw_line.partition("=")
                if not separator:
                    continue
                key = key.strip().lower()
                value = value.strip()
                if key == "filament used [g]":
                    usage = [float(item) for item in re.split(r"[,;]", value) if item.strip()]
                elif key in METADATA_KEYS:
                    metadata[key] = split_values(value)
    return metadata, usage


def parse(path: pathlib.Path) -> dict:
    digest = hashlib.sha256()
    with path.open("rb") as source:
        magic = source.read(4)
        source.seek(0)
        for block in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(block)
    metadata, usage = parse_bgcode(path) if magic == b"GCDE" else parse_text(path)
    if not usage:
        raise ValueError("G-code does not contain filament usage in grams")
    types = metadata.get("filament_type", [])
    colors = metadata.get("filament_colour", metadata.get("extruder_colour", []))
    consumptions = []
    for index, grams in enumerate(usage):
        if grams <= 0:
            continue
        color = colors[index].upper() if index < len(colors) and re.fullmatch(r"#[0-9A-Fa-f]{6}", colors[index]) else None
        consumptions.append({"extruderIndex": index, "estimatedWeightG": round(grams, 2), "materialType": types[index] if index < len(types) else None, "colorHex": color})
    return {"fileName": output_file_name(path), "sha256": digest.hexdigest(), "metadata": metadata, "consumptions": consumptions}


def output_file_name(source: pathlib.Path) -> str:
    configured = os.environ.get("SLIC3R_PP_OUTPUT_NAME", "").strip()
    if configured:
        return configured.replace("\\", "/").rstrip("/").rsplit("/", 1)[-1]
    name = source.name
    return name[:-3] if name.lower().endswith(".pp") else name


def write_log(message: str) -> pathlib.Path:
    root = pathlib.Path(os.environ.get("LOCALAPPDATA", pathlib.Path.home())) / "FilamentManager" / "PrusaSlicer"
    root.mkdir(parents=True, exist_ok=True)
    path = root / "filamentmanager-prusaslicer.log"
    timestamp = datetime.datetime.now(datetime.timezone.utc).isoformat(timespec="seconds")
    with path.open("a", encoding="utf-8") as output:
        output.write(f"[{timestamp}] {message}\n")
    return path


def report_failure(message: str, printer: str, source: pathlib.Path) -> None:
    log_path = write_log(f"FAILED printer={printer!r} file={str(source)!r}: {message}")
    print(f"FilamentManager import failed (G-code export continues): {message}", file=sys.stderr)
    print(f"FilamentManager diagnostic log: {log_path}", file=sys.stderr)


def tls_context() -> ssl.SSLContext:
    return ssl.create_default_context(cafile=certifi.where() if certifi is not None else None)


def main() -> int:
    if len(sys.argv) < 2:
        print("Usage: filamentmanager-prusaslicer.py FILE.gcode|FILE.bgcode", file=sys.stderr)
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
        with urllib.request.urlopen(request, timeout=20, context=tls_context()) as response:
            result = json.load(response)
        result_text = str(result.get("url", result.get("id", "created")))
        write_log(f"OK printer={printer!r} file={str(pathlib.Path(sys.argv[-1]))!r}: {result_text}")
        print("FilamentManager print job: " + result_text)
    except urllib.error.HTTPError as error:
        try:
            response_text = error.read(4096).decode("utf-8", errors="replace").strip()
        except OSError:
            response_text = ""
        detail = f"server returned HTTP {error.code}" + (f": {response_text}" if response_text else "")
        report_failure(detail, printer, pathlib.Path(sys.argv[-1]))
    except urllib.error.URLError as error:
        if isinstance(error.reason, ssl.SSLCertVerificationError):
            detail = "TLS certificate verification failed. Update Python, then run 'py -3 -m pip install --upgrade certifi'; never disable HTTPS verification. " + str(error.reason)
        else:
            detail = str(error)
        report_failure(detail, printer, pathlib.Path(sys.argv[-1]))
    except (OSError, ValueError, json.JSONDecodeError) as error:
        report_failure(str(error), printer, pathlib.Path(sys.argv[-1]))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
