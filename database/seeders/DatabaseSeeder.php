<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Admin::firstOrCreate(
            ['email' => 'admin@umkm.com'],
            [
                'nama' => 'Administrator',
                'email' => 'admin@umkm.com',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
            ]
        );

        \App\Models\Kategori::firstOrCreate(['namaKategori' => 'Umum']);
    }
}
