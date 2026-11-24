#!/usr/bin/env python3
"""Verifikator integritas 100% file gambar katalog lokal."""

from __future__ import annotations

import json
import sys
from pathlib import Path

from PIL import Image

SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent
MASTER_CATALOG_PATH = PROJECT_ROOT / "database" / "seeders" / "data" / "master_fotocopy_catalog.json"
STORAGE_PUBLIC_DIR = PROJECT_ROOT / "storage" / "app" / "public"
CATALOG_IMG_DIR = STORAGE_PUBLIC_DIR / "fotocopy_catalog"


def main() -> int:
    if not MASTER_CATALOG_PATH.is_file():
        print(f"ERROR: Master catalog tidak ditemukan: {MASTER_CATALOG_PATH}", file=sys.stderr)
        return 1

    catalog = json.loads(MASTER_CATALOG_PATH.read_text(encoding="utf-8"))
    missing: list[str] = []
    corrupt: list[str] = []
    total_size = 0

    for item in catalog:
        rel_path = item["image_path"]
        img_path = STORAGE_PUBLIC_DIR / rel_path
        if not img_path.is_file():
            missing.append(rel_path)
            continue

        size = img_path.stat().st_size
        total_size += size
        if size <= 2048:
            corrupt.append(f"{rel_path} (terlalu kecil: {size} bytes)")
            continue

        try:
            with Image.open(img_path) as img:
                img.verify()
            with Image.open(img_path) as img:
                w, h = img.size
            if w < 120 or h < 120:
                corrupt.append(f"{rel_path} (resolusi terlalu kecil: {w}x{h})")
        except Exception as exc:
            corrupt.append(f"{rel_path} ({exc})")

    actual_files = list(CATALOG_IMG_DIR.glob("*.webp")) if CATALOG_IMG_DIR.is_dir() else []

    print(f"Total SKU Master       : {len(catalog)}")
    print(f"Total File WEBP Disk   : {len(actual_files)}")
    print(f"Ukuran Total Folder    : {total_size / 1024 / 1024:.2f} MB ({total_size} bytes)")
    print(f"Gambar Hilang (Missing): {len(missing)}")
    print(f"Gambar Rusak (Corrupt) : {len(corrupt)}")

    if missing:
        print("Daftar Hilang:", missing[:15], file=sys.stderr)
    if corrupt:
        print("Daftar Rusak :", corrupt[:15], file=sys.stderr)

    return 1 if (missing or corrupt) else 0


if __name__ == "__main__":
    raise SystemExit(main())
