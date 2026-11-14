import os
from playwright.sync_api import sync_playwright

artifact_dir = r"C:\Users\Asus Tuf\.gemini\antigravity\brain\d6411217-9e56-4805-a767-2dd85389ac12"
os.makedirs(artifact_dir, exist_ok=True)

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    
    # 1. Desktop viewport
    page = browser.new_page(viewport={"width": 1280, "height": 900})
    
    # Listing page
    page.goto("http://127.0.0.1:8000/fotocopy", timeout=30000)
    page.wait_for_timeout(2000)
    page.screenshot(path=os.path.join(artifact_dir, "fotocopy-listing-google-photos.png"), full_page=False)
    print("Screenshot 1 taken: fotocopy-listing-google-photos.png")
    
    # Detail page: SGF Printing (Digital Print / Banner)
    page.goto("http://127.0.0.1:8000/fotocopy/8", timeout=30000)
    page.wait_for_timeout(2000)
    page.screenshot(path=os.path.join(artifact_dir, "fotocopy-sgf-detail.png"), full_page=False)
    print("Screenshot 2 taken: fotocopy-sgf-detail.png")
    
    # Detail page: Stiker Custom Medan
    page.goto("http://127.0.0.1:8000/fotocopy/17", timeout=30000)
    page.wait_for_timeout(2000)
    page.screenshot(path=os.path.join(artifact_dir, "fotocopy-stiker-detail.png"), full_page=False)
    print("Screenshot 3 taken: fotocopy-stiker-detail.png")
    
    # Order page: Stiker Custom Medan
    page.goto("http://127.0.0.1:8000/fotocopy/17/order", timeout=30000)
    page.wait_for_timeout(2000)
    page.screenshot(path=os.path.join(artifact_dir, "fotocopy-stiker-order.png"), full_page=False)
    print("Screenshot 4 taken: fotocopy-stiker-order.png")
    
    browser.close()

print("[+] All screenshots captured successfully!")

