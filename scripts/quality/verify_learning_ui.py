"""Render and verify the redesigned learner UI in real Chromium.

This script uses the isolated database and credentials created by
``scripts/e2e/prepare.mjs``. It never reads or writes the development database.
"""

from __future__ import annotations

import json
import os
from pathlib import Path
import subprocess
import sys
import time
from urllib.error import URLError
from urllib.parse import urlparse
from urllib.request import urlopen

from playwright.sync_api import Page, sync_playwright


REPO_ROOT = Path(__file__).resolve().parents[2]
RUN_ID = os.environ.get("HOSPITRAINITY_E2E_RUN_ID", "ui-skills-visual")
ARTIFACT_ROOT = REPO_ROOT / "storage" / "framework" / "testing" / f"e2e-{RUN_ID}"
BASE_URL = "http://127.0.0.1:8010"
SCREENSHOT_ROOT = ARTIFACT_ROOT / "visual-review"


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


def wait_for_server(process: subprocess.Popen[bytes]) -> None:
    deadline = time.monotonic() + 30
    while time.monotonic() < deadline:
        if process.poll() is not None:
            raise RuntimeError(f"The isolated PHP server exited with status {process.returncode}.")
        try:
            with urlopen(f"{BASE_URL}/login", timeout=1) as response:
                if response.status == 200:
                    return
        except (TimeoutError, URLError):
            time.sleep(0.2)
    raise RuntimeError(f"The isolated PHP server did not become ready at {BASE_URL}.")


def open_page(page: Page, path: str) -> None:
    response = page.goto(f"{BASE_URL}{path}", wait_until="networkidle")
    require(response is not None and response.ok, f"{path} did not return a successful response.")
    require(
        page.evaluate("document.documentElement.scrollWidth <= document.documentElement.clientWidth"),
        f"{path} has horizontal page overflow.",
    )


def sign_in(page: Page, account: dict[str, str]) -> None:
    open_page(page, "/login")
    form_action = page.locator("form").first.get_attribute("action")
    page.get_by_label("Email address").fill(account["email"])
    page.get_by_label("Password").fill(account["password"])
    page.get_by_role("button", name="Sign in", exact=True).click()
    page.wait_for_load_state("networkidle")
    destination = urlparse(page.url).path.rstrip("/")
    alerts = page.locator('[role="alert"]').all_inner_texts()
    visible_text = " ".join(page.locator("body").inner_text().split())[:600]
    require(
        destination == "/dashboard",
        f"Learner sign-in ended at {destination or '/'} instead of /dashboard. "
        f"Form: {form_action}; alerts: {alerts}; page: {visible_text}",
    )


def verify_interactions(page: Page, path: str) -> None:
    failures = page.locator("a[href], button:not(:disabled), summary").evaluate_all(
        """elements => elements
            .filter(element => {
                const style = getComputedStyle(element);
                const rect = element.getBoundingClientRect();
                return style.visibility !== 'hidden'
                    && style.display !== 'none'
                    && rect.width > 0
                    && rect.height > 0
                    && style.cursor !== 'pointer';
            })
            .map(element => `${element.tagName.toLowerCase()}:${element.textContent.trim().slice(0, 60)}`)"""
    )
    require(failures == [], f"{path} has interactive elements without pointer cursors: {failures}")


def verify_back_control(page: Page) -> None:
    back = page.locator(".hsp-back-control").first
    require(back.count() == 1, f"{page.url} does not expose the shared back control.")
    box = back.bounding_box()
    require(box is not None and box["width"] >= 44 and box["height"] >= 44, "Back target is smaller than 44px.")
    back.hover()
    page.wait_for_timeout(250)
    opacity = back.locator(".hsp-back-control__label").evaluate("element => getComputedStyle(element).opacity")
    require(opacity == "1", "The destination label does not appear when the back control is hovered.")


