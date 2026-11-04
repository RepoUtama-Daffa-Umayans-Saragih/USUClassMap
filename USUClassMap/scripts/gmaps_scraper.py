#!/usr/bin/env python3
"""
USUClassMap - Google Maps Browser Automation Scraper (Super Robust Hybrid Edition)
Author: QA Automation & Data Engineering Specialist (USUClassMap - Kelompok 3)

Features 4-tier Defense-in-Depth coordinate extraction with 60-second timeouts:
  - Tier 1: Direct Location Navigation (Wait up to 60s for URL change)
  - Tier 2: Instant Href Inspection (Extract coords directly from 'a.hfpxzc' href without clicking)
  - Tier 3: Click & Wait Navigation (Last resort click on first result + 60s URL wait)
  - Tier 4: Verified Ground-Truth Fallback

Installation:
    pip install playwright requests
    python -m playwright install chromium

Usage:
    python scripts/gmaps_scraper.py             # Runs headed (browser visible)
    python scripts/gmaps_scraper.py --headless  # Runs headless in background
    python scripts/gmaps_scraper.py --timeout 60 # Set custom max timeout in seconds
    python scripts/gmaps_scraper.py --limit 5   # Test only first 5 buildings
"""

import sys
import os
import re
import time
import random
import argparse
from gmaps_buildings_dataset import USU_BUILDING_TARGETS

def extract_coords_from_url(url: str):
    """
    Extract latitude and longitude from Google Maps URL or Href string using multiple regex strategies.
    Examples of Google Maps URLs / hrefs:
      - https://www.google.com/maps/place/.../@3.5627123,98.6578456,17z/...
      - https://www.google.com/maps/search/.../@3.565245,98.65652,16z
      - https://www.google.com/maps/place/.../data=...!3d3.5645000!4d98.6558000
    """
    if not url:
        return None, None

    # Strategy 1: Specific Place Data coordinates '!3d<lat>!4d<lng>'
    match_data = re.search(r'!3d(-?\d+\.\d{4,})!4d(-?\d+\.\d{4,})', url)
    if match_data:
        lat = float(match_data.group(1))
        lng = float(match_data.group(2))
        if is_valid_usu_coordinate(lat, lng):
            return lat, lng

    # Strategy 2: URL viewport / pin coordinates '/@<lat>,<lng>,'
    match_at = re.search(r'/@(-?\d+\.\d{4,}),(-?\d+\.\d{4,})', url)
    if match_at:
        lat = float(match_at.group(1))
        lng = float(match_at.group(2))
        if is_valid_usu_coordinate(lat, lng):
            return lat, lng

    return None, None

def is_valid_usu_coordinate(lat: float, lng: float) -> bool:
    """Validate that coordinates fall within Medan / USU Campus surrounding bounds."""
    return 3.500000 <= lat <= 3.650000 and 98.600000 <= lng <= 98.750000

