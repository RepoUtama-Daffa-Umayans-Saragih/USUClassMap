from playwright.sync_api import sync_playwright
from bs4 import BeautifulSoup
import re

def test_photos():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        # Direct URL to SGF Printing Medan
        url = "https://www.google.com/maps/place/SGF+PRINTING+Medan/@3.5614,98.6512,15z"
        print(f"[*] Navigating to: {url}")
        page.goto(url, timeout=60000)
        page.wait_for_timeout(5000)
        
        soup = BeautifulSoup(page.content(), 'html.parser')
        
        # Check all img tags
        imgs = soup.find_all('img')
        print(f"Total imgs: {len(imgs)}")
        for img in imgs:
            src = img.get('src', '')
            if 'googleusercontent.com' in src:
                print(f"Found Google photo: {src}")
                
        # Check phone button
        phone_btn = soup.find('button', attrs={'data-item-id': re.compile(r'^phone:')})
        print(f"Phone button: {phone_btn.get('data-item-id') if phone_btn else 'None'}")
        
        # Check address button
        addr_btn = soup.find('button', attrs={'data-item-id': re.compile(r'^address')})
        print(f"Address button: {addr_btn.get('aria-label') if addr_btn else 'None'}")

        browser.close()

if __name__ == '__main__':
    test_photos()

