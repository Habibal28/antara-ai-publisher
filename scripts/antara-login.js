import "dotenv/config";
import { chromium } from "playwright";

if (!process.env.ANTARA_USERNAME || !process.env.ANTARA_PASSWORD) {
    throw new Error("ANTARA_USERNAME dan ANTARA_PASSWORD wajib diatur di .env.");
}

let browser;

try {
    browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    await page.goto("https://branda.antaranews.com/", {
        waitUntil: "domcontentloaded",
        timeout: 60000,
    });
    await page.locator('input[name="user"]').fill(process.env.ANTARA_USERNAME);
    await page.locator('input[name="pass"]').fill(process.env.ANTARA_PASSWORD);
    await page.locator('button[name="login"]').click();
    await page.waitForLoadState("networkidle", { timeout: 30000 });

    const loginForm = page.locator('input[name="user"]');
    if (await loginForm.isVisible().catch(() => false)) {
        throw new Error("Login ANTARA belum berhasil; formulir login masih terlihat.");
    }

    await context.storageState({ path: "antara-session.json" });
    process.stdout.write("Login ANTARA berhasil; session diperbarui.\n");
} finally {
    if (browser) await browser.close();
}
