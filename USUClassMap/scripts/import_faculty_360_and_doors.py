#!/usr/bin/env python3
"""Import Faculty 360° Panoramas, Map 29 Real Door Photos, and Generate Rectilinear Door Shots.

Preserves all existing protected assets:
- storage/app/public/foto360/424.jpg..495.jpg (untouched)
- database/seeders/snapshots/fib_walk_graph.json (untouched)
- public/images/foto-360p/*.jpg (1.jpg..112.jpg untouched)

Outputs purely under USUClassMap/public/images/... and manifests under:
- storage/app/media-maps/door_photo_mapping.json
- storage/app/media-maps/faculty_media_manifest.json
- database/seeders/snapshots/faculty_media_manifest.json
"""

from __future__ import annotations

import hashlib
import json
import os
import pathlib
import re
import sqlite3
import sys
from typing import Any

import numpy as np
from PIL import Image, ImageOps

SCRIPT_DIR = pathlib.Path(__file__).resolve().parent
PROJECT_DIR = SCRIPT_DIR.parent
sys.path.insert(0, str(SCRIPT_DIR))

from generate_door_shots_from_360 import (  # noqa: E402
    compute_source_projection,
    effective_fov_and_pitch,
    project_rectilinear,
    save_webp,
    select_best_yaws,
    subtle_sharpen,
)

SOURCE_360_DIR = pathlib.Path(r"D:\Documents\TUBES WEB SEMANTIK\FOTO 360P")
SOURCE_DOOR_DIR = pathlib.Path(r"D:\Documents\TUBES WEB SEMANTIK\FOTO PINTU")

PUBLIC_360_ROOT = PROJECT_DIR / "public" / "images" / "foto-360p"
PUBLIC_DOOR_REAL = PROJECT_DIR / "public" / "images" / "doors" / "real"
PUBLIC_DOOR_360 = PROJECT_DIR / "public" / "images" / "doors" / "from-360"

FIB_SPHERICAL_DIR = PROJECT_DIR / "storage" / "app" / "public" / "foto360"
FIB_WALK_JSON = PROJECT_DIR / "database" / "seeders" / "snapshots" / "fib_walk_graph.json"
CALIBRATIONS_JSON = PROJECT_DIR / "database" / "seeders" / "snapshots" / "photo_360_calibrations.json"

STORAGE_MAPS_DIR = PROJECT_DIR / "storage" / "app" / "media-maps"
SNAPSHOT_MANIFEST = PROJECT_DIR / "database" / "seeders" / "snapshots" / "faculty_media_manifest.json"
DB_PATH = PROJECT_DIR / "database" / "database.sqlite"

SUBFOLDER_SLUGS = {
    "ELEKTRO": "elektro",
    "F KEP": "f-kep",
    "FARMASI": "farmasi",
    "FASILKOM": "fasilkom",
    "FIB": "fib",
    "FISIP": "fisip",
    "FKM": "fkm",
    "FMIPA": "fmipa",
    "HUKUM": "hukum",
    "TEKNIK INDUSTRI": "teknik-industri",
}

# Non-360 flat photos found inside FOTO 360P (excluded from 360 pool; used as real door shots where applicable)
EXCLUDED_360_FILENAMES = {
    ("HUKUM", "IMG-20261113-WA0023.jpg"),
    ("ROOT", "IMG-20260923-WA0031.jpg"),
    ("ROOT", "IMG-20261113-WA0027.jpg"),
    ("ROOT", "IMG-20261113-WA0031.jpg"),
}

