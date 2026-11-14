import time
from playwright.sync_api import sync_playwright
from bs4 import BeautifulSoup

def test_scrape():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            locale="id-ID"
        )
        page = context.new_page()
        
        search_query = "fotokopi dekat Universitas Sumatera Utara Medan"
        url = f"https://www.google.com/maps/search/{search_query.replace(' ', '+')}"
        print(f"[*] Navigating to: {url}")
        page.goto(url, timeout=60000)
        page.wait_for_timeout(5000)
        
        # Scroll the feed
        try:
            feed = page.locator('div[role="feed"]')
            for _ in range(3):
                feed.evaluate("node => node.scrollTop = node.scrollHeight")
                time.sleep(2)
        except Exception as e:
            print(f"[!] Scroll warning: {e}")
            
        soup = BeautifulSoup(page.content(), 'html.parser')
        listings = soup.find_all('div', class_='Nv2PK')
        print(f"[*] Found {len(listings)} listings")
        
        for idx, item in enumerate(listings[:3]):
            name_el = item.find('div', class_='fontHeadlineSmall')
            name = name_el.text.strip() if name_el else "Unknown"
            
            # Find photo
            img_el = item.find('img')
            img_url = img_el['src'] if img_el and img_el.has_attr('src') else None
            
            # Find link
            a_el = item.find('a', class_='hfpxzc')
            link = a_el['href'] if a_el and a_el.has_attr('href') else None
            
            # Find text lines (category, address, phone)
            lines = [div.text.strip() for div in item.find_all('div', class_='W4Efsd') if div.text.strip()]
            
            print(f"\n--- Place #{idx+1}: {name} ---")
            print(f"Photo: {img_url[:60] if img_url else 'None'}...")
            print(f"Link: {link[:60] if link else 'None'}...")
            print(f"Lines: {lines[:4]}")
            
        browser.close()

if __name__ == '__main__':
    test_scrape()

