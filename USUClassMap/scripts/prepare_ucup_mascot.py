"""Process Ucup mascot image into transparent full-body stand and square avatar WebP assets.

Uses border-seeded flood-fill + bottom drop-shadow cleanup + leg-gap cleanup +
1-2px edge feathering strictly around background boundaries so all white areas
inside the character (eyes, teeth, USU emblem, compass, sneakers) stay 100% solid.
"""

from __future__ import annotations

from collections import deque
from pathlib import Path
import shutil
import numpy as np
from PIL import Image


def bfs_flood_fill(seeds: list[tuple[int, int]], mask: np.ndarray, eligible: np.ndarray) -> np.ndarray:
    """4-connected BFS flood fill starting from seed coordinates within eligible pixels."""
    h, w = mask.shape
    out = mask.copy()
    q: deque[tuple[int, int]] = deque(seeds)
    for y, x in seeds:
        out[y, x] = True

    while q:
        cy, cx = q.popleft()
        if cy > 0 and not out[cy - 1, cx] and eligible[cy - 1, cx]:
            out[cy - 1, cx] = True
            q.append((cy - 1, cx))
        if cy + 1 < h and not out[cy + 1, cx] and eligible[cy + 1, cx]:
            out[cy + 1, cx] = True
            q.append((cy + 1, cx))
        if cx > 0 and not out[cy, cx - 1] and eligible[cy, cx - 1]:
            out[cy, cx - 1] = True
            q.append((cy, cx - 1))
        if cx + 1 < w and not out[cy, cx + 1] and eligible[cy, cx + 1]:
            out[cy, cx + 1] = True
            q.append((cy, cx + 1))

    return out


def dilate_4(mask: np.ndarray) -> np.ndarray:
    """One-pixel 4-connected dilation without SciPy dependency."""
    h, w = mask.shape
    out = mask.copy()
    out[1:, :] |= mask[:-1, :]
    out[:-1, :] |= mask[1:, :]
    out[:, 1:] |= mask[:, :-1]
    out[:, :-1] |= mask[:, 1:]
    return out


def build_transparent_rgba(rgb_arr: np.ndarray) -> np.ndarray:
    """Remove outer white backdrop & ground shadow cleanly while preserving interior whites."""
    h, w, _ = rgb_arr.shape
    r = rgb_arr[:, :, 0].astype(np.float32)
    g = rgb_arr[:, :, 1].astype(np.float32)
    b = rgb_arr[:, :, 2].astype(np.float32)

    min_c = np.minimum(np.minimum(r, g), b)
    max_c = np.maximum(np.maximum(r, g), b)
    sat = max_c - min_c
    lum = 0.299 * r + 0.587 * g + 0.114 * b

    # Pass 1: White / near-white neutral backdrop connected to outer image borders
    white_eligible = ((min_c > 228) & (sat < 22)) | ((r > 235) & (g > 235) & (b > 235))
    border_seeds: list[tuple[int, int]] = []
    for x in range(w):
        if white_eligible[0, x]:
            border_seeds.append((0, x))
        if white_eligible[h - 1, x]:
            border_seeds.append((h - 1, x))
    for y in range(1, h - 1):
        if white_eligible[y, 0]:
            border_seeds.append((y, 0))
        if white_eligible[y, w - 1]:
            border_seeds.append((y, w - 1))

    bg_mask = bfs_flood_fill(border_seeds, np.zeros((h, w), dtype=bool), white_eligible)

    # Pass 2: Un-outlined neutral floor oval drop-shadow near bottom (y >= 90% height)
    shadow_eligible = np.zeros((h, w), dtype=bool)
    shadow_zone = (lum >= 125) & (sat <= 26)
    y_start = int(h * 0.90)
    shadow_eligible[y_start:, :] = shadow_zone[y_start:, :]

    # Seed from bottom-zone pixels 4-adjacent to existing Pass-1 bg_mask
    bg_adj_bottom = dilate_4(bg_mask) & (~bg_mask) & shadow_eligible
    ys_s, xs_s = np.where(bg_adj_bottom)
    bg_mask = bfs_flood_fill(list(zip(ys_s.tolist(), xs_s.tolist())), bg_mask, shadow_eligible)

    # Also clean tiny 1-2px fringe directly adjacent to bottom shadow oval (excluding dark ink outline lum < 95)
    fringe_shadow = dilate_4(bg_mask) & (~bg_mask) & shadow_eligible & (lum >= 115)
    bg_mask |= fringe_shadow

    # Pass 3: Re-seed white flood-fill from updated bg_mask so the inner leg-gap becomes transparent too
    white_adj = dilate_4(bg_mask) & (~bg_mask) & white_eligible
    ys_w, xs_w = np.where(white_adj)
    bg_mask = bfs_flood_fill(list(zip(ys_w.tolist(), xs_w.tolist())), bg_mask, white_eligible)

    # Pass 4: Anti-aliased 1-2px edge feathering strictly along foreground boundary
    alpha = np.where(bg_mask, 0.0, 255.0).astype(np.float32)
    ring1 = dilate_4(bg_mask) & (~bg_mask)
    ring2 = dilate_4(bg_mask | ring1) & (~bg_mask) & (~ring1)
    edge_band = ring1 | ring2

    neutral_halo = edge_band & (min_c > 195) & (sat < 25)
    soft_alpha = np.clip((248.0 - lum) / 58.0 * 255.0, 0.0, 255.0)
    alpha = np.where(neutral_halo, np.minimum(alpha, soft_alpha), alpha)

    # Decontaminate semi-transparent white fringe against #FFFFFF background
    out_rgb = rgb_arr.astype(np.float32).copy()
    decontam = (alpha > 1.0) & (alpha < 250.0)
    a_frac = np.where(decontam, alpha / 255.0, 1.0)
    for c in range(3):
        ch = out_rgb[:, :, c]
        recovered = np.clip((ch - (1.0 - a_frac) * 255.0) / np.maximum(a_frac, 0.08), 0.0, 255.0)
        out_rgb[:, :, c] = np.where(decontam, recovered, ch)

    rgba = np.dstack([
        np.clip(out_rgb, 0, 255).astype(np.uint8),
        np.clip(alpha, 0, 255).astype(np.uint8),
    ])
    return rgba


