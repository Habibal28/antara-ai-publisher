# Panduan Integrasi WordPress dan Yoast SEO

Panduan ini memasang dukungan agar ANTARA AI Publisher dapat mengirim artikel lengkap ke WordPress: kategori, tags, penulis, gambar andalan, dan focus keyphrase Yoast. WordPress berada di hosting terpisah, sehingga bridge dipasang dari Dashboard WordPress atau File Manager hosting—bukan dari terminal VPS aplikasi Laravel.

## 1. Cara kerjanya

Saat artikel ditulis ulang, Gemini menghasilkan judul, isi, meta description, focus keyword, tags, dan kategori. Laravel menyimpan hasilnya di database agar tetap tersedia ketika artikel menunggu persetujuan atau publikasi dicoba ulang.

Setelah artikel disetujui, Laravel akan mencari atau membuat kategori dan tags, mencari penulis **Habib Al Bay Haqqi**, mengunggah gambar sumber ke Media Library, lalu membuat post sebagai draft. Laravel memeriksa bahwa focus keyphrase tersimpan di Yoast sebelum mengubah status post menjadi `publish`.

Yoast menyediakan REST API untuk membaca metadata SEO, tetapi API tersebut tidak menerima perubahan metadata melalui POST atau PUT. Karena itu, bridge mendaftarkan field focus keyphrase dan meta description Yoast agar dapat ditulis melalui WordPress REST API. Bridge versi 1.3.1 juga mendaftarkan meta `MAJPRO_Writer` untuk mengisi field **Penulis Berita** pada metabox WPmedia. Tema memetakan input HTML `writer-value` ke meta `MAJPRO_Writer`; mengirim meta `writer-value` saja tidak mengisi kolom tema. Pemetaan ini diverifikasi dari `inc/class-wpmedia-metabox-settings.php` dalam `wpmedia.zip`. [Dokumentasi REST API Yoast](https://developer.yoast.com/customization/apis/rest-api/) dan [panduan metadata REST WordPress](https://developer.wordpress.org/rest-api/extending-the-rest-api/modifying-responses/) menjelaskan mekanisme metadata REST.

## 2. Prasyarat

- Plugin **Yoast SEO** aktif di situs WordPress.
- Akun WordPress Administrator untuk memasang plugin bridge.
- Akun API WordPress dapat mengedit dan menerbitkan post, mengunggah media, mengelola tags/kategori, menetapkan author, serta mengedit metadata lanjutan Yoast.
- VPS Laravel dapat mengakses REST API situs WordPress melalui HTTPS.

## 3. Pasang plugin bridge dari Dashboard WordPress

Paket ZIP siap unggah ada di `wordpress/antara-yoast-rest-bridge.zip` dalam repositori Laravel.

1. Unduh `antara-yoast-rest-bridge.zip` ke komputer Anda. Jika kode berada di GitHub, buka folder `wordpress/`, pilih ZIP tersebut, lalu pilih **Download**.
2. Masuk ke Dashboard WordPress dengan akun Administrator.
3. Buka **Plugins/Plugin → Add New Plugin/Tambah Plugin Baru**.
4. Klik **Upload Plugin/Upload Plugin** di bagian atas halaman.
5. Klik **Choose File/Pilih File**, pilih `antara-yoast-rest-bridge.zip`, lalu klik **Install Now/Instal Sekarang**.
6. Setelah instalasi berhasil, klik **Activate Plugin/Aktifkan Plugin**.
7. Buka daftar **Plugins/Plugin**. Pastikan `ANTARA AI Publisher - Yoast REST Bridge` dan `Yoast SEO` berstatus aktif.

Bridge adalah plugin WordPress biasa. Tidak perlu membuka terminal VPS untuk memasangnya. Jika menu upload tidak tersedia atau hosting menolak pemasangan plugin, lanjutkan dengan File Manager.

### Alternatif: pasang melalui File Manager hosting

1. Buka File Manager dari panel hosting, misalnya cPanel atau hPanel.
2. Temukan folder instalasi WordPress. Folder tersebut berisi `wp-config.php`.
3. Buka `wp-content/plugins/`.
4. Unggah file ZIP `antara-yoast-rest-bridge.zip` ke folder `plugins`.
5. Pilih ZIP itu dan klik **Extract/Unzip**.
6. Pastikan hasil ekstrak memiliki susunan tepat seperti ini:

   ```text
   wp-content/
   └── plugins/
       └── antara-yoast-rest-bridge/
           └── antara-yoast-rest-bridge.php
   ```

7. Kembali ke Dashboard WordPress → **Plugins/Plugin**, lalu aktifkan bridge.

Jika folder hasil ekstrak berlapis ganda, pindahkan folder dalam agar file PHP berada tepat pada lokasi di atas.

## 4. Siapkan akun API WordPress

1. Di Dashboard, buka **Users/Pengguna**, lalu pilih akun yang akan digunakan Laravel.
2. Pastikan akun dapat menerbitkan post, membuat atau memilih kategori dan tags, mengunggah media, serta menetapkan author.
3. Pada profil pengguna, cari bagian **Application Passwords**.
4. Isi nama seperti `ANTARA AI Publisher`, lalu klik **Add New Application Password/Buat Application Password**.
5. Simpan password yang muncul saat itu juga ke password manager. WordPress biasanya hanya menampilkannya sekali.
6. Jangan kirim application password melalui chat, commit ke Git, atau memasukkannya ke README.
7. Akun API juga memerlukan kemampuan `wpseo_edit_advanced_metadata` agar dapat menulis focus keyphrase. Akun Administrator biasanya memiliki kemampuan yang diperlukan.

Jika bagian Application Passwords tidak tersedia, pastikan situs menggunakan HTTPS dan hosting atau plugin keamanan tidak menonaktifkan fitur itu.

## 5. Atur konfigurasi Laravel di VPS

Di file `.env` aplikasi Laravel pada VPS, isi variabel berikut. Gunakan URL dasar situs WordPress, tanpa `/wp-json` di bagian akhir.

```dotenv
WORDPRESS_URL=https://alamat-wordpress-anda
WORDPRESS_USERNAME=nama-pengguna-api
WORDPRESS_APPLICATION_PASSWORD="application password dari WordPress"
WORDPRESS_AUTHOR_NAME="Habib Al Bay Haqqi"
WORDPRESS_AUTHOR_ID=
```

Biasanya `WORDPRESS_AUTHOR_NAME` cukup untuk mencari penulis. Jika REST API tidak mengizinkan pencarian pengguna, isi `WORDPRESS_AUTHOR_ID` dengan ID angka pengguna tersebut. ID dapat dilihat di URL halaman edit pengguna Dashboard, misalnya `user-edit.php?user_id=123` berarti ID-nya `123`.

Setelah menyimpan `.env`, dari direktori Laravel jalankan:

```bash
php artisan config:clear
php artisan config:cache
```

Application password dapat ditulis dalam tanda kutip jika mengandung spasi. Jangan menampilkan isi `.env` ketika meminta bantuan.

## 6. Deploy Laravel dan jalankan migrasi

Pastikan kode Laravel terbaru sudah ada di VPS, termasuk migration dan folder `wordpress/`. Dari direktori Laravel, jalankan:

```bash
php artisan migrate --force
php artisan migrate:status
```

Migration menambahkan kolom metadata SEO termasuk meta description, serta ID kategori, tags, author, gambar, dan post WordPress pada tabel artikel. Jika queue worker berjalan sebagai proses tetap, minta worker memuat ulang kode:

```bash
php artisan queue:restart
```

## 7. Perilaku kategori dan tags

Kategori yang dibuat AI dicocokkan dengan nama kategori WordPress. Jika tidak ditemukan, Laravel membuat kategori baru dengan nama tersebut. Tags juga dicari berdasarkan nama atau dibuat bila belum ada. Pastikan akun API memiliki izin mengelola kedua taxonomy tersebut.

## 8. Uji dengan satu artikel

Pilih satu artikel baru yang sudah melewati rewrite, lalu periksa metadata sebelum mengirimnya ke Telegram:

```sql
SELECT id, status, seo_focus_keyword, seo_tags, seo_category,
       wordpress_category_id, wordpress_tag_ids, wordpress_author_id,
       wordpress_media_id, wordpress_post_id
FROM articles
ORDER BY id DESC
LIMIT 5;
```

Pesan Telegram untuk persetujuan menampilkan focus keyword, kategori, dan tags. Periksa artikel beserta metadata tersebut sebelum menekan **Approve**. Artikel yang disetujui akan diterbitkan oleh jadwal otomatis, atau dapat diproses manual:

```bash
php artisan wordpress:publish ID_ARTIKEL
```

Perintah itu menerbitkan artikel sungguhan jika seluruh langkah berhasil. Jika gagal pada penyimpanan focus keyphrase, post akan tetap sebagai draft dan ID post disimpan agar percobaan ulang memperbarui draft yang sama.

## 9. Periksa hasil di WordPress

Setelah Laravel melaporkan publikasi berhasil:

1. Buka post di Dashboard WordPress. Pastikan akun author WordPress dan field **Penulis Berita** terisi. Field **Penulis Berita** mengikuti `WORDPRESS_AUTHOR_NAME` dari `.env`.
2. Pastikan kategori dan tags sesuai dengan isi berita.
3. Pastikan gambar sumber tampil sebagai **Featured image/Gambar andalan**.
4. Buka panel Yoast SEO dan pastikan focus keyphrase terisi.
5. Buka halaman publik untuk memastikan post terbit.

Laravel menyimpan ID post, media, author, kategori, dan tags di database artikel. Jika perintah gagal, periksa `error_message` pada record dan `storage/logs/laravel.log`. Hapus rahasia dari log sebelum membagikannya.

Untuk mengisi meta description dan field Penulis Berita pada post yang sudah ada, perbarui plugin bridge dari ZIP terbaru, lalu jalankan `php artisan wordpress:sync-author ID_ARTIKEL`. Jika artikel lama belum memiliki meta description, perintah membuat ringkasan dari isi rewrite yang tersimpan.

## 10. Artikel lama

Artikel yang sudah direwrite sebelum kolom meta description tersedia tidak otomatis memperoleh deskripsi hasil Gemini. Perintah `wordpress:sync-author` membuat ringkasan dari isi rewrite untuk post lama. Jika ingin membuat ulang seluruh metadata SEO dengan Gemini, `ai:rewrite` dapat dijalankan ulang, tetapi perintah tersebut juga dapat mengganti judul dan isi artikel:

```bash
php artisan ai:rewrite ID_ARTIKEL
```

Perintah tersebut mengirim lagi judul, isi sumber, dan fakta artikel ke Gemini serta dapat mengganti judul dan isi rewrite yang tersimpan. Periksa hasil dan setujui kembali draft sebelum mengirim atau menerbitkannya.
