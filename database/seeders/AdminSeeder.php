<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::create([
            'nama' => 'Administrator',
            'email' => 'admin@umkm.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);
    }
}