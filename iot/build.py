#!/usr/bin/env python3
"""
Build ESP32 firmware untuk Monitoring PH Air tanpa Arduino IDE.

Usage:
  python3 iot/build.py                 # build firmware
  python3 iot/build.py --upload        # build + flash ke ESP32
  python3 iot/build.py --upload --port /dev/cu.usbserial-XXXX
"""

import argparse
from typing import List, Optional
import shutil
import subprocess
import sys
from pathlib import Path
from serial.tools import list_ports  # type: ignore[import-untyped]

BOARD_FQBN = "esp32:esp32:esp32"
BOARD_URL = "https://espressif.github.io/arduino-esp32/package_esp32_index.json"

REQUIRED_LIBRARIES = ["LiquidCrystal I2C", "OneWire", "DallasTemperature"]

PROJECT_ROOT = Path(__file__).resolve().parent.parent
SKETCH_DIR = PROJECT_ROOT / "iot" / "running"
BUILD_DIR = PROJECT_ROOT / "iot" / "build"


def run(command: List[str], message: Optional[str] = None) -> None:
    prefix = f"[{message}] " if message else ""
    print(f"{prefix}→ {' '.join(command)}")
    result = subprocess.run(command, capture_output=True, text=True)
    if result.returncode != 0:
        print(result.stdout)
        print(result.stderr)
        sys.exit(result.returncode)
    if result.stdout.strip():
        print(result.stdout.strip())


def command_exists(name: str) -> bool:
    return shutil.which(name) is not None


def ensure_arduino_cli() -> None:
    if command_exists("arduino-cli"):
        return
    print("[SETUP] arduino-cli belum terpasang. Install via Homebrew?")
    if sys.platform != "darwin" or not command_exists("brew"):
        sys.exit("[ERROR] Install arduino-cli manual: https://arduino.github.io/arduino-cli/")
    run(["brew", "install", "arduino-cli"], "SETUP")


def ensure_board_core() -> None:
    installed = subprocess.run(
        ["arduino-cli", "core", "list"], capture_output=True, text=True
    ).stdout
    if "esp32:esp32" not in installed:
        config_ok = subprocess.run(
            ["arduino-cli", "config", "dump"],
            capture_output=True,
            text=True,
        ).stdout
        if BOARD_URL not in config_ok:
            run(
                ["arduino-cli", "config", "add", "board_manager.additional_urls", BOARD_URL],
                "SETUP",
            )
        run(["arduino-cli", "core", "update-index"], "SETUP")
        run(["arduino-cli", "core", "install", "esp32:esp32"], "SETUP")


def ensure_libraries() -> None:
    installed = subprocess.run(
        ["arduino-cli", "lib", "list"], capture_output=True, text=True
    ).stdout.lower()
    for library in REQUIRED_LIBRARIES:
        if library.lower() not in installed:
            run(["arduino-cli", "lib", "install", library], f"LIB:{library}")


def build() -> None:
    run(
        [
            "arduino-cli",
            "compile",
            "--fqbn",
            BOARD_FQBN,
            f"--output-dir={BUILD_DIR}",
            str(SKETCH_DIR),
        ],
        "BUILD",
    )
    merged = next(BUILD_DIR.rglob("*.merged.bin"), None)
    if merged:
        print(f"✅ Firmware siap: {merged}")


def find_esp32_port() -> str:
    ports = list_ports.comports()
    for port in ports:
        if port.vid is None:
            continue
        print(f"  - {port.device}  ({port.description})")
    chosen = input("Masukkan port ESP32 (misal /dev/cu.usbserial-XXXX): ").strip()
    if not chosen:
        sys.exit("[ERROR] Port tidak boleh kosong.")
    return chosen


def upload(port: str) -> None:
    run(
        [
            "arduino-cli",
            "upload",
            "--fqbn",
            BOARD_FQBN,
            "--input-dir",
            str(BUILD_DIR),
            "--port",
            port,
            str(SKETCH_DIR),
        ],
        "UPLOAD",
    )
    print("✅ Firmware berhasil di-flash ke ESP32.")


def main() -> None:
    parser = argparse.ArgumentParser(description="Build & flash firmware ESP32 monitoring.")
    parser.add_argument("--upload", action="store_true", help="Flash firmware setelah build.")
    parser.add_argument("--port", help="Port serial ESP32 (misal /dev/cu.usbserial-XXXX).")
    args = parser.parse_args()

    if not SKETCH_DIR.exists():
        sys.exit(f"[ERROR] Sketch tidak ditemukan: {SKETCH_DIR}")
    if args.upload and not args.port:
        args.port = find_esp32_port()

    ensure_arduino_cli()
    ensure_board_core()
    ensure_libraries()
    build()

    if args.upload:
        upload(args.port)


if __name__ == "__main__":
    main()
