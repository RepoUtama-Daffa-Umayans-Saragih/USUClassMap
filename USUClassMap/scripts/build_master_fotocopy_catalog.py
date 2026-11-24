#!/usr/bin/env python3
"""Builder & validator master_fotocopy_catalog.json untuk USUClassMap."""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent
OUTPUT_PATH = PROJECT_ROOT / "database" / "seeders" / "data" / "master_fotocopy_catalog.json"

sys.path.insert(0, str(SCRIPT_DIR))
from catalog_data import ALL_MODULES  # noqa: E402

JASA_CATEGORY_MAP = {
    "Print Hitam Putih": "Print",
    "Print Warna": "Print",
    "Print Ukuran Besar": "Print",
    "Print Media Khusus": "Print",
    "Fotokopi": "Fotocopy",
    "Jilid": "Jilid",
    "Laminating & Finishing": "Finishing",
    "Scan & Digital": "Digital",
    "Cetak Foto": "Digital",
    "Kebutuhan Skripsi": "Digital",
}

ARCHETYPE_MAP = {
    "Print Hitam Putih": ["print_kilat"],
    "Print Warna": ["print_kilat"],
    "Print Ukuran Besar": ["print_kilat"],
    "Print Media Khusus": ["print_kilat"],
    "Fotokopi": ["print_kilat"],
    "Laminating & Finishing": ["print_kilat"],
    "Kertas HVS": ["print_kilat"],
    "Jilid": ["skripsi"],
    "Scan & Digital": ["skripsi"],
    "Kebutuhan Skripsi": ["skripsi"],
    "Cetak Foto": ["skripsi"],
    "Map & Pengarsipan": ["skripsi"],
    "Amplop": ["skripsi"],
    "Pulpen": ["atk_teknik"],
    "Pensil": ["atk_teknik"],
    "Spidol & Highlighter": ["atk_teknik"],
    "Kertas Gambar Teknik": ["atk_teknik"],
    "Pengukur & Alat Gambar": ["atk_teknik"],
    "Alat Potong": ["atk_teknik"],
    "Koreksi & Penghapus": ["atk_teknik"],
    "Buku & Loose Leaf": ["atk_teknik"],
    "Niche Kampus": ["serba_ada"],
    "Event & Panitia": ["serba_ada"],
    "Elektronik & Digital": ["serba_ada"],
    "Lakban & Isolasi": ["serba_ada"],
    "Lem & Perekat": ["serba_ada"],
    "Kertas Khusus": ["serba_ada"],
    "Sticky Notes & Marker": ["serba_ada"],
    "Stapler & Penjepit": ["serba_ada"],
    "Nota & Kwitansi": ["serba_ada"],
}

NICHE_SUBCATEGORIES = {"Niche Kampus", "Event & Panitia", "Elektronik & Digital"}
REQUIRED_META_KEYS = (
    "merk",
    "tipe",
    "ukuran_atau_gramasi",
    "warna_varian",
    "isi_kemasan",
    "penjualan",
    "sku_ref",
)


def normalize_item(raw: dict) -> dict:
    sku = str(raw["sku"]).strip()
    slug = str(raw["slug"]).strip()
    name = str(raw["name"]).strip()
    sub_category = str(raw["sub_category"]).strip()
    pack_qty = int(raw.get("pack_qty", 1))
    unit = str(raw["unit"]).strip()

    is_jasa = sku.startswith("JSA-")
    item_type = "jasa" if is_jasa else "barang"
    brand = None if is_jasa else (raw.get("brand") or "Umum")

    if is_jasa:
        category = JASA_CATEGORY_MAP[sub_category]
        segment = "jasa"
    else:
        category = "ATK"
        if sub_category in NICHE_SUBCATEGORIES:
            segment = "niche"
        elif pack_qty > 1:
            segment = "paket"
        else:
            segment = "eceran"

    price_options = [int(p) for p in raw["price_options"]]

    if is_jasa:
        stock_range = None
    elif raw.get("stock_range") is not None:
        stock_range = [int(raw["stock_range"][0]), int(raw["stock_range"][1])]
    elif max(price_options) >= 100000:
        stock_range = [1, 8]
    elif segment == "niche":
        stock_range = [5, 60]
    elif segment == "paket":
        stock_range = [3, 40]
    else:
        stock_range = [20, 250]

    archetypes = list(raw.get("archetypes") or ARCHETYPE_MAP[sub_category])

    default_isi = raw.get("meta_isi")
    if not default_isi:
        default_isi = f"Layanan 1 {unit}" if is_jasa else f"1 {unit}"

    metadata = {
        "merk": str(brand or "Jasa Toko"),
        "tipe": str(raw["meta_tipe"]).strip(),
        "ukuran_atau_gramasi": str(raw["meta_ukuran"]).strip(),
        "warna_varian": str(raw["meta_warna"]).strip(),
        "isi_kemasan": str(default_isi).strip(),
        "penjualan": str(raw["meta_penjualan"]).strip(),
        "sku_ref": sku,
    }

    return {
        "sku": sku,
        "slug": slug,
        "name": name,
        "item_type": item_type,
        "category": category,
        "sub_category": sub_category,
        "segment": segment,
        "brand": brand,
        "pack_qty": pack_qty,
        "unit": unit,
        "price_options": price_options,
        "description": str(raw["description"]).strip(),
        "metadata": metadata,
        "stock_range": stock_range,
        "core": bool(raw.get("core", False)),
        "pair_group": raw.get("pair_group"),
        "archetypes": archetypes,
        "image_path": f"fotocopy_catalog/{slug}.webp",
        "image_search_query": str(raw["image_search_query"]).strip(),
        "image_fallback_queries": [str(q).strip() for q in raw.get("image_fallback_queries", []) if str(q).strip()],
    }


