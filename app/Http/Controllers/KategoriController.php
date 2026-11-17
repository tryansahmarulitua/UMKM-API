<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KategoriController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Kategori::orderBy('namaKategori')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'namaKategori' => ['required', 'string', 'max:100', 'unique:tb_kategoriproduk,namaKategori'],
        ]);

        $kategori = Kategori::create($validated);
        return response()->json(['message' => 'Kategori berhasil ditambahkan.', 'kategori' => $kategori], 201);
    }

    public function update(Request $request, Kategori $kategori): JsonResponse
    {
        $validated = $request->validate([
            'namaKategori' => ['required', 'string', 'max:100', "unique:tb_kategoriproduk,namaKategori,{$kategori->id}"],
        ]);

        $kategori->update($validated);
        return response()->json(['message' => 'Kategori berhasil diperbarui.', 'kategori' => $kategori]);
    }

    public function destroy(Kategori $kategori): JsonResponse
    {
        $kategori->delete();
        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }
}