# Verified visual plaque transcription for all 29 files in FOTO PINTU (+ fallback mapping if un-plaqued/duplicate)
REAL_DOOR_SPECS: list[dict[str, Any]] = [
    {"src_dir": "PINTU", "src_file": "IMG-20260901-WA0049.jpg", "out": "fib-k-302.webp", "plaque": "K - 302 (Gedung K FIB)", "room_ids": [17], "building_ids": [], "note": "Kecocokan plang presisi R#17 (K.302)"},
    {"src_dir": "PINTU", "src_file": "IMG-20260901-WA0050.jpg", "out": "fib-k-301.webp", "plaque": "K - 301 (Gedung K FIB)", "room_ids": [18], "building_ids": [56], "note": "Kecocokan plang presisi R#18 (K.301) & Gedung FIB-K"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0083.jpg", "out": "gl-2c.webp", "plaque": "GLR 02C (Gedung Lama)", "room_ids": [115], "building_ids": [], "note": "Kecocokan plang presisi R#115 (GL 2C)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0084.jpg", "out": "gl-3.webp", "plaque": "GLR 03 (Gedung Lama)", "room_ids": [113], "building_ids": [], "note": "Kecocokan plang presisi R#113 (GL 3)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0086.jpg", "out": "gl-4a.webp", "plaque": "GLR 04A (Gedung Lama)", "room_ids": [114], "building_ids": [66], "note": "Kecocokan plang presisi R#114 (GL 4A) & Gedung FEB-GL"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0087.jpg", "out": "gl-4b.webp", "plaque": "GLR 04B (Gedung Lama)", "room_ids": [111], "building_ids": [], "note": "Kecocokan plang presisi R#111 (GL 4B)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0089.jpg", "out": "fib-a-102.webp", "plaque": "A 102 (Gedung A FIB)", "room_ids": [52, 129], "building_ids": [53], "note": "Kecocokan plang presisi R#52, R#129 (A.102) & Gedung FIB-A"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0091.jpg", "out": "fib-a-101-koridor.webp", "plaque": "(Tanpa Plang — Pintu Besi & Kayu Koridor A FIB)", "room_ids": [6, 21], "building_ids": [48], "note": "Pintu sayap Gedung A FIB tanpa plang; dipetakan ke R#6/R#21 (A 101) & B#48 (A FIB)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0093.jpg", "out": "fib-h-205.webp", "plaque": "H - 205 (Gedung H FIB)", "room_ids": [47], "building_ids": [], "note": "Kecocokan plang presisi R#47 (H 205 FIB USU)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0094.jpg", "out": "fib-o-104.webp", "plaque": "O.104 Kelas Pintar Internasional (FIB)", "room_ids": [30, 101], "building_ids": [], "note": "Kecocokan plang presisi R#30 & R#101 (O.104)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0096.jpg", "out": "fib-laboratorium-koridor.webp", "plaque": "(Tanpa Plang — Pintu Sudut Lt.1 Dekat Parkir Motor FIB)", "room_ids": [49, 128], "building_ids": [60], "note": "Pintu kelas lt.1 tanpa plang; dipetakan ke R#49/R#128 (LABORATORIUM) & B#60 (FIB-D)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0097.jpg", "out": "fib-h-102.webp", "plaque": "H - 102 (Gedung H FIB)", "room_ids": [26, 29, 100], "building_ids": [59], "note": "Kecocokan plang presisi R#26, R#29, R#100 (H 102) & Gedung FIB-H"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0098.jpg", "out": "fib-h-103.webp", "plaque": "H - 103 (Gedung H FIB)", "room_ids": [25, 99], "building_ids": [], "note": "Kecocokan plang presisi R#25 & R#99 (H 103)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0100.jpg", "out": "fib-h-202.webp", "plaque": "H - 202 (Gedung H FIB)", "room_ids": [56], "building_ids": [], "note": "Kecocokan plang presisi R#56 (H 202 FIB USU)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0101.jpg", "out": "fib-h-104.webp", "plaque": "H - 104 (Gedung H FIB)", "room_ids": [5, 19, 24, 46, 98, 126], "building_ids": [], "note": "Kecocokan plang presisi R#5, R#19, R#24, R#46, R#98, R#126 (H 104)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0102.jpg", "out": "fib-o-101.webp", "plaque": "O.101 Kelas Pintar Internasional (FIB)", "room_ids": [10, 31], "building_ids": [50, 27], "note": "Kecocokan plang presisi R#10, R#31 (O 101) & Gedung FIB-O / FIB"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0103.jpg", "out": "fib-h-203.webp", "plaque": "H - 203 (Gedung H FIB)", "room_ids": [53, 55, 130], "building_ids": [], "note": "Kecocokan plang presisi R#53, R#55, R#130 (H 203)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0105.jpg", "out": "fib-h-206.webp", "plaque": "H - 206 (Gedung H FIB)", "room_ids": [9, 28], "building_ids": [], "note": "Kecocokan plang presisi R#9 & R#28 (H 206)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0106.jpg", "out": "fib-h-201.webp", "plaque": "H - 201 (Gedung H FIB)", "room_ids": [58, 132], "building_ids": [], "note": "Kecocokan plang presisi R#58 & R#132 (H 201)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0107.jpg", "out": "fib-b-101.webp", "plaque": "B - 101 (Gedung B FIB)", "room_ids": [57, 77], "building_ids": [61], "note": "Plang B-101 ke B#61 (FIB-B); baris 'B-101' belum ada terpisah di tabel rooms sehingga dipetakan ke R#57/R#77 ('104' FIB/GKB)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0108.jpg", "out": "fib-h-207.webp", "plaque": "H - 207 (Gedung H FIB)", "room_ids": [44, 54, 131], "building_ids": [], "note": "Kecocokan plang presisi R#44, R#54, R#131 (H 207)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0109.jpg", "out": "fib-b-102.webp", "plaque": "B 102 — Program Studi S2 Sejarah (FIB)", "room_ids": [45], "building_ids": [54], "note": "Plang B 102 belum ada baris 'B 102' di tabel rooms; dipetakan ke R#45 (P 203 FIB USU) & B#54 (FIB-P)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0110.jpg", "out": "fib-h-208.webp", "plaque": "H - 208 (Gedung H FIB)", "room_ids": [7, 22], "building_ids": [], "note": "Kecocokan plang presisi R#7 & R#22 (H 208)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0111.jpg", "out": "fib-h-105.webp", "plaque": "H - 105 (Gedung H FIB)", "room_ids": [23, 97], "building_ids": [], "note": "Kecocokan plang presisi R#23 & R#97 (Gedung H 105)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0112.jpg", "out": "fib-o-102.webp", "plaque": "O.102 Kelas Pintar Internasional (FIB)", "room_ids": [33, 103], "building_ids": [52], "note": "Plang O.102 belum punya baris O.102 di rooms; dipetakan ke R#33/R#103 (M RSM 1) & B#52 (FIB-L)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0113.jpg", "out": "fib-h-204.webp", "plaque": "H - 204 (Gedung H FIB)", "room_ids": [42, 124], "building_ids": [], "note": "Kecocokan plang presisi R#42 & R#124 (H 204)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0114.jpg", "out": "gl-2b.webp", "plaque": "GLR 02B (Gedung Lama)", "room_ids": [68, 116], "building_ids": [], "note": "Kecocokan plang presisi R#68 & R#116 (GL 2B)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0115.jpg", "out": "fib-o-103.webp", "plaque": "O.103 Kelas Pintar Internasional (FIB)", "room_ids": [20, 51, 92], "building_ids": [], "note": "Kecocokan plang presisi R#20, R#51, R#92 (O.103)"},
    {"src_dir": "PINTU", "src_file": "IMG-20261123-WA0116.jpg", "out": "fib-h-205-detail.webp", "plaque": "H - 205 (Close-up Plang Gedung H FIB)", "room_ids": [38, 108], "building_ids": [58], "note": "Sudut kedua H-205; WA0093 dipasang ke R#47 (H 205), WA0116 dipetakan ke R#38/R#108 (M RSM 2) & B#58 (FIB-N)"},
]

# Bonus authentic flat door photos discovered inside FOTO 360P
BONUS_REAL_DOORS: list[dict[str, Any]] = [
    {"src_path": SOURCE_360_DIR / "IMG-20261113-WA0027.jpg", "out": "gl-1.webp", "plaque": "GLR 01 (Gedung Lama — dari root FOTO 360P)", "room_ids": [66, 112], "building_ids": [], "note": "Foto pintu asli GLR 01 dari root FOTO 360P"},
    {"src_path": SOURCE_360_DIR / "IMG-20260923-WA0031.jpg", "out": "ft-teknik-industri-smartroom.webp", "plaque": "INTERNATIONAL SMARTCLASSROOM TEKNIK INDUSTRI", "room_ids": [], "building_ids": [74], "note": "Foto pintu asli Teknik Industri dari root FOTO 360P"},
    {"src_path": SOURCE_360_DIR / "HUKUM" / "IMG-20261113-WA0023.jpg", "out": "fh-r203-r204.webp", "plaque": "RUANG KULIAH MIKn R. 203 & R. 204 (FH)", "room_ids": [], "building_ids": [23, 63], "note": "Foto pintu asli Fakultas Hukum dari subfolder HUKUM"},
]


def slugify(text: str) -> str:
    s = re.sub(r"[^a-zA-Z0-9]+", "-", str(text).strip().lower()).strip("-")
    return s or "item"


def md5_file(path: pathlib.Path) -> str:
    h = hashlib.md5()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(65536), b""):
            h.update(chunk)
    return h.hexdigest()


