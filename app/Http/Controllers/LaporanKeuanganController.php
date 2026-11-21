<?php

namespace App\Http\Controllers;

use App\Models\LaporanKeuangan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class LaporanKeuanganController extends Controller
{
    private const RULES = [
        'jenis'       => ['required', 'in:Pemasukan,Pengeluaran,Modal'],
        'namaSumber'  => ['required', 'string', 'max:100'],
        'jumlah'      => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'], // decimal(15,2)
        'tanggal'     => ['required', 'date'],
        'keterangan'  => ['nullable', 'string', 'max:100'],
    ];

    public function index(Request $request): JsonResponse
    {
        $laporan = LaporanKeuangan::where('idAdmin', $request->user()->getAuthIdentifier())
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($laporan);
    }

    public function show(Request $request, LaporanKeuangan $laporan): JsonResponse
    {
        $this->ensureOwner($request, $laporan);

        return response()->json($laporan);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(self::RULES);

        $laporan = LaporanKeuangan::create([
            'idAdmin' => $request->user()->getAuthIdentifier(),
            ...$validated,
        ]);

        return response()->json(['message' => 'Laporan keuangan berhasil ditambahkan.', 'laporan' => $laporan], 201);
    }

    public function update(Request $request, LaporanKeuangan $laporan): JsonResponse
    {
        $this->ensureOwner($request, $laporan);
        $this->ensureManual($laporan);

        $laporan->update($request->validate(self::RULES));

        return response()->json(['message' => 'Laporan keuangan berhasil diperbarui.', 'laporan' => $laporan]);
    }

    public function destroy(Request $request, LaporanKeuangan $laporan): JsonResponse
    {
        $this->ensureOwner($request, $laporan);
        $this->ensureManual($laporan);
        $laporan->delete();

        return response()->json(['message' => 'Laporan keuangan berhasil dihapus.']);
    }

    /** Another admin's report answers exactly like a missing one, so guessing IDs reveals nothing. */
    private function ensureOwner(Request $request, LaporanKeuangan $laporan): void
    {
        if ($laporan->getAttribute('idAdmin') != $request->user()->getAuthIdentifier()) {
            throw new HttpResponseException(self::tidakDitemukan());
        }
    }

    /** Income recorded from a paid order is locked, so the books always match the orders. */
    private function ensureManual(LaporanKeuangan $laporan): void
    {
        if ($laporan->idPesanan !== null) {
            throw new HttpResponseException(response()->json(
                ['message' => 'Catatan ini dibuat otomatis dari pesanan, jadi tidak bisa diubah atau dihapus.'], 422,
            ));
        }
    }

    public static function tidakDitemukan(): JsonResponse
    {
        return response()->json(['message' => 'Laporan tidak ditemukan.'], 404);
    }
}
