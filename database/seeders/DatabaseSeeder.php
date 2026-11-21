<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Kategori;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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

        // One business, one admin: products and categories are shared, while reports and the profile
        // belong to an account. Changing ADMIN_EMAIL or ADMIN_PASSWORD therefore updates the existing
        // admin (keeping their data) instead of adding a second account.
        $admin = Admin::orderBy('idAdmin')->first();
        if (! $admin) {
            Admin::create(['nama' => config('admin.name'), 'email' => $email, 'password' => $password]);
        } else {
            $credentialsChanged = $admin->email !== $email || ! Hash::check($password, $admin->password);
            $admin->update(['nama' => config('admin.name'), 'email' => $email, 'password' => $password]);
            if ($credentialsChanged) {
                $admin->tokens()->delete(); // log out every device that used the old credentials
            }
        }

        Kategori::firstOrCreate(['namaKategori' => 'Umum']);
    }
}
