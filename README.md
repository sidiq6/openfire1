# OPENFIRE — Website Restoran Premium + CMS Privat

OPENFIRE adalah **proyek portofolio fiktif**, bukan bukti restoran telah beroperasi atau terdaftar resmi di Google Maps. Arsip ini memuat website publik dan dashboard admin berbasis **PHP native + MySQL**, dengan aset dari arsip `OPENFIRE.zip` yang telah dikonversi menjadi WebP.

## Isi proyek

- Website publik responsif: hero, tentang, konsep, 35 menu (4 kategori), galeri 13 foto, kontak, peta Google Maps.
- Website bilingual **Bahasa Indonesia dan English**. Terjemahan awal dikelola manual, bukan otomatis menggunakan layanan eksternal.
- CMS privat: ringkasan, edit semua bagian website, CRUD/arsip produk, kategori, galeri, upload foto, metadata gambar, terjemahan UI, kontak, SEO, akun, password, dan sesi.
- Login aman dengan `password_hash`, PHP session + sesi database, CSRF, pembatasan percobaan login, validasi upload, dan PDO prepared statements.
- File `database/schema.sql`, `database/seeds.sql`, `database/assets-manifest.json` untuk pemetaan aset asli.
- Tidak ada akun admin bawaan; akun pertama dibuat lewat setup satu kali.

## 1. Persiapan perangkat

