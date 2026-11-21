<?php

namespace App\Http\Controllers;

use App\Models\LaporanKeuangan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LaporanKeuanganController extends Controller
{
    private const RULES = [
        'jenis'       => ['required', 'in:Pemasukan,Pengeluaran'],
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

        $laporan->update($request->validate(self::RULES));

        return response()->json(['message' => 'Laporan keuangan berhasil diperbarui.', 'laporan' => $laporan]);
    }

    public function destroy(Request $request, LaporanKeuangan $laporan): JsonResponse
    {
        $this->ensureOwner($request, $laporan);
        $laporan->delete();

        return response()->json(['message' => 'Laporan keuangan berhasil dihapus.']);
    }

    private function ensureOwner(Request $request, LaporanKeuangan $laporan): void
    {
        abort_unless($laporan->getAttribute('idAdmin') == $request->user()->getAuthIdentifier(), 403, 'Laporan ini bukan milik akun Anda.');
    }
}
