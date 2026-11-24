#!/usr/bin/env python3
"""
Deep Scan Panorama 360° Pipeline (FASE 1 + FASE 2) for FOTO 360P (112 images).

Features:
- FASE 1: Idempotent 2-pass safe rename in D:/Documents/TUBES WEB SEMANTIK/FOTO 360P (1.jpg..112.jpg)
          + SHA-1 verification + rename_mapping.json + copy to public/images/foto-360p/
- FASE 2: Adaptive equirectangular/cylindrical/rectilinear unwarping using numpy + cv2.remap (LANCZOS4)
          with CLAHE + unsharp contrast enhancement, multi-angle AI Vision scan via ClassAI,
          and mandatory zoom verification pass (fov=35°) to guarantee zero hallucination.
- Resumable per-photo scan_cache/{id}.json + parallel ThreadPoolExecutor + atomic scan_results.json
  + invokes `php artisan foto360p:markdown` to build DAFTAR_RUANGAN_DEEP_SCAN_360P.md.
"""

import argparse
import base64
import concurrent.futures
import datetime
import difflib
import hashlib
import json
import math
import os
import re
import shutil
import subprocess
import sys
import tempfile
import threading
import time
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

import cv2
import numpy as np
import requests
import urllib3
from PIL import Image

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

PROJECT_ROOT = Path(__file__).resolve().parent.parent
DEFAULT_SRC_DIR = Path(r"D:\Documents\TUBES WEB SEMANTIK\FOTO 360P")
PUBLIC_DIR = PROJECT_ROOT / "public" / "images" / "foto-360p"
STORAGE_DIR = PROJECT_ROOT / "storage" / "app" / "foto-360p"
CACHE_DIR = STORAGE_DIR / "scan_cache"
TILES_TMP_DIR = STORAGE_DIR / "_tiles_tmp"
RENAME_MAPPING_PATH = STORAGE_DIR / "rename_mapping.json"
SCAN_RESULTS_PATH = STORAGE_DIR / "scan_results.json"

ENV_PATH = PROJECT_ROOT / ".env"

MODEL_CANDIDATES = [
    "claude-sonnet-4.5",
    "claude-sonnet-4",
    "gpt-4o",
    "claude-haiku-4.5",
]

PRINT_LOCK = threading.Lock()


def log(msg: str) -> None:
    with PRINT_LOCK:
        print(msg, flush=True)


def load_env(path: Path) -> Dict[str, str]:
    env: Dict[str, str] = {}
    if not path.exists():
        return env
    with path.open("r", encoding="utf-8", errors="ignore") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            k, v = line.split("=", 1)
            env[k.strip()] = v.strip().strip('"').strip("'")
    return env


def sha1_file(path: Path) -> str:
    h = hashlib.sha1()
    with path.open("rb") as f:
        while True:
            chunk = f.read(65536)
            if not chunk:
                break
            h.update(chunk)
    return h.hexdigest()


def projection_for(w: int, h: int) -> Dict[str, Any]:
    if w <= 0 or h <= 0:
        return {"type": "equirectangular", "haov": 360.0, "vaov": 60.0, "is_panorama": True}
    ratio = w / float(h)
    if ratio >= 2.2:
        return {
            "type": "equirectangular",
            "haov": 360.0,
            "vaov": round(360.0 * h / w, 2),
            "is_panorama": True,
        }
    if ratio >= 1.8:
        return {
            "type": "equirectangular",
            "haov": 360.0,
            "vaov": 180.0,
            "is_panorama": True,
        }
    return {
        "type": "equirectangular",
        "haov": 90.0,
        "vaov": round(90.0 * h / w, 2),
        "is_panorama": False,
    }


def coords_to_pct(yaw: float, pitch: float, proj: Dict[str, Any]) -> Tuple[float, float]:
    haov = max(1.0, float(proj.get("haov", 360.0)))
    vaov = max(1.0, float(proj.get("vaov", 60.0)))
    if haov >= 359.9:
        x_pct = ((yaw + 180.0) % 360.0) / 360.0
    else:
        x_pct = (yaw + haov / 2.0) / haov
    y_pct = (vaov / 2.0 - pitch) / vaov
    return (
        round(max(0.0, min(1.0, x_pct)), 4),
        round(max(0.0, min(1.0, y_pct)), 4),
    )