def save_optimized_webp(src_path: pathlib.Path, out_path: pathlib.Path, max_w: int = 2560, quality: int = 84) -> tuple[int, int]:
    """EXIF-correct, optionally downscale if wider than max_w, and save .webp cleanly."""
    with Image.open(src_path) as im:
        im_t = ImageOps.exif_transpose(im).convert("RGB")
        w, h = im_t.size
        if w > max_w:
            new_h = max(1, int(round(h * (max_w / float(w)))))
            im_t = im_t.resize((max_w, new_h), Image.Resampling.LANCZOS)
            w, h = im_t.size
        out_path.parent.mkdir(parents=True, exist_ok=True)
        im_t.save(str(out_path), format="WEBP", quality=quality, method=6)
        return (w, h)


def analyze_panorama(webp_path: pathlib.Path, preset_yaw: float | None = None, preset_pitch: float = 0.0) -> dict[str, Any]:
    with Image.open(webp_path) as im:
        rgb = np.array(im.convert("RGB"), dtype=np.uint8)
        h, w = rgb.shape[:2]
    haov, vaov = compute_source_projection(w, h)
    best = select_best_yaws(rgb, top_k=3, hfov_deg=70.0, pitch_deg=-1.5 if vaov >= 150.0 else 0.0)
    yaws = [b["yaw"] for b in best] if best else [0.0, 90.0, 180.0]

    default_yaw = float(preset_yaw) if preset_yaw is not None else float(yaws[0])
    default_pitch = float(preset_pitch) if preset_yaw is not None else (-1.5 if vaov >= 150.0 else 0.0)
    min_pitch = -85.0 if vaov >= 150.0 else round(-vaov * 0.42, 1)
    max_pitch = 85.0 if vaov >= 150.0 else round(vaov * 0.42, 1)
    default_hfov = 95.0 if vaov >= 150.0 else round(min(92.0, max(62.0, vaov * 1.35)), 1)

    return {
        "width": w,
        "height": h,
        "haov": haov,
        "vaov": vaov,
        "default_yaw": round(default_yaw, 1),
        "default_pitch": round(default_pitch, 1),
        "hfov": default_hfov,
        "min_pitch": min_pitch,
        "max_pitch": max_pitch,
        "best_yaws": best,
        "top_score": float(best[0]["score"]) if best else 0.0,
    }