def verify_learner_shell(page: Page, current_label: str | None) -> None:
    width = page.viewport_size["width"]
    desktop = width >= 1024
    rail = page.locator(".hsp-shell-rail")
    bottom_navigation = page.locator(".hsp-shell-bottom-nav")
    topbar = page.locator(".hsp-shell-topbar")
    require(topbar.is_visible(), f"The learner top bar is missing at {width}px.")
    require(rail.is_visible() == desktop, f"Desktop rail visibility is wrong at {width}px.")
    require(bottom_navigation.is_visible() != desktop, f"Mobile navigation visibility is wrong at {width}px.")

    for control_id in ["learner-context-panel", "learner-account-panel"]:
        control = page.locator(f'[data-shell-disclosure-button][aria-controls="{control_id}"]')
        box = control.bounding_box()
        require(box is not None and box["width"] >= 44 and box["height"] >= 44, f"{control_id} trigger is smaller than 44px at {width}px.")

    navigation_name = "Primary learner navigation" if desktop else "Mobile learner navigation"
    navigation = page.get_by_role("navigation", name=navigation_name)
    require(navigation.is_visible(), f"{navigation_name} is not visible at {width}px.")
    expected_current_count = 0 if current_label is None else 1
    require(
        navigation.locator('[aria-current="page"]').count() == expected_current_count,
        f"{navigation_name} exposes an incorrect number of current pages.",
    )
    if current_label is not None:
        require(
            navigation.locator('[aria-current="page"]').inner_text().strip() == current_label,
            f"Expected {current_label} to be current in {navigation_name}.",
        )

    for label in ["Home", "Learn", "Progress", "Help"]:
        link = navigation.get_by_role("link", name=label, exact=True)
        require(link.count() == 1 and link.is_visible(), f"{label} is not reachable at {width}px.")
        box = link.bounding_box()
        require(box is not None and box["width"] >= 44 and box["height"] >= 44, f"{label} is smaller than 44px at {width}px.")

    focus_label = current_label or "Home"
    current_link = navigation.get_by_role("link", name=focus_label, exact=True)
    current_link.focus()
    outline_width = current_link.evaluate("element => parseFloat(getComputedStyle(element).outlineWidth)")
    require(outline_width >= 3, f"The visible current navigation item lacks a 3px focus indicator at {width}px.")
    require(
        page.evaluate("document.documentElement.scrollWidth <= document.documentElement.clientWidth"),
        f"The learner shell causes root overflow at {width}px.",
    )


def verify_shell_disclosures(page: Page, evidence_prefix: str) -> None:
    context_button = page.locator('[data-shell-disclosure-button][aria-controls="learner-context-panel"]')
    require(context_button.get_attribute("aria-label") == "Open learning context", "The learning-context action is not named clearly.")
    context_button.click()
    context_panel = page.locator("#learner-context-panel")
    require(context_panel.is_visible(), "The learning-context disclosure did not open.")
    require(context_button.get_attribute("aria-expanded") == "true", "Learning-context state is not exposed.")
    require(context_panel.locator('[aria-current="true"]').count() == 1, "The current learning context is not identified.")
    capture(page, f"{evidence_prefix}-learning-context-panel")
    page.keyboard.press("Escape")
    require(context_panel.is_hidden(), "Escape did not close the learning-context disclosure.")
    require(context_button.evaluate("element => element === document.activeElement"), "Focus did not return to the learning-context button.")

    account_button = page.locator('[data-shell-disclosure-button][aria-controls="learner-account-panel"]')
    require(account_button.get_attribute("aria-label") == "Open account", "The account action is not named clearly.")
    account_button.click()
    account_panel = page.locator("#learner-account-panel")
    require(account_panel.is_visible(), "The account disclosure did not open.")
    require(account_panel.locator('[role="menu"], [role="menuitem"]').count() == 0, "Account navigation incorrectly uses application-menu roles.")
    require(account_panel.get_by_role("link", name="Switch role", exact=True).count() == 0, "An ordinary learner received a role-switch control.")
    language_switcher = account_panel.locator("[data-language-switcher]")
    require(language_switcher.get_by_text("Bahasa Indonesia", exact=True).count() == 1, "The full Bahasa Indonesia label is not visible in the account panel.")
    require(language_switcher.get_by_text("English", exact=True).count() == 1, "The full English label is not visible in the account panel.")
    capture(page, f"{evidence_prefix}-account-panel")
    page.keyboard.press("Escape")
    require(account_panel.is_hidden(), "Escape did not close the account disclosure.")
    require(account_button.evaluate("element => element === document.activeElement"), "Focus did not return to the account button.")