def atomic_write_json(path: Path, data: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    fd, tmp_name = tempfile.mkstemp(prefix=path.name + ".", suffix=".tmp", dir=str(path.parent))
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as f:
            json.dump(data, f, ensure_ascii=False, indent=2)
        os.replace(tmp_name, path)
    except Exception:
        if os.path.exists(tmp_name):
            os.unlink(tmp_name)
        raise


# ---------------------------------------------------------------------------
# FASE 1: 2-Pass Safe Rename + Mapping + Copy to public/images/foto-360p/
# ---------------------------------------------------------------------------

def run_rename_phase(src_dir: Path) -> List[Dict[str, Any]]:
    if not src_dir.is_dir():
        raise FileNotFoundError(f"Source directory not found: {src_dir}")

    STORAGE_DIR.mkdir(parents=True, exist_ok=True)
    PUBLIC_DIR.mkdir(parents=True, exist_ok=True)

    current_jpgs = sorted(
        [p for p in src_dir.iterdir() if p.is_file() and p.suffix.lower() == ".jpg"],
        key=lambda p: p.name.lower(),
    )
    if len(current_jpgs) != 112:
        raise RuntimeError(f"Expected exactly 112 JPG files in {src_dir}, found {len(current_jpgs)}")

    expected_names = {f"{i}.jpg" for i in range(1, 113)}
    actual_names = {p.name for p in current_jpgs}

    if RENAME_MAPPING_PATH.is_file() and actual_names == expected_names:
        log("[FASE 1] Files 1.jpg..112.jpg already renamed cleanly and mapping exists. Re-syncing public copies...")
        with RENAME_MAPPING_PATH.open("r", encoding="utf-8") as f:
            mapping_data = json.load(f)
        entries = mapping_data.get("items", mapping_data) if isinstance(mapping_data, dict) else mapping_data
        by_id = {int(e["id"]): e for e in entries}
        for i in range(1, 113):
            src_file = src_dir / f"{i}.jpg"
            dst_file = PUBLIC_DIR / f"{i}.jpg"
            cur_sha1 = sha1_file(src_file)
            if cur_sha1 != by_id[i]["sha1"]:
                raise RuntimeError(f"SHA1 mismatch on existing {src_file}")
            shutil.copy2(src_file, dst_file)
            if sha1_file(dst_file) != cur_sha1:
                raise RuntimeError(f"Copy SHA1 mismatch on {dst_file}")
        log(f"[FASE 1] Verified & synced 112 files to {PUBLIC_DIR}")
        return [by_id[i] for i in range(1, 113)]

    # Build deterministic ascending mapping BEFORE any filesystem mutation
    entries: List[Dict[str, Any]] = []
    for idx, src_path in enumerate(current_jpgs, start=1):
        with Image.open(src_path) as im:
            w, h = im.size
        entries.append({
            "id": idx,
            "filename": f"{idx}.jpg",
            "original_filename": src_path.name,
            "size_bytes": src_path.stat().st_size,
            "width": int(w),
            "height": int(h),
            "sha1": sha1_file(src_path),
        })

    mapping_payload = {
        "generated_at": datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=7))).isoformat(),
        "source_dir": str(src_dir),
        "total_images": len(entries),
        "items": entries,
    }
    atomic_write_json(RENAME_MAPPING_PATH, mapping_payload)
    log(f"[FASE 1] Saved rename_mapping.json BEFORE rename ({len(entries)} entries)")

    # Pass 1: rename every file to __tmp_001.jpg .. __tmp_112.jpg
    for entry in entries:
        orig_path = src_dir / entry["original_filename"]
        tmp_path = src_dir / f"__tmp_{entry['id']:03d}.jpg"
        orig_path.rename(tmp_path)

    # Pass 2: rename __tmp_001.jpg -> 1.jpg .. 112.jpg and verify SHA-1
    for entry in entries:
        tmp_path = src_dir / f"__tmp_{entry['id']:03d}.jpg"
        final_path = src_dir / entry["filename"]
        tmp_path.rename(final_path)
        if sha1_file(final_path) != entry["sha1"]:
            raise RuntimeError(f"Post-rename SHA1 verification failed for {final_path}")

    # Copy all 112 files to USUClassMap/public/images/foto-360p/
    for existing_dst in PUBLIC_DIR.glob("*.jpg"):
        existing_dst.unlink()

    for entry in entries:
        src_file = src_dir / entry["filename"]
        dst_file = PUBLIC_DIR / entry["filename"]
        shutil.copy2(src_file, dst_file)
        if sha1_file(dst_file) != entry["sha1"]:
            raise RuntimeError(f"Public copy SHA1 verification failed for {dst_file}")

    public_count = len(list(PUBLIC_DIR.glob("*.jpg")))
    log(f"[FASE 1] 2-pass rename & copy COMPLETE: {public_count} files ready in {PUBLIC_DIR}")
    return entries


# ---------------------------------------------------------------------------
# FASE 2: Rectilinear Unwarping + Contrast Enhance + ClassAI Vision + Zoom Pass
# ---------------------------------------------------------------------------

