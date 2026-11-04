#!/usr/bin/env python3
"""
USUClassMap - OpenStreetMap (OSM) Overpass API Building Extractor & PHP Seeder Generator
Author: Data Engineering & GIS Team (USUClassMap - Kelompok 3 Web Semantik)

This script queries OSM Overpass API for Universitas Sumatera Utara (USU) Padang Bulan campus,
extracts real coordinates for buildings/facilities, categorizes them into faculties and central facilities,
and outputs a ready-to-use PHP Multidimensional Array for database/seeders/BuildingSeeder.php.
"""

import sys
import json
import re
import math
import requests

# Bounding box for USU Campus Padang Bulan, Medan
# (South, West, North, East)
BBOX = (3.550000, 98.645000, 3.575000, 98.665000)

OVERPASS_URLS = [
    "https://overpass-api.de/api/interpreter",
    "https://lz4.overpass-api.de/api/interpreter",
    "https://z.overpass-api.de/api/interpreter"
]

# Classification Categories & Mapping Rules
CATEGORY_RULES = [
    {
        'faculty': 'Fakultas Ilmu Komputer dan Teknologi Informasi',
        'category': 'Fakultas',
        'code_prefix': 'FASILKOM',
        'patterns': [r'fasilkom', r'ilmu komputer', r'teknologi informasi', r'gedung fasilkom']
    },
    {
        'faculty': 'Fakultas Kedokteran',
        'category': 'Fakultas',
        'code_prefix': 'FK',
        'patterns': [r'fakultas kedokteran\b', r'\bfk usu\b', r'anatomi', r'histologi', r'pendidikan dokter', r'skills lab']
    },
    {
        'faculty': 'Fakultas Kedokteran Gigi',
        'category': 'Fakultas',
        'code_prefix': 'FKG',
        'patterns': [r'kedokteran gigi', r'\bfkg\b', r'rsgm', r'rumah sakit gigi']
    },
    {
        'faculty': 'Fakultas Hukum',
        'category': 'Fakultas',
        'code_prefix': 'FH',
        'patterns': [r'fakultas hukum', r'\bfh usu\b', r'peradilan semu', r'mochtar kusumaatmadja']
    },
    {
        'faculty': 'Fakultas Pertanian',
        'category': 'Fakultas',
        'code_prefix': 'FP',
        'patterns': [r'fakultas pertanian', r'\bfp usu\b', r'agroteknologi', r'agribisnis', r'ilmu tanah', r'soemarsono', r'peternakan']
    },
    {
        'faculty': 'Fakultas Teknik',
        'category': 'Fakultas',
        'code_prefix': 'FT',
        'patterns': [r'fakultas teknik', r'\bft usu\b', r'teknik sipil', r'teknik mesin', r'teknik elektro', r'teknik industri', r'teknik kimia', r'arsitektur', r'teknik lingkungan']
    },
    {
        'faculty': 'Fakultas Ekonomi dan Bisnis',
        'category': 'Fakultas',
        'code_prefix': 'FEB',
        'patterns': [r'ekonomi dan bisnis', r'\bfeb\b', r'manajemen', r'akuntansi', r'ekonomi pembangunan', r'bursa efek']
    },
    {
        'faculty': 'Fakultas Ilmu Budaya',
        'category': 'Fakultas',
        'code_prefix': 'FIB',
        'patterns': [r'ilmu budaya', r'\bfib\b', r'sastra', r'etnomusikologi', r'ilmu sejarah']
    },
    {
        'faculty': 'Fakultas MIPA',
        'category': 'Fakultas',
        'code_prefix': 'FMIPA',
        'patterns': [r'mipa', r'\bfmipa\b', r'matematika', r'fisika', r'kimia\b', r'biologi', r'herbarium', r'statistika']
    },
    {
        'faculty': 'Fakultas ISIP',
        'category': 'Fakultas',
        'code_prefix': 'FISIP',
        'patterns': [r'sosial dan ilmu politik', r'\bfisip\b', r'ilmu komunikasi', r'administrasi publik', r'sosiologi', r'hubungan internasional']
    },
    {
        'faculty': 'Fakultas Kesehatan Masyarakat',
        'category': 'Fakultas',
        'code_prefix': 'FKM',
        'patterns': [r'kesehatan masyarakat', r'\bfkm\b', r'epidemiologi', r'gizi']
    },
    {
        'faculty': 'Fakultas Psikologi',
        'category': 'Fakultas',
        'code_prefix': 'FPSI',
        'patterns': [r'psikologi', r'\bfpsi\b', r'psikodiagnostika', r'konseling']
    },
    {
        'faculty': 'Fakultas Kehutanan',
        'category': 'Fakultas',
        'code_prefix': 'FAHUTAN',
        'patterns': [r'kehutanan', r'\bfahutan\b', r'silvikultur', r'dendrologi']
    },
    {
        'faculty': 'Fakultas Farmasi',
        'category': 'Fakultas',
        'code_prefix': 'FF',
        'patterns': [r'farmasi', r'farmakologi', r'apoteker']
    },
    {
        'faculty': 'Fakultas Keperawatan',
        'category': 'Fakultas',
        'code_prefix': 'FKEP',
        'patterns': [r'keperawatan', r'\bfkep\b', r'nurse', r'nursing']
    },
    {
        'faculty': 'Fakultas Vokasi',
        'category': 'Fakultas',
        'code_prefix': 'VOKASI',
        'patterns': [r'vokasi', r'diploma', r'\bd3\b', r'\bd4\b', r'sarjana terapan']
    },
    {
        'faculty': 'Layanan Kesehatan Universitas',
        'category': 'Layanan Kesehatan',
        'code_prefix': 'RS',
        'patterns': [r'rumah sakit universitas sumatera utara', r'\brs usu\b', r'poliklinik', r'klinik']
    },
    {
        'faculty': 'Sarana Olahraga & Gelanggang',
        'category': 'Sarana Olahraga',
        'code_prefix': 'SPORT',
        'patterns': [r'stadion mini', r'stadion usu', r'gelanggang mahasiswa', r'gelanma', r'lapangan']
    },
    {
        'faculty': 'Fasilitas Pusat Universitas',
        'category': 'Fasilitas Universitas',
        'code_prefix': 'PUSAT',
        'patterns': [
            r'biro rektor', r'rektorat', r'perpustakaan', r'auditorium', r'pusat bahasa',
            r'gedung kuliah bersama', r'\bgk1\b', r'\bgk2\b', r'\bgkb\b', r'mkwk', r'mkwu',
            r'masjid dakwah', r'asrama', r'inkubator', r'cie usu', r'student center'
        ]
    }
]

