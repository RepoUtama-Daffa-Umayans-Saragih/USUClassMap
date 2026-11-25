#!/usr/bin/env python3
"""Equirectangular/Cylindrical 360° -> Rectilinear Perspective Door Shot Generator.

Projects natural 3:4 or 4:3 pinhole perspective views (straight vertical edges, zero
equirectangular barrel/curve distortion) from 360° panoramas using gnomonic inverse
mapping (NumPy + OpenCV cv2.remap) and ranks 12 yaw angles (0°..330°) to select
authentic classroom door/entrance/corridor viewpoints.
"""

from __future__ import annotations

import argparse
import math
import os
import pathlib
from typing import Iterable

import cv2
import numpy as np
from PIL import Image, ImageOps


def compute_source_projection(width: int, height: int) -> tuple[float, float]:
    """Return (haov_deg, vaov_deg) matching Photo360pScanRepository::projectionFor."""
    if width <= 0 or height <= 0:
        return (360.0, 60.0)
    ratio = width / float(height)
    if ratio >= 2.2:
        return (360.0, round(360.0 * height / float(width), 2))
    if ratio >= 1.8:
        return (360.0, 180.0)
    return (90.0, round(90.0 * height / float(width), 2))


def effective_fov_and_pitch(
    vaov_deg: float,
    requested_hfov_deg: float = 70.0,
    requested_pitch_deg: float = -1.5,
    out_w: int = 960,
    out_h: int = 720,
) -> tuple[float, float]:
    """Clamp horizontal FOV & pitch so cylindrical panoramas never hit clamped top/bottom bands."""
    if vaov_deg >= 150.0:
        hfov = max(62.0, min(75.0, float(requested_hfov_deg)))
        pitch = max(-6.0, min(6.0, float(requested_pitch_deg)))
        return (hfov, pitch)

    aspect = out_w / float(max(1, out_h))
    usable_half_vaov_rad = math.radians(max(12.0, vaov_deg * 0.90) / 2.0)
    max_half_hfov_rad = math.atan(math.tan(usable_half_vaov_rad) * aspect)
    max_hfov_deg = math.degrees(2.0 * max_half_hfov_rad)

    hfov = max(52.0, min(74.0, min(float(requested_hfov_deg), max_hfov_deg)))
    # Keep pitch near 0 on thin cylindrical strips so both lintel and lower frame stay inside the strip
    pitch = max(-1.5, min(1.5, float(requested_pitch_deg) * 0.4))
    return (round(hfov, 2), round(pitch, 2))


def project_rectilinear(
    img_rgb: np.ndarray,
    yaw_deg: float = 0.0,
    pitch_deg: float = -1.5,
    hfov_deg: float = 70.0,
    out_w: int = 960,
    out_h: int = 720,
    vaov_deg: float | None = None,
    haov_deg: float | None = None,
) -> np.ndarray:
    """Inverse gnomonic projection onto a straight rectilinear pinhole camera plane."""
    src_h, src_w = img_rgb.shape[:2]
    auto_haov, auto_vaov = compute_source_projection(src_w, src_h)
    haov = float(haov_deg if haov_deg is not None else auto_haov)
    vaov = float(vaov_deg if vaov_deg is not None else auto_vaov)

    eff_hfov, eff_pitch = effective_fov_and_pitch(
        vaov_deg=vaov,
        requested_hfov_deg=hfov_deg,
        requested_pitch_deg=pitch_deg,
        out_w=out_w,
        out_h=out_h,
    )

    f = (out_w * 0.5) / math.tan(math.radians(eff_hfov * 0.5))

    u = (np.arange(out_w, dtype=np.float32) + 0.5) - (out_w * 0.5)
    v = (out_h * 0.5) - (np.arange(out_h, dtype=np.float32) + 0.5)
    grid_u, grid_v = np.meshgrid(u, v)

    # Camera forward along +Z, right +X, up +Y
    ray_x = grid_u
    ray_y = grid_v
    ray_z = np.full_like(grid_u, f, dtype=np.float32)

    norm = np.sqrt(ray_x * ray_x + ray_y * ray_y + ray_z * ray_z)
    rx = ray_x / norm
    ry = ray_y / norm
    rz = ray_z / norm

    # Pitch rotation around +X axis (positive pitch tilts camera upward)
    pr = math.radians(eff_pitch)
    cos_p, sin_p = math.cos(pr), math.sin(pr)
    ry_p = ry * cos_p + rz * sin_p
    rz_p = -ry * sin_p + rz * cos_p
    rx_p = rx

    # Yaw rotation around +Y axis (positive yaw looks toward right of panorama center)
    yr = math.radians(yaw_deg)
    cos_y, sin_y = math.cos(yr), math.sin(yr)
    wx = rx_p * cos_y + rz_p * sin_y
    wy = ry_p
    wz = -rx_p * sin_y + rz_p * cos_y

    lon = np.arctan2(wx, wz)
    lat = np.arcsin(np.clip(wy, -1.0, 1.0))

    haov_rad = math.radians(haov)
    vaov_rad = math.radians(vaov)

    map_x = (((lon / haov_rad) + 0.5) * src_w) - 0.5
    map_y = ((0.5 - (lat / vaov_rad)) * src_h) - 0.5

    # Wrap horizontally only for full 360° panoramas; clamp vertically within valid scanlines
    border_mode = cv2.BORDER_WRAP if haov >= 359.0 else cv2.BORDER_REPLICATE
    map_x = map_x.astype(np.float32)
    map_y = np.clip(map_y, 0.0, max(0.0, float(src_h - 1))).astype(np.float32)

    projected = cv2.remap(
        img_rgb,
        map_x,
        map_y,
        interpolation=cv2.INTER_CUBIC,
        borderMode=border_mode,
    )
    return projected