def enhance_image(bgr: np.ndarray) -> np.ndarray:
    """Apply subtle CLAHE on L channel + unsharp mask for small whiteboard/door signage."""
    lab = cv2.cvtColor(bgr, cv2.COLOR_BGR2LAB)
    l_ch, a_ch, b_ch = cv2.split(lab)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    l_enhanced = clahe.apply(l_ch)
    merged = cv2.cvtColor(cv2.merge([l_enhanced, a_ch, b_ch]), cv2.COLOR_LAB2BGR)
    blurred = cv2.GaussianBlur(merged, (0, 0), sigmaX=1.2)
    sharpened = cv2.addWeighted(merged, 1.5, blurred, -0.5, 0)
    return sharpened


def render_rectilinear_view(
    src_bgr: np.ndarray,
    haov: float,
    vaov: float,
    yaw0_deg: float,
    pitch0_deg: float,
    fov_h_deg: float = 70.0,
    out_w: int = 800,
    out_h: Optional[int] = None,
) -> np.ndarray:
    H, W = src_bgr.shape[:2]
    f = (out_w / 2.0) / math.tan(math.radians(fov_h_deg / 2.0))

    if out_h is None:
        fov_v_deg = min(vaov, fov_h_deg)
        out_h = max(240, int(round(2.0 * f * math.tan(math.radians(fov_v_deg / 2.0)))))

    # Pixel grid relative to center
    xs = (np.arange(out_w, dtype=np.float32) - (out_w / 2.0))[None, :]  # +right
    ys_up = ((out_h / 2.0) - np.arange(out_h, dtype=np.float32))[:, None]  # +up
    zs = np.full((out_h, out_w), f, dtype=np.float32)

    p0 = math.radians(pitch0_deg)
    y0 = math.radians(yaw0_deg)
    cp, sp = math.cos(p0), math.sin(p0)
    cy, sy = math.cos(y0), math.sin(y0)

    # Rotate camera ray by pitch0 (around X), then yaw0 (around Y)
    x1 = np.broadcast_to(xs, (out_h, out_w))
    y1 = ys_up * cp + zs * sp
    z1 = -ys_up * sp + zs * cp

    x2 = x1 * cy + z1 * sy
    z2 = -x1 * sy + z1 * cy

    r = np.sqrt(x2 * x2 + y1 * y1 + z2 * z2)
    lon_deg = np.degrees(np.arctan2(x2, z2))
    lat_deg = np.degrees(np.arcsin(np.clip(y1 / np.maximum(r, 1e-6), -1.0, 1.0)))

    if haov >= 359.9:
        src_x = ((lon_deg + 180.0) % 360.0) / 360.0 * W
    else:
        half_h = haov / 2.0
        src_x = np.where(
            (lon_deg >= -half_h) & (lon_deg <= half_h),
            (lon_deg + half_h) / haov * W,
            -1.0,
        )

    half_v = vaov / 2.0
    src_y = np.where(
        (lat_deg >= -half_v) & (lat_deg <= half_v),
        (half_v - lat_deg) / vaov * H,
        -1.0,
    )

    # Set x invalid wherever y is invalid so cv2.remap paints border black cleanly
    src_x = np.where(src_y >= 0.0, src_x, -1.0).astype(np.float32)
    src_y = np.where(src_x >= 0.0, src_y, -1.0).astype(np.float32)

    if haov >= 359.9:
        # Horizontal wrap around seam by padding 2 columns from opposite edges for seam-free LANCZOS4
        pad = 4
        wrapped_bgr = np.concatenate([src_bgr[:, -pad:], src_bgr, src_bgr[:, :pad]], axis=1)
        src_x_wrapped = np.where(src_x >= 0.0, src_x + pad, -1.0).astype(np.float32)
        view = cv2.remap(
            wrapped_bgr,
            src_x_wrapped,
            src_y,
            interpolation=cv2.INTER_LANCZOS4,
            borderMode=cv2.BORDER_CONSTANT,
            borderValue=(0, 0, 0),
        )
    else:
        view = cv2.remap(
            src_bgr,
            src_x,
            src_y,
            interpolation=cv2.INTER_LANCZOS4,
            borderMode=cv2.BORDER_CONSTANT,
            borderValue=(0, 0, 0),
        )

    return enhance_image(view)


def tile_pixel_to_yaw_pitch(
    cx: float,
    cy: float,
    yaw0_deg: float,
    pitch0_deg: float,
    fov_h_deg: float,
    out_w: int,
    out_h: int,
) -> Tuple[float, float]:
    f = (out_w / 2.0) / math.tan(math.radians(fov_h_deg / 2.0))
    x = (max(0.0, min(1.0, float(cx))) - 0.5) * out_w
    y_up = (0.5 - max(0.0, min(1.0, float(cy)))) * out_h

    p0 = math.radians(pitch0_deg)
    y0 = math.radians(yaw0_deg)
    cp, sp = math.cos(p0), math.sin(p0)
    cy_cos, sy_sin = math.cos(y0), math.sin(y0)

    y1 = y_up * cp + f * sp
    z1 = -y_up * sp + f * cp

    x2 = x * cy_cos + z1 * sy_sin
    z2 = -x * sy_sin + z1 * cy_cos

    r = math.sqrt(x2 * x2 + y1 * y1 + z2 * z2)
    yaw_deg = math.degrees(math.atan2(x2, z2))
    pitch_deg = math.degrees(math.asin(max(-1.0, min(1.0, y1 / max(r, 1e-6)))))
    return (round(yaw_deg, 1), round(pitch_deg, 1))


