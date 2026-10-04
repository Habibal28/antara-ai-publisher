import { chromium } from "playwright";

let browser;

try {
    browser = await chromium.launch({
        headless: false,
    });

    const context = await browser.newContext({
        storageState: "antara-session.json",
    });
    const page = await context.newPage();

    const url = "https://branda.antaranews.com/data/index.php?date=2026-09-28";

    await page.goto(url, {
        waitUntil: "domcontentloaded",
        timeout: 60000,
    });

    // Beri waktu untuk daftar yang dimuat setelah dokumen utama.
    await page.waitForLoadState("networkidle", { timeout: 10000 }).catch(() => {});

    const articleUrls = await page.locator("a[href]").evaluateAll((anchors) => {
        const urls = new Set();

        for (const anchor of anchors) {
            const href = anchor.getAttribute("href");

            if (!href) {
                continue;
            }

            let articleUrl;

            try {
                articleUrl = new URL(href, window.location.href);
            } catch {
                continue;
            }

            if (
                articleUrl.pathname.endsWith("/content.php") &&
                articleUrl.searchParams.has("id")
            ) {
                urls.add(articleUrl.href);
            }
        }

        return [...urls];
    });

    console.log(`Total URL unik: ${articleUrls.length}`);

    if (articleUrls.length === 0) {
        const pageInfo = await page.locator("body").evaluate((body) => ({
            title: document.title,
            text: body.innerText.slice(0, 1000),
            links: [...document.querySelectorAll("a[href]")]
                .map((anchor) => anchor.href)
                .filter((href) => /content|artikel|berita/i.test(href))
                .slice(0, 20),
        }));

        console.log("URL halaman:", page.url());
        console.log("Judul halaman:", pageInfo.title);
        console.log("Cuplikan halaman:", pageInfo.text);
        console.log("Tautan terkait:", JSON.stringify(pageInfo.links, null, 2));
    }

    articleUrls.slice(0, 10).forEach((articleUrl, index) => {
        console.log(`${index + 1}. ${articleUrl}`);
    });
} finally {
    if (browser) {
        await browser.close();
    }
}
