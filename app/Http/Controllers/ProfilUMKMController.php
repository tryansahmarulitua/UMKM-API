<?php

namespace App\Http\Controllers;

use App\Models\ProfilUMKM;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilUMKMController extends Controller
{
    /**     * Get public UMKM profile.
     */
    public function publicShow(): JsonResponse
    {
        $profil = ProfilUMKM::latest('updated_at')->first();
        return response()->json($profil);
    }

    /**     * Get profil UMKM untuk admin yang login
     */
    public function show(): JsonResponse
    {
        $profil = ProfilUMKM::where('idAdmin', auth()->id())->first();

        if (!$profil) {
            return response()->json(['message' => 'Profil belum dibuat.', 'profil' => null], 404);
        }

        return response()->json($profil);
    }

    /**
     * Simpan atau update profil UMKM
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'namaUMKM'   => ['required', 'string', 'max:100'],
            'deskripsi'  => ['required', 'string'],
            'alamat'     => ['required', 'string', 'max:255'],
            'noTelepon'  => ['required', 'string', 'max:20'],
            'email'      => ['nullable', 'email', 'max:100'],
            'mediaSosial' => ['nullable', 'string', 'max:255'],
            'foto'       => ['nullable', 'image', 'max:2048'],
        ]);

        $profil = ProfilUMKM::where('idAdmin', auth()->id())->first();

        if ($profil) {
            // Update existing
            if ($request->hasFile('foto')) {
                if ($profil->foto) {
                    Storage::disk('public')->delete($profil->foto);
                }
                $validated['foto'] = $request->file('foto')->store('profil', 'public');
            }

            $updateData = collect($validated)->except('foto')->toArray();
            if ($request->hasFile('foto')) {
                if ($profil->foto) {
                    Storage::disk('public')->delete($profil->foto);
                }
                $updateData['foto'] = $request->file('foto')->store('profil', 'public');
            }

            $profil->update($updateData);

            return response()->json(['message' => 'Profil berhasil diperbarui.', 'profil' => $profil->fresh()]);
        }

        // Create new
        $fotoPath = null;
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('profil', 'public');
        }

        $profil = ProfilUMKM::create([
            'idAdmin'     => auth()->id(),
            'namaUMKM'    => $validated['namaUMKM'],
            'deskripsi'   => $validated['deskripsi'],
            'alamat'      => $validated['alamat'],
            'noTelepon'   => $validated['noTelepon'],
            'email'       => $validated['email'] ?? null,
            'mediaSosial' => $validated['mediaSosial'] ?? null,
            'foto'        => $fotoPath,
        ]);

        return response()->json(['message' => 'Profil berhasil dibuat.', 'profil' => $profil], 201);
    }
}
