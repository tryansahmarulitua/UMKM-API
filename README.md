# UMKM-API

Backend website Menu Sedap ala Bunda Riris berbasis **Laravel**. API ini dipakai oleh dashboard admin `UMKM-APP` dan website publik `UMKM-LandingPages`.

---

## Fungsi Backend

| Fitur | Keterangan |
|---|---|
| Autentikasi | Login dan logout admin dengan Laravel Sanctum. Tidak ada registrasi publik; akun admin dibuat lewat seeder |
| Produk | CRUD data produk UMKM (nama, harga, foto, deskripsi, kategori) |
| Kategori | Pengelolaan kategori produk |
| Profil UMKM | Simpan dan tampilkan profil UMKM |
| Laporan Keuangan | CRUD catatan laporan keuangan |
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

Backend berjalan di `http://localhost:8000`.

---

## Endpoint API

### Publik (tanpa token)

| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/api/auth/login` | Login admin dan mendapatkan token |
| GET | `/api/public/produk` | Produk yang ditampilkan di katalog |
| GET | `/api/public/profil` | Profil usaha |
| POST | `/api/chat` | Chatbot (maks. 500 karakter, 30 pesan/menit per IP) |

### Terproteksi (memerlukan Bearer Token)

| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/api/auth/logout` | Logout pengguna |
| GET | `/api/auth/me` | Info pengguna aktif |
| GET/POST/PUT/DELETE | `/api/produk` | Kelola data produk |
| GET/POST/PUT/DELETE | `/api/kategori` | Kelola kategori |
| GET/POST | `/api/profil` | Profil UMKM |
| GET/POST/PUT/DELETE | `/api/laporan-keuangan` | Laporan keuangan |

---

## Teknologi

- **Laravel 12** — PHP Framework (PHP 8.4)
- **Laravel Sanctum** — Token-based API authentication
- **MySQL** — Database (SQLite untuk test)

---

## Test

```bash
php artisan test
```
