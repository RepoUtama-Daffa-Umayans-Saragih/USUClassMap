import urllib.request
import re
import json
from playwright.sync_api import sync_playwright
from bs4 import BeautifulSoup

queries = [
    ('1', 'Fakultas Teknik USU Medan'),
    ('2', 'Fakultas Ilmu Sosial dan Ilmu Politik USU Medan'),
    ('3', 'Fakultas Kedokteran USU Medan'),
    ('4', 'Fakultas Hukum USU Medan'),
    ('5', 'Perpustakaan Universitas Sumatera Utara Medan'),
    ('6', 'Pintu 1 USU Jl Universitas Medan'),
    ('13', 'BISA INTERNET Jl Dr Mansyur Medan'),
    ('16', 'Percetakan Jamin Ginting Medan'),
    ('18', 'AR FOTO COPY Jl Sumber Padang Bulan Medan')
]

res = {}

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    for key, q in queries:
        try:
            url = f"https://www.google.com/maps/search/{q.replace(' ', '+')}"
            page.goto(url, timeout=25000)
            page.wait_for_timeout(3500)
            soup = BeautifulSoup(page.content(), 'html.parser')
            photo = ''
            for img in soup.find_all('img'):
                src = img.get('src', '')
                if ('googleusercontent.com/grass' in src or 'googleusercontent.com/p/' in src) and '=w' in src:
                    photo = re.sub(r'=w\d+-h\d+.*', '=w800-h600-k-no', src)
                    break
            if photo:
                try:
                    r = urllib.request.urlopen(photo, timeout=5)
                    if r.status == 200:
                        res[key] = photo
                        print(f"{key} ({q}) -> 200 OK: {photo[:60]}...", flush=True)
                    else:
                        print(f"{key} ({q}) -> Status {r.status}", flush=True)
                except Exception as ex:
                    print(f"{key} ({q}) -> URL verify failed: {ex}", flush=True)
            else:
                print(f"{key} ({q}) -> No photo found", flush=True)
        except Exception as e:
            print(f"{key} ({q}) -> Exception: {e}", flush=True)
    browser.close()

with open('usu-scraper/campus_photos.json', 'w', encoding='utf-8') as f:
    json.dump(res, f, indent=2)
print("[+] Done fetching campus photos!", flush=True)