def build_pano_pools() -> tuple[dict[str, list[dict[str, Any]]], dict[int, dict[str, Any]]]:
    """Import quality 360s into public/images/foto-360p/<faculty-slug>/ and return analyzed pools."""
    pools: dict[str, list[dict[str, Any]]] = {}
    sub_md5s: set[str] = set()

    # 1. Process 10 populated faculty subfolders
    for folder_name, slug in SUBFOLDER_SLUGS.items():
        src_dir = SOURCE_360_DIR / folder_name
        pool_items: list[dict[str, Any]] = []
        if src_dir.is_dir():
            for src_file in sorted(src_dir.iterdir()):
                if not src_file.is_file():
                    continue
                sub_md5s.add(md5_file(src_file))
                if (folder_name, src_file.name) in EXCLUDED_360_FILENAMES:
                    continue
                with Image.open(src_file) as im:
                    w, h = ImageOps.exif_transpose(im).size
                ratio = w / float(max(1, h))
                if h < 165 or ratio < 1.8 or ratio > 9.5:
                    continue

                out_name = f"{slugify(src_file.stem)}.webp"
                out_path = PUBLIC_360_ROOT / slug / out_name
                save_optimized_webp(src_file, out_path, max_w=2560, quality=84)
                rel_path = f"images/foto-360p/{slug}/{out_name}"
                meta = analyze_panorama(out_path)
                pool_items.append({"rel_path": rel_path, "abs_path": str(out_path), "source": str(src_file), **meta})

        # Sort each faculty pool by highest doorway/architectural score so best interior frames lead
        pool_items.sort(key=lambda x: x["top_score"], reverse=True)
        pools[slug] = pool_items

    # 2. Convert calibrated spherical scenes 428..495 into public/images/foto-360p/fib/scene-<id>.webp
    fib_scenes_by_id: dict[int, dict[str, Any]] = {}
    if FIB_WALK_JSON.is_file():
        walk_data = json.loads(FIB_WALK_JSON.read_text(encoding="utf-8"))
        calibrations = {}
        if CALIBRATIONS_JSON.is_file():
            for entry in json.loads(CALIBRATIONS_JSON.read_text(encoding="utf-8")):
                calibrations[str(entry.get("filename", ""))] = entry

        referenced_fib_sids = {
            428, 429, 430, 433, 436, 437, 438, 439, 440, 441, 442,
            444, 446, 447, 456, 458, 462, 465, 466, 467, 468, 469,
            470, 474, 480, 481, 482, 484, 486, 487, 489, 491, 492,
            493, 494, 495,
        }
        for sid_str, scene in walk_data.get("scenes", {}).items():
            sid = int(sid_str)
            if scene.get("excluded") or sid == 434 or sid not in referenced_fib_sids:
                continue
            fname = str(scene.get("filename", f"{sid}.jpg"))
            src_sph = FIB_SPHERICAL_DIR / fname
            if not src_sph.is_file():
                continue
            out_name = f"scene-{sid}.webp"
            out_path = PUBLIC_360_ROOT / "fib" / out_name
            cal = calibrations.get(fname, {})
            preset_yaw = cal.get("yaw", scene.get("default_yaw", 0.0))
            preset_pitch = cal.get("pitch", scene.get("default_pitch", 0.0))
            save_optimized_webp(src_sph, out_path, max_w=2560, quality=84)
            rel_path = f"images/foto-360p/fib/{out_name}"
            meta = analyze_panorama(out_path, preset_yaw=preset_yaw, preset_pitch=preset_pitch)
            fib_scenes_by_id[sid] = {
                "scene_id": sid,
                "label": scene.get("label", ""),
                "zone": scene.get("zone", ""),
                "rel_path": rel_path,
                "abs_path": str(out_path),
                "source": str(src_sph),
                **meta,
            }

    # 3. Process unique root panoramas (split 4 into psikologi and remainder into campus-root)
    unique_root_panos: list[dict[str, Any]] = []
    for src_file in sorted(SOURCE_360_DIR.iterdir()):
        if not src_file.is_file():
            continue
        if ("ROOT", src_file.name) in EXCLUDED_360_FILENAMES:
            continue
        if md5_file(src_file) in sub_md5s:
            continue
        with Image.open(src_file) as im:
            w, h = ImageOps.exif_transpose(im).size
        ratio = w / float(max(1, h))
        if h < 165 or ratio < 1.8 or ratio > 9.5:
            continue
        unique_root_panos.append({"src_file": src_file})

    psikologi_src = unique_root_panos[:4]
    campus_root_src = unique_root_panos[4:]

    for pool_slug, items_src in [("psikologi", psikologi_src), ("campus-root", campus_root_src)]:
        pool_items: list[dict[str, Any]] = []
        for entry in items_src:
            src_file = entry["src_file"]
            out_name = f"{slugify(src_file.stem)}.webp"
            out_path = PUBLIC_360_ROOT / pool_slug / out_name
            save_optimized_webp(src_file, out_path, max_w=2560, quality=84)
            rel_path = f"images/foto-360p/{pool_slug}/{out_name}"
            meta = analyze_panorama(out_path)
            pool_items.append({"rel_path": rel_path, "abs_path": str(out_path), "source": str(src_file), **meta})
        pool_items.sort(key=lambda x: x["top_score"], reverse=True)
        pools[pool_slug] = pool_items

    return pools, fib_scenes_by_id


