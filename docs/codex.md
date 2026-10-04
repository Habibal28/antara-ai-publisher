# Codex Instructions

## Project

Project ini adalah sistem otomatisasi publikasi berita:

ANTARA → AI → Telegram Approval → WordPress

Sistem dikembangkan sebagai MVP terlebih dahulu, kemudian dikembangkan
secara bertahap ke versi berikutnya.

---

# 1. WAJIB BACA KONTEKS

Sebelum melakukan perubahan apa pun, baca:

1. `PROJECT.md`
2. `docs/ARCHITECTURE.md`
3. `docs/PROGRESS.md`
4. `docs/TASK.md`

Jika `TASK.md` bertentangan dengan architecture,
ikuti `ARCHITECTURE.md` dan jangan melakukan perubahan besar
tanpa menjelaskan konflik tersebut.

---

# 2. ATURAN UTAMA

-   Fokus hanya pada task yang ada di `TASK.md`.
-   Jangan membuat fitur di luar task.
-   Jangan mengubah architecture tanpa alasan yang jelas.
-   Jangan menambahkan dependency jika tidak diperlukan.
-   Jangan mengganti framework atau stack.
-   Jangan menghapus kode yang sudah bekerja.
-   Jangan melakukan refactor besar jika tidak diperlukan.
-   Gunakan solusi sederhana untuk MVP.
-   Jangan over-engineering.

---

# 3. STACK

Backend:

-   Laravel
-   PHP
-   MySQL

Automation:

-   Node.js
-   Playwright

AI:

-   Google Gemini API

Notification:

-   Telegram Bot

Publishing:

-   WordPress REST API

---

# 4. ARSITECTURE PRINCIPLE

Laravel adalah orchestrator utama.

Workflow utama:

ANTARA
→ ingestion
→ deduplication
→ database
→ AI processing
→ Telegram approval
→ WordPress publishing

Workflow MVP harus deterministic.

Jangan membuat AI Agent untuk workflow yang sudah dapat
ditentukan secara jelas oleh program.

Belum menggunakan:

-   Vector Database
-   RAG
-   Agent
-   MCP
-   Multi-agent

Teknologi tersebut hanya digunakan jika memang diperlukan
pada versi berikutnya.

---

# 5. ANTARA

ANTARA menggunakan halaman subscription yang membutuhkan authentication.

Playwright digunakan untuk browser automation.

Session yang sudah berhasil dibuat:

`antara-session.json`

Jangan meminta user memberikan:

-   username
-   password
-   cookie
-   session credential

Credential harus menggunakan environment variable jika
authentication otomatis diperlukan.

`antara-session.json` tidak boleh masuk Git.

---

# 6. SCRAPING RULE

Jangan mengarang selector.

Selector harus berdasarkan HTML ANTARA yang benar-benar
sudah ditemukan atau hasil inspeksi browser.

Article page yang sudah berhasil:

-   title: `h2.titletext`
-   article container: `#newbody`
-   article paragraphs: `#newbody > p`
-   date berada di `.request-info`
-   source ID berasal dari query parameter `id`
-   image URL sudah berhasil diambil dari halaman artikel

Scraper artikel sudah berhasil.

Jangan membuat ulang scraper artikel kecuali diperlukan
untuk memperbaiki bug.

---

# 7. LARAVEL RULE

Gunakan:

-   Eloquent Model
-   Service untuk external integration
-   Job untuk proses asynchronous jika diperlukan
-   Scheduler untuk proses terjadwal

Hindari:

-   logic besar di Controller
-   raw SQL jika Eloquent sudah cukup
-   giant service yang menangani semua integration

Integration dipisahkan berdasarkan tanggung jawab.

Contoh:

-   `AntaraService`
-   `AIService`
-   `TelegramService`
-   `WordPressService`

---

# 8. DATABASE

Model utama:

`Article`

Deduplication MVP menggunakan:

1. source + source_id
2. source + source_url_hash
3. content_hash

Jangan menggunakan semantic similarity untuk MVP.

Semantic deduplication dapat ditambahkan pada versi berikutnya.

---

# 9. SECURITY

Credential/API key tidak boleh hardcoded.

Gunakan `.env`.

Jangan menampilkan:

-   password
-   API key
-   bot token
-   session credential

di output atau commit.

Jangan commit:

-   `.env`
-   `antara-session.json`
-   `node_modules/`

---

# 10. TESTING

Setiap perubahan harus diuji.

Jika task berhubungan dengan Playwright:

jalankan script Playwright yang relevan.

Jika task berhubungan dengan Laravel:

gunakan command Laravel yang relevan.

Jangan mengatakan "berhasil" jika belum diuji.

Jika test gagal:

1. tampilkan error sebenarnya
2. analisis penyebab
3. lakukan perbaikan minimal
4. jalankan test kembali

---

# 11. DEVELOPMENT STYLE

Prioritaskan:

-   readable code
-   simple code
-   maintainable code
-   Laravel convention
-   separation of concerns

Jangan membuat abstraksi hanya untuk terlihat kompleks.

---

# 12. TASK DISCIPLINE

`TASK.md` adalah task aktif.

Kerjakan task tersebut saja.

Jika task sudah selesai:

1. test
2. laporkan file yang berubah
3. laporkan hasil test
4. update `docs/PROGRESS.md` jika diminta atau jika perubahan
   memang menandai progress project

Jangan melanjutkan otomatis ke task berikutnya.

---

# 13. RESPONSE FORMAT

Setelah coding selesai, berikan:

## Changed

Daftar file yang diubah.

## What Changed

Penjelasan singkat.

## Test

Command yang dijalankan.

## Result

Hasil test.

## Next

Task berikutnya hanya disebutkan sebagai informasi.
Jangan mengerjakannya otomatis.
