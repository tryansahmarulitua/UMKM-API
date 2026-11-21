<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Kategori;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The dashboard shows API validation messages as-is, so they must read as Indonesian.
 */
class PesanValidasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(Admin::create(['nama' => 'Owner', 'email' => 'owner@bunda.id', 'password' => 'rahasia123']));
    }

    public function test_empty_product_form_explains_each_field_in_indonesian(): void
    {
        $this->postJson('/api/produk', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.namaProduk.0', 'Nama produk wajib diisi.')
            ->assertJsonPath('errors.idKategori.0', 'Kategori wajib diisi.')
            ->assertJsonPath('errors.harga.0', 'Harga wajib diisi.')
            ->assertJsonPath('message', 'Kategori wajib diisi. (dan 4 kesalahan lainnya)');
    }

    public function test_photo_over_5_mb_gets_a_plain_size_message(): void
    {
        Storage::fake('public');
        $kategori = Kategori::create(['namaKategori' => 'Abon']);

        $this->post('/api/produk', [
            'idKategori' => $kategori->idKategori, 'namaProduk' => 'Abon', 'deskripsi' => 'Gurih', 'harga' => 1000, 'tampil' => 'Y',
            'foto' => UploadedFile::fake()->image('besar.jpg')->size(6000),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.foto.0', 'Ukuran foto maksimal 5 MB.');
    }

    public function test_limits_and_duplicates_are_explained_in_indonesian(): void
    {
        Kategori::create(['namaKategori' => 'Abon']);

        $this->postJson('/api/kategori', ['namaKategori' => 'Abon'])
            ->assertJsonPath('errors.namaKategori.0', 'Nama kategori sudah dipakai.');
        $this->postJson('/api/kategori', ['namaKategori' => str_repeat('a', 21)])
            ->assertJsonPath('errors.namaKategori.0', 'Nama kategori maksimal 20 karakter.');
        $this->postJson('/api/laporan-keuangan', ['jenis' => 'Lain', 'namaSumber' => 'Pasar', 'jumlah' => -5, 'tanggal' => 'kemarin'])
            ->assertJsonPath('errors.jenis.0', 'Jenis yang dipilih tidak valid.')
            ->assertJsonPath('errors.jumlah.0', 'Jumlah minimal 0.')
            ->assertJsonPath('errors.tanggal.0', 'Tanggal bukan tanggal yang valid.');
        $this->postJson('/api/profil', ['namaUMKM' => 'Bunda', 'deskripsi' => 'x', 'alamat' => 'Bekasi', 'noTelepon' => '0812345678', 'email' => 'bukan-email'])
            ->assertJsonPath('errors.email.0', 'Email harus berupa alamat email yang valid.');
    }
}