def classify_building_pool(b: dict[str, Any]) -> str:
    code = str(b.get("code") or "").upper().strip()
    fac = str(b.get("faculty") or "").upper().strip()
    name = str(b.get("name") or "").upper().strip()

    if code == "FT-ELEKTRO_K" or "ELEKTRO" in name:
        return "elektro"
    if code == "FT-INDUSTRI_J" or "INDUSTRI" in name:
        return "teknik-industri"
    if code.startswith("FT-") or fac == "FT" or "FAKULTAS TEKNIK" in fac:
        return "elektro" if ("MESIN" in code or "SIPIL" in code) else "teknik-industri"
    if code == "FKEP" or fac == "FKEP" or "KEPERAWATAN" in name:
        return "f-kep"
    if code in ("FF", "FF-LT.2", "MSJ-ASSYIFA") or fac == "FF" or "FARMASI" in name:
        return "farmasi"
    if "FASILKOM" in code or "FASILKOM" in fac:
        return "fasilkom"
    if code == "A FIB" or code.startswith("FIB") or fac == "FIB" or "ILMU BUDAYA" in name:
        return "fib"
    if "FISIP" in code or fac == "FISIP" or "SOSIAL DAN ILMU POLITIK" in name:
        return "fisip"
    if code.startswith("FKM") or fac == "FKM":
        return "fkm"
    if code.startswith("FMIPA") or code == "PERPUS-MIPA" or fac == "FMIPA" or "MIPA" in name:
        return "fmipa"
    if code in ("FH", "FH-GB") or fac == "FH" or "HUKUM" in name:
        return "hukum"
    if code.startswith("FPSI") or fac == "FPSI" or "PSIKOLOGI" in name:
        return "psikologi"
    return "campus-root"


def fib_scene_for_building_code(code: str) -> int:
    u = code.upper().strip()
    if u in ("FIB-O", "O FIB"):
        return 430
    if u in ("FIB-A", "A FIB"):
        return 436
    if u == "FIB-B":
        return 439
    if u == "FIB-F":
        return 482
    if u == "FIB-H":
        return 456
    if u == "FIB-I":
        return 484
    if u == "FIB-K":
        return 474
    if u == "FIB-M":
        return 489
    if u == "FIB":
        return 495
    if u in ("FIB-P", "FIB-D", "FIB-L", "FIB-N"):
        return {"FIB-P": 441, "FIB-D": 442, "FIB-L": 433, "FIB-N": 491}[u]
    return 495