def build_tile_specs(proj: Dict[str, Any]) -> List[Dict[str, Any]]:
    haov = float(proj["haov"])
    vaov = float(proj["vaov"])
    is_pano = bool(proj["is_panorama"])

    if is_pano:
        yaws = [-135.0, -90.0, -45.0, 0.0, 45.0, 90.0, 135.0, 180.0]
        fov_h = 70.0
    else:
        yaws = [-22.5, 22.5]
        fov_h = 65.0

    if vaov <= 75.0:
        pitches = [0.0]
    else:
        max_p = max(0.0, (vaov / 2.0) - 25.0)
        pitches = [0.0]
        if max_p >= 20.0:
            p_step = min(35.0, max_p)
            pitches = [0.0, round(p_step, 1), round(-p_step, 1)]
        if max_p >= 55.0 and not is_pano:
            pitches = [0.0, 35.0, -35.0]

    # Cap portrait tiles cleanly while covering top/middle/bottom
    out_w = 800
    f = (out_w / 2.0) / math.tan(math.radians(fov_h / 2.0))
    fov_v = min(vaov, 70.0)
    out_h = max(240, int(round(2.0 * f * math.tan(math.radians(fov_v / 2.0)))))

    specs: List[Dict[str, Any]] = []
    k = 1
    for p in pitches:
        for y in yaws:
            specs.append({
                "tile": k,
                "yaw0": y,
                "pitch0": p,
                "fov_h": fov_h,
                "out_w": out_w,
                "out_h": out_h,
            })
            k += 1
    return specs


def encode_bgr_b64(bgr: np.ndarray, quality: int = 85) -> str:
    ok, buf = cv2.imencode(".jpg", bgr, [int(cv2.IMWRITE_JPEG_QUALITY), quality])
    if not ok:
        raise RuntimeError("Failed to JPEG-encode image buffer")
    return base64.b64encode(buf.tobytes()).decode("ascii")


def extract_json_object(text: str) -> Dict[str, Any]:
    text = text.strip()
    # Strip markdown code fences if present
    fenced = re.search(r"```(?:json)?\s*(\{.*?\})\s*```", text, re.S)
    if fenced:
        text = fenced.group(1)
    first_brace = text.find("{")
    last_brace = text.rfind("}")
    if first_brace != -1 and last_brace > first_brace:
        candidate = text[first_brace : last_brace + 1]
        return json.loads(candidate)
    raise ValueError(f"No JSON object found in model output: {text[:250]}")


def call_vision_api(
    endpoint: str,
    api_key: str,
    model: str,
    content_parts: List[Dict[str, Any]],
    max_tokens: int = 1500,
) -> str:
    last_err: Optional[Exception] = None
    for attempt in range(1, 4):
        try:
            resp = requests.post(
                endpoint,
                headers={
                    "Authorization": f"Bearer {api_key}",
                    "Content-Type": "application/json",
                },
                json={
                    "model": model,
                    "temperature": 0,
                    "max_tokens": max_tokens,
                    "messages": [{"role": "user", "content": content_parts}],
                },
                timeout=120,
                verify=False,
            )
            if resp.status_code in (429, 500, 502, 503, 504):
                raise RuntimeError(f"Transient HTTP {resp.status_code}: {resp.text[:200]}")
            if resp.status_code >= 400:
                raise RuntimeError(f"HTTP {resp.status_code}: {resp.text[:300]}")
            payload = resp.json()
            return str(payload["choices"][0]["message"]["content"])
        except Exception as e:
            last_err = e
            if attempt < 3:
                time.sleep(2.0 * attempt)
    raise RuntimeError(f"Vision API failed after 3 retries: {last_err}")


