<?php

namespace App\Http\Controllers;

use App\Models\ProfilUMKM;
use App\Support\FotoUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilUMKMController extends Controller
{
    /**
     * Profile shown on the public landing page.
     */
    public function publicShow(): JsonResponse
    {
        return response()->json(ProfilUMKM::latest('updated_at')->first());
    }

    public function show(Request $request): JsonResponse
    {
        $profil = ProfilUMKM::where('idAdmin', $request->user()->getAuthIdentifier())->first();

        if (!$profil) {
            return response()->json(['message' => 'Profil belum dibuat.', 'profil' => null], 404);
        }

        return response()->json($profil);
    }

    /**
     * Create the admin's profile, or update it when it already exists.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'namaUMKM'   => ['required', 'string', 'max:100'],
            'deskripsi'  => ['required', 'string'],
            'alamat'     => ['required', 'string', 'max:255'],
            'noTelepon'  => ['required', 'string', 'max:15', 'regex:/^\+?[0-9][0-9\s-]{7,}$/'],
            'email'      => ['nullable', 'email', 'max:100'],
            'mediaSosial' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'foto'       => FotoUploader::RULES,
        ], [
            'noTelepon.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, tanda -, atau + di depan (contoh 0812-3456-7890).',
            'mediaSosial.url' => 'Media sosial harus berupa link lengkap, contoh https://instagram.com/namausaha.',
        ]);

        $profil = ProfilUMKM::firstOrNew(['idAdmin' => $request->user()->getAuthIdentifier()]);
        $isNew = ! $profil->exists;
        $data = collect($validated)->except('foto')->all();

        if ($request->hasFile('foto')) {
            if ($profil->foto) {
                Storage::disk('public')->delete($profil->foto);
            }
            $data['foto'] = FotoUploader::simpan($request->file('foto'), 'profil');
        }

        $profil->fill($data)->save();

        return response()->json([
            'message' => $isNew ? 'Profil berhasil dibuat.' : 'Profil berhasil diperbarui.',
            'profil'  => $profil->fresh(),
        ], $isNew ? 201 : 200);
    }
}