def score_door_candidate(rgb_small: np.ndarray) -> float:
    """Heuristic score preferring doorway/corridor vertical jambs & interior framing over sky/trees/blank walls."""
    h, w = rgb_small.shape[:2]
    gray = cv2.cvtColor(rgb_small, cv2.COLOR_RGB2GRAY)
    hsv = cv2.cvtColor(rgb_small, cv2.COLOR_RGB2HSV)

    # Vertical vs horizontal edges in mid-frame architecture region
    roi = gray[int(0.10 * h):int(0.90 * h), :]
    gx = np.abs(cv2.Sobel(roi, cv2.CV_32F, 1, 0, ksize=3))
    gy = np.abs(cv2.Sobel(roi, cv2.CV_32F, 0, 1, ksize=3))

    col_gx = gx.mean(axis=0)
    left_jamb = float(np.percentile(col_gx[int(0.12 * w):int(0.42 * w)], 85))
    right_jamb = float(np.percentile(col_gx[int(0.58 * w):int(0.88 * w)], 85))
    jamb_symmetry = min(left_jamb, right_jamb) * 0.7 + 0.3 * (left_jamb + right_jamb) * 0.5

    vert_mean = float(gx.mean())
    horiz_mean = float(gy.mean()) + 1e-3
    vert_ratio = min(2.6, vert_mean / horiz_mean)

    # Central door panel / room depth texture (avoid plain featureless painted wall)
    center_gray = gray[int(0.18 * h):int(0.82 * h), int(0.28 * w):int(0.72 * w)]
    center_std = float(center_gray.std())
    center_texture_score = min(48.0, center_std) - (25.0 if center_std < 14.0 else 0.0)

    # Penalize sky dominance in top half
    top_hsv = hsv[:int(0.50 * h), :, :]
    sky_mask = (
        ((top_hsv[:, :, 2] > 225) & (top_hsv[:, :, 1] < 38))
        | ((top_hsv[:, :, 0] >= 88) & (top_hsv[:, :, 0] <= 132) & (top_hsv[:, :, 1] > 42) & (top_hsv[:, :, 2] > 125))
    )
    sky_ratio = float(sky_mask.mean())

    # Penalize outdoor green tree/lawn dominance
    green_mask = (hsv[:, :, 0] >= 32) & (hsv[:, :, 0] <= 88) & (hsv[:, :, 1] > 50) & (hsv[:, :, 2] > 40)
    green_ratio = float(green_mask.mean())

    # Penalize close photographer/person blocking lower center
    bot_center = hsv[int(0.58 * h):int(0.96 * h), int(0.32 * w):int(0.68 * w), :]
    skin_or_dark_mask = (
        ((bot_center[:, :, 0] <= 22) & (bot_center[:, :, 1] >= 40) & (bot_center[:, :, 1] <= 175) & (bot_center[:, :, 2] >= 75))
        | (bot_center[:, :, 2] < 24)
    )
    person_ratio = float(skin_or_dark_mask.mean())

    # Penalize extreme exposure or stitch artifact edges
    mean_v = float(hsv[:, :, 2].mean())
    exposure_bonus = 12.0 if 75.0 <= mean_v <= 198.0 else -18.0
    dark_edge_ratio = float((gray[:max(1, int(0.08 * h)), :] < 10).mean() + (gray[int(0.92 * h):, :] < 10).mean())

    score = (
        jamb_symmetry * 1.45
        + vert_ratio * 18.0
        + center_texture_score * 0.9
        + exposure_bonus
        - sky_ratio * 65.0
        - green_ratio * 42.0
        - person_ratio * 45.0
        - dark_edge_ratio * 30.0
    )
    return float(score)


