<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Kategori;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.email');
        $password = (string) config('admin.password');

        // PP §4.5: at least 8 characters with letters and numbers; never a default password in code.
        if (! $email || ! preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password)) {
            throw new RuntimeException('Isi ADMIN_EMAIL dan ADMIN_PASSWORD (minimal 8 karakter, berisi huruf dan angka) di .env sebelum menjalankan seeder.');
        }

        Admin::firstOrCreate(['email' => $email], [
            'nama' => config('admin.name'),
            'password' => $password,
        ]);

        Kategori::firstOrCreate(['namaKategori' => 'Umum']);
    }
}