def verify_primary_journey(page: Page) -> None:
    navigation_name = "Primary learner navigation" if page.viewport_size["width"] >= 1024 else "Mobile learner navigation"
    navigation = page.get_by_role("navigation", name=navigation_name)

    navigation.get_by_role("link", name="Learn", exact=True).click()
    page.wait_for_load_state("networkidle")
    require(urlparse(page.url).fragment == "learning-modules", "Learn does not lead to the real module list.")
    require(page.locator("#learning-modules").count() == 1, "The learning-module destination is missing.")

    navigation = page.get_by_role("navigation", name=navigation_name)
    navigation.get_by_role("link", name="Progress", exact=True).click()
    page.wait_for_load_state("networkidle")
    verify_learner_shell(page, "Progress")
    require(page.get_by_role("heading", name="My confidence history").count() == 1, "Progress did not open the truthful reflection history.")

    navigation = page.get_by_role("navigation", name=navigation_name)
    navigation.get_by_role("link", name="Help", exact=True).click()
    page.wait_for_load_state("networkidle")
    verify_learner_shell(page, "Help")

    navigation = page.get_by_role("navigation", name=navigation_name)
    navigation.get_by_role("link", name="Home", exact=True).click()
    page.wait_for_load_state("networkidle")
    verify_learner_shell(page, "Home")


def verify_mobile_focus_visibility(page: Page) -> None:
    footer_link = page.get_by_role("link", name="My privacy requests", exact=True)
    footer_link.focus()
    page.wait_for_timeout(100)
    link_box = footer_link.bounding_box()
    topbar_box = page.locator(".hsp-shell-topbar").bounding_box()
    bottom_bar_box = page.locator(".hsp-shell-bottom-nav").bounding_box()
    require(link_box is not None and topbar_box is not None and bottom_bar_box is not None, "Could not measure focused mobile content.")
    require(link_box["y"] >= topbar_box["y"] + topbar_box["height"], "The fixed top bar obscures focused mobile content.")
    require(link_box["y"] + link_box["height"] <= bottom_bar_box["y"], "The fixed bottom navigation obscures focused mobile content.")
    page.evaluate("window.scrollTo(0, 0)")


def capture(page: Page, name: str) -> None:
    page.screenshot(path=SCREENSHOT_ROOT / f"{name}.png", full_page=True)