def select_best_vision_model(endpoint: str, api_key: str, forced_model: Optional[str]) -> str:
    if forced_model:
        return forced_model

    from PIL import ImageDraw

    probe_img = Image.new("RGB", (340, 100), (255, 255, 255))
    draw = ImageDraw.Draw(probe_img)
    draw.text((20, 35), "RUANG 104 - LAB KOMPUTER", fill=(0, 0, 0))
    probe_bgr = cv2.cvtColor(np.array(probe_img), cv2.COLOR_RGB2BGR)
    b64 = encode_bgr_b64(probe_bgr, 88)

    for candidate in MODEL_CANDIDATES:
        try:
            out = call_vision_api(
                endpoint,
                api_key,
                candidate,
                [
                    {"type": "text", "text": "Read the exact text in this image. Return only the text."},
                    {"type": "image_url", "image_url": {"url": f"data:image/jpeg;base64,{b64}"}},
                ],
                max_tokens=40,
            )
            norm = re.sub(r"[^A-Z0-9]", "", out.upper())
            if "104" in norm or "RUANG" in norm or "KOMPUTER" in norm:
                log(f"[MODEL PROBE] Selected vision model: {candidate} (probe={out.strip()!r})")
                return candidate
        except Exception as e:
            log(f"[MODEL PROBE] Candidate {candidate} unavailable: {e}")

    raise RuntimeError("No ClassAI vision model succeeded during probe")


def norm_alnum(s: str) -> str:
    return re.sub(r"[^A-Z0-9]", "", (s or "").upper())


