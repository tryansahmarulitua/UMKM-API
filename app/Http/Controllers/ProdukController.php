<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProdukController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(): JsonResponse
    {
        $produk = Produk::with(['kategori:idKategori,namaKategori'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($produk);
    }

    /**     * Public storefront products.
     */
    public function publicIndex(): JsonResponse
    {
        return response()->json(Produk::with('kategori:idKategori,namaKategori')
            ->where('tampil', true)->orderByDesc('created_at')->get());
    }

    /**     * Show the specified product.
     */
    public function show(Produk $produk): JsonResponse
    {
        return response()->json($produk->load('kategori:idKategori,namaKategori'));
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): JsonResponse
    {
        return response()->json(['message' => 'Create product form']);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idKategori' => ['required', 'exists:tb_kategoriproduk,idKategori'],
            'namaProduk' => ['required', 'string', 'max:100'],
            'deskripsi'  => ['required', 'string'],
            'harga'      => ['required', 'numeric', 'min:0'],
            'tampil'     => ['required', 'in:Y,N'],
            'foto'       => ['nullable', 'image', 'max:2048'],
        ]);

        $validated['tampil'] = $validated['tampil'] === 'Y';

        $fotoPath = null;
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('produk', 'public');
        }

        $produk = Produk::create([
            'idKategori' => $validated['idKategori'],
            'idAdmin'    => $request->user()->getAuthIdentifier(),
            'namaProduk' => $validated['namaProduk'],
            'deskripsi'  => $validated['deskripsi'],
            'harga'      => $validated['harga'],
            'tampil'     => $validated['tampil'],
            'foto'       => $fotoPath,
        ]);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan.',
            'produk'  => $produk->load('kategori:idKategori,namaKategori'),
        ], 201);
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Produk $produk): JsonResponse
    {
        return response()->json(['message' => 'Edit product form', 'produk' => $produk]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, Produk $produk): JsonResponse
    {
        $validated = $request->validate([
            'idKategori' => ['required', 'exists:tb_kategoriproduk,idKategori'],
            'namaProduk' => ['required', 'string', 'max:100'],
            'deskripsi'  => ['required', 'string'],
            'harga'      => ['required', 'numeric', 'min:0'],
            'tampil'     => ['required', 'in:Y,N'],
            'foto'       => ['nullable', 'image', 'max:2048'],
        ]);

        $validated['tampil'] = $validated['tampil'] === 'Y';

        if ($request->hasFile('foto')) {
            if ($produk->foto) {
                Storage::disk('public')->delete($produk->foto);
            }
            $validated['foto'] = $request->file('foto')->store('produk', 'public');
        }

        $produk->update([
            'idKategori' => $validated['idKategori'],
            'namaProduk' => $validated['namaProduk'],
            'deskripsi'  => $validated['deskripsi'],
            'harga'      => $validated['harga'],
            'tampil'     => $validated['tampil'],
            'foto'       => $validated['foto'] ?? $produk->foto,
        ]);

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'produk'  => $produk->fresh('kategori:idKategori,namaKategori'),
        ]);
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Produk $produk): JsonResponse
    {
        if ($produk->foto) {
            Storage::disk('public')->delete($produk->foto);
        }

        $produk->delete();

        return response()->json(['message' => 'Produk berhasil dihapus.']);
    }
}