def validate_catalog(items: list[dict]) -> list[str]:
    errors: list[str] = []
    seen_skus: set[str] = set()
    seen_slugs: set[str] = set()
    seen_names: set[str] = set()

    for idx, item in enumerate(items):
        sku = item["sku"]
        slug = item["slug"]
        name = item["name"]

        if sku in seen_skus:
            errors.append(f"[{idx}] Duplikat sku: {sku}")
        seen_skus.add(sku)

        if slug in seen_slugs:
            errors.append(f"[{sku}] Duplikat slug: {slug}")
        seen_slugs.add(slug)

        if name in seen_names:
            errors.append(f"[{sku}] Duplikat name: {name}")
        seen_names.add(name)

        if not re.fullmatch(r"[a-z0-9-]+", slug):
            errors.append(f"[{sku}] Slug tidak valid: {slug}")

        if '"' in name:
            errors.append(f"[{sku}] Name mengandung tanda kutip ganda (\"): {name}")

        if len(item["description"]) == 0 or len(item["description"]) > 240:
            errors.append(f"[{sku}] Panjang deskripsi tidak valid ({len(item['description'])})")

        if not item["price_options"] or any(p <= 0 for p in item["price_options"]):
            errors.append(f"[{sku}] price_options tidak valid: {item['price_options']}")

        meta = item["metadata"]
        for key in REQUIRED_META_KEYS:
            val = meta.get(key)
            if not isinstance(val, str) or not val.strip():
                errors.append(f"[{sku}] metadata['{key}'] kosong atau bukan string")

        if not item["image_search_query"]:
            errors.append(f"[{sku}] image_search_query kosong")

        if len(item["image_fallback_queries"]) < 2:
            errors.append(f"[{sku}] image_fallback_queries kurang dari 2")

        if not item["archetypes"]:
            errors.append(f"[{sku}] archetypes kosong")

    seg_counts = {"jasa": 0, "eceran": 0, "paket": 0, "niche": 0}
    for item in items:
        seg_counts[item["segment"]] += 1

    if len(items) < 190:
        errors.append(f"Total SKU ({len(items)}) kurang dari 190")
    if seg_counts["jasa"] < 50:
        errors.append(f"Jumlah jasa ({seg_counts['jasa']}) kurang dari 50")
    if seg_counts["eceran"] < 60:
        errors.append(f"Jumlah eceran ({seg_counts['eceran']}) kurang dari 60")
    if seg_counts["paket"] < 30:
        errors.append(f"Jumlah paket ({seg_counts['paket']}) kurang dari 30")
    if seg_counts["niche"] < 25:
        errors.append(f"Jumlah niche ({seg_counts['niche']}) kurang dari 25")

    return errors


def main() -> int:
    normalized: list[dict] = []
    for mod_items in ALL_MODULES:
        for raw in mod_items:
            normalized.append(normalize_item(raw))

    errors = validate_catalog(normalized)
    if errors:
        print("VALIDASI GAGAL:", file=sys.stderr)
        for err in errors:
            print(f" - {err}", file=sys.stderr)
        return 1

    OUTPUT_PATH.parent.mkdir(parents=True, exist_ok=True)
    with OUTPUT_PATH.open("w", encoding="utf-8") as fh:
        json.dump(normalized, fh, indent=2, ensure_ascii=False)
        fh.write("\n")

    seg_counts = {"jasa": 0, "eceran": 0, "paket": 0, "niche": 0}
    core_count = 0
    for item in normalized:
        seg_counts[item["segment"]] += 1
        if item["core"]:
            core_count += 1

    print(f"Master katalog berhasil dibuat: {OUTPUT_PATH}")
    print(f"Total SKU   : {len(normalized)}")
    print(f"  - Jasa    : {seg_counts['jasa']}")
    print(f"  - Eceran  : {seg_counts['eceran']}")
    print(f"  - Paket   : {seg_counts['paket']}")
    print(f"  - Niche   : {seg_counts['niche']}")
    print(f"  - Core    : {core_count}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