def analyze_single_image(
    entry: Dict[str, Any],
    endpoint: str,
    api_key: str,
    model: str,
    force: bool = False,
) -> Dict[str, Any]:
    img_id = int(entry["id"])
    cache_path = CACHE_DIR / f"{img_id}.json"

    if not force and cache_path.is_file():
        with cache_path.open("r", encoding="utf-8") as f:
            cached = json.load(f)
        if cached.get("status") in ("detected", "no_text", "blurry"):
            return cached

    img_path = PUBLIC_DIR / entry["filename"]
    if not img_path.is_file():
        raise FileNotFoundError(f"Public image missing: {img_path}")

    src_bgr = cv2.imread(str(img_path))
    if src_bgr is None:
        raise RuntimeError(f"Failed to read image with OpenCV: {img_path}")

    H, W = src_bgr.shape[:2]
    proj = projection_for(W, H)
    tile_specs = build_tile_specs(proj)

    tile_dir = TILES_TMP_DIR / str(img_id)
    tile_dir.mkdir(parents=True, exist_ok=True)

    try:
        # Create overview image (<= 1568 px wide)
        scale = min(1.0, 1568.0 / max(1, W))
        ov_w = max(1, int(round(W * scale)))
        ov_h = max(1, int(round(H * scale)))
        overview_bgr = cv2.resize(src_bgr, (ov_w, ov_h), interpolation=cv2.INTER_AREA)

        # Render rectilinear tiles & persist temporarily for inspection/debugging
        rendered_tiles: List[Tuple[Dict[str, Any], np.ndarray]] = []
        for spec in tile_specs:
            t_bgr = render_rectilinear_view(
                src_bgr,
                proj["haov"],
                proj["vaov"],
                spec["yaw0"],
                spec["pitch0"],
                spec["fov_h"],
                spec["out_w"],
                spec["out_h"],
            )
            cv2.imwrite(str(tile_dir / f"tile_{spec['tile']:02d}.jpg"), t_bgr)
            rendered_tiles.append((spec, t_bgr))

        main_prompt = (
            "Anda adalah sistem AI Computer Vision presisi tinggi yang memindai foto ruangan & gedung kampus "
            "(koridor kelas, laboratorium, aula, kantor, dll).\n"
            "Berikut diberikan:\n"
            "1) OVERVIEW: gambar penuh.\n"
            "2) Beberapa TILE (potongan perspektif rectilinear yang sudah diperbaiki distorsinya).\n\n"
            "ATURAN MUTLAK ZERO-HALLUCINATION:\n"
            "- Hanya laporkan tulisan yang BENAR-BENAR TERBACA secara fisik huruf demi huruf di gambar/tile.\n"
            "- DILARANG KERAS menebak atau mengarang nama ruangan/fakultas dari suasana ruangan bila tidak ada papan teksnya!\n"
            "- Jika tidak ada papan/tulisan sama sekali -> status = \"no_text\" dan rooms = [].\n"
            "- Jika ada papan/tulisan tetapi terlalu buram/pecah untuk dipastikan bacaannya -> status = \"blurry\", rooms = [], "
            "dan jelaskan pada scene_summary.\n"
            "- Masukkan ke `rooms` HANYA papan/label ruangan atau lokasi (misal kode/nomor ruang kuliah seperti R.101, 2.5, "
            "Lab Komputer, Ruang Sidang, Ruang Dosen, Tata Usaha, Aula, Perpustakaan, Musholla, Toilet, papan nama fakultas/departemen/lantai).\n"
            "- Tulisan lain yang bukan penanda ruangan (mis. spanduk acara, poster, jam, merek AC/proyektor, jadwal kertas) "
            "masukkan ke `all_readable_texts` saja, JANGAN ke `rooms`.\n"
            "- Untuk setiap entri di `rooms`, beri nomor `tile` (1..N) tempat papan terlihat paling jelas, serta posisi pusat papan "
            "di tile tersebut `cx` (0.0=kiri .. 1.0=kanan) dan `cy` (0.0=atas .. 1.0=bawah).\n\n"
            "Balas HANYA JSON murni valid sesuai skema ini tanpa teks lain:\n"
            '{"status":"detected|no_text|blurry","scene_summary":"ringkasan singkat kondisi foto (Indonesia)",'
            '"all_readable_texts":["teks..."],"rooms":[{"room_name":"nama ringkas rapi",'
            '"raw_text":"karakter persis di papan","category":"ruang_kuliah|laboratorium|ruang_dosen|administrasi|fasilitas|papan_fakultas|lainnya",'
            '"confidence":"high|medium|low","tile":1,"cx":0.5,"cy":0.5,"evidence_note":"lokasi visual papan"}]}'
        )

        # If a full spherical/portrait image has > 12 tiles, chunk into <= 10 tiles/request so payload stays lean
        batches = [rendered_tiles[i : i + 10] for i in range(0, len(rendered_tiles), 10)]
        merged_summaries: List[str] = []
        merged_all_texts: List[str] = []
        raw_model_status = "no_text"
        candidate_rooms: List[Dict[str, Any]] = []

        for b_idx, batch in enumerate(batches):
            parts: List[Dict[str, Any]] = [
                {"type": "text", "text": main_prompt},
                {"type": "text", "text": f"OVERVIEW ({W}x{H}):"},
                {"type": "image_url", "image_url": {"url": f"data:image/jpeg;base64,{encode_bgr_b64(overview_bgr, 82)}"}},
            ]
            for spec, t_bgr in batch:
                parts.append({
                    "type": "text",
                    "text": f"TILE {spec['tile']} (yaw={spec['yaw0']}, pitch={spec['pitch0']}, fov={spec['fov_h']}):",
                })
                parts.append({
                    "type": "image_url",
                    "image_url": {"url": f"data:image/jpeg;base64,{encode_bgr_b64(t_bgr, 85)}"},
                })

            raw_out = call_vision_api(endpoint, api_key, model, parts, max_tokens=1500)
            parsed = extract_json_object(raw_out)

            st = str(parsed.get("status", "no_text")).lower()
            if st == "detected":
                raw_model_status = "detected"
            elif st == "blurry" and raw_model_status != "detected":
                raw_model_status = "blurry"

            if parsed.get("scene_summary"):
                merged_summaries.append(str(parsed["scene_summary"]).strip())

            for txt in parsed.get("all_readable_texts") or []:
                s = str(txt).strip()
                if s and s not in merged_all_texts:
                    merged_all_texts.append(s)

            spec_map = {s["tile"]: s for s, _ in batch}
            for r in parsed.get("rooms") or []:
                if not isinstance(r, dict):
                    continue
                t_num = int(r.get("tile") or batch[0][0]["tile"])
                spec = spec_map.get(t_num, batch[0][0])
                r_copy = dict(r)
                r_copy["_spec"] = spec
                candidate_rooms.append(r_copy)

        # Mandatory Zoom Verification Pass (fov=35°) per candidate room
        kept_rooms: List[Dict[str, Any]] = []
        rejected_candidates: List[Dict[str, Any]] = []

        for cand in candidate_rooms:
            room_name = str(cand.get("room_name", "")).strip()
            raw_text = str(cand.get("raw_text", room_name)).strip()
            if not room_name or not raw_text:
                continue

            spec = cand["_spec"]
            cx = float(cand.get("cx", 0.5))
            cy = float(cand.get("cy", 0.5))
            yaw_deg, pitch_deg = tile_pixel_to_yaw_pitch(
                cx, cy, spec["yaw0"], spec["pitch0"], spec["fov_h"], spec["out_w"], spec["out_h"]
            )

            # Skip redundant zoom API calls if an identical room is already kept within 22°
            is_dup = False
            n_room = norm_alnum(room_name)
            for ex in kept_rooms:
                if norm_alnum(ex["room_name"]) == n_room:
                    dyaw = abs((yaw_deg - ex["yaw"] + 180.0) % 360.0 - 180.0)
                    dpitch = abs(pitch_deg - ex["pitch"])
                    if dyaw <= 22.0 and dpitch <= 22.0:
                        is_dup = True
                        break
            if is_dup:
                continue

            zoom_bgr = render_rectilinear_view(
                src_bgr,
                proj["haov"],
                proj["vaov"],
                yaw_deg,
                pitch_deg,
                fov_h_deg=35.0,
                out_w=1000,
                out_h=700,
            )

            verify_prompt = (
                "Look closely at this zoomed crop from a campus photo. "
                "Is there clearly readable signage/text naming a room or location near the center/frame? "
                "Transcribe the EXACT visible characters faithfully. Do NOT guess blurry or unreadable letters.\n"
                'Return JSON only: {"visible": true|false, "read_as": "exact characters or empty string"}'
            )
            verify_raw = call_vision_api(
                endpoint,
                api_key,
                model,
                [
                    {"type": "text", "text": verify_prompt},
                    {"type": "image_url", "image_url": {"url": f"data:image/jpeg;base64,{encode_bgr_b64(zoom_bgr, 88)}"}},
                ],
                max_tokens=160,
            )
            try:
                verify_json = extract_json_object(verify_raw)
            except Exception:
                verify_json = {"visible": False, "read_as": ""}

            is_vis = bool(verify_json.get("visible", False))
            read_as = str(verify_json.get("read_as", "")).strip()
            n_cand = norm_alnum(raw_text)
            n_read = norm_alnum(read_as)
            sim = difflib.SequenceMatcher(None, n_read, n_cand).ratio() if (n_cand and n_read) else 0.0

            if is_vis and sim >= 0.6:
                x_pct, y_pct = coords_to_pct(yaw_deg, pitch_deg, proj)
                kept_rooms.append({
                    "room_name": room_name,
                    "raw_text": raw_text,
                    "verified_read": read_as,
                    "category": str(cand.get("category", "lainnya") or "lainnya"),
                    "confidence": str(cand.get("confidence", "medium") or "medium"),
                    "yaw": yaw_deg,
                    "pitch": pitch_deg,
                    "x_pct": x_pct,
                    "y_pct": y_pct,
                    "evidence_note": str(cand.get("evidence_note", "") or ""),
                    "source": "ai",
                })
            else:
                rejected_candidates.append({
                    "room_name": room_name,
                    "raw_text": raw_text,
                    "zoom_visible": is_vis,
                    "zoom_read_as": read_as,
                    "similarity": round(sim, 3),
                    "yaw": yaw_deg,
                    "pitch": pitch_deg,
                })

        if len(kept_rooms) > 0:
            final_status = "detected"
        elif raw_model_status == "blurry" or len(rejected_candidates) > 0:
            final_status = "blurry"
        else:
            final_status = "no_text"

        scene_summary = merged_summaries[0] if merged_summaries else (
            "Tulisan terdeteksi." if final_status == "detected" else
            "Papan atau teks terlihat namun terlalu buram untuk diverifikasi." if final_status == "blurry" else
            "Tidak terdapat papan nama ruangan pada foto ini."
        )

        item_result = {
            "id": img_id,
            "filename": entry["filename"],
            "original_filename": entry["original_filename"],
            "image_url": f"/images/foto-360p/{entry['filename']}",
            "width": int(W),
            "height": int(H),
            "size_kb": round(img_path.stat().st_size / 1024.0, 1),
            "projection": proj,
            "status": final_status,
            "detected_rooms": kept_rooms,
            "rejected_candidates": rejected_candidates,
            "all_readable_texts": merged_all_texts,
            "scene_summary": scene_summary,
            "verified_by_admin": False,
            "admin_note": "",
            "scanned_at": datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=7))).isoformat(),
        }

        atomic_write_json(cache_path, item_result)
        return item_result
    finally:
        if tile_dir.exists():
            shutil.rmtree(tile_dir, ignore_errors=True)


