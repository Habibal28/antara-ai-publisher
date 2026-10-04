# Panduan Alur dan Operasional ANTARA AI Publisher

Dokumen ini menjelaskan bagaimana berita bergerak dari ANTARA sampai terbit di WordPress, perintah manual yang tersedia, serta komponen server yang menjalankan proses otomatis.

## Ringkasan alur

```text
ANTARA
  -> antara:import
  -> database (pending)
  -> ai:extract-facts (processing)
  -> ai:rewrite (drafted)
  -> telegram:send-draft (waiting_approval)
  -> klik Approve di Telegram (approved)
  -> wordpress:publish (published)
```

Persetujuan Telegram adalah gerbang sebelum WordPress. Perintah publish memilih artikel berstatus `approved` saja.

## Pengambilan berita: berapa banyak dan lewat proses apa?

`php artisan antara:import` memakai tanggal hari ini menurut zona waktu `Asia/Jakarta`; tanggal juga bisa diberikan secara eksplisit. Proses menjalankan `node scripts/antara-login.js` untuk memperbarui sesi Playwright, lalu `node scripts/antara-import.js TANGGAL` untuk membaca daftar artikel ANTARA pada tanggal itu dan membuka URL artikelnya satu per satu.

Tidak ada batas tetap 10 artikel. Jumlah yang ditemukan bergantung pada isi halaman ANTARA untuk tanggal tersebut. Sistem mengimpor artikel unik dan melewati yang sudah ada berdasarkan identitas sumber, hash URL, dan hash konten yang dinormalisasi. Ringkasan jumlah masuk dan dilewati ditampilkan oleh proses import.

Sesi ANTARA dapat kedaluwarsa. Import otomatis menjalankan langkah login terlebih dahulu; jika login atau scraping gagal, periksa konfigurasi Playwright dan sesi, lalu jalankan ulang import.

## Tahapan artikel dan perintahnya

| Tahap                   | Perintah                                 | Yang dilakukan                                                        | Status utama               |
| ----------------------- | ---------------------------------------- | --------------------------------------------------------------------- | -------------------------- |
| Import                  | `php artisan antara:import [YYYY-MM-DD]` | Mengambil daftar dan detail berita ANTARA lalu menyimpan artikel baru | `pending`                  |
| Ekstrak fakta           | `php artisan ai:extract-facts [ID]`      | Menghasilkan data fakta dari satu artikel                             | `processing`               |
| Tulis ulang             | `php artisan ai:rewrite [ID]`            | Membuat judul, isi, focus keyword, tags, dan kategori berdasarkan fakta serta aturan gaya | `drafted`                  |
| Kirim untuk persetujuan | `php artisan telegram:send-draft [ID]`   | Mengirim draft dengan tombol Approve dan Reject                       | `waiting_approval`         |
| Persetujuan             | Klik tombol pada Telegram                | Webhook menerima callback dan mencatat keputusan                      | `approved` atau `rejected` |
| Terbitkan               | `php artisan wordpress:publish [ID]`     | Menerbitkan artikel yang sudah approved dan menyimpan ID post         | `published`                |

Untuk tahap AI, Telegram, dan WordPress, satu pemanggilan tanpa ID mengambil paling banyak satu artikel yang memenuhi syarat. Ulangi perintah untuk memproses artikel berikutnya. Jika memberi ID, artikel itu harus berada pada tahap yang sesuai. Periksa aturan gaya di [`ai-rewrite-rules.md`](ai-rewrite-rules.md) sebelum mengubah gaya tulisan.

Status `failed` berarti tahap sebelumnya mengalami masalah. Lihat pesan error artikel dan log aplikasi sebelum mencoba ulang. Hindari membagikan log mentah tanpa memeriksa apakah ada informasi rahasia.

Saat import, URL gambar langsung dari ANTARA diunduh ke `storage/app/private/antara-images` (disk `local`) dan lokasi file disimpan pada kolom `articles.image_path`. Pengiriman Telegram dan unggahan featured image WordPress menggunakan file lokal ini. File gambar tidak masuk Git dan tidak ikut `push`/`pull`; setiap mesin harus mengimpornya sendiri, dan direktori `storage` harus persisten saat deployment. Field **Penulis Berita** pada WordPress memakai nilai `WORDPRESS_AUTHOR_NAME` dari `.env`. Rewrite juga menghasilkan meta description untuk Yoast.

