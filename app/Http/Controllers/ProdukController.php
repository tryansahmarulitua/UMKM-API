<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use App\Support\FotoUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProdukController extends Controller
{
    private const RELASI = ['kategori:idKategori,namaKategori', 'rasa:idRasa,namaRasa'];

    public function index(): JsonResponse
    {
        return response()->json(Produk::with(self::RELASI)->orderByDesc('created_at')->get());
    }

    /**
     * Products shown on the public landing page.
     */
    public function publicIndex(): JsonResponse
    {
        $produk = Produk::with(self::RELASI)->where('tampil', true)->orderByDesc('created_at')->get();

        return response()->json($produk->makeHidden('idAdmin'));
    }

    public function show(Produk $produk): JsonResponse
    {
        return response()->json($produk->load(self::RELASI));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['idAdmin'] = $request->user()->getAuthIdentifier();
        $data['foto'] = $request->hasFile('foto') ? FotoUploader::simpan($request->file('foto'), 'produk') : null;

        $produk = Produk::create($data);
        $this->simpanRasa($request, $produk);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan.',
            'produk'  => $produk->load(self::RELASI),
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
            $data['foto'] = FotoUploader::simpan($request->file('foto'), 'produk');
        }

        $produk->update($data);
        $this->simpanRasa($request, $produk);

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'produk'  => $produk->fresh(self::RELASI),
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
            'foto'       => FotoUploader::RULES,
            'rasa'       => ['nullable', 'array'],
            'rasa.*'     => ['integer', 'distinct', 'exists:tb_rasa,idRasa'],
        ]);
        $data['tampil'] = $data['tampil'] === 'Y';
        unset($data['rasa']);

        return $data;
    }

    /**
     * Flavour labels from the dashboard form. FormData cannot send an empty array, so unticking every
     * label arrives as rasa="" (null); a request without the field leaves the labels unchanged.
     */
    private function simpanRasa(Request $request, Produk $produk): void
    {
        if ($request->exists('rasa')) {
            $produk->rasa()->sync(array_filter((array) $request->input('rasa')));
        }
    }
}