def main() -> None:
    credentials_path = ARTIFACT_ROOT / "credentials.json"
    database_path = ARTIFACT_ROOT / "database.sqlite"
    require(credentials_path.is_file(), f"Missing {credentials_path}; run the isolated E2E preparation first.")
    require(database_path.is_file(), f"Missing {database_path}; run the isolated E2E preparation first.")
    credentials = json.loads(credentials_path.read_text(encoding="utf-8"))
    SCREENSHOT_ROOT.mkdir(parents=True, exist_ok=True)

    server_env = os.environ.copy()
    server_env["HOSPITRAINITY_E2E_RUN_ID"] = RUN_ID
    node_binary = os.environ.get("NODE_BINARY", r"C:\Program Files\nodejs\node.exe")
    creation_flags = subprocess.CREATE_NO_WINDOW if sys.platform == "win32" else 0
    server_log = (ARTIFACT_ROOT / "visual-review-server.log").open("wb")
    server = subprocess.Popen(
        [node_binary, str(REPO_ROOT / "scripts" / "e2e" / "visual-server.mjs")],
        cwd=REPO_ROOT,
        env=server_env,
        stdin=subprocess.PIPE,
        stdout=server_log,
        stderr=server_log,
        creationflags=creation_flags,
    )

    try:
        wait_for_server(server)
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True)
            page = browser.new_page(viewport={"width": 1440, "height": 900})
            browser_errors: list[str] = []
            page.on("console", lambda message: browser_errors.append(message.text) if message.type == "error" else None)
            page.on("pageerror", lambda error: browser_errors.append(str(error)))
            sign_in(page, credentials["learner"])

            open_page(page, "/dashboard")
            verify_learner_shell(page, "Home")
            verify_shell_disclosures(page, "desktop")
            capture(page, "desktop-learner-shell-light")

            open_page(page, "/preferences/display")
            page.get_by_role("radio", name="Dark").check()
            page.get_by_role("button", name="Save preferences").click()
            page.wait_for_load_state("networkidle")

            open_page(page, "/help")
            verify_learner_shell(page, "Help")
            verify_back_control(page)
            verify_interactions(page, "/help")
            capture(page, "desktop-help-dark")

            open_page(page, "/curriculum/HSP-C02")
            verify_learner_shell(page, "Learn")
            step_sizes = page.locator(".hsp-learning-step").evaluate_all(
                "steps => steps.map(step => step.querySelectorAll('.hsp-lesson-link').length)"
            )
            require(step_sizes and max(step_sizes) <= 5, f"Learning steps exceed five sections: {step_sizes}")
            require(sum(step_sizes) == 13, f"Module 2 should expose all 13 source sections, got {step_sizes}.")
            verify_back_control(page)
            verify_interactions(page, "/curriculum/HSP-C02")
            capture(page, "desktop-module-journey-dark")

            open_page(page, "/curriculum/sections/HSP-C02-LS-04")
            verify_learner_shell(page, "Learn")
            require(page.locator(".hsp-learning-cards article").count() > 0, "Learner vocabulary did not render as learning cards.")
            require(page.locator(".hsp-lesson-content table").count() == 0, "Learner still receives a copied source table.")
            require("of 85" not in page.locator("body").inner_text(), "The global 85-section counter is still visible.")
            verify_back_control(page)
            verify_interactions(page, "/curriculum/sections/HSP-C02-LS-04")
            capture(page, "desktop-vocabulary-cards-dark")

            open_page(page, "/curriculum/activities/HSP-C02-ACT-PRACTICE")
            verify_learner_shell(page, "Learn")
            require(page.locator(".hsp-activity-prompt").count() > 0, "Practice prompts lack the expanded activity treatment.")
            verify_back_control(page)
            verify_interactions(page, "/curriculum/activities/HSP-C02-ACT-PRACTICE")
            capture(page, "desktop-practice-dark")

            open_page(page, "/curriculum/HSP-C02/steps/1")
            verify_learner_shell(page, "Learn")
            require(page.get_by_role("heading", name="You reached the step wrap-up").count() == 1, "Step wrap-up is missing.")
            verify_back_control(page)
            verify_interactions(page, "/curriculum/HSP-C02/steps/1")
            capture(page, "desktop-step-wrap-up-dark")

            open_page(page, "/institution-memberships")
            verify_learner_shell(page, None)
            current_context = page.locator(".hsp-context-current")
            require(current_context.count() >= 1, "The current learning context is not labelled.")
            require(current_context.locator("button").count() == 0, "The current context is still a no-op button.")
            verify_interactions(page, "/institution-memberships")
            capture(page, "desktop-learning-context-dark")

            for width, height in [(320, 800), (390, 844), (768, 900), (1280, 900)]:
                page.set_viewport_size({"width": width, "height": height})
                open_page(page, "/dashboard")
                verify_learner_shell(page, "Home")
                verify_primary_journey(page)
                if width < 1024:
                    verify_mobile_focus_visibility(page)
                capture(page, f"shell-dark-{width}")

            page.set_viewport_size({"width": 390, "height": 844})
            open_page(page, "/dashboard")
            verify_shell_disclosures(page, "mobile")
            for path, name in [
                ("/help", "mobile-help-dark"),
                ("/curriculum/HSP-C02", "mobile-module-journey-dark"),
                ("/curriculum/sections/HSP-C02-LS-04", "mobile-vocabulary-cards-dark"),
                ("/curriculum/activities/HSP-C02-ACT-PRACTICE", "mobile-practice-dark"),
                ("/curriculum/HSP-C02/steps/1", "mobile-step-wrap-up-dark"),
            ]:
                open_page(page, path)
                expected_current = "Help" if path == "/help" else "Learn"
                verify_learner_shell(page, expected_current)
                verify_interactions(page, path)
                capture(page, name)

            page.set_viewport_size({"width": 1280, "height": 900})
            open_page(page, "/preferences/display")
            page.get_by_role("radio", name="Light").check()
            page.get_by_role("radio", name="Reduce").check()
            page.get_by_role("button", name="Save preferences").click()
            page.wait_for_load_state("networkidle")
            open_page(page, "/dashboard")
            verify_learner_shell(page, "Home")
            nav_link = page.get_by_role("navigation", name="Primary learner navigation").get_by_role("link", name="Home", exact=True)
            nav_link.hover()
            require(nav_link.evaluate("element => getComputedStyle(element).transform") == "none", "Reduced motion still translates learner navigation.")
            capture(page, "desktop-learner-shell-light-reduced")

            require(browser_errors == [], f"Chromium reported browser errors: {browser_errors}")
            browser.close()
    finally:
        if server.stdin is not None and server.poll() is None:
            server.stdin.write(b"stop\n")
            server.stdin.flush()
        try:
            server.wait(timeout=5)
        except subprocess.TimeoutExpired:
            server.terminate()
            server.wait(timeout=5)
        server_log.close()

    print(json.dumps({"status": "passed", "screenshots": str(SCREENSHOT_ROOT), "checked": 31}))


if __name__ == "__main__":
    main()
