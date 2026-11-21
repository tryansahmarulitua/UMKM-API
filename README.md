# UMKM-API

Backend website Menu Sedap ala Bunda Riris berbasis **Laravel**. API ini dipakai oleh dashboard admin `UMKM-APP` dan website publik `UMKM-LandingPages`.

---

## Fungsi Backend

| Fitur | Keterangan |
|---|---|
| Autentikasi | Login dan logout admin dengan Laravel Sanctum. Token berlaku 7 hari. Tidak ada registrasi publik; akun admin dibuat lewat seeder |
| Produk | CRUD data produk UMKM (nama, harga, foto, deskripsi, kategori) |
| Kategori | Pengelolaan kategori produk |
| Profil UMKM | Simpan dan tampilkan profil UMKM |
| Laporan Keuangan | CRUD catatan pemasukan dan pengeluaran |
| Riwayat Login | Catatan waktu, IP, dan perangkat setiap login admin |
| Chatbot | Asisten produk untuk pengunjung, memakai Google Gemini dan data produk serta profil |
| Keamanan | Endpoint admin dilindungi token Sanctum. Login dikunci 15 menit setelah 5 kali gagal |

---

## Cara Menjalankan

```bash
cd UMKM-API
composer install
cp .env.example .env
php artisan key:generate
```

Isi `ADMIN_EMAIL` dan `ADMIN_PASSWORD` (minimal 8 karakter, huruf dan angka) di `.env`, lalu:

```bash
php artisan migrate --seed
php artisan serve
```

Backend berjalan di `http://localhost:8000`. Di laptop, database memakai SQLite; kalau ditanya apakah file `database/database.sqlite` perlu dibuat, jawab `yes`.

Sistem ini dirancang untuk **satu akun admin**. Untuk mengganti email atau password admin, ubah `ADMIN_EMAIL` atau `ADMIN_PASSWORD` di `.env`, lalu jalankan `php artisan db:seed`. Seeder memperbarui admin yang sudah ada dan tidak membuat akun kedua, sehingga produk, laporan, dan profil tetap utuh. Semua perangkat yang sedang login akan otomatis keluar.

### Pengaturan penting di `.env`

| Variabel | Default | Fungsi |
|---|---|---|
| `SANCTUM_EXPIRATION` | `10080` | Masa berlaku token login dalam menit (7 hari). Isi `0` untuk tanpa batas. |
| `GEMINI_API_KEY` | (kosong) | Kunci Google Gemini untuk chatbot. Tanpa kunci ini, chatbot membalas "asisten sedang tidak tersedia". |
| `GEMINI_MODEL` | `gemini-3.8-flash` | Model Gemini yang dipakai chatbot. |
| `CHAT_DAILY_LIMIT` | `500` | Batas pesan chat per hari untuk semua pengunjung, supaya kuota Gemini tidak habis. |
| `APP_TIMEZONE` | `Asia/Jakarta` | Zona waktu aplikasi. |

Bahasa aplikasi ditetapkan ke bahasa Indonesia di `config/app.php`, jadi pesan validasi selalu berbahasa Indonesia (`lang/id/`), apa pun isi `APP_LOCALE`.

### Foto produk dan profil

- Foto disimpan langsung di `public/storage`, sehingga **tidak perlu `php artisan storage:link`**. InfinityFree tidak bisa membuat symlink.
- Saat diunggah, foto otomatis diperkecil menjadi JPEG dengan sisi terpanjang 1200 px dan kualitas 80. Foto HP 3–5 MB menjadi sekitar 100–250 KB.
- Batas upload 5 MB per foto. Kalau ekstensi PHP `exif` tersedia di server, foto tegak dari HP ikut diputar sesuai orientasinya.
- Isi `public/storage` tidak masuk Git, jadi folder ini tidak boleh ditimpa saat deploy ulang.

---

## Endpoint API

### Publik (tanpa token)

| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/api/auth/login` | Login admin dan mendapatkan token |
| GET | `/api/public/produk` | Produk yang ditampilkan di katalog |
| GET | `/api/public/profil` | Profil usaha |
| POST | `/api/chat` | Chatbot: `message` maks. 500 karakter, `history` maks. 6 pesan. Dibatasi 30 pesan/menit per IP dan `CHAT_DAILY_LIMIT` per hari |

### Terproteksi (memerlukan Bearer Token)

| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/api/auth/logout` | Logout pengguna |
| GET | `/api/auth/me` | Info pengguna aktif |
| GET | `/api/auth/login-history` | Riwayat login admin |
| GET/POST/PUT/DELETE | `/api/produk` | Kelola data produk |
| GET/POST/PUT/DELETE | `/api/kategori` | Kelola kategori |
| GET/POST | `/api/profil` | Profil UMKM |
| GET/POST/PUT/DELETE | `/api/laporan-keuangan` | Laporan keuangan |

Catatan:
- **Edit produk yang menyertakan foto** dikirim sebagai `POST /api/produk/{id}?_method=PUT` dengan `multipart/form-data`, karena PHP tidak membaca upload file lewat `PUT`.
- Error validasi dikembalikan dengan status `422` dalam format `{ "message": "...", "errors": { "field": ["..."] } }`, dan pesannya berbahasa Indonesia.

---

## Deploy

Langkah ini ditulis untuk hosting tanpa terminal (misalnya InfinityFree). Kalau hosting punya terminal, langkah 2 cukup diganti dengan `php artisan migrate --force` setelah langkah 3.

1. **Backup.** Export database lewat phpMyAdmin, dan download folder `public/storage` (foto).
2. **Buat tabel baru di phpMyAdmin**, *sebelum* mengupload kode. Kode baru mencatat setiap login ke `login_history`; tanpa tabel ini, admin tidak bisa login. Lewati langkah ini kalau tabelnya sudah ada.

   ```sql
   CREATE TABLE login_history (
     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     idAdmin BIGINT UNSIGNED NOT NULL,
     ip_address VARCHAR(45) NULL,
     user_agent TEXT NULL,
     created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
     CONSTRAINT login_history_idadmin_foreign FOREIGN KEY (idAdmin)
       REFERENCES tb_admin(idAdmin) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

   INSERT INTO migrations (migration, batch)
   SELECT '2026_11_21_000000_create_login_history_table', COALESCE(MAX(batch), 0) + 1 FROM migrations;
   ```

3. **Upload kode dari `main`:** `app/`, `bootstrap/app.php`, `config/`, `database/`, `lang/`, dan `routes/`. `vendor/` hanya perlu diupload ulang kalau `composer.lock` berubah. Folder `.github/` tidak perlu diupload.
   - **Jangan hapus atau timpa `public/storage`.**
   - Kalau ada file `bootstrap/cache/config.php` di server, hapus supaya konfigurasi baru terbaca.
4. **Periksa `.env` server:** `APP_ENV=production`, `APP_DEBUG=false`, `APP_NAME="Menu Sedap ala Bunda Riris"`, `APP_URL` berisi domain API, `DB_CONNECTION=mysql` beserta `DB_*`, dan `GEMINI_API_KEY`. Variabel lain di tabel di atas sudah punya default yang sesuai.
5. **Uji setelah deploy:** login di dashboard dan cek Riwayat Login, upload foto produk, coba chatbot di landing page, lalu simpan profil dengan data tidak valid dan pastikan pesan error tampil dalam bahasa Indonesia.

Domain frontend yang boleh memanggil API diatur di `config/cors.php`. Kalau domain Vercel berubah, tambahkan di `allowed_origins`.

---

## Teknologi

- **Laravel 12**: PHP Framework (PHP 8.4)
- **Laravel Sanctum**: token-based API authentication
- **MySQL**: database di server (SQLite di laptop dan saat test)
- **Google Gemini**: model untuk chatbot

---

## Test

```bash
php artisan test
```

Test juga berjalan otomatis lewat GitHub Actions (`.github/workflows/ci.yml`) di setiap PR dan setiap push ke `main`. Merge PR sebaiknya menunggu tanda hijau di tab **Checks**.
