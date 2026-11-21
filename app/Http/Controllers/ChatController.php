<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use App\Models\ProfilUMKM;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'history' => ['sometimes', 'array', 'max:12'],
            'history.*.role' => ['required', 'in:user,model'],
            'history.*.text' => ['required', 'string', 'max:2000'],
        ]);

        $profile = ProfilUMKM::latest('updated_at')->first();
        $products = Produk::with('kategori:idKategori,namaKategori')
            ->where('tampil', true)
            ->get(['idProduk', 'idKategori', 'namaProduk', 'deskripsi', 'harga']);

        $context = [
            'Profil UMKM' => $profile?->only(['namaUMKM', 'deskripsi', 'alamat', 'noTelepon', 'email']),
            'Produk tersedia' => $products->map(fn (Produk $product) => [
                'nama' => $product->namaProduk,
                'kategori' => $product->kategori?->namaKategori,
                'deskripsi' => $product->deskripsi,
                'harga' => $product->harga,
            ])->values()->all(),
        ];

        $contents = collect($validated['history'] ?? [])
            ->map(fn (array $turn) => [
                'role' => $turn['role'],
                'parts' => [['text' => $turn['text']]],
            ])->push([
                'role' => 'user',
                'parts' => [['text' => $validated['message']]],
            ])->values()->all();

        $businessName = $profile?->namaUMKM ?? config('app.name');

        try {
            $response = Http::timeout(30)
                ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/' . config('services.gemini.model') . ':generateContent', [
                    'systemInstruction' => [
                        'parts' => [[
                            'text' => "Kamu adalah asisten ramah untuk {$businessName}. " . 'Jawab dalam bahasa Indonesia. Gunakan hanya data konteks berikut. Jika data tidak tersedia, katakan pengguna dapat menghubungi pemilik UMKM. Jangan mengarang harga, stok, alamat, kontak, atau kebijakan. Jawaban ringkas dan praktis. Konteks: ' . json_encode($context, JSON_UNESCAPED_UNICODE),
                        ]],
                    ],
                    'contents' => $contents,
                    'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 400],
                ]);

            if ($response->failed()) {
                Log::warning('Gemini chat request failed', ['status' => $response->status()]);
                return response()->json(['message' => 'Maaf, asisten sedang tidak tersedia.'], 502);
            }

            $answer = data_get($response->json(), 'candidates.0.content.parts.0.text');
            if (!is_string($answer) || trim($answer) === '') {
                return response()->json(['message' => 'Maaf, asisten belum dapat menjawab.'], 502);
            }

            return response()->json(['answer' => trim($answer)]);
        } catch (\Throwable $exception) {
            Log::error('Gemini chat request error', ['message' => $exception->getMessage()]);
            return response()->json(['message' => 'Maaf, asisten sedang tidak tersedia.'], 502);
        }
    }
}