def save_stand_webp(cropped_rgba: Image.Image, dest: Path) -> tuple[tuple[int, int], int]:
    """Scale proportionally to ~420px tall and export lightweight WebP (< 40 KB)."""
    w, h = cropped_rgba.size
    target_h = 420
    target_w = max(1, int(round(w * (target_h / h))))
    resized = cropped_rgba.resize((target_w, target_h), Image.Resampling.LANCZOS)

    for quality in (84, 80, 76, 72, 68):
        resized.save(dest, format="WEBP", quality=quality, method=6)
        size_bytes = dest.stat().st_size
        if size_bytes <= 40960:
            return resized.size, size_bytes

    return resized.size, dest.stat().st_size


def save_avatar_webp(cropped_rgba: Image.Image, dest: Path) -> tuple[tuple[int, int], int]:
    """Extract clean 1:1 square crop focused on Ucup head/hat/sparkles/jacket emblem at 160x160."""
    arr = np.array(cropped_rgba)
    cw, ch = cropped_rgba.size
    upper_limit = int(ch * 0.57)
    upper_alpha = arr[:upper_limit, :, 3] > 20
    _, xs_up = np.where(upper_alpha)
    if len(xs_up) > 0:
        min_x = int(xs_up.min())
        max_x = int(xs_up.max()) + 1
    else:
        min_x, max_x = 0, cw

    side = min(ch, max(upper_limit, max_x - min_x))
    cx = (min_x + max_x) // 2
    left = max(0, min(cw - side, cx - side // 2))
    right = min(cw, left + side)
    top = 0
    bottom = min(ch, side)

    # Keep exact 1:1 square canvas centered on upper portrait even if cw < side
    square = Image.new("RGBA", (side, side), (0, 0, 0, 0))
    patch = cropped_rgba.crop((left, top, right, bottom))
    paste_x = max(0, (side - patch.width) // 2)
    square.paste(patch, (paste_x, 0))

    avatar = square.resize((160, 160), Image.Resampling.LANCZOS)
    avatar.save(dest, format="WEBP", quality=88, method=6)
    return avatar.size, dest.stat().st_size


def main() -> None:
    root = Path(__file__).resolve().parent.parent
    mascot_dir = root / "public" / "images" / "mascot"
    mascot_dir.mkdir(parents=True, exist_ok=True)

    raw_dest = mascot_dir / "ucup-raw.jpg"
    wa_source = root / "WhatsApp Image 2026-11-25 at 08.26.35.jpeg"
    if not raw_dest.exists():
        if not wa_source.exists():
            raise FileNotFoundError(f"Source mascot image missing: {wa_source}")
        shutil.copy2(wa_source, raw_dest)

    im = Image.open(raw_dest).convert("RGB")
    rgb_arr = np.array(im)
    rgba_arr = build_transparent_rgba(rgb_arr)

    ys, xs = np.where(rgba_arr[:, :, 3] > 8)
    pad = 4
    y0 = max(0, int(ys.min()) - pad)
    y1 = min(rgba_arr.shape[0], int(ys.max()) + 1 + pad)
    x0 = max(0, int(xs.min()) - pad)
    x1 = min(rgba_arr.shape[1], int(xs.max()) + 1 + pad)

    cropped_im = Image.fromarray(rgba_arr[y0:y1, x0:x1], mode="RGBA")
    stand_path = mascot_dir / "ucup-stand.webp"
    avatar_path = mascot_dir / "ucup-avatar.webp"

    stand_size, stand_bytes = save_stand_webp(cropped_im, stand_path)
    avatar_size, avatar_bytes = save_avatar_webp(cropped_im, avatar_path)

    print(f"RAW_COPIED={raw_dest.exists()} SIZE={im.size}")
    print(f"AUTOCROP_BBOX=({x0},{y0})-({x1},{y1}) CROPPED_SIZE={cropped_im.size}")
    print(f"STAND_WEBP={stand_path} SIZE={stand_size} BYTES={stand_bytes} ({stand_bytes/1024:.2f} KB)")
    print(f"AVATAR_WEBP={avatar_path} SIZE={avatar_size} BYTES={avatar_bytes} ({avatar_bytes/1024:.2f} KB)")


if __name__ == "__main__":
    main()
