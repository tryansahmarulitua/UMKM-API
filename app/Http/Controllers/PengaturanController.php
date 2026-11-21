<?php

namespace App\Http\Controllers;

use App\Models\Pengaturan;
use App\Support\NomorWhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Transfer account and WhatsApp number for orders (Project Plan §1.2, FT11). */
class PengaturanController extends Controller
{
    private const KOLOM = ['namaBank', 'nomorRekening', 'atasNama', 'nomorWhatsApp'];

    /** What the checkout page needs: payment details and the WhatsApp number. */
    public function publicShow(): JsonResponse
    {
        return response()->json(Pengaturan::untukPembeli());
    }

    public function show(): JsonResponse
    {
        return response()->json(Pengaturan::aktif()->only(self::KOLOM));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'namaBank' => ['nullable', 'string', 'max:50'],
            'nomorRekening' => ['nullable', 'string', 'max:30', 'regex:/^[0-9][0-9\s-]{4,29}$/'],
            'atasNama' => ['nullable', 'string', 'max:100'],
            'nomorWhatsApp' => ['nullable', 'string', 'max:20', 'regex:'.NomorWhatsApp::REGEX],
        ]);
        if (array_key_exists('nomorWhatsApp', $data)) {
            $data['nomorWhatsApp'] = NomorWhatsApp::rapikan($data['nomorWhatsApp']);
        }

        $pengaturan = Pengaturan::aktif();
        $pengaturan->fill($data)->save();

        return response()->json(['message' => 'Pengaturan berhasil disimpan.', 'pengaturan' => $pengaturan->only(self::KOLOM)]);
    }
}
