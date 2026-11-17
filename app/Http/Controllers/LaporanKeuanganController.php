<?php

namespace App\Http\Controllers;

use App\Models\LaporanKeuangan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LaporanKeuanganController extends Controller
{
    public function index(): JsonResponse
    {
        $laporan = LaporanKeuangan::where('idAdmin', auth()->id())
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($laporan);
    }

    public function show(LaporanKeuangan $laporan): JsonResponse
    {
        abort_unless($laporan->idAdmin === auth()->id(), 403);
        return response()->json($laporan);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'namaSumber'  => ['required', 'string', 'max:100'],
            'jumlah'      => ['required', 'numeric', 'min:0'],
            'tanggal'     => ['required', 'date'],
            'keterangan'  => ['nullable', 'string', 'max:255'],
        ]);

        $laporan = LaporanKeuangan::create([
            'idAdmin' => auth()->id(),
            ...$validated,
        ]);

        return response()->json(['message' => 'Laporan keuangan berhasil ditambahkan.', 'laporan' => $laporan], 201);
    }

    public function update(Request $request, LaporanKeuangan $laporan): JsonResponse
    {
        abort_unless($laporan->idAdmin === auth()->id(), 403);

        $validated = $request->validate([
            'namaSumber'  => ['required', 'string', 'max:100'],
            'jumlah'      => ['required', 'numeric', 'min:0'],
            'tanggal'     => ['required', 'date'],
            'keterangan'  => ['nullable', 'string', 'max:255'],
        ]);

        $laporan->update($validated);

        return response()->json(['message' => 'Laporan keuangan berhasil diperbarui.', 'laporan' => $laporan]);
    }

    public function destroy(LaporanKeuangan $laporan): JsonResponse
    {
        abort_unless($laporan->idAdmin === auth()->id(), 403);
        $laporan->delete();
        return response()->json(['message' => 'Laporan keuangan berhasil dihapus.']);
    }
}
