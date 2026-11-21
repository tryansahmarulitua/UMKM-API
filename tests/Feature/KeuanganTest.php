<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\LaporanKeuangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Project Plan §1.2 and FT10: income, expenses and business capital (modal), with daily, weekly and
 * monthly summaries that match a manual calculation. Capital adds to the balance but is not profit.
 */
class KeuanganTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::create(['nama' => 'Owner', 'email' => 'owner@bunda.id', 'password' => 'rahasia123']);
    }

    private function catat(string $jenis, float $jumlah, string $tanggal, ?Admin $admin = null): void
    {
        LaporanKeuangan::create([
            'idAdmin' => ($admin ?? $this->admin)->idAdmin, 'jenis' => $jenis,
            'namaSumber' => $jenis, 'jumlah' => $jumlah, 'tanggal' => $tanggal,
        ]);
    }

    public function test_capital_can_be_recorded_as_its_own_type(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/laporan-keuangan', ['jenis' => 'Modal', 'namaSumber' => 'Tabungan pribadi', 'jumlah' => 2000000, 'tanggal' => '2026-10-01'])
            ->assertCreated()
            ->assertJsonPath('laporan.jenis', 'Modal');

        $this->postJson('/api/laporan-keuangan', ['jenis' => 'Hutang', 'namaSumber' => 'x', 'jumlah' => 1, 'tanggal' => '2026-10-01'])
            ->assertJsonValidationErrors('jenis');
    }

    private function dataContoh(): void
    {
        // December 2025 carries into 2026 as the opening balance.
        $this->catat('Modal', 500000, '2025-12-20');
        $this->catat('Pengeluaran', 100000, '2025-12-21');
        // October 2026
        $this->catat('Modal', 2000000, '2026-10-01');
        $this->catat('Pemasukan', 750000, '2026-10-05');
        $this->catat('Pemasukan', 500000.50, '2026-10-28');
        $this->catat('Pengeluaran', 800000, '2026-10-30');
        // November 2026: Sunday the 1st, Monday the 2nd (two records), Monday the 30th.
        $this->catat('Pemasukan', 300000, '2026-11-01');
        $this->catat('Pemasukan', 1600000, '2026-11-02');
        $this->catat('Pengeluaran', 50000, '2026-11-02');
        $this->catat('Pengeluaran', 600000.25, '2026-11-30');
        // Another admin's money never appears.
        $other = Admin::create(['nama' => 'Lain', 'email' => 'lain@bunda.id', 'password' => 'rahasia123']);
        $this->catat('Pemasukan', 9999999, '2026-10-10', $other);
        Sanctum::actingAs($this->admin);
    }

    public function test_monthly_summary_matches_a_manual_calculation(): void
    {
        $this->dataContoh();

        $response = $this->getJson('/api/laporan-keuangan/ringkasan?periode=bulanan&tahun=2026')->assertOk();

        $response->assertJsonPath('periode', 'bulanan')
            ->assertJsonPath('mulai', '2026-01-01')
            ->assertJsonPath('sampai', '2026-12-31')
            ->assertJsonPath('saldoAwal', 400000)
            ->assertJsonPath('tahunTersedia', [2025, 2026]);

        $this->assertSame([
            ['mulai' => '2026-10-01', 'sampai' => '2026-10-31', 'modal' => 2000000, 'pemasukan' => 1250000.5, 'pengeluaran' => 800000, 'laba' => 450000.5, 'saldoAkhir' => 2850000.5],
            ['mulai' => '2026-11-01', 'sampai' => '2026-11-30', 'modal' => 0, 'pemasukan' => 1900000, 'pengeluaran' => 650000.25, 'laba' => 1249999.75, 'saldoAkhir' => 4100000.25],
        ], $response->json('rincian'));

        $this->assertSame(
            ['modal' => 2000000, 'pemasukan' => 3150000.5, 'pengeluaran' => 1450000.25, 'laba' => 1700000.25, 'saldoAkhir' => 4100000.25],
            $response->json('total'),
        );
    }

    public function test_weekly_summary_runs_monday_to_sunday_and_is_cut_at_the_month_edges(): void
    {
        $this->dataContoh();

        $response = $this->getJson('/api/laporan-keuangan/ringkasan?periode=mingguan&bulan=2026-11')->assertOk();

        $response->assertJsonPath('mulai', '2026-11-01')
            ->assertJsonPath('sampai', '2026-11-30')
            ->assertJsonPath('saldoAwal', 2850000.5);

        $this->assertSame([
            ['mulai' => '2026-11-01', 'sampai' => '2026-11-01', 'modal' => 0, 'pemasukan' => 300000, 'pengeluaran' => 0, 'laba' => 300000, 'saldoAkhir' => 3150000.5],
            ['mulai' => '2026-11-02', 'sampai' => '2026-11-08', 'modal' => 0, 'pemasukan' => 1600000, 'pengeluaran' => 50000, 'laba' => 1550000, 'saldoAkhir' => 4700000.5],
            ['mulai' => '2026-11-30', 'sampai' => '2026-11-30', 'modal' => 0, 'pemasukan' => 0, 'pengeluaran' => 600000.25, 'laba' => -600000.25, 'saldoAkhir' => 4100000.25],
        ], $response->json('rincian'));
        // The weeks add up to the same month total as the monthly summary.
        $response->assertJsonPath('total.laba', 1249999.75);
    }

    public function test_daily_summary_has_one_row_per_day_with_records(): void
    {
        $this->dataContoh();

        $rincian = $this->getJson('/api/laporan-keuangan/ringkasan?periode=harian&bulan=2026-11')->assertOk()->json('rincian');

        $this->assertSame(['2026-11-01', '2026-11-02', '2026-11-30'], array_column($rincian, 'mulai'));
        $this->assertSame(array_column($rincian, 'mulai'), array_column($rincian, 'sampai'));
        $this->assertSame(1550000, $rincian[1]['laba']);
    }

    public function test_summary_defaults_to_monthly_for_the_current_year_and_is_empty_without_records(): void
    {
        $this->travelTo('2026-11-21 10:00:00');
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/laporan-keuangan/ringkasan')
            ->assertOk()
            ->assertJsonPath('periode', 'bulanan')
            ->assertJsonPath('mulai', '2026-01-01')
            ->assertJsonPath('saldoAwal', 0)
            ->assertJsonPath('rincian', [])
            ->assertJsonPath('tahunTersedia', [2026])
            ->assertJsonPath('total.saldoAkhir', 0);

        $this->getJson('/api/laporan-keuangan/ringkasan?periode=harian')
            ->assertOk()
            ->assertJsonPath('mulai', '2026-11-01')
            ->assertJsonPath('sampai', '2026-11-30');
    }

    public function test_summary_requires_login_and_valid_parameters(): void
    {
        $this->getJson('/api/laporan-keuangan/ringkasan')->assertUnauthorized();

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/laporan-keuangan/ringkasan?tahun=abc')->assertJsonValidationErrors('tahun');
        $this->getJson('/api/laporan-keuangan/ringkasan?tahun=1999')->assertJsonValidationErrors('tahun');
        $this->getJson('/api/laporan-keuangan/ringkasan?periode=tahunan')->assertJsonValidationErrors('periode');
        $this->getJson('/api/laporan-keuangan/ringkasan?periode=harian&bulan=2026-13')->assertJsonValidationErrors('bulan');
    }
}
