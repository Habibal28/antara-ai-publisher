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