**Disarankan PC/laptop untuk instalasi lokal:** pasang [XAMPP](https://www.apachefriends.org/) yang memuat PHP, Apache dan MySQL/MariaDB (pastikan versi PHP minimal 8.2). Alternatif: Laragon, MAMP, atau hosting PHP/MySQL. Di ponsel, Anda bisa mengelola CMS melalui browser **setelah website berhasil dihosting**; ponsel tidak wajib memiliki MySQL.

Persyaratan: PHP 8.2+ dengan **PDO MySQL (`pdo_mysql`)**, `fileinfo`, `session`, `json`; `gd` dan `mbstring` direkomendasikan. Database MySQL 8+ (atau MariaDB versi kompatibel). Pastikan extension `pdo_mysql` aktif — PHP tidak dapat membuka database MySQL tanpa driver tersebut.

## 2. Ekstrak file

Ekstrak arsip sehingga folder memiliki bentuk:

```text
OPENFIRE/
  index.php
  public/              website dan assets
  backoffice-openfire/ dashboard privat
  api/                 endpoint publik dan admin
  app/                 konfigurasi, helper dan autentikasi
  database/            schema, seeds dan mapping assets
  storage/             log
  .env.example
  README.md
  DEPLOYMENT.md
```

**XAMPP contoh**: tempatkan `OPENFIRE` di `C:\xampp\htdocs\OPENFIRE`. Jalankan Apache dan MySQL.

## 3. Buat database dan impor SQL

Buka `http://localhost/phpmyadmin`, buat database **`openfire`** dengan kolasi `utf8mb4_unicode_ci`.

- Pilih database `openfire` → Import → `database/schema.sql` → Go.
- Import lagi file **`database/seeds.sql`** → Go.

`schema.sql` membentuk semua tabel. `seeds.sql` mengisi 49 aset, 35 menu, 13 entri galeri, 4 kategori, dan seluruh teks bahasa ID/EN. Tidak ada data harga palsu; harga menu awal bernilai `NULL` dan tidak ditampilkan.

Bila menggunakan terminal MySQL:

```sh
mysql -u root -p -e "CREATE DATABASE openfire CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p openfire < database/schema.sql
mysql -u root -p openfire < database/seeds.sql
```

## 4. Konfigurasi koneksi

Salin `.env.example` menjadi `.env` (file baru, bukan folder). Pada Windows, pastikan namanya benar-benar **`.env`**, bukan `.env.txt`.

Contoh instalasi di XAMPP (hanya untuk komputer lokal):

```ini
APP_ENV=local
APP_DEBUG=false
APP_URL=http://localhost
APP_BASE_PATH=/OPENFIRE
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=openfire
DB_USER=root
DB_PASS=
INSTALL_TOKEN=GANTI_INI_DENGAN_64_KARAKTER_ACAK_YANG_BERBEDA
```

Untuk hosting sungguhan, **jangan** gunakan pengguna MySQL `root`. Buat pengguna database khusus dan password acak, atur `APP_URL=https://domainanda.com`, `APP_BASE_PATH=` (kosong untuk domain root). Token instalasi harus random, minimal 32 karakter; Anda bisa menghasilkannya dengan perintah `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` atau password generator yang aman.

> Jangan unggah file `.env` ke GitHub. File ini sudah tercakup dalam `.gitignore`, tetapi periksa lagi sebelum mengunggah apa pun.

## 5. Buat administrator pertama

**Setelah database dan `.env` benar:**

- XAMPP: buka `http://localhost/OPENFIRE/backoffice-openfire/setup.php`.
- Isi token instalasi persis seperti `INSTALL_TOKEN` di `.env`.
- Masukkan nama, email, password kuat minimal 12 karakter, dan konfirmasi.
- Akun dibuat sekali. Sesudah itu halaman setup otomatis ditolak.
- **Hapus atau kosongkan INSTALL_TOKEN di `.env` setelah admin dibuat.**

Login melalui `http://localhost/OPENFIRE/backoffice-openfire/login.php` menggunakan akun yang Anda buat. Dashboard di `.../backoffice-openfire/` hanya dapat diakses setelah login.

## 6. Buka website dan admin

- Website: `http://localhost/OPENFIRE/`
- Admin: `http://localhost/OPENFIRE/backoffice-openfire/`
- Website tidak mempunyai tombol maupun link login admin.

Opsi menjalankan *development server* PHP dari folder `OPENFIRE` (hanya bila MySQL tersedia dan dikonfigurasi):

```sh
php -S localhost:8000 router.php
```

Dalam opsi ini pakai `APP_URL=http://localhost:8000`, `APP_BASE_PATH=` (kosong), lalu buka `http://localhost:8000/`.

## 7. Cara mengelola konten

- **Konten Website:** pilih bahasa pada dropdown → pilih bagian hero, tentang, pengalaman, menu, galeri, lokasi, kontak atau footer → edit judul, deskripsi, tombol dan pilih gambar → Simpan.
- **Menu & Minuman:** tambah/edit produk → tentukan slug unik, kategori, gambar, nama/deskripsi ID dan EN, harga, status, urutan → Simpan. Arsipkan produk yang sudah tidak dijual agar tidak tampil; Anda dapat mengaktifkan lagi melalui Edit.
- **Harga:** harga kosong tidak tampil. Isi angka rupiah lalu centang `Tampilkan harga produk ini`. Di Pengaturan aktifkan `Tampilkan harga menu` agar harga terlihat di website.
- **Kategori:** atur urutan, nama dan terjemahan. Nonaktifkan kategori jika ingin menyembunyikan seluruh produk di dalamnya.
- **Media:** pilih file JPEG/PNG/WebP maksimal 8 MB → Unggah → metadata. Jika extension PHP GD tersedia, gambar dan thumbnail dikonversi menjadi WebP. Foto bawaan tidak dapat dihapus, supaya pemetaan arsip tetap terjaga.
- **Galeri:** tambahkan dari Media Library; atur urutan dan status. Edit caption dan alt text melalui **Media & Gambar**.
- **Terjemahan:** edit label antarmuka; pilih bahasa yang ingin diedit. Bahasa baru dibuat sebagai draft nonaktif, lalu terjemahan per modul perlu dilengkapi sebelum diaktifkan.
- **Kontak & Pengaturan:** edit WhatsApp, sosial media, alamat, landmark, embed Google Maps, SEO dan bahasa default.
- **Akun & Keamanan:** ganti data admin, ubah password dan akhiri sesi perangkat lain. Keluar melalui tombol `Keluar` di sidebar.

Perubahan disimpan ke **MySQL** dan terlihat di website sesudah dimuat ulang. Pergantian bahasa publik melalui AJAX/FETCH tanpa reload penuh; pilihan bahasa disimpan di localStorage.

## 8. Google Maps dan tautan sosial

Akun bawaan yang dikonfigurasi:

- TikTok: `https://www.tiktok.com/@arfvnnn1`
- Instagram: `https://www.instagram.com/whoisarfan`
- WhatsApp: `https://wa.me/62895637382492`

Peta awal menggunakan pencarian **alamat demonstrasi** di Google Maps (`q=alamat&output=embed`) dan tombol `Petunjuk Arah` dengan URL pencarian rute; tidak ada koordinat yang dikarang. Jika ingin pin akurat, masukkan URL embed/directions yang valid melalui dashboard. Jika peta diblokir oleh browser atau koneksi, pengguna tetap mendapat tombol Google Maps.

## 9. Keamanan penting

1. Pakai HTTPS untuk hosting publik dan ganti password database dari nilai contoh.
2. Batasi akses ke direktori `app/`, `database/`, `storage/`, `bin/`, `.env`; pengaturan Apache `.htaccess` disediakan. Hosting Nginx harus menggunakan aturan deny setara (lihat DEPLOYMENT.md).
3. Jangan beri izin PHP mengeksekusi file dari folder `public/uploads/`.
4. Batasi izin file `.env` untuk akun webserver yang tepat (contoh `640` pada Linux).
5. Lakukan backup database dan folder upload secara berkala.
6. Jangan mengubah file SQL seed setelah website berisi perubahan nyata, karena seed hanya untuk instalasi awal.

## 10. Bila muncul masalah

| Gejala | Solusi |
|---|---|
| `Database belum terhubung` | Periksa nama DB, username/password, port dan extension `pdo_mysql` |
| Setup admin tidak muncul | Pastikan schema.sql sudah diimpor, admin belum ada, token di .env benar |
| Foto tidak muncul | Pastikan folder `public/assets/images` tidak terlewat saat upload |
| Upload gagal | Periksa batas `upload_max_filesize`, `post_max_size`, izin folder `public/uploads`, extension `fileinfo` dan `gd` |
| Login terus kembali ke halaman login | Pastikan cookies diaktifkan, APP_URL/HTTPS sesuai, dan tabel admin_sessions ada |
| Embed Google Maps tidak muncul | Coba koneksi internet dan buka link `Petunjuk Arah` sebagai cadangan |
| URL website di subfolder salah | Sesuaikan `APP_BASE_PATH`, misalnya `/OPENFIRE` |

## Catatan QA

Kode PHP dan JavaScript telah diperiksa sintaks serta keberadaan berkas assetnya dalam paket. **Pengujian transaksi MySQL langsung, browser end-to-end setelah impor database, dan Lighthouse pada hosting nyata tetap harus dilakukan** pada lingkungan yang memiliki ekstensi `pdo_mysql` dan server MySQL. Jangan memasarkan sebagai sudah diuji production sebelum menjalankan checklist DEPLOYMENT.md.