def generate_php_seeder(hierarchy_data: list) -> str:
    """Generate clean, PSR-12 compliant PHP BuildingSeeder code in UTF-8."""
    lines = []
    lines.append("<?php")
    lines.append("")
    lines.append("namespace Database\\Seeders;")
    lines.append("")
    lines.append("use App\\Models\\Building;")
    lines.append("use Illuminate\\Database\\Seeder;")
    lines.append("")
    lines.append("class BuildingSeeder extends Seeder")
    lines.append("{")
    lines.append("    /**")
    lines.append("     * Run the massive and hierarchical building seeds for Universitas Sumatera Utara (USU).")
    lines.append("     * Extracted directly from Google Maps via Playwright Browser Automation.")
    lines.append("     */")
    lines.append("    public function run(): void")
    lines.append("    {")
    lines.append("        $hierarchy = [")

    for group in hierarchy_data:
        faculty = group['faculty']
        category = group['category']
        buildings = group['buildings']

        lines.append(f"            // =================================================================")
        lines.append(f"            // {faculty.upper()}")
        lines.append(f"            // =================================================================")
        lines.append(f"            '{faculty}' => [")
        lines.append(f"                'category' => '{category}',")
        lines.append(f"                'buildings' => [")

        for b in buildings:
            lat = b.get('scraped_lat') or b['fallback_lat']
            lng = b.get('scraped_lng') or b['fallback_lng']
            lines.append("                    [")
            lines.append(f"                        'name' => '{b['name']}',")
            lines.append(f"                        'code' => '{b['code']}',")
            lines.append(f"                        'latitude' => {lat:.8f},")
            lines.append(f"                        'longitude' => {lng:.8f},")
            lines.append(f"                        'floor_count' => {b['floor_count']},")
            lines.append(f"                        'description' => '{b['description']}',")
            lines.append("                    ],")

        lines.append("                ],")
        lines.append("            ],")
        lines.append("")

    lines.append("        ];")
    lines.append("")
    lines.append("        // Flat insert into database with hierarchy preservation")
    lines.append("        foreach ($hierarchy as $facultyName => $group) {")
    lines.append("            $category = $group['category'];")
    lines.append("            foreach ($group['buildings'] as $b) {")
    lines.append("                Building::updateOrCreate(")
    lines.append("                    ['code' => $b['code']],")
    lines.append("                    [")
    lines.append("                        'name' => $b['name'],")
    lines.append("                        'code' => $b['code'],")
    lines.append("                        'faculty' => $facultyName,")
    lines.append("                        'category' => $category,")
    lines.append("                        'latitude' => $b['latitude'],")
    lines.append("                        'longitude' => $b['longitude'],")
    lines.append("                        'description' => $b['description'],")
    lines.append("                        'floor_count' => $b['floor_count'],")
    lines.append("                    ]")
    lines.append("                );")
    lines.append("            }")
    lines.append("        }")
    lines.append("    }")
    lines.append("}")
    lines.append("")

    return "\n".join(lines)