def run_scan_phase(
    entries: List[Dict[str, Any]],
    only_ids: Optional[List[int]],
    limit: Optional[int],
    workers: int,
    force: bool,
    forced_model: Optional[str],
) -> None:
    env = load_env(ENV_PATH)
    api_key = env.get("CLASSAI_API_KEY", "").strip()
    if not api_key:
        raise RuntimeError("CLASSAI_API_KEY not found in USUClassMap/.env")
    endpoint = env.get("CLASSAI_ENDPOINT", "https://router.classai.id/v1/chat/completions").strip()

    CACHE_DIR.mkdir(parents=True, exist_ok=True)
    TILES_TMP_DIR.mkdir(parents=True, exist_ok=True)

    selected_model = select_best_vision_model(endpoint, api_key, forced_model)

    targets = entries
    if only_ids:
        id_set = set(only_ids)
        targets = [e for e in targets if int(e["id"]) in id_set]
    if limit is not None and limit > 0:
        targets = targets[:limit]

    log(f"[FASE 2] Starting Deep Scan on {len(targets)} images with workers={workers}, model={selected_model}...")

    errors: List[Tuple[int, str]] = []
    completed = 0

    def worker_fn(entry: Dict[str, Any]) -> Tuple[int, Optional[Dict[str, Any]], Optional[str]]:
        try:
            res = analyze_single_image(entry, endpoint, api_key, selected_model, force=force)
            return (int(entry["id"]), res, None)
        except Exception as exc:
            return (int(entry["id"]), None, str(exc))

    with concurrent.futures.ThreadPoolExecutor(max_workers=max(1, workers)) as executor:
        futures = [executor.submit(worker_fn, e) for e in targets]
        for fut in concurrent.futures.as_completed(futures):
            img_id, res, err = fut.result()
            completed += 1
            if err:
                errors.append((img_id, err))
                log(f"  [{completed}/{len(targets)}] #{img_id}: ERROR -> {err[:160]}")
            else:
                rooms = [r["room_name"] for r in res.get("detected_rooms", [])]
                log(f"  [{completed}/{len(targets)}] #{img_id}: status={res['status']} rooms={rooms}")

    # Clean temp tile directory completely
    if TILES_TMP_DIR.exists():
        shutil.rmtree(TILES_TMP_DIR, ignore_errors=True)

    # Preserve existing verified_by_admin / admin_note if scan_results.json existed
    existing_admin: Dict[int, Dict[str, Any]] = {}
    if SCAN_RESULTS_PATH.is_file():
        try:
            with SCAN_RESULTS_PATH.open("r", encoding="utf-8") as f:
                old = json.load(f)
            for item in old.get("items", []):
                if item.get("verified_by_admin") or item.get("admin_note"):
                    existing_admin[int(item["id"])] = {
                        "verified_by_admin": bool(item.get("verified_by_admin", False)),
                        "admin_note": str(item.get("admin_note", "")),
                    }
        except Exception:
            pass

    # Assemble scan_results.json from all cached files in 1..112
    all_items: List[Dict[str, Any]] = []
    missing_ids: List[int] = []
    for entry in entries:
        eid = int(entry["id"])
        cpath = CACHE_DIR / f"{eid}.json"
        if cpath.is_file():
            with cpath.open("r", encoding="utf-8") as f:
                item = json.load(f)
            if eid in existing_admin:
                item["verified_by_admin"] = existing_admin[eid]["verified_by_admin"]
                item["admin_note"] = existing_admin[eid]["admin_note"]
            all_items.append(item)
        else:
            missing_ids.append(eid)

    if len(all_items) == len(entries) and not errors and not missing_ids:
        payload = {
            "generated_at": datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=7))).isoformat(),
            "total_images": len(all_items),
            "scan_engine": f"ClassAI Vision ({selected_model}) + rectilinear unwarp (CLAHE+sharpen) + zoom verification pass",
            "items": sorted(all_items, key=lambda x: int(x["id"])),
        }
        atomic_write_json(SCAN_RESULTS_PATH, payload)
        log(f"[FASE 2] Wrote complete scan_results.json ({len(all_items)} items)")

        log("[FASE 2] Running `php artisan foto360p:markdown`...")
        proc = subprocess.run(
            ["php", "artisan", "foto360p:markdown"],
            cwd=str(PROJECT_ROOT),
            capture_output=True,
            text=True,
        )
        log(proc.stdout)
        if proc.returncode != 0:
            raise RuntimeError(f"foto360p:markdown failed: {proc.stderr}")
    else:
        log(f"[FASE 2] Partial cache saved. cached={len(all_items)}/{len(entries)}, errors={[e[0] for e in errors]}, missing={missing_ids}")
        if errors:
            raise RuntimeError(f"Scan errors occurred on IDs: {[e[0] for e in errors]}")


