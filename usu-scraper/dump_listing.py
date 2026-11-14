import sys
from playwright.sync_api import sync_playwright
from bs4 import BeautifulSoup

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    page.goto('https://www.google.com/maps/search/fotokopi+dekat+Universitas+Sumatera+Utara+Medan', timeout=60000)
    page.wait_for_timeout(5000)
    
    soup = BeautifulSoup(page.content(), 'html.parser')
    listings = soup.find_all('div', class_='Nv2PK')
    print(f"Total listings: {len(listings)}", flush=True)
    
    for i, it in enumerate(listings[:5]):
        name_el = it.find('div', class_='fontHeadlineSmall')
        name = name_el.text.strip() if name_el else "?"
        lines = [d.text.strip() for d in it.find_all('div', class_='W4Efsd') if d.text.strip()]
        a = it.find('a', class_='hfpxzc')
        href = a['href'] if a else ""
        print(f"\nItem #{i}: {name}", flush=True)
        print(f"Lines: {lines}", flush=True)
        print(f"Href: {href[:70]}", flush=True)
        
    browser.close()

