# OPENFIRE — Panduan Deploy dan Checklist QA

## Hosting yang kompatibel

Pilih paket shared hosting/cPanel atau VPS dengan PHP 8.2+, MySQL 8 atau MariaDB yang kompatibel, HTTPS, extension `pdo_mysql`, `fileinfo`, `session`, `json`, dan disarankan `gd` serta `mbstring`.

**Vercel static hosting saja tidak cukup untuk aplikasi ini**, karena membutuhkan server PHP dan MySQL. Gunakan layanan yang menjalankan PHP/MySQL atau VPS, bukan deployment statis.

## Cara deploy di cPanel

1. Buat database MySQL + user database dengan password kuat dan akses hanya ke database OPENFIRE.
2. Impor `database/schema.sql` kemudian `database/seeds.sql` via phpMyAdmin.
3. Upload isi folder `OPENFIRE` sebagai root web di `public_html/` melalui File Manager dan ekstrak. Pastikan file tersembunyi `.htaccess` terbawa.
4. Salin `.env.example` menjadi `.env`, konfigurasi kredensial, URL, path, dan token random. `APP_URL` sebaiknya `https://domainanda.com` dan `APP_BASE_PATH=` kosong.
5. Pastikan `public/uploads` writable (`755` bila owner webserver benar; hindari `777`). `.env` harus tidak bisa didownload.
6. Buka `/backoffice-openfire/setup.php` via HTTPS untuk membuat akun admin pertama; setelah itu kosongkan token instalasi.
7. Akses homepage dan dashboard untuk pengujian.
8. Aktifkan backup berkala MySQL + folder `public/uploads`.

**Jika instal di subfolder**, contoh `https://example.com/openfire/`: gunakan `APP_URL=https://example.com` dan `APP_BASE_PATH=/openfire`. Perbarui aturan robots di root domain bila diperlukan.

## Setup Nginx

Pada Nginx, `.htaccess` **tidak berfungsi**. Tambahkan aturan blokir pada virtual host sebelum handler PHP. Contoh konsep:

```nginx
location ~ /(?:app|database|storage|bin)/ { deny all; }
location ~ /\.(?!well-known/) { deny all; }
location ~ /public/uploads/.*\.(?:php[0-9]?|phtml|phar|cgi|pl|py|sh)$ { deny all; }
location ~ ^/backoffice-openfire/ { add_header X-Robots-Tag "noindex, nofollow" always; try_files $uri =404; }
```

**Perhatian:** aturan Nginx perlu dirancang bersama handler PHP milik server; jangan menyalin `location` secara buta jika konfigurasi server memerlukan precedence berbeda. Endpoint `login.php`, `setup.php`, dan `index.php` pada folder backoffice tetap harus diproses oleh PHP. Prioritaskan agar folder konfigurasi dan upload non-executable terlindungi.

## Checklist fungsi sebelum publikasi

- [ ] Website publik terbuka dari URL hosting tanpa error 500.
- [ ] Semua 49 gambar termapping, 13 foto galeri, dan 35 produk menu memiliki berkas valid.
- [ ] Semua menu dapat difilter berdasarkan 4 kategori; empty state tidak rusak.
- [ ] Sakelar ID/EN mengganti header, bagian halaman, menu, galeri, footer dan metadata dengan benar.
- [ ] Header desktop/mobile, fokus keyboard, dan lightbox galeri berfungsi.
- [ ] WhatsApp, Instagram, dan TikTok menuju URL yang tepat.
- [ ] Embed Google Maps dan link Petunjuk Arah berfungsi pada jaringan pengguna.
- [ ] URL admin tidak ditampilkan pada halaman publik.
- [ ] Login dengan password salah ditolak; login benar berhasil.
- [ ] Akses ke `api/admin.php` tanpa sesi mengembalikan 401.
- [ ] Pembaruan hero (teks & foto) langsung muncul pada website.
- [ ] Tambah produk baru dengan gambar serta deskripsi ID/EN berhasil; edit dan arsip bekerja.
- [ ] Mengatur harga dan toggle harga benar, tidak tampil untuk price NULL.
- [ ] Menambah/mengatur galeri, alt text dan caption tersimpan.
- [ ] Mengubah tautan sosial, pesan WhatsApp, alamat, dan map bertahan setelah refresh.
- [ ] Password admin bisa diperbarui; logout menginvalidasi sesi.
- [ ] File `.env` / SQL tidak dapat diunduh lewat browser.
- [ ] Upload file berbahaya, file kosong, dan file di atas limit ditolak.
- [ ] Tes layout 360, 390, 768, 1024, 1440, 1920 px di browser.
- [ ] Jalankan Lighthouse nyata; perbaiki temuan tanpa mengklaim angka skor yang belum diukur.

## Keterbatasan eksternal

- Tidak ada API terjemahan otomatis berbayar. Sistem awal **ID/EN adalah terjemahan manual**; bahasa lain harus dilengkapi manual sebelum diterbitkan.
- Google Maps memakai Google Maps `q=alamat` jika embed belum dikonfigurasi; alamat ini hanya demo portofolio, bukan verifikasi usaha.
- Foto berasal dari arsip yang disediakan; kualitas maksimal tidak dapat melebihi resolusi asli.
- Hosting harus menyediakan layanan PHP/MySQL nyata. Perubahan konten tidak dapat disimpan jika hanya file statis yang diunggah.
