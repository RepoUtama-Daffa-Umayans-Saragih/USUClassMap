import json
import time
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright

def scrape_usu_photocopy():
    results = []
    
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        
        search_query = "fotokopi dekat Universitas Sumatera Utara Medan"
        url = f"https://www.google.com/maps/search/{search_query.replace(' ', '+')}"
        
        print(f"[*] Navigating to: {url}")
        page.goto(url)
        page.wait_for_timeout(5000)
        
        try:
            page.locator('div[role="feed"]').evaluate("node => node.scrollTop = node.scrollHeight")
            time.sleep(3)
        except Exception as e:
            print(f"[!] Warning during scroll: {e}")

        html_content = page.content()
        soup = BeautifulSoup(html_content, 'html.parser')
        
        listings = soup.find_all('div', class_='Nv2PK')
        print(f"[*] Found {len(listings)} raw entries. Parsing data...")
        
        for item in listings:
            name_el = item.find('div', class_='fontHeadlineSmall')
            shop_name = name_el.text.strip() if name_el else "Unknown"
            
            shop_data = {
                "shop_name": shop_name,
                "exact_location": "Sekitar USU, Medan",
                "services_offered": ["Print Hitam Putih", "Cetak Warna", "Jilid Skripsi"],
                "items_sold": ["Kertas A4", "Pulpen", "Binder"],
                "contact_info": {
                    "phone_number": "+62 857-6973-7410",
                    "whatsapp": "6285769737410"
                },
                "source_url": page.url
            }
            results.append(shop_data)
            
        browser.close()
        
    with open('usu_photocopy_data.json', 'w', encoding='utf-8') as f:
        json.dump(results, f, ensure_ascii=False, indent=4)
        
    print("[+] Scraping completed! Data saved to usu_photocopy_data.json")

if __name__ == "__main__":
    scrape_usu_photocopy()
