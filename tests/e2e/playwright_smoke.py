import os
import re
import sys
import time
from pathlib import Path
from urllib.parse import urljoin

from playwright.sync_api import Error, Page, expect, sync_playwright


APP_BASE_URL = os.getenv("APP_BASE_URL", "http://localhost/Expense-Tracker-System/")
RUN_REPORT_CHECK = os.getenv("RUN_REPORT_CHECK", "0") == "1"
REPORT_BASE_URL = os.getenv(
    "REPORT_BASE_URL",
    "https://zagrosbaban1.github.io/Expense-Tracker-System/report/",
)


def app_url(path: str = "") -> str:
    return urljoin(APP_BASE_URL, path)


def assert_no_php_failure(page: Page) -> None:
    body = page.locator("body")
    expect(body).not_to_contain_text("Fatal error")
    expect(body).not_to_contain_text("Parse error")
    expect(body).not_to_contain_text("Warning:")
    expect(body).not_to_contain_text("Connection Fail")


def save_failure_artifacts(page: Page, name: str) -> None:
    artifact_dir = Path("tests/e2e/artifacts")
    artifact_dir.mkdir(parents=True, exist_ok=True)
    safe_name = re.sub(r"[^a-zA-Z0-9_-]+", "-", name).strip("-") or "failure"
    (artifact_dir / f"{safe_name}.html").write_text(page.content(), encoding="utf-8")
    page.screenshot(path=str(artifact_dir / f"{safe_name}.png"), full_page=True)


def goto_app(page: Page, path: str = "") -> None:
    page.goto(app_url(path), wait_until="domcontentloaded")
    assert_no_php_failure(page)


def check_public_pages(page: Page) -> None:
    goto_app(page)
    expect(page).to_have_title(re.compile("Login"))
    expect(page.get_by_role("heading", name="Daily Expense Tracker")).to_be_visible()
    expect(page.get_by_text("Log in")).to_be_visible()
    expect(page.get_by_placeholder("E-mail")).to_be_visible()
    expect(page.get_by_placeholder("Password")).to_be_visible()

    page.get_by_placeholder("E-mail").fill("missing-user@example.com")
    page.get_by_placeholder("Password").fill("wrong-password")
    page.get_by_role("button", name="Login").click()
    expect(page.get_by_text("Invalid Details.")).to_be_visible()

    goto_app(page, "register.php")
    expect(page).to_have_title(re.compile("Signup"))
    expect(page.get_by_text("Sign Up")).to_be_visible()
    expect(page.get_by_placeholder("Full Name")).to_be_visible()
    expect(page.get_by_placeholder("Mobile Number")).to_be_visible()

    goto_app(page, "forgot-password.php")
    expect(page).to_have_title(re.compile("Password"))
    expect(page.get_by_placeholder("E-mail")).to_be_visible()


def check_protected_redirect(browser) -> None:
    context = browser.new_context()
    page = context.new_page()
    try:
        goto_app(page, "dashboard.php")
        expect(page).to_have_url(re.compile(r"(index|logout)\.php$|Expense-Tracker-System/?$"))
        expect(page.get_by_text("Log in")).to_be_visible()
    finally:
        context.close()


def register_user(page: Page, email: str, password: str) -> None:
    goto_app(page, "register.php")
    page.get_by_placeholder("Full Name").fill("Playwright Test User")
    page.get_by_placeholder("E-mail").fill(email)
    page.get_by_placeholder("Mobile Number").fill("1234567890")
    page.locator("input[name='password']").fill(password)
    page.locator("input[name='repeatpassword']").fill(password)
    page.get_by_role("button", name="Register").click()
    expect(page.get_by_text("You have successfully registered")).to_be_visible()


def login(page: Page, email: str, password: str) -> None:
    goto_app(page)
    page.get_by_placeholder("E-mail").fill(email)
    page.get_by_placeholder("Password").fill(password)
    page.get_by_role("button", name="Login").click()
    page.wait_for_url(re.compile("dashboard\\.php"), timeout=10_000)
    assert_no_php_failure(page)
    expect(page.get_by_role("heading", name="Expense overview")).to_be_visible()


def add_item(page: Page, item_name: str) -> None:
    goto_app(page, "add-item.php")
    expect(page.locator(".panel-heading").filter(has_text="Add Items")).to_be_visible()
    page.once("dialog", lambda dialog: dialog.accept())
    page.get_by_placeholder("e.g. Groceries").fill(item_name)
    page.get_by_role("button", name="Add Item").click()
    page.wait_for_load_state("domcontentloaded")
    expect(page.get_by_text(item_name)).to_be_visible()


