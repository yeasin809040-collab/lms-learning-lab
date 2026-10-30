"""Smoke test on a real disposable WordPress installation and real headless Chrome."""
import os
from pathlib import Path
from urllib.parse import urlencode
from urllib.request import Request, urlopen

from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait


ROOT = "http://127.0.0.1:8888"
ARTIFACTS = Path("artifacts")
ARTIFACTS.mkdir(exist_ok=True)

options = Options()
options.add_argument("--headless=new")
options.add_argument("--no-sandbox")
options.add_argument("--disable-dev-shm-usage")
options.add_argument("--disable-gpu")
options.add_argument("--window-size=1440,900")

driver = webdriver.Chrome(options=options)
try:
    driver.get(ROOT)
    WebDriverWait(driver, 20).until(lambda d: d.find_elements(By.CSS_SELECTOR, ".hero h1"))
    assert "Northline" in driver.title, f"Unexpected title: {driver.title}"
    assert "Make your next move" in driver.find_element(By.CSS_SELECTOR, "h1").text
    assert len(driver.find_elements(By.CSS_SELECTOR, ".service-card")) == 3
    assert len(driver.find_elements(By.CSS_SELECTOR, ".step")) == 3
    hero = driver.find_element(By.CSS_SELECTOR, ".hero-image")
    assert driver.execute_script("return arguments[0].complete && arguments[0].naturalWidth > 0;", hero)
    assert driver.execute_script("return document.documentElement.scrollWidth <= window.innerWidth + 1;")
    driver.save_screenshot(str(ARTIFACTS / "desktop-wordpress.png"))
    print("PASS desktop: WordPress-rendered homepage, image and layout")

    # Check ordinary WordPress pages and 404 routing. No customer data is used.
    driver.get(ROOT + "/unmistakably-nonexistent-northline-test-page/")
    WebDriverWait(driver, 10).until(lambda d: d.find_elements(By.CSS_SELECTOR, ".inner-page h1"))
    assert "couldn't find" in driver.find_element(By.CSS_SELECTOR, "h1").text.lower()
    print("PASS WordPress 404 template")

    # Mobile nav, click-to-close, keyboard Escape and overflow.
    driver.set_window_size(390, 844)
    driver.get(ROOT)
    WebDriverWait(driver, 10).until(lambda d: d.find_elements(By.CSS_SELECTOR, ".menu-toggle"))
    assert driver.execute_script("return document.documentElement.scrollWidth <= window.innerWidth + 1;")
    driver.save_screenshot(str(ARTIFACTS / "mobile-wordpress.png"))
    toggle = driver.find_element(By.CSS_SELECTOR, ".menu-toggle")
    assert toggle.is_displayed()
    toggle.click()
    assert toggle.get_attribute("aria-expanded") == "true"
    driver.find_element(By.CSS_SELECTOR, ".main-nav a[href$='#services']").click()
    # Anchor navigation may produce a fresh DOM. Reacquire instead of using a stale element.
    WebDriverWait(driver, 10).until(lambda d: d.find_element(By.CSS_SELECTOR, ".menu-toggle").get_attribute("aria-expanded") == "false")
    toggle = driver.find_element(By.CSS_SELECTOR, ".menu-toggle")
    assert "#services" in driver.current_url
    toggle.click()
    driver.switch_to.active_element.send_keys(Keys.ESCAPE)
    WebDriverWait(driver, 10).until(lambda d: d.find_element(By.CSS_SELECTOR, ".menu-toggle").get_attribute("aria-expanded") == "false")
    print("PASS mobile: no horizontal overflow, responsive navigation and Escape")

    # Invalid CSRF nonce must be rejected before mail handling. Synthetic form data only.
    payload = urlencode({"action": "northline_contact", "northline_nonce": "invalid", "name": "QA Test", "email": "qa@example.invalid", "message": "Synthetic validation"}).encode()
    response = urlopen(Request(ROOT + "/wp-admin/admin-post.php", data=payload), timeout=15)
    assert "contact=invalid" in response.url, f"Unexpected CSRF result: {response.url}"
    print("PASS contact: invalid nonce rejected without sending email")
finally:
    driver.quit()
