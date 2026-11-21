<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProdukController extends Controller
{
    private const KATEGORI = 'kategori:idKategori,namaKategori';

    public function index(): JsonResponse
    {
        return response()->json(Produk::with(self::KATEGORI)->orderByDesc('created_at')->get());
    }

    /**
     * Products shown on the public landing page.
     */
    public function publicIndex(): JsonResponse
    {
        return response()->json(Produk::with(self::KATEGORI)->where('tampil', true)->orderByDesc('created_at')->get());
    }

    public function show(Produk $produk): JsonResponse
    {
        return response()->json($produk->load(self::KATEGORI));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['idAdmin'] = $request->user()->getAuthIdentifier();
        $data['foto'] = $request->hasFile('foto') ? $request->file('foto')->store('produk', 'public') : null;

        $produk = Produk::create($data);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan.',
            'produk'  => $produk->load(self::KATEGORI),
        ], 201);
    }

    public function update(Request $request, Produk $produk): JsonResponse
    {
        $data = $this->validated($request);
        unset($data['foto']); // keep the current photo unless a new file is uploaded

        if ($request->hasFile('foto')) {
            if ($produk->foto) {
                Storage::disk('public')->delete($produk->foto);
            }
            $data['foto'] = $request->file('foto')->store('produk', 'public');
        }

        $produk->update($data);

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'produk'  => $produk->fresh(self::KATEGORI),
        ]);
    }

    public function destroy(Produk $produk): JsonResponse
    {
        if ($produk->foto) {
            Storage::disk('public')->delete($produk->foto);
        }

        $produk->delete();

        return response()->json(['message' => 'Produk berhasil dihapus.']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'idKategori' => ['required', 'exists:tb_kategoriproduk,idKategori'],
            'namaProduk' => ['required', 'string', 'max:100'],
            'deskripsi'  => ['required', 'string', 'max:255'],
            'harga'      => ['required', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
            'tampil'     => ['required', 'in:Y,N'],
            'foto'       => ['nullable', 'image', 'max:2048'],
        ]);
        $data['tampil'] = $data['tampil'] === 'Y';

        return $data;
    }
}