def select_best_yaws(
    img_rgb: np.ndarray,
    top_k: int = 3,
    min_separation_deg: float = 55.0,
    hfov_deg: float = 70.0,
    pitch_deg: float = -1.5,
    candidate_yaws: Iterable[float] | None = None,
) -> list[dict[str, float]]:
    """Evaluate 12 yaw candidates (0°, 30°, ..., 330°) and pick top_k well-separated viewpoints."""
    yaws = list(candidate_yaws) if candidate_yaws is not None else [float(y) for y in range(0, 360, 30)]
    src_h, src_w = img_rgb.shape[:2]
    haov, vaov = compute_source_projection(src_w, src_h)

    scored: list[tuple[float, float]] = []
    for y in yaws:
        thumb = project_rectilinear(
            img_rgb,
            yaw_deg=y,
            pitch_deg=pitch_deg,
            hfov_deg=hfov_deg,
            out_w=320,
            out_h=240,
            vaov_deg=vaov,
            haov_deg=haov,
        )
        scored.append((score_door_candidate(thumb), y))

    scored.sort(key=lambda item: item[0], reverse=True)

    picked: list[dict[str, float]] = []
    for s, y in scored:
        def ang_diff(a: float, b: float) -> float:
            d = abs((a - b + 180.0) % 360.0 - 180.0)
            return d

        if all(ang_diff(y, prev["yaw"]) >= min_separation_deg for prev in picked):
            picked.append({"yaw": round(y, 1), "score": round(s, 2)})
            if len(picked) >= top_k:
                break

    while len(picked) < top_k and scored:
        for s, y in scored:
            if all(abs(y - prev["yaw"]) > 1.0 for prev in picked):
                picked.append({"yaw": round(y, 1), "score": round(s, 2)})
                if len(picked) >= top_k:
                    break
        break

    return picked


def subtle_sharpen(rgb: np.ndarray) -> np.ndarray:
    """Gentle unsharp mask for crisp architectural details."""
    blurred = cv2.GaussianBlur(rgb, (0, 0), sigmaX=1.0)
    sharpened = cv2.addWeighted(rgb, 1.18, blurred, -0.18, 0)
    return sharpened


def load_rgb(path: str | os.PathLike) -> np.ndarray:
    with Image.open(path) as im:
        im_t = ImageOps.exif_transpose(im).convert("RGB")
        return np.array(im_t, dtype=np.uint8)


def save_webp(rgb: np.ndarray, out_path: str | os.PathLike, quality: int = 86) -> None:
    pathlib.Path(out_path).parent.mkdir(parents=True, exist_ok=True)
    Image.fromarray(rgb).save(str(out_path), format="WEBP", quality=quality, method=6)


def generate_door_shot_from_file(
    src_path: str | os.PathLike,
    out_path: str | os.PathLike,
    yaw_deg: float | None = None,
    pitch_deg: float = -1.5,
    hfov_deg: float = 70.0,
    out_w: int = 960,
    out_h: int = 720,
    quality: int = 86,
) -> dict[str, object]:
    """Generate a single crisp rectilinear door photo .webp from a 360° panorama file."""
    img_rgb = load_rgb(src_path)
    src_h, src_w = img_rgb.shape[:2]
    haov, vaov = compute_source_projection(src_w, src_h)

    if yaw_deg is None:
        best = select_best_yaws(img_rgb, top_k=1, hfov_deg=hfov_deg, pitch_deg=pitch_deg)
        chosen_yaw = best[0]["yaw"] if best else 0.0
    else:
        chosen_yaw = float(yaw_deg)

    eff_hfov, eff_pitch = effective_fov_and_pitch(
        vaov_deg=vaov,
        requested_hfov_deg=hfov_deg,
        requested_pitch_deg=pitch_deg,
        out_w=out_w,
        out_h=out_h,
    )

    projected = project_rectilinear(
        img_rgb,
        yaw_deg=chosen_yaw,
        pitch_deg=eff_pitch,
        hfov_deg=eff_hfov,
        out_w=out_w,
        out_h=out_h,
        vaov_deg=vaov,
        haov_deg=haov,
    )
    projected = subtle_sharpen(projected)
    save_webp(projected, out_path, quality=quality)

    return {
        "source": str(src_path),
        "output": str(out_path),
        "yaw": chosen_yaw,
        "pitch": eff_pitch,
        "hfov": eff_hfov,
        "vaov": vaov,
        "width": out_w,
        "height": out_h,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Generate rectilinear perspective door shot from 360° image.")
    parser.add_argument("--input", required=True, help="Input 360° image path")
    parser.add_argument("--output", required=True, help="Output .webp path")
    parser.add_argument("--yaw", type=float, default=None, help="Explicit yaw angle (deg); auto-selected if omitted")
    parser.add_argument("--pitch", type=float, default=-1.5, help="Camera pitch angle (deg)")
    parser.add_argument("--hfov", type=float, default=70.0, help="Horizontal FOV (deg)")
    parser.add_argument("--width", type=int, default=960, help="Output width (px)")
    parser.add_argument("--height", type=int, default=720, help="Output height (px)")
    args = parser.parse_args()

    res = generate_door_shot_from_file(
        src_path=args.input,
        out_path=args.output,
        yaw_deg=args.yaw,
        pitch_deg=args.pitch,
        hfov_deg=args.hfov,
        out_w=args.width,
        out_h=args.height,
    )
    print("GENERATED:", res)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
