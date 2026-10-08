# Task Status

## Task checklist

- [x] ANTARA list/detail scraping and article import implementation
- [x] Implementasi command Telegram `/jateng` dan `/jogja` untuk import berita wilayah
- [ ] Deploy perubahan dan perbarui webhook Telegram agar menerima `message`; verifikasi langsung kedua command
- [x] AI fact extraction (10 stored articles processed individually and evidence validated)
- [x] AI rewrite (10 drafts processed individually; style rules remain user-editable)
- [x] Telegram draft delivery and approval/rejection webhook implementation
- [x] Telegram approval end-to-end (Approve callback changed article #1 to `approved` on 2026-10-03)
- [x] WordPress publishing implementation for approved articles
- [x] WordPress end-to-end publishing verification (article #1 published as WordPress post 19313; readback returned status `publish`)
- [x] SEO metadata publishing (Yoast focus keyphrase, tags, category, fixed author, and featured image)
- [x] Scheduler entries for fact extraction, rewrite, Telegram draft delivery, and approved-only WordPress publishing
- [x] Scheduler runtime and host cron setup
- [x] Database queue job dispatch for scheduled workflow commands
- [x] Queue worker runtime setup
- [x] Deduplication improvement (whitespace-normalized content hashes implemented and existing hashes migrated)
- [x] Validation for imported ANTARA records and Gemini output structure/evidence
- [x] Production deployment

## Completed: AI rewrite

- Rewrites use validated facts and the source article as context.
- One article is processed per `php artisan ai:rewrite` invocation.
- Draft title/content are stored in `rewritten_title` and `rewritten_content` with status `drafted`.
- Ten stored articles were rewritten individually using Gemini 3.5 Flash-Lite.
- The command reports no articles waiting for rewrite.
- Custom editorial rules can be added to `docs/ai-rewrite-rules.md` and will be included in future rewrites.
- Feature tests pass: `php artisan test --filter='AIServiceTest|RewriteArticleCommandTest|ExtractArticleFactsCommandTest'`.

## Telegram approval

- [x] The send-draft command and callback webhook are implemented.
- End-to-end approval is verified: clicking Approve changed article #1 from `waiting_approval` to `approved` on 2026-10-03.
- The callback was delivered through a temporary Cloudflare HTTPS tunnel. Telegram later reported HTTP 530; the temporary server, proxy, and tunnel have been stopped. Configure and register a stable production HTTPS endpoint before relying on future approvals.
- Telegram API calls from the queue worker failed with a network connection error in this environment, so no new draft was sent.
- The bot token appeared in a local error record and tool output during diagnostics. The database record was scrubbed and local logs contain no bot URL. The token in `.env` was confirmed valid; rotate it before production use because it was exposed.

- Drafts can be sent one at a time with `php artisan telegram:send-draft [article_id]`.
- Telegram messages include Approve and Reject buttons.
- A secret-validated webhook updates only articles in `waiting_approval` to `approved` or `rejected`.
- Telegram credentials and webhook secret are read from environment configuration.
- Webhook registration is available with `php artisan telegram:set-webhook [https://public-host/telegram/webhook]`.

## Completed: WordPress publishing implementation

- `php artisan wordpress:publish [article_id]` publishes one approved article at a time.
- Articles outside `approved` status and articles with an existing `wordpress_post_id` are skipped.
- Successful responses save `wordpress_post_id`, `published_at`, and `published` status.
- API errors are saved to `error_message`; the article remains approved for review/retry. If the local featured image is missing, publishing attempts to restore it from the saved ANTARA image URL. A failed download or upload leaves the article approved for another scheduled retry instead of cancelling it.
- WordPress URL, username, and application password are configured through environment variables.
- Feature tests pass: `php artisan test --filter=PublishWordPressArticleTest`.
- WordPress REST authentication was verified with a read-only request (HTTP 200).
- Live publishing is verified for article #1: WordPress post 19313 was created, `wordpress_post_id` and `published_at` were persisted, and a readback returned status `publish`.

## Next

Deploy perubahan regional command dan daftarkan ulang webhook Telegram agar update `message` diterima.

## Rencana: pengambilan berita berdasarkan wilayah melalui Telegram

Status: implementasi lokal selesai; deployment dan uji command langsung melalui Telegram belum diverifikasi.

- `/jateng` dan `/jogja` dikenali pada webhook hanya dari chat Telegram yang dikonfigurasi. Keduanya langsung memberi konfirmasi, lalu menjalankan import pada queue `scheduled`.
- Import memperbarui sesi ANTARA, mengambil berita tanggal hari ini, mencocokkan isi judul/berita dengan daftar wilayah, dan mengikuti pagination sampai mendapat 10 kecocokan atau semua halaman habis.
- Import memakai deduplikasi ANTARA yang sudah ada. Hasil Telegram mencantumkan jumlah artikel baru, duplikat, halaman yang diperiksa, dan jumlah kecocokan; bila kurang dari 10, bot memberi tahu bahwa halaman sumber telah habis.
- `telegram:set-webhook` kini meminta update `message` dan `callback_query`, serta mendaftarkan `/jateng` dan `/jogja` ke menu command bot. Jalankan ulang command tersebut setelah kode deploy agar trigger aktif pada bot yang berjalan.
- Artikel yang baru masuk mengikuti pipeline ekstraksi fakta, rewrite, dan approval Telegram yang sudah berjalan.

### Wilayah acuan Jawa Tengah

29 kabupaten: Banjarnegara, Banyumas, Batang, Blora, Boyolali, Brebes, Cilacap, Demak, Grobogan, Jepara, Karanganyar, Kebumen, Kendal, Klaten, Kudus, Magelang, Pati, Pekalongan, Pemalang, Purbalingga, Purworejo, Rembang, Semarang, Sragen, Sukoharjo, Tegal, Temanggung, Wonogiri, Wonosobo.

6 kota: Magelang, Pekalongan, Salatiga, Semarang, Surakarta (Solo), Tegal.

### Wilayah acuan DI Yogyakarta

4 kabupaten: Bantul, Gunungkidul, Kulon Progo, Sleman.

1 kota: Yogyakarta.

Daftar kabupaten/kota Jawa Tengah merujuk [direktori Pemerintah Provinsi Jawa Tengah](https://jatengprov.go.id/website-kab-kota/); jumlah 29 kabupaten dan 6 kota juga dinyatakan oleh [Pemerintah Provinsi Jawa Tengah](https://visitjawatengah.jatengprov.go.id/about-us/id). DIY memiliki 4 kabupaten dan 1 kota menurut [dokumen JDIH Pemerintah DIY](https://jdih.jogjaprov.go.id/upload/2024/pdf/Tahapan%20Perda/2024/Perda4-2024/02.Penjelasan%20atau%20Keterangan%20Raperda%20Perubahan%20Kedua%20Atas%20Perda%20Hak%20Keuangan.pdf).

## WordPress SEO metadata

- Rewrite output now includes a focus keyword, tags, and category and persists them on the article record.
- Telegram approval previews include the SEO metadata.
- WordPress publishing resolves or creates taxonomy terms, assigns author `Habib Al Bay Haqqi`, uploads the source image as featured media, and publishes the post.
- Install and activate `wordpress/antara-yoast-rest-bridge.zip` as a regular WordPress plugin so the REST API accepts and returns Yoast's `_yoast_wpseo_focuskw` field.
- Run the new Laravel migration on the deployment and verify one complete article before marking this task done.

## Scheduler progress

- [x] Laravel schedules fact extraction, rewrite, Telegram draft delivery, and WordPress publishing once per minute through unique database queue jobs. Each command still processes at most one article per invocation.
- [ ] Run the scheduler continuously with the host scheduler (for example, `php artisan schedule:work` during development or the Laravel scheduler cron entry in production); runtime and cron setup have not been verified.
- [ ] Run a queue worker continuously with `php artisan queue:work database --queue=scheduled`; runtime has not been verified.
- [x] ANTARA import is scheduled every 4 hours (00:00, 04:00, 08:00, 12:00, 16:00, 20:00 WIB) and refreshes its session immediately before scraping, using credentials from `.env` in headless Playwright.
- [ ] Verify scheduled ANTARA login/import on a network-enabled host with configured credentials.
