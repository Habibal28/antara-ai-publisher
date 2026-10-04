import { chromium } from "playwright";

const browser = await chromium.launch({
    headless: false,
});

const context = await browser.newContext({
    storageState: "antara-session.json",
});

const page = await context.newPage();

const url =
    "https://branda.antaranews.com/data/content.php?id=13453440&date=2026-09-28&page=1";

await page.goto(url, {
    waitUntil: "domcontentloaded",
    timeout: 60000,
});

await page.waitForLoadState("networkidle").catch(() => {});

// ===============================
// SOURCE ID
// ===============================

const currentUrl = new URL(page.url());

const sourceId = currentUrl.searchParams.get("id") || "";

// ===============================
// TITLE
// ===============================

const titleLocator = page.locator("h2.titletext");

let title = "";

if (await titleLocator.count()) {
    title = (await titleLocator.first().innerText()).trim();
}

// ===============================
// DATE
// ===============================

const dateLocator = page.locator(".request-info li").filter({
    hasText: "Tanggal:",
});

let dateText = "";

if (await dateLocator.count()) {
    dateText = (await dateLocator.first().innerText())
        .replace("Tanggal:", "")
        .trim();
}

// ===============================
// CONTENT
// ===============================

let content = "";

const paragraphLocator = page.locator("#newbody p");

const paragraphCount = await paragraphLocator.count();

if (paragraphCount > 0) {
    const paragraphs = await paragraphLocator.allInnerTexts();

    content = paragraphs
        .map((text) => text.trim())
        .filter((text) => text.length > 0)
        .join("\n\n");
} else {
    // fallback kalau isi artikel tidak pakai <p>
    const bodyLocator = page.locator("#newbody");

    if (await bodyLocator.count()) {
        content = (await bodyLocator.first().innerText()).trim();
    }
}

// ===============================
// FOTO PREVIEW
// ===============================

let imageUrl = "";

const imageLocator = page.locator(".boxcontentfoto > a > img").first();

if (await imageLocator.count()) {
    const src = await imageLocator.getAttribute("src");

    if (src) {
        imageUrl = new URL(src, page.url()).href;
    }
}

// ===============================
// LINK DOWNLOAD FOTO
// ===============================

let contentPhotoUrl = "";
let photoId = "";

const contentPhotoLocator = page
    .locator('.boxcontentfoto a[href*="content_photo.php?idphoto="]')
    .first();

if (await contentPhotoLocator.count()) {
    const href = await contentPhotoLocator.getAttribute("href");

    if (href) {
        contentPhotoUrl = new URL(href, page.url()).href;
        photoId = new URL(contentPhotoUrl).searchParams.get("idphoto") || "";
    }
}

// ===============================
// RESULT
// ===============================

const result = {
    sourceId,
    title,
    date: dateText,
    content,
    photoId,
    contentPhotoUrl,
    imageUrl,
};

// ===============================
// OUTPUT
// ===============================

console.log("\n===== HASIL SCRAPING =====\n");

console.log(JSON.stringify(result, null, 2));

// ===============================
// DEBUG OPTIONAL
// ===============================

console.log("\n===== DEBUG =====");

console.log("Jumlah paragraf:", paragraphCount);

console.log("Foto preview ditemukan:", imageUrl ? "YA" : "TIDAK");

console.log("Halaman foto ditemukan:", contentPhotoUrl ? "YA" : "TIDAK");

// ===============================
// CLOSE
// ===============================

await browser.close();
