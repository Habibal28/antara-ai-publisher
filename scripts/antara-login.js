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

    await page.waitForFunction(() => {
        const username = document.querySelector('input[name="user"]');
        const error = document.querySelector("#error");
        const errorText = error?.textContent?.trim();

        return !username || Boolean(errorText);
    }, { timeout: 10000 }).catch(() => {});

    const loginForm = page.locator('input[name="user"]');
    if (await loginForm.isVisible().catch(() => false)) {
        const diagnostics = await page.evaluate(() => {
            const visible = (element) => {
                const style = window.getComputedStyle(element);
                const rect = element.getBoundingClientRect();

                return style.display !== "none" &&
                    style.visibility !== "hidden" &&
                    rect.width > 0 &&
                    rect.height > 0;
            };
            const selectors = [
                '[role="alert"]',
                ".alert",
                ".error",
                ".text-danger",
                ".invalid-feedback",
                "#error",
                "#message",
            ];
            const messages = [...new Set(
                selectors.flatMap((selector) =>
                    [...document.querySelectorAll(selector)]
                        .map((element) => element.innerText.trim())
                        .filter(Boolean),
                ),
            )];

            return {
                url: window.location.href,
                title: document.title,
                messages,
            };
        });
        const detail = diagnostics.messages.length
            ? ` Pesan halaman: ${diagnostics.messages.join(" | ")}`
            : " Halaman tidak menampilkan pesan validasi yang dikenali.";

        throw new Error(
            `Login ANTARA belum berhasil; formulir login masih terlihat. URL: ${diagnostics.url}; judul: ${diagnostics.title}.${detail}`,
        );
    }

    await context.storageState({ path: "antara-session.json" });
    process.stdout.write("Login ANTARA berhasil; session diperbarui.\n");
} finally {
    if (browser) await browser.close();
}
