import re
from playwright.sync_api import sync_playwright
from bs4 import BeautifulSoup

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    url = "https://www.google.com/maps/search/SGF+PRINTING+Medan+Jl+Jamin+Ginting"
    page.goto(url, timeout=30000)
    page.wait_for_timeout(4000)
    
    soup = BeautifulSoup(page.content(), 'html.parser')
    for img in soup.find_all('img'):
        src = img.get('src', '')
        if 'googleusercontent.com' in src or 'streetview' in src:
            print("Found Photo:", src[:100], flush=True)
            
    # Check style background images
    for tag in soup.find_all(style=True):
        if 'googleusercontent.com' in tag['style']:
            print("Found Style Photo:", tag['style'][:100], flush=True)

    browser.close()