def fib_scene_for_room_name(name: str) -> int | None:
    u = re.sub(r"\s+", " ", name.upper().strip())
    # Specific FIB rooms in scenes 428..494
    mapping = [
        (r"\bO[\s.]*10[24]\b", 428),
        (r"\bO[\s.]*10[13]\b", 429),
        (r"\bA[\s.]*102\b", 437),
        (r"\bA[\s.]*101\b", 438),
        (r"\bB[\s.-]*101\b", 439),
        (r"\bB[\s.-]*102\b", 440),
        (r"\bB[\s.-]*103\b", 441),
        (r"\bF[\s.]*202\b", 444),
        (r"\bF[\s.]*201\b", 446),
        (r"\bF[\s.]*203\b", 447),
        (r"\bH[\s.-]*10[23]\b", 458),
        (r"\bH[\s.-]*10[45]\b", 462),
        (r"\bH[\s.]*208\b", 465),
        (r"\bH[\s.]*207\b", 466),
        (r"\bH[\s.]*206\b", 467),
        (r"\bH[\s.]*202\b", 468),
        (r"\bH[\s.]*204\b", 469),
        (r"\bH[\s.]*203\b", 470),
        (r"\bH[\s.]*201\b", 468),
        (r"\bH[\s.]*205\b", 465),
        (r"\bK[\s.]*302\b", 480),
        (r"\bK[\s.]*301\b", 481),
        (r"\bI[\s.]*104\b", 486),
        (r"\bI[\s.]*103\b", 487),
        (r"\bM[\s.]*10[12]\b", 492),
        (r"\bM[\s.]*104\b", 493),
        (r"\bM[\s.]*108\b", 494),
        (r"\bM\s*RSM", 493),
        (r"\bSTUDIO\s*[2346]", 474),
        (r"LABORATORIUM", 494),
    ]
    for pattern, sid in mapping:
        if re.search(pattern, u):
            return sid
    return None


def classify_room_pool(room: dict[str, Any], building_pool: str) -> str:
    """Assign pool cleanly, including GKB/cross-listed campus rooms matching their home faculty."""
    b_code = str(room.get("b_code") or "").upper().strip()
    r_name = str(room.get("name") or "").upper().strip()

    if b_code == "FIB" or b_code.startswith("FIB") or b_code == "A FIB":
        return "fib"

    if b_code == "GKB":
        if re.search(r"\b(?:H|O|M|F|I|A)[\s.\-]*\d{3}\b|STUDIO\s*\d|M\s*RSM|SEJARAH", r_name):
            return "fib"
        if "MATEMATIKA" in r_name or "MIPA" in r_name:
            return "fmipa"
        if "PSIKOLOGI" in r_name:
            return "psikologi"
        if re.match(r"^(C|D)-\d{3}$", r_name):
            return "fasilkom"
        if r_name.startswith("GL "):
            return "campus-root"

    return building_pool


def build_rectilinear_door_file(
    pano_abs_path: str,
    yaw_deg: float,
    out_path: pathlib.Path,
    cache: dict[str, np.ndarray],
) -> None:
    if pano_abs_path not in cache:
        with Image.open(pano_abs_path) as im:
            cache[pano_abs_path] = np.array(im.convert("RGB"), dtype=np.uint8)
    img_rgb = cache[pano_abs_path]
    h, w = img_rgb.shape[:2]
    haov, vaov = compute_source_projection(w, h)
    eff_hfov, eff_pitch = effective_fov_and_pitch(vaov_deg=vaov, requested_hfov_deg=70.0, requested_pitch_deg=-1.5)
    projected = project_rectilinear(
        img_rgb,
        yaw_deg=yaw_deg,
        pitch_deg=eff_pitch,
        hfov_deg=eff_hfov,
        out_w=960,
        out_h=720,
        vaov_deg=vaov,
        haov_deg=haov,
    )
    projected = subtle_sharpen(projected)
    save_webp(projected, out_path, quality=86)