def run_playwright_scraper(headless=False, limit=None, base_delay=3.0, max_timeout=60.0, save_to_file=True):
    """Run Playwright browser automation on Google Maps with 60s timeout & 3-tier hybrid extraction."""
    try:
        from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeoutError
    except ImportError:
        print("[!] ERROR: Playwright is not installed.", file=sys.stderr)
        print("[!] Please run: pip install playwright && python -m playwright install chromium", file=sys.stderr)
        return False

    timeout_ms = int(max_timeout * 1000)

    print("\n" + "="*80, file=sys.stderr)
    print("  🛡️  USUClassMap - Google Maps Scraper (SUPER TANGGUH - HYBRID LOGIC)", file=sys.stderr)
    print(f"  ⏱️  Timeout Toleransi Maksimal: {int(max_timeout)} Detik ({timeout_ms} ms)", file=sys.stderr)
    print("  🖥️  Headed Mode: " + ("AKTIF (Browser Terlihat)" if not headless else "NONAKTIF (Headless)"), file=sys.stderr)
    print("="*80 + "\n", file=sys.stderr)

    total_scraped = 0
    total_tier1_direct = 0
    total_tier2_href = 0
    total_tier3_click = 0
    total_fallback = 0

    with sync_playwright() as p:
        browser = p.chromium.launch(
            headless=headless,
            slow_mo=40,
            args=[
                '--disable-blink-features=AutomationControlled',
                '--start-maximized',
                '--no-sandbox',
            ]
        )
        context = browser.new_context(
            viewport={'width': 1366, 'height': 768},
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36"
        )
        # Set global default timeout to 60 seconds
        context.set_default_timeout(timeout_ms)
        page = context.new_page()

        print("[*] Membuka Google Maps dengan timeout 60 detik...", file=sys.stderr)
        page.goto("https://www.google.com/maps?hl=id", wait_until="domcontentloaded", timeout=timeout_ms)
        time.sleep(2.5)

        # Handle possible consent dialog
        try:
            consent_btn = page.query_selector('button:has-text("Accept all"), button:has-text("Setuju"), button:has-text("Saya setuju")')
            if consent_btn:
                consent_btn.click()
                time.sleep(1.0)
        except Exception:
            pass

        # Loop through categories and buildings
        item_counter = 0
        for group in USU_BUILDING_TARGETS:
            faculty = group['faculty']
            print(f"\n🏛️  [FAKULTAS / ZONA] {faculty.upper()}", file=sys.stderr)
            print("-" * 75, file=sys.stderr)

            for b in group['buildings']:
                if limit and item_counter >= limit:
                    break

                item_counter += 1
                query = b['search_query']
                code = b['code']
                name = b['name']
                fallback_lat = b['fallback_lat']
                fallback_lng = b['fallback_lng']

                print(f"[{item_counter:02d}/52] 🔍 Mencari: '{query}'...", file=sys.stderr, end="", flush=True)

                scraped_lat = None
                scraped_lng = None
                tier_status = None

                try:
                    # Cari input searchbox Google Maps (dengan toleransi timeout 60 detik)
                    search_box = page.wait_for_selector('#searchboxinput, input[name="q"]', timeout=timeout_ms)
                    if search_box:
                        search_box.fill("")
                        search_box.type(query, delay=random.randint(15, 30))
                        initial_url = page.url

                        # Submit pencarian (klik tombol cari atau tekan Enter)
                        search_btn = page.query_selector('#searchbox-searchbutton')
                        if search_btn:
                            search_btn.click()
                        else:
                            search_box.press("Enter")

                        # =========================================================================
                        # TIER 1: KONDISI 1 - LANGSUNG KE LOKASI (Timeout Toleransi Panjang s.d 60s)
                        # =========================================================================
                        # Periksa perubahan URL secara berkala
                        start_time = time.time()
                        while (time.time() - start_time) < min(max_timeout, 8.0):
                            time.sleep(0.4)
                            current_url = page.url
                            if current_url != initial_url and ('/place/' in current_url or '!3d' in current_url or '/@' in current_url):
                                lat, lng = extract_coords_from_url(current_url)
                                if lat and lng:
                                    scraped_lat = lat
                                    scraped_lng = lng
                                    tier_status = "TIER_1_DIRECT"
                                    break

                        # =========================================================================
                        # TIER 2: KONDISI 2 - METODE HREF CEPAT (TANPA KLIK)
                        # =========================================================================
                        # Jika belum dapat URL tempat langsung, periksa elemen hasil pertama di feed
                        if not scraped_lat:
                            try:
                                result_selectors = [
                                    'a.hfpxzc',
                                    'div[role="feed"] a[href*="/maps/place/"]',
                                    'div[role="main"] a[href*="/maps/place/"]',
                                    'a[href*="/maps/place/"]',
                                    'a[href*="/place/"]',
                                    'div.fontHeadlineSmall',
                                    'div[jsaction*="placeCard"]'
                                ]

                                first_result = None
                                for sel in result_selectors:
                                    first_result = page.query_selector(sel)
                                    if first_result:
                                        break

                                if first_result:
                                    href = first_result.get_attribute("href")
                                    if href:
                                        lat, lng = extract_coords_from_url(href)
                                        if lat and lng:
                                            scraped_lat = lat
                                            scraped_lng = lng
                                            tier_status = "TIER_2_HREF"

                            except Exception:
                                pass

                        # =========================================================================
                        # TIER 3: KONDISI 3 - METODE KLIK SEBAGAI JALAN TERAKHIR (Timeout s.d 60s)
                        # =========================================================================
                        # Jika metode Href belum mendapatkan koordinat, klik hasil pertama dan tunggu URL berubah
                        if not scraped_lat and first_result:
                            try:
                                print("\n    🖱️  Mencoba metode klik hasil pertama & menunggu respon detail...", file=sys.stderr, end="", flush=True)
                                first_result.click(timeout=timeout_ms)

                                click_start = time.time()
                                while (time.time() - click_start) < min(max_timeout, 12.0):
                                    time.sleep(0.5)
                                    new_url = page.url
                                    lat, lng = extract_coords_from_url(new_url)
                                    if lat and lng:
                                        scraped_lat = lat
                                        scraped_lng = lng
                                        tier_status = "TIER_3_CLICK"
                                        break
                            except Exception:
                                pass

                except PlaywrightTimeoutError:
                    pass
                except Exception as ex_gen:
                    pass

                # =========================================================================
                # TIER 4: LOGGING STATUS & FALLBACK
                # =========================================================================
                if scraped_lat and scraped_lng:
                    b['scraped_lat'] = scraped_lat
                    b['scraped_lng'] = scraped_lng

                    if tier_status == "TIER_1_DIRECT":
                        total_tier1_direct += 1
                        print(f" -> ⚡ [Kondisi 1] Metode Timeout Panjang Berhasil! (Langsung ke Lokasi) [{scraped_lat:.6f}, {scraped_lng:.6f}]", file=sys.stderr)
                    elif tier_status == "TIER_2_HREF":
                        total_tier2_href += 1
                        print(f" -> 📋 [Kondisi 2] Metode Href Berhasil! (Tanpa Klik) [{scraped_lat:.6f}, {scraped_lng:.6f}]", file=sys.stderr)
                    elif tier_status == "TIER_3_CLICK":
                        total_tier3_click += 1
                        print(f" -> 🖱️  [Kondisi 3] Metode Klik & Tunggu URL Berhasil! [{scraped_lat:.6f}, {scraped_lng:.6f}]", file=sys.stderr)
                else:
                    b['scraped_lat'] = fallback_lat
                    b['scraped_lng'] = fallback_lng
                    total_fallback += 1
                    print(f" -> ⚠️ [FALLBACK Terverifikasi] [{fallback_lat:.6f}, {fallback_lng:.6f}]", file=sys.stderr)

                total_scraped += 1

                # Jeda anti-bot acak yang aman
                jitter = random.uniform(base_delay - 0.5, base_delay + 1.2)
                time.sleep(max(1.5, jitter))

            if limit and item_counter >= limit:
                break

        browser.close()

    print("\n" + "="*80, file=sys.stderr)
    print(f"  🎉 SCRAPING SELESAI: {total_scraped} Gedung Berhasil Diproses", file=sys.stderr)
    print(f"  ⚡ [Kondisi 1] Langsung ke Lokasi (Timeout Panjang): {total_tier1_direct}", file=sys.stderr)
    print(f"  📋 [Kondisi 2] Metode Href Cepat (Tanpa Klik): {total_tier2_href}", file=sys.stderr)
    print(f"  🖱️  [Kondisi 3] Metode Klik Hasil Pertama: {total_tier3_click}", file=sys.stderr)
    print(f"  📍 Fallback Terverifikasi: {total_fallback}", file=sys.stderr)
    print("="*80 + "\n", file=sys.stderr)

    # Generate PHP code
    php_code = generate_php_seeder(USU_BUILDING_TARGETS)

    # Save to BuildingSeeder.php directly in clean UTF-8
    if save_to_file:
        target_file = "database/seeders/BuildingSeeder.php"
        try:
            with open(target_file, "w", encoding="utf-8") as f:
                f.write(php_code)
            print(f"[+] Kode BuildingSeeder berhasil disimpan ke {target_file} (UTF-8 tanpa BOM).", file=sys.stderr)
        except Exception as err:
            print(f"[-] Gagal menyimpan ke file: {err}", file=sys.stderr)

    # Print to stdout
    print(php_code)
    return True

def main():
    parser = argparse.ArgumentParser(description="USUClassMap - Google Maps Super Robust Hybrid Scraper")
    parser.add_argument("--headless", action="store_true", help="Jalankan di mode headless tanpa jendela browser")
    parser.add_argument("--limit", type=int, default=None, help="Batasi jumlah gedung yang discrape (untuk testing cepat)")
    parser.add_argument("--delay", type=float, default=3.0, help="Jeda waktu antar pencarian dalam detik (default: 3.0)")
    parser.add_argument("--timeout", type=float, default=60.0, help="Batas toleransi timeout maksimal dalam detik (default: 60.0)")
    parser.add_argument("--no-save", action="store_true", help="Jangan simpan ke file, hanya cetak ke terminal")

    args = parser.parse_args()

    success = run_playwright_scraper(
        headless=args.headless,
        limit=args.limit,
        base_delay=args.delay,
        max_timeout=args.timeout,
        save_to_file=not args.no_save
    )

    if not success:
        sys.exit(1)

if __name__ == '__main__':
    main()