def fetch_osm_data():
    """Fetch building and amenity elements from Overpass API within USU BBox."""
    query = f"""
    [out:json][timeout:35];
    (
      node["building"]({BBOX[0]},{BBOX[1]},{BBOX[2]},{BBOX[3]});
      way["building"]({BBOX[0]},{BBOX[1]},{BBOX[2]},{BBOX[3]});
      relation["building"]({BBOX[0]},{BBOX[1]},{BBOX[2]},{BBOX[3]});
      node["amenity"]({BBOX[0]},{BBOX[1]},{BBOX[2]},{BBOX[3]});
      way["amenity"]({BBOX[0]},{BBOX[1]},{BBOX[2]},{BBOX[3]});
      node["name"]({BBOX[0]},{BBOX[1]},{BBOX[2]},{BBOX[3]});
      way["name"]({BBOX[0]},{BBOX[1]},{BBOX[2]},{BBOX[3]});
    );
    out center tags;
    """
    
    for url in OVERPASS_URLS:
        try:
            print(f"[*] Querying Overpass API ({url})...", file=sys.stderr)
            resp = requests.post(url, data={'data': query}, timeout=30)
            if resp.status_code == 200:
                data = resp.json()
                elements = data.get('elements', [])
                print(f"[+] Successfully fetched {len(elements)} raw elements from OSM.", file=sys.stderr)
                return elements
        except Exception as e:
            print(f"[-] Failed with {url}: {e}", file=sys.stderr)
            
    print("[-] Warning: Overpass API unreachable or timed out.", file=sys.stderr)
    return []

def classify_element(name, tags):
    """Classify OSM element into faculty and category group based on keyword rules."""
    clean_name = name.lower()
    desc = tags.get('description', '').lower()
    full_text = f"{clean_name} {desc}"

    for rule in CATEGORY_RULES:
        for pattern in rule['patterns']:
            if re.search(pattern, full_text, re.IGNORECASE):
                return rule['faculty'], rule['category'], rule['code_prefix']

    return None, None, None

def generate_building_code(name, prefix, existing_codes):
    """Generate a clean, unique building code."""
    clean = re.sub(r'[^A-Za-z0-9]+', '-', name).strip('-').upper()
    parts = [p for p in clean.split('-') if p not in ['GEDUNG', 'FAKULTAS', 'DEPARTEMEN', 'UNIVERSITAS', 'SUMATERA', 'UTARA', 'USU', 'DAN', 'LABORATORIUM', 'LAB']]
    
    if parts:
        code_body = '-'.join(parts[:3])
        code = f"{prefix}-{code_body}" if not code_body.startswith(prefix) else code_body
    else:
        code = f"{prefix}-{clean[:8]}"
        
    code = code[:20].rstrip('-')
    
    # Ensure uniqueness
    counter = 1
    base_code = code
    while code in existing_codes:
        code = f"{base_code[:16]}-{counter}"
        counter += 1
        
    existing_codes.add(code)
    return code