def main() -> int:
    conn = sqlite3.connect(str(DB_PATH))
    conn.row_factory = sqlite3.Row
    cur = conn.cursor()
    buildings = [dict(r) for r in cur.execute("SELECT * FROM buildings ORDER BY id").fetchall()]
    rooms = [
        dict(r)
        for r in cur.execute(
            "SELECT r.*, b.code AS b_code, b.name AS b_name, b.faculty AS b_faculty "
            "FROM rooms r LEFT JOIN buildings b ON b.id = r.building_id ORDER BY r.id"
        ).fetchall()
    ]
    conn.close()

    print(f"[1/4] Analyzing & importing 360 panoramas for {len(buildings)} buildings and {len(rooms)} rooms...")
    pools, fib_scenes_by_id = build_pano_pools()
    for slug, plist in sorted(pools.items()):
        print(f"  pool {slug:16s}: {len(plist)} panos")
    print(f"  fib spherical scenes : {len(fib_scenes_by_id)} active")

    print("[2/4] Converting 29 FOTO PINTU + 3 real root/hukum door photos...")
    real_door_by_room: dict[int, str] = {}
    real_door_by_building: dict[int, str] = {}
    mapping_records: list[dict[str, Any]] = []

    all_real_specs = REAL_DOOR_SPECS + BONUS_REAL_DOORS
    room_lookup = {r["id"]: r for r in rooms}
    bldg_lookup = {b["id"]: b for b in buildings}

    for spec in all_real_specs:
        if spec.get("src_dir") == "PINTU":
            src_path = SOURCE_DOOR_DIR / spec["src_file"]
        else:
            src_path = pathlib.Path(spec["src_path"])
        out_path = PUBLIC_DOOR_REAL / spec["out"]
        rel_door = f"images/doors/real/{spec['out']}"
        w, h = save_optimized_webp(src_path, out_path, max_w=1200, quality=86)

        for rid in spec["room_ids"]:
            real_door_by_room[rid] = rel_door
        for bid in spec["building_ids"]:
            real_door_by_building[bid] = rel_door

        mapping_records.append({
            "source_filename": src_path.name,
            "source_folder": "FOTO PINTU" if spec.get("src_dir") == "PINTU" else src_path.parent.name,
            "plaque_read": spec["plaque"],
            "output_rel_path": rel_door,
            "dimensions": f"{w}x{h}",
            "matched_rooms": [
                {"id": rid, "name": room_lookup[rid]["name"], "building_code": room_lookup[rid]["b_code"]}
                for rid in spec["room_ids"]
                if rid in room_lookup
            ],
            "matched_buildings": [
                {"id": bid, "code": bldg_lookup[bid]["code"], "name": bldg_lookup[bid]["name"]}
                for bid in spec["building_ids"]
                if bid in bldg_lookup
            ],
            "note": spec["note"],
        })

    STORAGE_MAPS_DIR.mkdir(parents=True, exist_ok=True)
    door_map_path = STORAGE_MAPS_DIR / "door_photo_mapping.json"
    door_map_path.write_text(json.dumps(mapping_records, indent=2, ensure_ascii=False), encoding="utf-8")

    print("[3/4] Assigning 360s & projecting rectilinear door shots...")
    pano_rgb_cache: dict[str, np.ndarray] = {}
    pool_cursors: dict[str, int] = {k: 0 for k in pools}
    pano_yaw_use: dict[str, int] = {}

    def pick_pano(pool_slug: str, idx_hint: int | None = None) -> dict[str, Any]:
        plist = pools.get(pool_slug) or pools["campus-root"]
        if idx_hint is not None:
            return plist[idx_hint % len(plist)]
        pos = pool_cursors.get(pool_slug, 0)
        pool_cursors[pool_slug] = pos + 1
        return plist[pos % len(plist)]

    def next_yaw_for_pano(pano: dict[str, Any], prefer_first: bool = False) -> float:
        rel = pano["rel_path"]
        yaws = [b["yaw"] for b in pano.get("best_yaws", [])] or [0.0]
        if prefer_first:
            return float(yaws[0])
        k = pano_yaw_use.get(rel, 0)
        pano_yaw_use[rel] = k + 1
        return float(yaws[k % len(yaws)])

    building_manifest: list[dict[str, Any]] = []
    rectilinear_count = 0

    for b in buildings:
        bid = int(b["id"])
        pool_slug = classify_building_pool(b)

        # Select 360° panorama for this building
        if pool_slug == "fib":
            sid = fib_scene_for_building_code(str(b.get("code") or ""))
            pano = fib_scenes_by_id.get(sid) or pick_pano("fib")
            is_fib_spherical = sid in fib_scenes_by_id
        else:
            pano = pick_pano(pool_slug)
            is_fib_spherical = False

        # Select or project door photo for this building
        if bid in real_door_by_building:
            door_rel = real_door_by_building[bid]
            door_source = "real"
            chosen_yaw = float(pano.get("default_yaw", 0.0))
        else:
            door_slug = f"bldg-{slugify(b['code'])}.webp"
            door_out = PUBLIC_DOOR_360 / door_slug
            door_rel = f"images/doors/from-360/{door_slug}"
            door_source = "rectilinear_360"
            chosen_yaw = next_yaw_for_pano(pano)
            build_rectilinear_door_file(pano["abs_path"], chosen_yaw, door_out, pano_rgb_cache)
            rectilinear_count += 1

        meta = {
            "pool": pool_slug,
            "haov": pano["haov"],
            "vaov": pano["vaov"],
            "yaw": pano["default_yaw"] if is_fib_spherical else chosen_yaw,
            "pitch": pano["default_pitch"],
            "hfov": pano["hfov"],
            "min_pitch": pano["min_pitch"],
            "max_pitch": pano["max_pitch"],
            "width": pano["width"],
            "height": pano["height"],
            "door_source": door_source,
        }

        building_manifest.append({
            "id": bid,
            "code": b["code"],
            "name": b["name"],
            "faculty": b.get("faculty"),
            "pool": pool_slug,
            "photo_360_path": pano["rel_path"],
            "door_photo_path": door_rel,
            "photo_360_meta": meta,
        })

    building_by_id_meta = {bm["id"]: bm for bm in building_manifest}
    room_manifest: list[dict[str, Any]] = []

    # Separate index for GKB FIB rooms vs standard rooms so FIB/GL rooms cycle cleanly through FIB panos
    fib_folder_idx = 0

    for r in rooms:
        rid = int(r["id"])
        bid = int(r["building_id"])
        b_meta = building_by_id_meta[bid]
        r_pool = classify_room_pool(r, b_meta["pool"])

        # Pick 360° panorama for this room
        if r_pool == "fib":
            sid = fib_scene_for_room_name(str(r.get("name") or ""))
            if sid and sid in fib_scenes_by_id and (fib_folder_idx % 2 == 0 or bid == 27):
                pano = fib_scenes_by_id[sid]
                is_fib_spherical = True
            else:
                fib_list = pools.get("fib") or []
                if fib_list:
                    pano = fib_list[fib_folder_idx % len(fib_list)]
                    fib_folder_idx += 1
                    is_fib_spherical = False
                else:
                    pano = fib_scenes_by_id[495]
                    is_fib_spherical = True
        else:
            pano = pick_pano(r_pool)
            is_fib_spherical = False

        # Pick real door photo or generate varied rectilinear shot
        if rid in real_door_by_room:
            door_rel = real_door_by_room[rid]
            door_source = "real"
            chosen_yaw = float(pano.get("default_yaw", 0.0))
        else:
            door_slug = f"room-{rid:03d}-{slugify(r['name'])[:32]}.webp"
            door_out = PUBLIC_DOOR_360 / door_slug
            door_rel = f"images/doors/from-360/{door_slug}"
            door_source = "rectilinear_360"
            chosen_yaw = next_yaw_for_pano(pano)
            build_rectilinear_door_file(pano["abs_path"], chosen_yaw, door_out, pano_rgb_cache)
            rectilinear_count += 1

        meta = {
            "pool": r_pool,
            "haov": pano["haov"],
            "vaov": pano["vaov"],
            "yaw": pano["default_yaw"] if is_fib_spherical else chosen_yaw,
            "pitch": pano["default_pitch"],
            "hfov": pano["hfov"],
            "min_pitch": pano["min_pitch"],
            "max_pitch": pano["max_pitch"],
            "width": pano["width"],
            "height": pano["height"],
            "door_source": door_source,
        }

        room_manifest.append({
            "id": rid,
            "building_id": bid,
            "building_code": r.get("b_code"),
            "name": r["name"],
            "pool": r_pool,
            "photo_360_path": pano["rel_path"],
            "door_photo_path": door_rel,
            "photo_360_meta": meta,
        })

    full_manifest = {
        "stats": {
            "buildings_total": len(building_manifest),
            "rooms_total": len(room_manifest),
            "real_door_photos_imported": len(mapping_records),
            "rectilinear_door_shots_generated": rectilinear_count,
            "panos_per_pool": {k: len(v) for k, v in sorted(pools.items())},
            "fib_spherical_scenes_converted": len(fib_scenes_by_id),
        },
        "buildings": building_manifest,
        "rooms": room_manifest,
    }

    manifest_json = json.dumps(full_manifest, indent=2, ensure_ascii=False)
    (STORAGE_MAPS_DIR / "faculty_media_manifest.json").write_text(manifest_json, encoding="utf-8")
    SNAPSHOT_MANIFEST.parent.mkdir(parents=True, exist_ok=True)
    SNAPSHOT_MANIFEST.write_text(manifest_json, encoding="utf-8")

    print("[4/4] COMPLETE:", json.dumps(full_manifest["stats"], indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
