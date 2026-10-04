import { chromium } from "playwright";

const date = process.argv[2];

if (!/^\d{4}-\d{2}-\d{2}$/.test(date ?? "")) {
    throw new Error("Tanggal harus menggunakan format YYYY-MM-DD.");
}

let browser;

try {
    browser = await chromium.launch({ headless: true });

    const context = await browser.newContext({
        storageState: "antara-session.json",
    });
    const page = await context.newPage();

    await page.goto(
        `https://branda.antaranews.com/data/index.php?date=${encodeURIComponent(date)}`,
        { waitUntil: "domcontentloaded", timeout: 60000 },
    );
    await page.waitForLoadState("networkidle", { timeout: 10000 }).catch(() => {});

    const articleUrls = await page.locator("a[href]").evaluateAll((anchors) => {
        const urls = new Set();

        for (const anchor of anchors) {
            const href = anchor.getAttribute("href");

            if (!href) continue;

            try {
                const articleUrl = new URL(href, window.location.href);

                if (
                    articleUrl.pathname.endsWith("/content.php") &&
                    articleUrl.searchParams.has("id")
                ) {
                    urls.add(articleUrl.href);
                }
            } catch {
                // Abaikan href yang bukan URL valid.
            }
        }

        return [...urls];
    });

    const articles = [];

    for (const url of articleUrls) {
        await page.goto(url, {
            waitUntil: "domcontentloaded",
            timeout: 60000,
        });

        const article = await page.evaluate(() => {
            const currentUrl = new URL(window.location.href);
            const title = document.querySelector("h2.titletext")?.innerText.trim() ?? "";
            const dateText = document
                .querySelector(".request-info li")
                ?.innerText.replace("Tanggal:", "")
                .trim() ?? "";
            const contentElement = document.querySelector("#newbody");
            const paragraphs = [...(contentElement?.querySelectorAll("p") ?? [])]
                .map((paragraph) => paragraph.innerText.trim())
                .filter(Boolean);
            const content = paragraphs.length
                ? paragraphs.join("\n\n")
                : contentElement?.innerText.trim() ?? "";
            const imageSrc = document.querySelector(".boxcontentfoto > a > img")?.getAttribute("src");

            return {
                source_id: currentUrl.searchParams.get("id") ?? "",
                source_url: currentUrl.href,
                source_title: title,
                source_published_at: dateText,
                source_content: content,
                image_url: imageSrc ? new URL(imageSrc, currentUrl.href).href : null,
            };
        });

        if (!article.source_id || !article.source_title || !article.source_content) {
            throw new Error(`Data artikel tidak lengkap dari ${url}`);
        }

        articles.push(article);
    }

    process.stdout.write(JSON.stringify(articles));
} finally {
    if (browser) await browser.close();
}
