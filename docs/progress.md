# Project Progress

## Completed

-   [x] Laravel project
-   [x] MySQL connection
-   [x] Articles migration
-   [x] Article model
-   [x] Playwright installed
-   [x] Chromium installed
-   [x] ANTARA session berhasil
-   [x] ANTARA article berhasil dibuka
-   [x] Scraping title berhasil
-   [x] Scraping date berhasil
-   [x] Scraping content berhasil
-   [x] Scraping image URL berhasil
-   [x] Scraping daftar artikel ANTARA
-   [x] Simpan artikel ANTARA ke database
-   [x] AI fact extraction (10 artikel diproses satu per satu; bukti divalidasi)
-   [x] AI rewrite (10 draft dibuat satu per satu; menunggu aturan gaya khusus opsional)

## Current

-   [x] Telegram approval end-to-end (artikel #1 berubah menjadi approved melalui callback Approve pada 3 Oktober 2026)
-   [x] WordPress publishing implementation (approved articles only; feature tests pass)
-   [x] WordPress end-to-end publishing verification (artikel #1 terbit sebagai post 19313; pembacaan ulang mengembalikan status publish)

## Next

-   [ ] Scheduler (scheduled entries configured, including daily ANTARA import with session refresh; continuous runtime and host cron setup pending)
-   [ ] Queue (scheduled command jobs implemented; worker runtime pending)
-   [x] Deduplication improvement (content hashes ignore whitespace-only variations; normalization migration applied)
-   [x] Validation (import payloads and Gemini facts/rewrites are structurally checked)
-   [ ] Production deployment (approval and publishing verified; stable webhook host, scheduler, and queue worker remain)
