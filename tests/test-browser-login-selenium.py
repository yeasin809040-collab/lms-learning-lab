#!/usr/bin/env python3
"""
Chrome/Selenium E2E verification for one disposable wp-env WordPress instance.
No live LMS, payment gateway or real student data.
"""
import os
import sys
import time
from pathlib import Path
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

BASE = os.getenv("LLS95_SITE_URL", "http://127.0.0.1:8888").rstrip("/")
USER = "lls95_demo_student"
PASSWORD = "LabDemo95!2026"
PORTAL = BASE + "/lls95-student-portal/"
LESSON = BASE + "/learning-lab-protected-sample-lesson/"
MARKER = "PRIVATE_LAB_LESSON"
counts = {"passed":0, "failed":0}
driver = None

def check(label, condition):
    if condition:
        counts["passed"] += 1
        print(f"BROWSER PASS: {label}", flush=True)
    else:
        counts["failed"] += 1
        print(f"BROWSER FAIL: {label}", flush=True)
        raise AssertionError(label)

def wait_css(selector, seconds=25):
    return WebDriverWait(driver, seconds).until(
        EC.presence_of_element_located((By.CSS_SELECTOR, selector))
    )

opts = webdriver.ChromeOptions()
opts.add_argument("--headless=new")
opts.add_argument("--no-sandbox")
opts.add_argument("--disable-dev-shm-usage")
opts.add_argument("--window-size=1400,1000")
opts.add_argument("--disable-gpu")

try:
    driver = webdriver.Chrome(options=opts)
    driver.set_page_load_timeout(45)

    # Fresh incognito browser profile, truly logged out.
    driver.get(PORTAL)
    portal = wait_css(".lls95-login-lab")
    check("Anonymous browser session starts logged out", portal.get_attribute("data-login-state")=="guest")
    check("WordPress sign-in form is available", bool(driver.find_elements(By.ID,"user_login"))
        and bool(driver.find_elements(By.ID,"user_pass")))

    driver.get(LESSON)
    check("Guest cannot read private lesson marker", MARKER not in driver.page_source
          and 'data-lls-access="denied"' in driver.page_source)

    driver.get(PORTAL)
    wait_css("#user_login").send_keys(USER)
    wait_css("#user_pass").send_keys(PASSWORD)
    wait_css("#wp-submit").click()

    portal=wait_css(".lls95-login-lab")
    check("Real WordPress form login authenticates subscriber",
          portal.get_attribute("data-login-state")=="enrolled")
    check("Authenticated browser user is synthetic learner",
          USER in driver.find_element(By.ID,"lls95-user").text)
    check("WordPress auth cookie exists",any("logged_in" in c["name"] for c in driver.get_cookies()))

    # Follow the canonical WordPress permalink: wp-env often redirects login to
    # localhost:8888 even when the initial test URL is 127.0.0.1:8888.
    # Cookies are scoped to host, so switching back to the initial literal
    # address can silently turn an authenticated request into a guest request.
    canonical_portal_url = driver.current_url
    canonical_lesson_url = driver.find_element(
        By.ID, "lls95-lesson-link"
    ).get_attribute("href")
    from urllib.parse import urlsplit
    portal_origin = urlsplit(canonical_portal_url)
    lesson_origin = urlsplit(canonical_lesson_url)
    check("WordPress lesson URL uses authenticated browser origin",
          (portal_origin.scheme, portal_origin.netloc) ==
          (lesson_origin.scheme, lesson_origin.netloc))
    print("BROWSER DIAGNOSTIC: initial_origin=" + urlsplit(BASE).netloc
          + " authenticated_origin=" + portal_origin.netloc, flush=True)

    driver.get(canonical_lesson_url)
    if MARKER not in driver.page_source or 'data-lls-access="granted"' not in driver.page_source:
        print("LESSON DIAGNOSTIC: url=" + driver.current_url, flush=True)
        print("LESSON DIAGNOSTIC: title=" + driver.title, flush=True)
        print("LESSON DIAGNOSTIC: is_denied=" +
              str('data-lls-access="denied"' in driver.page_source), flush=True)
        print("LESSON DIAGNOSTIC: wordpress_logged_in_cookie_present=" +
              str(any("logged_in" in c["name"] for c in driver.get_cookies())), flush=True)
    check("Logged-in enrolled student sees private lesson",
          MARKER in driver.page_source and 'data-lls-access="granted"' in driver.page_source)

    driver.get(canonical_portal_url)
    logout=wait_css("#lls95-logout-link")
    logout.click()
    portal=wait_css(".lls95-login-lab")
    check("WordPress logout changes browser auth state to guest",
          portal.get_attribute("data-login-state")=="guest")
    check("WordPress session has no logged-in auth cookie",
          not any("logged_in" in c["name"] for c in driver.get_cookies()))

    driver.get(canonical_lesson_url)
    check("After logout the protected lesson becomes inaccessible",
          MARKER not in driver.page_source and 'data-lls-access="denied"' in driver.page_source)

    # Invalid password must not silently authenticate the user.
    driver.get(canonical_portal_url)
    wait_css("#user_login").send_keys(USER)
    wait_css("#user_pass").send_keys("INVALID-LAB-PASSWORD-95")
    wait_css("#wp-submit").click()
    time.sleep(1.0)
    check("Invalid password does not create WordPress logged-in cookie",
          not any("logged_in" in c["name"] for c in driver.get_cookies()))

    # Fresh successful re-authentication after failed attempt.
    driver.get(canonical_portal_url)
    wait_css("#user_login").send_keys(USER)
    wait_css("#user_pass").send_keys(PASSWORD)
    wait_css("#wp-submit").click()
    portal=wait_css(".lls95-login-lab")
    check("Student can sign in again after logging out",
          portal.get_attribute("data-login-state")=="enrolled")
    driver.get(canonical_lesson_url)
    check("Private lesson accessible again after second login",
          MARKER in driver.page_source)

    print(f"BROWSER E2E TOTAL: {counts['passed']} PASS / {counts['failed']} FAIL",flush=True)
except Exception as error:
    print(f"BROWSER TEST ERROR: {type(error).__name__}: {error}",file=sys.stderr,flush=True)
    if driver:
        Path("artifacts").mkdir(parents=True,exist_ok=True)
        try:
            driver.save_screenshot("artifacts/95-browser-last-page.png")
            print("Saved failure screenshot to artifacts/95-browser-last-page.png",flush=True)
        except Exception:
            pass
    print(f"BROWSER E2E TOTAL: {counts['passed']} PASS / {max(1,counts['failed'])} FAIL",flush=True)
    raise
finally:
    if driver: driver.quit()