def load_entries_for_scan(src_dir: Path) -> List[Dict[str, Any]]:
    if RENAME_MAPPING_PATH.is_file():
        with RENAME_MAPPING_PATH.open("r", encoding="utf-8") as f:
            data = json.load(f)
        items = data.get("items", data) if isinstance(data, dict) else data
        if len(items) == 112:
            return sorted(items, key=lambda x: int(x["id"]))
    return run_rename_phase(src_dir)


def main() -> None:
    parser = argparse.ArgumentParser(description="Deep Scan 112 FOTO 360P Panoramas")
    parser.add_argument("--phase", choices=["rename", "scan", "all"], default="all")
    parser.add_argument("--src", default=str(DEFAULT_SRC_DIR))
    parser.add_argument("--only", default="", help="Comma-separated IDs, e.g. 1,3,33,60")
    parser.add_argument("--limit", type=int, default=None)
    parser.add_argument("--workers", type=int, default=3)
    parser.add_argument("--force", action="store_true")
    parser.add_argument("--model", default=None)
    args = parser.parse_args()

    src_dir = Path(args.src)
    only_ids = [int(x.strip()) for x in args.only.split(",") if x.strip()] if args.only else None

    if args.phase == "rename":
        run_rename_phase(src_dir)
    elif args.phase == "scan":
        entries = load_entries_for_scan(src_dir)
        run_scan_phase(entries, only_ids, args.limit, args.workers, args.force, args.model)
    else:
        entries = run_rename_phase(src_dir)
        run_scan_phase(entries, only_ids, args.limit, args.workers, args.force, args.model)


if __name__ == "__main__":
    main()