def add_category_and_budget(page: Page, category_name: str) -> None:
    goto_app(page, "manage-categories.php")
    expect(page.get_by_role("heading", name="Categories and budgets")).to_be_visible()

    page.locator("#category_name").fill(category_name)
    page.get_by_role("button", name="Add Category").click()
    expect(page.get_by_text("Category added successfully.")).to_be_visible()
    expect(page.locator("td").filter(has_text=category_name).first).to_be_visible()

    page.locator("#budget_category_id").select_option(label=category_name)
    page.locator("#budget_amount").fill("500")
    page.get_by_role("button", name="Save Budget").click()
    expect(page.get_by_text("Budget saved successfully.")).to_be_visible()


def add_expense(page: Page, item_name: str, category_name: str) -> None:
    goto_app(page, "add-expense.php")
    expect(page.get_by_role("heading", name="Add expense")).to_be_visible()

    page.locator("#item").select_option(label=item_name)
    page.locator("#categoryid").select_option(label=category_name)
    page.locator("#costitem").fill("42.50")
    page.locator("#notes").fill("Created by Playwright full-site test")
    page.get_by_role("button", name="Add Expense").click()
    page.wait_for_url(re.compile("manage-expense\\.php\\?status=added"), timeout=10_000)
    expect(page.get_by_text("Expense added successfully.")).to_be_visible()
    expect(page.get_by_text(item_name)).to_be_visible()


def check_manage_expense_filters(page: Page, item_name: str) -> None:
    goto_app(page, "manage-expense.php")
    page.locator("#q").fill(item_name)
    page.get_by_role("button", name="Apply Filters").click()
    expect(page.get_by_text(item_name)).to_be_visible()
    expect(page.get_by_text("1 records").or_(page.get_by_text("records"))).to_be_visible()


def add_recurring_expense(page: Page, item_name: str, category_name: str) -> None:
    goto_app(page, "manage-recurring.php")
    expect(page.locator(".metric-label").filter(has_text="Add recurring expense")).to_be_visible()
    page.locator("#item").select_option(label=item_name)
    page.locator("#categoryid").select_option(label=category_name)
    page.locator("#cost").fill("10")
    page.locator("#frequency").select_option("weekly")
    page.locator("#notes").fill("Created by Playwright full-site test")
    page.get_by_role("button", name="Add Recurring").click()
    expect(page.get_by_text("Recurring expense created.")).to_be_visible()
    expect(page.locator("td").filter(has_text=item_name).first).to_be_visible()


def check_authenticated_navigation(page: Page) -> None:
    pages = [
        ("dashboard.php", "Expense overview"),
        ("manage-expense.php", "Manage expenses"),
        ("expense-datewise-reports.php", "Daily report"),
        ("expense-monthwise-reports.php", "Monthly report"),
        ("expense-yearwise-reports.php", "Yearly report"),
        ("user-profile.php", "Profile"),
        ("change-password.php", "Change Password"),
    ]

    for path, text in pages:
        goto_app(page, path)
        expect(page.get_by_text(text, exact=False).first).to_be_visible()


def check_report_site(page: Page) -> None:
    page.goto(REPORT_BASE_URL, wait_until="domcontentloaded")
    expect(page).to_have_title(re.compile("Expense Tracker System"))
    expect(page.get_by_role("heading", name="Expense Tracker System")).to_be_visible()

    page.goto(urljoin(REPORT_BASE_URL, "validation.html"), wait_until="domcontentloaded")
    expect(page.get_by_role("heading", name="Validation", exact=True)).to_be_visible()
    expect(page.get_by_text("Testing Approach")).to_be_visible()


def run_full_app_test(browser) -> None:
    context = browser.new_context()
    page = context.new_page()
    timestamp = str(int(time.time()))
    email = f"playwright-{timestamp}@example.com"
    password = "Playwright123!"
    item_name = f"Playwright Item {timestamp}"
    category_name = f"Playwright Category {timestamp}"

    try:
        check_public_pages(page)
        check_protected_redirect(browser)
        register_user(page, email, password)
        login(page, email, password)
        add_item(page, item_name)
        add_category_and_budget(page, category_name)
        add_expense(page, item_name, category_name)
        check_manage_expense_filters(page, item_name)
        add_recurring_expense(page, item_name, category_name)
        check_authenticated_navigation(page)
    except Exception:
        save_failure_artifacts(page, "full-site-e2e-failure")
        raise
    finally:
        context.close()


def main() -> None:
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            run_full_app_test(browser)
            if RUN_REPORT_CHECK:
                report_context = browser.new_context()
                try:
                    check_report_site(report_context.new_page())
                finally:
                    report_context.close()
        finally:
            browser.close()

    print("Playwright full-site E2E test passed.")


if __name__ == "__main__":
    try:
        main()
    except Error as exc:
        print(f"Playwright full-site E2E test failed: {exc}", file=sys.stderr)
        sys.exit(1)
