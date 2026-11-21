<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\LaporanKeuangan;
use App\Models\Pengaturan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\ProfilUMKM;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Online ordering (Project Plan §1.2, §4.5, FT04, FT05, FT08, FT09, FT11, FT12, FT15):
 * checkout without an account, server-side prices, valid status flow, 24-hour auto-cancel,
 * and Lunas orders recorded as income exactly once.
 */
class PesananTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Produk $abon;

    private Produk $keripik;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-21 09:00:00');
        $this->admin = Admin::create(['nama' => 'Owner', 'email' => 'owner@bunda.id', 'password' => 'rahasia123']);
        $kategori = Kategori::create(['namaKategori' => 'Camilan']);
        $buat = fn (string $nama, float $harga, bool $tampil = true) => Produk::create([
            'idAdmin' => $this->admin->idAdmin, 'idKategori' => $kategori->idKategori,
            'namaProduk' => $nama, 'deskripsi' => '-', 'harga' => $harga, 'tampil' => $tampil,
        ]);
        $this->abon = $buat('Abon Sapi', 45000);
        $this->keripik = $buat('Keripik Pedas', 12500.50);
    }

    private function checkout(array $override = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/pesanan', array_merge([
            'namaPelanggan' => 'Rina',
            'noWhatsApp' => '0812-3456-7890',
            'pengiriman' => 'Dikirim',
            'items' => [
                ['idProduk' => $this->abon->idProduk, 'jumlah' => 2],
                ['idProduk' => $this->keripik->idProduk, 'jumlah' => 3],
            ],
        ], $override));
    }

    private function pesanan(string $status = 'Menunggu Pembayaran'): Pesanan
    {
        $nomor = $this->checkout()->json('nomorPesanan');
        $pesanan = Pesanan::where('nomorPesanan', $nomor)->firstOrFail();
        $pesanan->update(['status' => $status]);

        return $pesanan;
    }

    public function test_checkout_saves_the_order_with_database_prices_and_ignores_browser_prices(): void
    {
        $response = $this->checkout(['items' => [
            ['idProduk' => $this->abon->idProduk, 'jumlah' => 2, 'harga' => 1],
            ['idProduk' => $this->keripik->idProduk, 'jumlah' => 3, 'harga' => 1],
        ], 'total' => 5])->assertCreated();

        // 2 x 45.000 + 3 x 12.500,50 = 127.501,50
        $response->assertJsonPath('nomorPesanan', 'BR-20261121-0001')
            ->assertJsonPath('total', 127501.5)
            ->assertJsonPath('status', 'Menunggu Pembayaran')
            ->assertJsonPath('items.0.namaProduk', 'Abon Sapi')
            ->assertJsonPath('items.0.harga', 45000)
            ->assertJsonPath('items.0.subtotal', 90000)
            ->assertJsonPath('items.1.subtotal', 37501.5);

        $this->assertDatabaseHas('tb_pesanan', ['nomorPesanan' => 'BR-20261121-0001', 'noWhatsApp' => '6281234567890', 'pengiriman' => 'Dikirim']);
        $this->assertDatabaseCount('tb_detail_pesanan', 2);
    }

    public function test_order_numbers_count_up_per_day(): void
    {
        $this->checkout()->assertJsonPath('nomorPesanan', 'BR-20261121-0001');
        $this->checkout()->assertJsonPath('nomorPesanan', 'BR-20261121-0002');

        $this->travelTo('2026-11-22 08:00:00');
        $this->checkout()->assertJsonPath('nomorPesanan', 'BR-20261122-0001');
    }

    public function test_later_price_changes_do_not_change_saved_orders(): void
    {
        $nomor = $this->checkout()->json('nomorPesanan');
        $this->abon->update(['harga' => 99000]);

        Sanctum::actingAs($this->admin);
        $id = Pesanan::where('nomorPesanan', $nomor)->value('idPesanan');
        $this->getJson("/api/pesanan/{$id}")->assertOk()
            ->assertJsonPath('total', '127501.50')
            ->assertJsonPath('detail.0.harga', '45000.00');
    }

    public function test_checkout_returns_payment_info_and_the_whatsapp_number_from_settings(): void
    {
        ProfilUMKM::create(['idAdmin' => $this->admin->idAdmin, 'namaUMKM' => 'Bunda Riris', 'deskripsi' => '-', 'alamat' => 'Bekasi', 'noTelepon' => '0811111111']);

        // Without settings, the profile number is used.
        $this->checkout()->assertJsonPath('whatsapp', '0811111111')->assertJsonPath('pembayaran.nomorRekening', null);

        Pengaturan::create(['namaBank' => 'BCA', 'nomorRekening' => '1234567890', 'atasNama' => 'Kuat Arisdyati', 'nomorWhatsApp' => '6285222222222']);
        $this->checkout()
            ->assertJsonPath('whatsapp', '6285222222222')
            ->assertJsonPath('pembayaran', ['namaBank' => 'BCA', 'nomorRekening' => '1234567890', 'atasNama' => 'Kuat Arisdyati']);
    }

    public function test_checkout_rejects_bad_input(): void
    {
        $this->checkout(['items' => []])->assertJsonPath('errors.items.0', 'Keranjang masih kosong.');
        $this->checkout(['noWhatsApp' => '12345'])->assertJsonPath('errors.noWhatsApp.0', 'Nomor WhatsApp tidak valid. Contoh: 081234567890.');
        $this->checkout(['noWhatsApp' => '0212345678'])->assertJsonValidationErrors('noWhatsApp'); // landline
        // 16 digits once stored as 62..., one more than the column holds.
        $this->checkout(['noWhatsApp' => '6281234567890123'])->assertJsonValidationErrors('noWhatsApp');
        $this->checkout(['noWhatsApp' => '0812-3456-7890-123'])->assertJsonValidationErrors('noWhatsApp');
        $this->checkout(['pengiriman' => 'Drone'])->assertJsonValidationErrors('pengiriman');
        $this->checkout(['namaPelanggan' => ''])->assertJsonValidationErrors('namaPelanggan');
        $this->checkout(['items' => [['idProduk' => $this->abon->idProduk, 'jumlah' => 0]]])->assertJsonPath('errors', ['items.0.jumlah' => ['Jumlah tiap produk 1 sampai 50.']]);
        $this->checkout(['items' => [['idProduk' => $this->abon->idProduk, 'jumlah' => 51]]])->assertJsonValidationErrors('items.0.jumlah');
        $this->checkout(['items' => [['idProduk' => $this->abon->idProduk, 'jumlah' => 1], ['idProduk' => $this->abon->idProduk, 'jumlah' => 1]]])
            ->assertJsonValidationErrors('items.1.idProduk');

        $this->assertDatabaseCount('tb_pesanan', 0);
    }

    public function test_hidden_or_missing_products_cannot_be_ordered(): void
    {
        $this->keripik->update(['tampil' => false]);

        $this->checkout()->assertUnprocessable()
            ->assertJsonPath('errors.items.0', 'Keripik Pedas sudah tidak tersedia. Hapus dari keranjang lalu coba lagi.');
        $this->checkout(['items' => [['idProduk' => 99999, 'jumlah' => 1]]])
            ->assertJsonPath('errors.items.0', 'Ada produk yang sudah tidak tersedia. Hapus dari keranjang lalu coba lagi.');

        $this->assertDatabaseCount('tb_pesanan', 0);
    }

    public function test_orders_and_customer_data_are_admin_only(): void
    {
        $pesanan = $this->pesanan();

        $this->getJson('/api/pesanan')->assertUnauthorized();
        $this->getJson("/api/pesanan/{$pesanan->idPesanan}")->assertUnauthorized();
        $this->patchJson("/api/pesanan/{$pesanan->idPesanan}/status", ['status' => 'Lunas'])->assertUnauthorized();
        $this->getJson('/api/pengaturan')->assertUnauthorized();
        $this->putJson('/api/pengaturan', ['namaBank' => 'BRI'])->assertUnauthorized();
    }

    public function test_admin_lists_orders_newest_first_and_filters_by_status(): void
    {
        $lama = $this->pesanan();
        $this->travel(1)->hours();
        $baru = $this->pesanan('Lunas');

        Sanctum::actingAs($this->admin);
        $this->assertSame([$baru->idPesanan, $lama->idPesanan], array_column($this->getJson('/api/pesanan')->assertOk()->json(), 'idPesanan'));
        $this->assertSame([$baru->idPesanan], array_column($this->getJson('/api/pesanan?status=Lunas')->json(), 'idPesanan'));
        $this->getJson('/api/pesanan')->assertJsonPath('0.detail.0.namaProduk', 'Abon Sapi');
        $this->getJson('/api/pesanan?status=Hilang')->assertJsonValidationErrors('status');
    }

    public function test_only_valid_status_changes_are_accepted(): void
    {
        Sanctum::actingAs($this->admin);
        $ubah = fn (Pesanan $p, string $status) => $this->patchJson("/api/pesanan/{$p->idPesanan}/status", ['status' => $status]);

        $pesanan = $this->pesanan();
        $ubah($pesanan, 'Selesai')->assertUnprocessable()
            ->assertJsonPath('message', 'Status pesanan tidak bisa diubah dari Menunggu Pembayaran ke Selesai.');
        $ubah($pesanan, 'Lunas')->assertOk()->assertJsonPath('pesanan.status', 'Lunas');
        $ubah($pesanan, 'Dibatalkan')->assertUnprocessable();
        $ubah($pesanan, 'Menunggu Pembayaran')->assertUnprocessable();
        $ubah($pesanan, 'Selesai')->assertOk()->assertJsonPath('pesanan.status', 'Selesai');
        $ubah($pesanan, 'Lunas')->assertUnprocessable();

        $batal = $this->pesanan();
        $ubah($batal, 'Dibatalkan')->assertOk();
        $ubah($batal, 'Lunas')->assertUnprocessable();

        $ubah($batal, 'Hilang')->assertJsonValidationErrors('status');
    }

    public function test_marking_paid_records_the_paid_date_and_income_exactly_once(): void
    {
        Sanctum::actingAs($this->admin);
        $pesanan = $this->pesanan();
        $this->travelTo('2026-11-21 15:30:00');

        $this->patchJson("/api/pesanan/{$pesanan->idPesanan}/status", ['status' => 'Lunas'])
            ->assertOk()
            ->assertJsonPath('pesanan.tanggalLunas', '2026-11-21T08:30:00.000000Z');

        $this->assertSame(1, LaporanKeuangan::count());
        $laporan = LaporanKeuangan::first();
        $this->assertSame('Pemasukan', $laporan->jenis);
        $this->assertSame('127501.50', $laporan->jumlah);
        $this->assertSame('2026-11-21', $laporan->tanggal->format('Y-m-d'));
        $this->assertSame('Pesanan BR-20261121-0001', $laporan->namaSumber);
        $this->assertSame($pesanan->idPesanan, $laporan->idPesanan);

        // Repeating the request or finishing the order never adds a second record.
        $this->patchJson("/api/pesanan/{$pesanan->idPesanan}/status", ['status' => 'Lunas'])->assertUnprocessable();
        $this->patchJson("/api/pesanan/{$pesanan->idPesanan}/status", ['status' => 'Selesai'])->assertOk();
        $this->assertSame(1, LaporanKeuangan::count());

        // The automatic record shows up in the monthly summary like any income.
        $this->getJson('/api/laporan-keuangan/ringkasan?tahun=2026')->assertJsonPath('total.pemasukan', 127501.5);
    }

    public function test_automatic_income_cannot_be_edited_or_deleted(): void
    {
        Sanctum::actingAs($this->admin);
        $pesanan = $this->pesanan();
        $this->patchJson("/api/pesanan/{$pesanan->idPesanan}/status", ['status' => 'Lunas'])->assertOk();
        $laporan = LaporanKeuangan::first();

        $this->getJson('/api/laporan-keuangan')->assertJsonPath('0.idPesanan', $pesanan->idPesanan);
        $this->putJson("/api/laporan-keuangan/{$laporan->idLK}", ['jenis' => 'Pemasukan', 'namaSumber' => 'x', 'jumlah' => 1, 'tanggal' => '2026-11-21'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Catatan ini dibuat otomatis dari pesanan, jadi tidak bisa diubah atau dihapus.');
        $this->deleteJson("/api/laporan-keuangan/{$laporan->idLK}")->assertUnprocessable();
        $this->assertSame('127501.50', $laporan->fresh()->jumlah);

        // Records typed in by hand stay editable, and cannot be linked to an order.
        $id = $this->postJson('/api/laporan-keuangan', ['jenis' => 'Pemasukan', 'namaSumber' => 'Bazar', 'jumlah' => 1000, 'tanggal' => '2026-11-21', 'idPesanan' => $pesanan->idPesanan])
            ->assertCreated()->json('laporan.idLK');
        $this->assertNull(LaporanKeuangan::find($id)->idPesanan);
        $this->deleteJson("/api/laporan-keuangan/{$id}")->assertOk();
    }

    public function test_unpaid_orders_are_cancelled_after_24_hours_when_orders_are_opened(): void
    {
        $lama = $this->pesanan();
        $lunas = $this->pesanan('Lunas');
        $this->travel(23)->hours();
        $baru = $this->pesanan();
        $this->travel(1)->hours(); // $lama and $lunas are now exactly 24 hours old

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/pesanan')->assertOk();

        $this->assertSame('Dibatalkan', $lama->fresh()->status);
        $this->assertSame('Lunas', $lunas->fresh()->status);
        $this->assertSame('Menunggu Pembayaran', $baru->fresh()->status);
    }

    public function test_an_expired_order_cannot_be_marked_paid(): void
    {
        $pesanan = $this->pesanan();
        $this->travel(25)->hours();

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/pesanan/{$pesanan->idPesanan}/status", ['status' => 'Lunas'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Status pesanan tidak bisa diubah dari Dibatalkan ke Lunas.');
        $this->assertSame(0, LaporanKeuangan::count());
    }

    public function test_admin_updates_settings_and_checkout_shows_them(): void
    {
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/pengaturan')->assertOk()->assertJsonPath('namaBank', null);

        $this->putJson('/api/pengaturan', ['namaBank' => 'BRI', 'nomorRekening' => '0123-01-000123-50-1', 'atasNama' => 'Kuat Arisdyati', 'nomorWhatsApp' => '+62 857-1111-2222'])
            ->assertOk()
            ->assertJsonPath('pengaturan.nomorWhatsApp', '6285711112222');
        $this->putJson('/api/pengaturan', ['nomorWhatsApp' => '021-555'])->assertJsonValidationErrors('nomorWhatsApp');
        $this->putJson('/api/pengaturan', ['nomorWhatsApp' => '6281234567890123'])->assertJsonValidationErrors('nomorWhatsApp');
        $this->putJson('/api/pengaturan', ['nomorWhatsApp' => '0812-3456-7890-12'])->assertOk()
            ->assertJsonPath('pengaturan.nomorWhatsApp', '628123456789012');
        $this->putJson('/api/pengaturan', ['nomorWhatsApp' => '+62 857-1111-2222'])->assertOk();
        $this->putJson('/api/pengaturan', ['nomorRekening' => 'abc'])->assertJsonValidationErrors('nomorRekening');

        $this->getJson('/api/public/pengaturan')->assertOk()
            ->assertJsonPath('pembayaran.namaBank', 'BRI')
            ->assertJsonPath('whatsapp', '6285711112222');
        $this->assertSame(1, Pengaturan::count());
    }

    public function test_deleting_a_product_keeps_its_name_on_old_orders(): void
    {
        $pesanan = $this->pesanan();
        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/produk/{$this->abon->idProduk}")->assertOk();

        $this->getJson("/api/pesanan/{$pesanan->idPesanan}")
            ->assertJsonPath('detail.0.namaProduk', 'Abon Sapi')
            ->assertJsonPath('detail.0.idProduk', null);
    }
}