## Menjalankan satu artikel secara manual

Ganti tanggal dan ID dengan nilai yang sesuai. Untuk import hari ini, argumen tanggal boleh dihilangkan.

```bash
php artisan antara:import 2026-10-04
php artisan ai:extract-facts ID
php artisan ai:rewrite ID
php artisan telegram:send-draft ID
```

Setelah draft dikirim, buka Telegram dan tekan **Approve**. Tunggu webhook mencatat status `approved`, kemudian jalankan:

```bash
php artisan wordpress:publish ID
```

Publish membuat tulisan terlihat di WordPress. Alur juga mengunggah gambar sumber sebagai featured image, memasang author yang dikonfigurasi, dan mengirim Yoast focus keyphrase. Pasang dan aktifkan `wordpress/antara-yoast-rest-bridge.zip` dari Dashboard WordPress sebelum menggunakan fitur ini. Jalankan hanya setelah memeriksa draft dan memastikan persetujuan sudah tercatat.

Perintah tahap dapat dijalankan langsung; tidak perlu menjalankan scheduler atau queue worker untuk satu pemanggilan Artisan manual. `schedule:run` memeriksa jadwal dan memasukkan pekerjaan terjadwal ke antrean, jadi bukan pengganti perintah tahap ketika ingin memproses ID tertentu.

## Cara kerja otomatis

Jadwal aplikasi ada di `routes/console.php`:

| Jadwal                      | Perintah yang dimasukkan ke antrean |
| --------------------------- | ----------------------------------- |
| Setiap menit                | `ai:extract-facts`                  |
| Setiap menit                | `ai:rewrite`                        |
| Setiap menit                | `telegram:send-draft`               |
| Setiap menit                | `wordpress:publish`                 |
| Setiap hari pukul 06.00 WIB | `antara:import`                     |

Scheduler memasukkan job ke antrean `scheduled`. `RunScheduledCommand` menjalankan perintah Artisan terkait; setiap perintah memilih paling banyak satu artikel pada satu kali jalan. Dengan demikian beberapa artikel diproses bertahap lewat pemeriksaan berulang, bukan semuanya dalam satu pemanggilan.

Di VPS, otomatisasi memerlukan tiga bagian yang aktif:

1. Cron sistem yang menjalankan `php artisan schedule:run` setiap menit.
2. Queue worker yang terus memproses antrean `scheduled`, umumnya dijaga Supervisor.
3. Web server/PHP-FPM yang melayani aplikasi dan endpoint webhook Telegram melalui HTTPS.

Jika salah satu tidak aktif, jadwal, antrean, atau callback Telegram bisa tertunda. Verifikasi konfigurasi pada VPS sebelum mengandalkan pemrosesan otomatis.

## Telegram dan webhook

Bot mengirim draft ke chat yang dikonfigurasi beserta tombol keputusan. Telegram mengirim callback tombol ke `POST /telegram/webhook`. Aplikasi memeriksa secret webhook dan chat ID sebelum mengubah status artikel. Untuk alamat situs `https://ainews.moori.my.id`, endpoint publiknya adalah `https://ainews.moori.my.id/telegram/webhook`.

Jika callback tidak mengubah status, periksa bahwa webhook Telegram menunjuk ke URL HTTPS publik yang benar, secret sesuai, chat ID di konfigurasi benar, dan aplikasi dapat menerima request. Mengirim pesan draft saja belum membuktikan tombol persetujuan berfungsi.

Jika artikel tidak memiliki `image_path` (misalnya artikel dibuat sebelum alur unduh gambar diterapkan), jalankan ulang import pada server untuk tanggal sumber artikel. Import akan mengisi file gambar untuk artikel duplikat yang belum memilikinya. Periksa kolom `image_path` dan keberadaan file pada disk `local` sebelum mengirim draft.

Untuk memperbarui meta description dan field **Penulis Berita** pada post WordPress yang sudah ada, pasang bridge REST terbaru, lalu jalankan `php artisan wordpress:sync-author ID_ARTIKEL`. Jika artikel lama belum mempunyai meta description hasil rewrite, perintah ini membuat ringkasan dari isi rewrite yang tersimpan.

