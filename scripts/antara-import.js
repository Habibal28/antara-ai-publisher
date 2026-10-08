import { chromium } from "playwright";

const date = process.argv[2];
const region = process.argv[3] ?? null;
let areas = [];

if (!/^\d{4}-\d{2}-\d{2}$/.test(date ?? "")) {
    throw new Error("Tanggal harus menggunakan format YYYY-MM-DD.");
}

if (region) {
    try {
        areas = JSON.parse(process.argv[4] ?? "[]");
    } catch {
        throw new Error("Daftar wilayah tidak valid.");
    }
    if (!Array.isArray(areas) || areas.length === 0) {
        throw new Error("Daftar wilayah tidak boleh kosong.");
    }
}

const normalize = (value) => value.normalize("NFKC").toLocaleLowerCase("id-ID");

function matchesRegion(article) {
    const text = normalize(`${article.source_title}\n${article.source_content}`);
    return areas.some((area) => {
        const needle = normalize(area);
        let index = text.indexOf(needle);

        while (index !== -1) {
            const before = text[index - 1] ?? "";
            const after = text[index + needle.length] ?? "";
            if (!/[\p{L}\p{N}]/u.test(before) && !/[\p{L}\p{N}]/u.test(after)) return true;
            index = text.indexOf(needle, index + 1);
        }

        return false;
    });
}

async function getListPageInfo(page) {
    return page.locator("a[href]").evaluateAll((anchors) => {
        const articleUrls = new Set();
        const pagerLinks = [];

        for (const anchor of anchors) {
            const href = anchor.getAttribute("href");
            if (!href) continue;

            let url;
            try {
                url = new URL(href, window.location.href);
            } catch {
                continue;
            }

            if (
                url.pathname.endsWith("/content.php") &&
                url.searchParams.has("id")
            ) {
                articleUrls.add(url.href);
                continue;
            }

            if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) continue;
            pagerLinks.push({
                href: url.href,
                text: `${anchor.innerText ?? ""} ${anchor.getAttribute("aria-label") ?? ""} ${anchor.getAttribute("title") ?? ""}`.trim(),
                rel: anchor.getAttribute("rel") ?? "",
                active: Boolean(anchor.closest(".active, .current, [aria-current='page']")),
            });
        }

        const nextLink = pagerLinks.find((link) =>
            /(^|\s)(next|selanjutnya|berikutnya|lanjut)(\s|$)|[›»>]/i.test(link.text) ||
            /(^|\s)next(\s|$)/i.test(link.rel),
        );
        let nextUrl = nextLink?.href ?? null;

        if (!nextUrl) {
            const current = new URL(window.location.href);
            const pageKeys = ["page", "hal", "halaman", "p", "offset"];
            const currentValues = new Map(pageKeys.map((key) => [key, Number(current.searchParams.get(key) ?? 1)]));
            const numericLinks = pagerLinks.flatMap((link) => {
                const url = new URL(link.href);
                for (const key of pageKeys) {
                    const value = Number(url.searchParams.get(key));
                    if (Number.isInteger(value) && value > currentValues.get(key)) return [{ href: link.href, value }];
                }
                return [];
            });
            numericLinks.sort((a, b) => a.value - b.value);
            nextUrl = numericLinks[0]?.href ?? null;
        }

        return { articleUrls: [...articleUrls], nextUrl };
    });
}

async function getArticle(page, url) {
    await page.goto(url, { waitUntil: "domcontentloaded", timeout: 60000 });

    return page.evaluate(() => {
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
        const imageLink = document.querySelector(".iconfto a[href]");
        const imageUrl = imageLink
            ? new URL(imageLink.getAttribute("href"), currentUrl.href).href
            : null;

        return {
            source_id: currentUrl.searchParams.get("id") ?? "",
            source_url: currentUrl.href,
            source_title: title,
            source_published_at: dateText,
            source_content: content,
            image_url: imageUrl,
        };
    });
}

let browser;

try {
    browser = await chromium.launch({ headless: true });

    const context = await browser.newContext({ storageState: "antara-session.json" });
    const page = await context.newPage();
    let listUrl = `https://branda.antaranews.com/data/index.php?date=${encodeURIComponent(date)}`;
    const visitedPages = new Set();
    const visitedArticles = new Set();
    const articles = [];
    let pagesScanned = 0;

    while (listUrl && !visitedPages.has(listUrl) && (!region || articles.length < 10)) {
        visitedPages.add(listUrl);
        await page.goto(listUrl, { waitUntil: "domcontentloaded", timeout: 60000 });
        await page.waitForLoadState("networkidle", { timeout: 10000 }).catch(() => {});
        pagesScanned++;

        const { articleUrls, nextUrl } = await getListPageInfo(page);
        for (const url of articleUrls) {
            if (visitedArticles.has(url)) continue;
            visitedArticles.add(url);

            const article = await getArticle(page, url);
            if (!article.source_id || !article.source_title || !article.source_content) {
                throw new Error(`Data artikel tidak lengkap dari ${url}`);
            }

            if (!region || matchesRegion(article)) articles.push(article);
            if (region && articles.length >= 10) break;
        }

        listUrl = region ? nextUrl : null;
    }

    if (region) {
        process.stdout.write(JSON.stringify({ articles, pages_scanned: pagesScanned }));
    } else {
        process.stdout.write(JSON.stringify(articles));
    }
} finally {
    if (browser) await browser.close();
}
