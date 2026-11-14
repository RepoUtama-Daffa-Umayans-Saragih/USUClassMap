import re
import json
import time
from playwright.sync_api import sync_playwright
from bs4 import BeautifulSoup

places_to_query = [
    {"id": 7, "name": "Fotocopy FP USU", "query": "Fotocopy FP USU Medan"},
    {"id": 8, "name": "SGF PRINTING Medan", "query": "SGF PRINTING Medan Jl Jamin Ginting"},
    {"id": 9, "name": "Suwi Fotocopy - Printing & ATK", "query": "Suwi Fotocopy Printing ATK Medan"},
    {"id": 10, "name": "Percetakan Modena & Fotocopy", "query": "Percetakan Modena Fotocopy Medan Jl Dr Mansyur"},
    {"id": 11, "name": "Ruth Fotocopy", "query": "Ruth Fotocopy Medan Padang Bulan"},
    {"id": 12, "name": "FAHRI COMPUTER DAN FOTOCOPY", "query": "FAHRI COMPUTER DAN FOTOCOPY Medan Harmonika"},
    {"id": 13, "name": "BISA INTERNET FOTOCOPY", "query": "BISA INTERNET FOTOCOPY Medan Jl Dr Mansyur"},
    {"id": 14, "name": "Eman Fotocopy", "query": "Eman Fotocopy Medan Padang Bulan"},
    {"id": 15, "name": "Printzy Fotocopy & Printing", "query": "Printzy Fotocopy Printing Medan Abdul Hakim"},
    {"id": 16, "name": "New Nasional Fotocopy", "query": "New Nasional Fotocopy Medan Jamin Ginting"},
    {"id": 17, "name": "Stiker Custom Medan", "query": "Stiker Custom Medan Dr Mansyur"},
    {"id": 18, "name": "AR FOTO COPY / Cetak poto", "query": "AR FOTO COPY Cetak poto Medan Sumber"},
    {"id": 19, "name": "FOTOCOPY FORTUNA", "query": "FOTOCOPY FORTUNA Medan Harmonika Baru"},
    {"id": 20, "name": "Igna Foto Copy", "query": "Igna Foto Copy Medan"},
    {"id": 21, "name": "NEXT PERCETAKAN", "query": "NEXT PERCETAKAN Medan Jamin Ginting"},
    {"id": 22, "name": "Foto Copy Risa", "query": "Foto Copy Risa Medan Pintu Air"}
]

results = {}

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    for item in places_to_query:
        q = item["query"]
        print(f"[*] Searching for {item['name']}...", flush=True)
        try:
            url = f"https://www.google.com/maps/search/{q.replace(' ', '+')}"
            page.goto(url, timeout=25000)
            page.wait_for_timeout(3500)
            soup = BeautifulSoup(page.content(), 'html.parser')
            
            photo = ""
            for img in soup.find_all('img'):
                src = img.get('src', '')
                if ('googleusercontent.com' in src or 'streetviewpixels' in src or 'ggpht.com' in src) and ('=w' in src or '=s' in src):
                    photo = re.sub(r'=w\d+-h\d+.*', '=w800-h600-k-no', src)
                    break
                    
            phone = ""
            # Search phone button
            phone_btn = soup.find('button', attrs={'data-item-id': re.compile(r'phone:tel:')})
            if phone_btn:
                phone = phone_btn.get('data-item-id', '').replace('phone:tel:', '').strip()
            if not phone:
                phone_m = re.search(r'((?:08|\+62|061)[\d\s\-]{8,15})', soup.get_text())
                if phone_m:
                    phone = phone_m.group(1).strip()
                    
            results[item["id"]] = {
                "name": item["name"],
                "photo": photo,
                "phone": phone
            }
            print(f"    -> Phone: {phone} | Photo: {photo[:60]}...", flush=True)
        except Exception as e:
            print(f"    -> Error: {e}", flush=True)
            results[item["id"]] = {"name": item["name"], "photo": "", "phone": ""}
            
    browser.close()

with open("usu-scraper/scraped_photos_and_phones.json", "w", encoding="utf-8") as f:
    json.dump(results, f, indent=2, ensure_ascii=False)
print("[+] Done scraping all photos and phones!", flush=True)

