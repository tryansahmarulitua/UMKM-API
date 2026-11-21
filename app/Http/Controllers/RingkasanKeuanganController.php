<?php

namespace App\Http\Controllers;

use App\Models\LaporanKeuangan;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Daily, weekly and monthly finance summaries (Project Plan §1.2, FT10).
 *
 * - bulanan: one row per month of `tahun`;
 * - mingguan: one row per Monday-Sunday week of `bulan`, cut at the month edges so weeks add up to the month;
 * - harian: one row per day of `bulan`.
 *
 * Only periods with records get a row. Capital (modal) adds to the balance but is not profit:
 * laba = pemasukan - pengeluaran, saldo = modal + pemasukan - pengeluaran.
 * Amounts are summed in whole cents so the totals match a manual calculation exactly.
 */
class RingkasanKeuanganController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $input = $request->validate([
            'periode' => ['nullable', 'in:harian,mingguan,bulanan'],
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'bulan' => ['nullable', 'regex:/^(20\d\d|2100)-(0[1-9]|1[0-2])$/'],
        ]);
        $periode = $input['periode'] ?? 'bulanan';
        $today = CarbonImmutable::today();

        if ($periode === 'bulanan') {
            $mulai = $today->setDate((int) ($input['tahun'] ?? $today->year), 1, 1);
            $sampai = $mulai->endOfYear()->startOfDay();
        } else {
            $mulai = isset($input['bulan']) ? CarbonImmutable::createFromFormat('!Y-m', $input['bulan']) : $today->startOfMonth();
            $sampai = $mulai->endOfMonth()->startOfDay();
        }

        $records = LaporanKeuangan::where('idAdmin', $request->user()->getAuthIdentifier())
            ->get(['jenis', 'jumlah', 'tanggal']);

        $saldoAwal = 0;
        $kelompok = [];
        foreach ($records as $record) {
            $tanggal = CarbonImmutable::parse($record->tanggal->format('Y-m-d'));
            $sen = (int) round(((float) $record->jumlah) * 100);

            if ($tanggal->lt($mulai)) {
                $saldoAwal += $record->jenis === 'Pengeluaran' ? -$sen : $sen;
            } elseif ($tanggal->lte($sampai)) {
                [$awal, $akhir] = self::rentang($periode, $tanggal, $mulai, $sampai);
                $kelompok[$awal] ??= ['sampai' => $akhir, 'Modal' => 0, 'Pemasukan' => 0, 'Pengeluaran' => 0];
                $kelompok[$awal][$record->jenis] += $sen;
            }
        }
        ksort($kelompok);

        $saldo = $saldoAwal;
        $total = ['Modal' => 0, 'Pemasukan' => 0, 'Pengeluaran' => 0];
        $rincian = [];
        foreach ($kelompok as $awal => $jumlah) {
            $saldo += $jumlah['Modal'] + $jumlah['Pemasukan'] - $jumlah['Pengeluaran'];
            foreach (array_keys($total) as $jenis) {
                $total[$jenis] += $jumlah[$jenis];
            }
            $rincian[] = ['mulai' => $awal, 'sampai' => $jumlah['sampai'], ...self::baris($jumlah, $saldo)];
        }

        $tahunTersedia = $records->toBase()
            ->map(fn (LaporanKeuangan $record) => $record->tanggal->year)
            ->push($today->year)
            ->unique()->sort()->values()->all();

        return response()->json([
            'periode' => $periode,
            'mulai' => $mulai->toDateString(),
            'sampai' => $sampai->toDateString(),
            'tahunTersedia' => $tahunTersedia,
            'saldoAwal' => self::rupiah($saldoAwal),
            'rincian' => $rincian,
            'total' => self::baris($total, $saldo),
        ]);
    }

    /** First and last day of the row a date belongs to. */
    private static function rentang(string $periode, CarbonImmutable $tanggal, CarbonImmutable $mulai, CarbonImmutable $sampai): array
    {
        [$awal, $akhir] = match ($periode) {
            'harian' => [$tanggal, $tanggal],
            'mingguan' => [
                $tanggal->startOfWeek(CarbonImmutable::MONDAY)->max($mulai),
                $tanggal->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay()->min($sampai),
            ],
            'bulanan' => [$tanggal->startOfMonth(), $tanggal->endOfMonth()],
        };

        return [$awal->toDateString(), $akhir->toDateString()];
    }

    private static function baris(array $sen, int $saldoAkhir): array
    {
        return [
            'modal' => self::rupiah($sen['Modal']),
            'pemasukan' => self::rupiah($sen['Pemasukan']),
            'pengeluaran' => self::rupiah($sen['Pengeluaran']),
            'laba' => self::rupiah($sen['Pemasukan'] - $sen['Pengeluaran']),
            'saldoAkhir' => self::rupiah($saldoAkhir),
        ];
    }

    /** Whole rupiah stay integers in JSON (2000000, not 2000000.0). */
    private static function rupiah(int $sen): int|float
    {
        return $sen % 100 === 0 ? intdiv($sen, 100) : $sen / 100;
    }
}