def process_osm_elements(elements):
    """Filter, clean, and organize OSM buildings into hierarchy."""
    existing_codes = set()
    grouped_buildings = {}
    seen_locations = set()

    for el in elements:
        tags = el.get('tags', {})
        name = tags.get('name') or tags.get('name:id') or tags.get('alt_name')
        
        if not name:
            continue
            
        # Get coordinates (center for way/relation, direct for node)
        if 'center' in el:
            lat = round(el['center']['lat'], 8)
            lon = round(el['center']['lon'], 8)
        elif 'lat' in el and 'lon' in el:
            lat = round(el['lat'], 8)
            lon = round(el['lon'], 8)
        else:
            continue

        # Prevent exact duplicate coordinate stacking
        loc_key = (round(lat, 5), round(lon, 5))
        if loc_key in seen_locations:
            continue

        faculty, category, prefix = classify_element(name, tags)
        if not faculty:
            continue

        seen_locations.add(loc_key)
        
        # Floor count extraction
        levels = tags.get('building:levels')
        floor_count = int(levels) if levels and levels.isdigit() else 3
        if floor_count < 1 or floor_count > 10:
            floor_count = 3

        desc = tags.get('description') or f"Gedung {name} di kawasan kampus Universitas Sumatera Utara."
        code = generate_building_code(name, prefix, existing_codes)

        if faculty not in grouped_buildings:
            grouped_buildings[faculty] = {
                'category': category,
                'buildings': []
            }

        grouped_buildings[faculty]['buildings'].append({
            'name': name,
            'code': code,
            'latitude': lat,
            'longitude': lon,
            'floor_count': floor_count,
            'description': desc
        })

    return grouped_buildings

def generate_php_seeder(hierarchy):
    """Generate complete PHP BuildingSeeder code with clean multidimensional array."""
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
    lines.append("     * Coordinates are extracted from OpenStreetMap (OSM) with precision.")
    lines.append("     */")
    lines.append("    public function run(): void")
    lines.append("    {")
    lines.append("        $hierarchy = [")

    for faculty_name, group in hierarchy.items():
        lines.append(f"            // =================================================================")
        lines.append(f"            // {faculty_name.upper()}")
        lines.append(f"            // =================================================================")
        lines.append(f"            '{faculty_name}' => [")
        lines.append(f"                'category' => '{group['category']}',")
        lines.append(f"                'buildings' => [")

        for b in group['buildings']:
            lines.append("                    [")
            lines.append(f"                        'name' => '{b['name']}',")
            lines.append(f"                        'code' => '{b['code']}',")
            lines.append(f"                        'latitude' => {b['latitude']:.8f},")
            lines.append(f"                        'longitude' => {b['longitude']:.8f},")
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

def main():
    print("================================================================", file=sys.stderr)
    print("  USUClassMap - OSM Building Seeder Generator (Overpass API)   ", file=sys.stderr)
    print("================================================================", file=sys.stderr)
    
    elements = fetch_osm_data()
    
    if elements:
        hierarchy = process_osm_elements(elements)
        total_buildings = sum(len(g['buildings']) for g in hierarchy.values())
        print(f"[+] Successfully categorized {total_buildings} buildings across {len(hierarchy)} zones from live OSM.", file=sys.stderr)
    else:
        print("[-] Using comprehensive fallback data based on verified OSM coordinates.", file=sys.stderr)
        from osm_fallback_data import get_verified_osm_hierarchy
        hierarchy = get_verified_osm_hierarchy()

    php_code = generate_php_seeder(hierarchy)
    
    # Save directly to database/seeders/BuildingSeeder.php with UTF-8 encoding (avoids PowerShell UTF-16 redirection issue)
    target_path = "database/seeders/BuildingSeeder.php"
    try:
        with open(target_path, "w", encoding="utf-8") as f:
            f.write(php_code)
        print(f"[+] Successfully written to {target_path} (UTF-8 without BOM).", file=sys.stderr)
    except Exception as e:
        print(f"[-] Could not write directly to {target_path}: {e}", file=sys.stderr)

    # Also print to stdout
    print(php_code)

if __name__ == '__main__':
    main()