## Setup dan deployment server

Setiap deployment server perlu memenuhi hal berikut:

1. Siapkan `.env` server sendiri: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, database, `APP_URL` HTTPS, `QUEUE_CONNECTION=database`, `ANTARA_USERNAME`, `ANTARA_PASSWORD`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`, `TELEGRAM_WEBHOOK_SECRET`, `GEMINI_API_KEY`, serta kredensial WordPress. Jangan menyalin `.env` lokal atau memasukkan rahasia ke Git.
2. Pasang versi PHP dan ekstensi yang disyaratkan `composer.json`, lalu `composer install --no-dev --optimize-autoloader`. Pasang Node.js, jalankan `npm ci`, lalu `npx playwright install --with-deps chromium` pada Linux (perintah terakhir memerlukan hak instalasi paket OS). Pastikan PHP/CLI server memiliki akses internet keluar ke ANTARA, `img.antaranews.com`, Gemini, Telegram, dan WordPress.
3. Jalankan `php artisan migrate --force` pada server. Pastikan migration `image_path` tercatat di tabel `migrations`.
4. Pastikan `storage` dan `bootstrap/cache` dapat ditulis oleh user PHP/worker. Pertahankan `storage` lintas deployment/release; jangan menghapus `storage/app/private/antara-images` saat mengganti kode.
5. Bersihkan cache konfigurasi setelah mengubah `.env`: `php artisan optimize:clear`, lalu `php artisan config:cache`.
6. Jalankan satu import manual di server, misalnya `php artisan antara:import YYYY-MM-DD`. Verifikasi jumlah artikel, nilai `image_path`, dan file di `storage/app/private/antara-images`. Untuk artikel yang sudah ada, import ulang tanggal sumbernya agar file gambarnya diunduh.
7. Daftarkan webhook Telegram ke URL HTTPS publik: `php artisan telegram:set-webhook https://DOMAIN/telegram/webhook`. Uji satu artikel dengan `php artisan telegram:send-draft ID`, pastikan foto dan pesan approval masuk, lalu tekan Approve dan pastikan status berubah. Setelah disetujui, uji `php artisan wordpress:publish ID` dan pastikan media featured tampil.
8. Setelah uji manual berhasil, pasang cron scheduler (`* * * * * cd /PATH/KE/APP && php artisan schedule:run >> /dev/null 2>&1`) dan jalankan worker database terus-menerus di bawah Supervisor/systemd dengan perintah `php artisan queue:work database --queue=scheduled --sleep=3 --tries=1 --timeout=900`. Saat deployment kode baru, jalankan `php artisan queue:restart` agar worker memuat kode terbaru.

Jika memakai deployment berbasis folder release, arahkan `storage` tiap release ke direktori persistent yang sama. Jika worker dan web berjalan pada mesin berbeda, keduanya harus berbagi disk file yang sama atau file gambar harus disalin ke storage bersama; database hanya menyimpan path, bukan isi file.

## Memeriksa status dan masalah

Perintah pemeriksaan jadwal:

```bash
php artisan schedule:list
```

Di VPS, periksa juga status Supervisor untuk queue worker, entri cron scheduler, serta antrean job gagal:

```bash
php artisan queue:failed
```

Untuk melihat status artikel di database, jalankan query berikut melalui klien MySQL:

```sql
SELECT id, status, telegram_message_id, wordpress_post_id, approved_at, published_at
FROM articles
ORDER BY id;
```

Kolom `status` menunjukkan tahap terkini. `telegram_message_id` membantu menelusuri pesan draft; `wordpress_post_id` dan `published_at` menunjukkan hasil publikasi.

## Urutan diagnosis singkat

1. Pastikan artikel tersimpan dan lihat `status`-nya.
2. Jalankan perintah untuk tahap yang sesuai dengan status tersebut.
3. Jika status menjadi `failed`, baca pesan error dan log aplikasi.
4. Untuk tombol Telegram, cek webhook HTTPS dan pastikan callback mengubah status menjadi `approved` atau `rejected`.
5. Untuk publikasi, pastikan status `approved`, kredensial WordPress tersedia, lalu jalankan `wordpress:publish`.
6. Untuk operasi otomatis, pastikan cron, queue worker, dan web server aktif.
