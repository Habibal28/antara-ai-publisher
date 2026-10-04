# ANTARA News Automation

## 1. Project Overview

Project ini bertujuan membuat sistem otomatisasi publikasi berita
dari ANTARA ke website WordPress.

Workflow:

ANTARA
→ Fetch
→ Deduplication
→ Store
→ AI Rewrite
→ Telegram Draft
→ User Approval
→ WordPress
→ Published

User tetap menjadi pihak yang memberikan approval sebelum artikel
dipublikasikan.

---

# 2. Tujuan MVP

MVP harus mampu:

1. Mengambil daftar berita ANTARA.
2. Mengambil detail artikel.
3. Mengambil gambar artikel.
4. Mendeteksi artikel yang sudah pernah diproses.
5. Menyimpan artikel ke database.
6. Mengirim artikel ke AI Gemini.
7. Menghasilkan draft artikel.
8. Mengirim draft ke Telegram.
9. Menerima approval/rejection.
10. Mempublikasikan artikel yang disetujui ke WordPress.
11. Mencegah double publishing.
12. Mencatat error.

---

# 3. Workflow

## Ingestion

ANTARA
→ Playwright
→ Article URL
→ Article detail
→ Database

## AI

Database
→ Extract Facts
→ Rewrite
→ Validate
→ Database

## Approval

Database
→ Telegram
→ User

User:

APPROVE
atau
REJECT

## Publishing

Approved Article
→ WordPress REST API
→ WordPress Post

---

# 4. Teknologi

## Backend

Laravel

## Database

MySQL

## Browser Automation

Node.js + Playwright

## AI

Google Gemini API

## Messaging

Telegram Bot

## CMS

WordPress REST API

---

# 5. ANTARA

User memiliki akses subscription ANTARA.

Authentication dilakukan menggunakan browser automation.

Session saat development:

`antara-session.json`

Halaman artikel yang sudah berhasil diakses:

`https://branda.antaranews.com/data/content.php?id=...`

Halaman daftar berita:

`https://branda.antaranews.com/data/index.php?date=YYYY-MM-DD`

---

# 6. Data yang Diambil

Dari artikel ANTARA:

-   source ID
-   source URL
-   title
-   published date
-   article content
-   image URL

Data tambahan dapat ditambahkan jika memang diperlukan.

---

# 7. AI

AI menggunakan Google Gemini.

Tahap AI:

1. Fact extraction
2. Rewrite
3. Validation

AI tidak boleh membuat fakta baru.

Jika informasi tidak tersedia dari source,
AI harus mempertahankan informasi yang tersedia
atau memberikan nilai null pada extraction.

---

# 8. Publishing Rule

Artikel hanya boleh dipublikasikan setelah user melakukan approval.

Setelah WordPress berhasil membuat post:

`wordpress_post_id`

harus disimpan ke database.

ID tersebut digunakan untuk mencegah artikel
dipublikasikan dua kali.

---

# 9. Deduplication

MVP menggunakan:

-   source
-   source_id
-   source_url_hash
-   content_hash

Semantic duplicate detection ditunda.

---

# 10. Development Philosophy

MVP harus selesai secepat mungkin dengan architecture
yang sederhana.

Jangan membangun:

-   Agent
-   RAG
-   Vector Database
-   Multi-agent
-   Dashboard kompleks

sebelum workflow dasar selesai.

---

# 11. Target Pengembangan

## V1

Basic automation.

## V2

-   Queue
-   Retry
-   Better deduplication
-   AI validation
-   Better logging
-   Monitoring

## V3

-   Embeddings
-   Semantic deduplication
-   RAG jika diperlukan
-   Multiple sources
-   Evaluation
-   Advanced automation
