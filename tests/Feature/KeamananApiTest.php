<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\LaporanKeuangan;
use App\Models\Produk;
use App\Models\ProfilUMKM;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KeamananApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email = 'owner@bunda.id'): Admin
    {
        return Admin::create(['nama' => 'Owner', 'email' => $email, 'password' => 'rahasia123']);
    }

    private function laporan(Admin $owner): LaporanKeuangan
    {
        return LaporanKeuangan::create([
            'idAdmin' => $owner->idAdmin,
            'jenis' => 'Pemasukan',
            'namaSumber' => 'Penjualan',
            'jumlah' => 50000,
            'tanggal' => '2026-11-20',
        ]);
    }

    public function test_public_registration_is_closed(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Penyusup',
            'email' => 'penyusup@mail.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertNotFound();

        $this->assertDatabaseMissing('tb_admin', ['email' => 'penyusup@mail.com']);
    }

    public function test_seeder_refuses_to_create_admin_without_a_strong_password(): void
    {
        config(['admin.email' => 'owner@bunda.id', 'admin.password' => 'password']);

        $this->expectException(\RuntimeException::class);
        $this->seed();
    }

    public function test_seeder_creates_the_admin_from_env_and_the_admin_can_log_in(): void
    {
        config(['admin.email' => 'owner@bunda.id', 'admin.password' => 'rahasia123']);

        $this->seed();
        $this->seed();

        $this->assertSame(1, Admin::count());
        $this->postJson('/api/auth/login', ['email' => 'owner@bunda.id', 'password' => 'rahasia123'])->assertOk();
    }

    public function test_login_is_locked_for_15_minutes_after_5_failed_attempts(): void
    {
        $this->admin();
        $wrong = ['email' => 'owner@bunda.id', 'password' => 'salah'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', $wrong)->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', ['email' => 'owner@bunda.id', 'password' => 'rahasia123'])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Terlalu banyak percobaan login. Coba lagi dalam 15 menit.');

        $this->travel(16)->minutes();

        $this->postJson('/api/auth/login', ['email' => 'owner@bunda.id', 'password' => 'rahasia123'])->assertOk();
    }

    public function test_successful_login_resets_the_failed_attempt_counter(): void
    {
        $this->admin();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'owner@bunda.id', 'password' => 'salah']);
        }
        $this->postJson('/api/auth/login', ['email' => 'owner@bunda.id', 'password' => 'rahasia123'])->assertOk();

        $this->postJson('/api/auth/login', ['email' => 'owner@bunda.id', 'password' => 'salah'])->assertUnprocessable();
    }

    public function test_admin_cannot_read_or_delete_another_admins_financial_report(): void
    {
        $laporan = $this->laporan($this->admin());
        Sanctum::actingAs($this->admin('lain@bunda.id'));

        $this->getJson("/api/laporan-keuangan/{$laporan->idLK}")->assertForbidden();
        $this->deleteJson("/api/laporan-keuangan/{$laporan->idLK}")->assertForbidden();
        $this->assertDatabaseHas('tb_laporankeuangan', ['idLK' => $laporan->idLK]);
    }

    public function test_owner_can_still_read_and_delete_own_financial_report(): void
    {
        $owner = $this->admin();
        $laporan = $this->laporan($owner);
        Sanctum::actingAs($owner);

        $this->getJson("/api/laporan-keuangan/{$laporan->idLK}")->assertOk();
        $this->deleteJson("/api/laporan-keuangan/{$laporan->idLK}")->assertOk();
    }

    public function test_category_used_by_products_cannot_be_deleted(): void
    {
        $owner = $this->admin();
        $kategori = Kategori::create(['namaKategori' => 'Keripik']);
        Produk::create([
            'idKategori' => $kategori->idKategori, 'idAdmin' => $owner->idAdmin, 'namaProduk' => 'Keripik Pedas',
            'deskripsi' => 'Renyah', 'harga' => 15000, 'tampil' => true,
        ]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/kategori/{$kategori->idKategori}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kategori masih dipakai produk. Pindahkan atau hapus produknya dulu.');
        $this->assertDatabaseHas('tb_kategoriproduk', ['idKategori' => $kategori->idKategori]);
    }

    public function test_validation_limits_match_database_columns(): void
    {
        $owner = $this->admin();
        $kategori = Kategori::create(['namaKategori' => 'Abon']);
        Sanctum::actingAs($owner);

        $this->postJson('/api/kategori', ['namaKategori' => str_repeat('a', 21)])
            ->assertJsonValidationErrors('namaKategori');

        $this->postJson('/api/produk', [
            'idKategori' => $kategori->idKategori, 'namaProduk' => 'Abon', 'tampil' => 'Y',
            'deskripsi' => str_repeat('a', 256), 'harga' => 1000000,
        ])->assertJsonValidationErrors(['deskripsi', 'harga']);

        $this->postJson('/api/profil', [
            'namaUMKM' => 'Bunda Riris', 'deskripsi' => 'Camilan', 'alamat' => 'Bekasi', 'noTelepon' => str_repeat('8', 16),
        ])->assertJsonValidationErrors('noTelepon');

        $this->postJson('/api/laporan-keuangan', [
            'jenis' => 'Pemasukan', 'namaSumber' => 'Penjualan', 'jumlah' => 1000, 'tanggal' => '2026-11-20',
            'keterangan' => str_repeat('a', 101),
        ])->assertJsonValidationErrors('keterangan');
    }

    public function test_money_amounts_must_fit_the_column_and_have_at_most_2_decimals(): void
    {
        $kategori = Kategori::create(['namaKategori' => 'Abon']);
        Sanctum::actingAs($this->admin());
        $laporan = ['jenis' => 'Pemasukan', 'namaSumber' => 'Penjualan', 'tanggal' => '2026-11-20'];

        // decimal(15,2) holds at most 9,999,999,999,999.99; larger values used to crash MySQL with a 500.
        $this->postJson('/api/laporan-keuangan', $laporan + ['jumlah' => 10000000000000])->assertJsonValidationErrors('jumlah');
        $this->postJson('/api/laporan-keuangan', $laporan + ['jumlah' => 15000.555])->assertJsonValidationErrors('jumlah');
        // Sent as a string, like the dashboard form does; a JSON float this large loses precision.
        $this->postJson('/api/laporan-keuangan', $laporan + ['jumlah' => '9999999999999.99'])->assertCreated();

        $this->postJson('/api/produk', [
            'idKategori' => $kategori->idKategori, 'namaProduk' => 'Abon', 'tampil' => 'Y', 'deskripsi' => 'Gurih', 'harga' => 15000.555,
        ])->assertJsonValidationErrors('harga');
    }

    public function test_app_runs_on_jakarta_time_and_report_dates_are_not_shifted(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));

        $owner = $this->admin();
        Sanctum::actingAs($owner);
        $this->laporan($owner);

        // A UTC-serialised date would come out as 2026-11-19T17:00:00Z and show one day early in the dashboard.
        $this->getJson('/api/laporan-keuangan')->assertOk()->assertJsonPath('0.tanggal', '2026-11-20');
    }

    public function test_updating_profile_photo_keeps_exactly_one_file(): void
    {
        Storage::fake('public');
        $owner = $this->admin();
        Sanctum::actingAs($owner);
        $profil = ['namaUMKM' => 'Bunda Riris', 'deskripsi' => 'Camilan', 'alamat' => 'Bekasi', 'noTelepon' => '081234500000'];

        $this->post('/api/profil', $profil + ['foto' => UploadedFile::fake()->image('a.jpg')])->assertCreated();
        $this->post('/api/profil', $profil + ['foto' => UploadedFile::fake()->image('b.jpg')])->assertOk();

        $files = Storage::disk('public')->files('profil');
        $this->assertCount(1, $files);
        $this->assertSame($files[0], ProfilUMKM::first()->foto);
    }

    public function test_login_history_has_its_own_table_so_session_cleanup_cannot_erase_it(): void
    {
        $this->admin();
        $this->postJson('/api/auth/login', ['email' => 'owner@bunda.id', 'password' => 'rahasia123'])->assertOk();
        $token = $this->postJson('/api/auth/login', ['email' => 'owner@bunda.id', 'password' => 'rahasia123'])->json('token');

        $this->assertDatabaseCount('sessions', 0);
        $this->assertDatabaseCount('login_history', 2);

        $this->withToken($token)->getJson('/api/auth/login-history')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonStructure([['id', 'ip_address', 'user_agent', 'last_activity']]);
    }

    public function test_profile_rejects_unusable_social_link_and_phone_number(): void
    {
        Sanctum::actingAs($this->admin());
        $profil = ['namaUMKM' => 'Bunda Riris', 'deskripsi' => 'Camilan', 'alamat' => 'Bekasi'];

        foreach (['https://', 'https://exa mple.com', 'javascript:alert(1)', 'instagram.com/bunda'] as $link) {
            $this->postJson('/api/profil', $profil + ['noTelepon' => '081234500000', 'mediaSosial' => $link])
                ->assertJsonValidationErrors('mediaSosial');
        }
        $this->postJson('/api/profil', $profil + ['noTelepon' => 'telepon saya'])->assertJsonValidationErrors('noTelepon');

        $this->postJson('/api/profil', $profil + ['noTelepon' => '0812-3450-0000', 'mediaSosial' => 'https://instagram.com/bundariris_17new'])
            ->assertCreated();
    }

    public function test_chat_history_is_capped_at_6_turns(): void
    {
        Http::fake();
        $history = array_fill(0, 7, ['role' => 'user', 'text' => 'halo']);

        $this->postJson('/api/chat', ['message' => 'halo', 'history' => $history])->assertJsonValidationErrors('history');
    }

    public function test_chat_has_a_daily_limit_shared_by_all_visitors(): void
    {
        config(['services.gemini.daily_limit' => 2]);
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Halo!']]]]]])]);

        foreach (['10.0.0.1', '10.0.0.2'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/chat', ['message' => 'halo'])->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])->postJson('/api/chat', ['message' => 'halo'])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Asisten sedang istirahat. Silakan tanya langsung lewat WhatsApp.');
    }

    public function test_chat_message_is_limited_to_500_characters(): void
    {
        Http::fake();

        $this->postJson('/api/chat', ['message' => str_repeat('a', 501)])->assertJsonValidationErrors('message');
        Http::assertNothingSent();
    }

    public function test_chat_prompt_uses_the_business_name_from_the_profile(): void
    {
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Halo!']]]]]])]);
        ProfilUMKM::create([
            'idAdmin' => $this->admin()->idAdmin, 'namaUMKM' => 'Menu Sedap ala Bunda Riris',
            'deskripsi' => 'Camilan', 'alamat' => 'Bekasi', 'noTelepon' => '081234500000',
        ]);

        $this->postJson('/api/chat', ['message' => 'Ada yang pedas?'])->assertOk()->assertJsonPath('answer', 'Halo!');

        Http::assertSent(function ($request) {
            $prompt = $request['systemInstruction']['parts'][0]['text'];
            return str_contains($prompt, 'asisten ramah untuk Menu Sedap ala Bunda Riris')
                && ! str_contains($prompt, 'Jatisampurna');
        });
    }
}
