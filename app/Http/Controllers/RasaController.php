<?php

namespace App\Http\Controllers;

use App\Models\Rasa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RasaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Rasa::orderBy('namaRasa')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $rasa = Rasa::create($request->validate([
            'namaRasa' => ['required', 'string', 'max:20', 'unique:tb_rasa,namaRasa'],
        ]));

        return response()->json(['message' => 'Rasa berhasil ditambahkan.', 'rasa' => $rasa], 201);
    }

    public function update(Request $request, Rasa $rasa): JsonResponse
    {
        $rasa->update($request->validate([
            'namaRasa' => ['required', 'string', 'max:20', "unique:tb_rasa,namaRasa,{$rasa->idRasa},idRasa"],
        ]));

        return response()->json(['message' => 'Rasa berhasil diperbarui.', 'rasa' => $rasa]);
    }

    /** Products keep existing; they only lose this label. */
    public function destroy(Rasa $rasa): JsonResponse
    {
        $rasa->produk()->detach();
        $rasa->delete();

        return response()->json(['message' => 'Rasa berhasil dihapus.']);
    }
}
