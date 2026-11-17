# UMKM-API

Backend aplikasi UMKM Jatisampurna berbasis **Laravel**. API ini menyediakan data dan logika bisnis yang dikonsumsi oleh frontend `UMKM-APP`.

---

## Fungsi Backend

| Fitur | Keterangan |
|---|---|
| Autentikasi | Registrasi, login, logout pengguna menggunakan Laravel Sanctum |
| Produk | CRUD data produk UMKM (nama, harga, foto, deskripsi, kategori) |
| Kategori | Pengelolaan kategori produk |
| Profil UMKM | Simpan dan tampilkan profil UMKM |
| Laporan Keuangan | CRUD catatan laporan keuangan |
| Keamanan | Endpoint dilindungi token Sanctum kecuali login/register |

---

## Cara Menjalankan

```bash
cd UMKM-API
php artisan serve
```

Backend berjalan di `http://localhost:8000`.

---

## Endpoint API

### Publik (tanpa token)

| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/api/auth/register` | Registrasi pengguna baru |
| POST | `/api/auth/login` | Login dan mendapatkan token |

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

- **Laravel 11** — PHP Framework
- **Laravel Sanctum** — Token-based API authentication
- **MySQL** — Database
