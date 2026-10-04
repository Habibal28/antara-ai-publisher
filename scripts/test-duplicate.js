import { chromium } from "playwright";

const browser = await chromium.launch({
    headless: false,
});

const context = await browser.newContext({
    storageState: "antara-session.json",
});

const page = await context.newPage();

await page.goto(
    "https://branda.antaranews.com/data/index.php?date=2026-09-28",
    {
        waitUntil: "domcontentloaded",
    }
);

const links = await page
    .locator('a[href*="content.php?id="]')
    .evaluateAll((elements) =>
        elements.map((el) => ({
            title: el.innerText.trim(),
            url: new URL(el.getAttribute("href"), location.href).href,
        }))
    );

const uniqueLinks = Array.from(
    new Map(links.map((item) => [item.url, item])).values()
);

console.log(`Total URL unik: ${uniqueLinks.length}`);

console.log(uniqueLinks.slice(0, 20));

await browser.close();
