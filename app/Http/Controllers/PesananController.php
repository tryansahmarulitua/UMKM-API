<?php

namespace App\Http\Controllers;

use App\Models\LaporanKeuangan;
use App\Models\Pengaturan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Support\NomorWhatsApp;
use App\Support\Turnstile;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Online orders (Project Plan §1.2 and §4.5). Customers check out without an account; prices, totals
 * and order numbers are always calculated here, and anything the browser sends as a price is ignored.
 */
class PesananController extends Controller
{
    public const MAKS_JUMLAH = 50;
    public const MAKS_PRODUK = 20;

    /** Project Plan §4.5: kept loose (10) because many mobile users share one IP. */
    public const MAKS_PESANAN_PER_JAM = 10;

    public function store(Request $request): JsonResponse
    {
        // Honeypot: a field hidden from people, so only bots fill it in.
        if (filled($request->input('website'))) {
            return response()->json(['message' => 'Pesanan ditolak.'], 422);
        }

        $data = $request->validate([
            'namaPelanggan' => ['required', 'string', 'max:100'],
            'noWhatsApp' => ['required', 'string', 'max:20', 'regex:'.NomorWhatsApp::REGEX],
            'pengiriman' => ['required', 'in:Ambil sendiri,Dikirim'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAKS_PRODUK],
            'items.*.idProduk' => ['required', 'integer', 'distinct'],
            'items.*.jumlah' => ['required', 'integer', 'between:1,'.self::MAKS_JUMLAH],
            'turnstile' => ['nullable', 'string', 'max:2048'],
        ]);

        // Only orders that were actually saved count towards the hourly limit.
        $kunciBatas = 'pesanan:'.$request->ip();
        if (RateLimiter::tooManyAttempts($kunciBatas, self::MAKS_PESANAN_PER_JAM)) {
            return response()->json(['message' => 'Sudah 10 pesanan dalam satu jam dari jaringan ini. Coba lagi nanti, atau pesan langsung lewat WhatsApp.'], 429);
        }

        $ids = array_column($data['items'], 'idProduk');
        $semua = Produk::whereIn('idProduk', $ids)->get(['idProduk', 'namaProduk', 'harga', 'tampil'])->keyBy('idProduk');
        $tersedia = $semua->where('tampil', true);
        if ($tersedia->count() !== count($ids)) {
            $habis = $semua->firstWhere('tampil', false);
            throw ValidationException::withMessages(['items' => $habis
                ? "{$habis->namaProduk} sudah tidak tersedia. Hapus dari keranjang lalu coba lagi."
                : 'Ada produk yang sudah tidak tersedia. Hapus dari keranjang lalu coba lagi.']);
        }

        // Checked last: the token works only once, so a mistake elsewhere in the form must not use it up.
        if (! Turnstile::lolos($data['turnstile'] ?? null, $request->ip())) {
            return response()->json(['message' => 'Verifikasi keamanan gagal. Selesaikan verifikasi lalu coba lagi.'], 403);
        }

        // Whole cents, so the total matches a manual calculation exactly.
        $totalSen = 0;
        $baris = [];
        foreach ($data['items'] as $item) {
            $produk = $tersedia[$item['idProduk']];
            $hargaSen = (int) round(((float) $produk->harga) * 100);
            $subtotalSen = $hargaSen * $item['jumlah'];
            $totalSen += $subtotalSen;
            $baris[] = ['produk' => $produk, 'jumlah' => $item['jumlah'], 'hargaSen' => $hargaSen, 'subtotalSen' => $subtotalSen];
        }

        $pesanan = $this->simpan([
            'namaPelanggan' => $data['namaPelanggan'],
            'noWhatsApp' => NomorWhatsApp::rapikan($data['noWhatsApp']),
            'pengiriman' => $data['pengiriman'],
            'total' => $totalSen / 100,
        ], array_map(fn (array $row) => [
            'idProduk' => $row['produk']->idProduk,
            'namaProduk' => $row['produk']->namaProduk,
            'harga' => $row['hargaSen'] / 100,
            'jumlah' => $row['jumlah'],
            'subtotal' => $row['subtotalSen'] / 100,
        ], $baris));
        RateLimiter::hit($kunciBatas, 3600);

        return response()->json([
            'nomorPesanan' => $pesanan->nomorPesanan,
            'namaPelanggan' => $pesanan->namaPelanggan,
            'pengiriman' => $pesanan->pengiriman,
            'status' => Pesanan::MENUNGGU,
            'total' => self::angka($totalSen),
            'items' => array_map(fn (array $row) => [
                'namaProduk' => $row['produk']->namaProduk,
                'harga' => self::angka($row['hargaSen']),
                'jumlah' => $row['jumlah'],
                'subtotal' => self::angka($row['subtotalSen']),
            ], $baris),
            ...Pengaturan::untukPembeli(),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::in(array_keys(Pesanan::ALUR))]]);
        Pesanan::batalkanYangKedaluwarsa();

        $pesanan = Pesanan::with('detail')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('idPesanan')
            ->get();

        return response()->json($pesanan);
    }

    public function show(Pesanan $pesanan): JsonResponse
    {
        Pesanan::batalkanYangKedaluwarsa();

        return response()->json($pesanan->fresh('detail'));
    }

    public function ubahStatus(Request $request, Pesanan $pesanan): JsonResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(Pesanan::ALUR))]])['status'];
        Pesanan::batalkanYangKedaluwarsa();

        $pesanan = DB::transaction(function () use ($request, $pesanan, $status) {
            // Read the row again inside the transaction, so two quick clicks cannot both pass the check.
            $pesanan = Pesanan::lockForUpdate()->findOrFail($pesanan->idPesanan);
            if (! $pesanan->bolehMenjadi($status)) {
                throw new HttpResponseException(response()->json(
                    ['message' => "Status pesanan tidak bisa diubah dari {$pesanan->status} ke {$status}."], 422,
                ));
            }

            $pesanan->status = $status;
            if ($status === Pesanan::LUNAS) {
                $pesanan->tanggalLunas = now();
                // Paid orders become income automatically, exactly once (idPesanan is unique).
                LaporanKeuangan::firstOrCreate(['idPesanan' => $pesanan->idPesanan], [
                    'idAdmin' => $request->user()->getAuthIdentifier(),
                    'jenis' => 'Pemasukan',
                    'namaSumber' => "Pesanan {$pesanan->nomorPesanan}",
                    'jumlah' => $pesanan->total,
                    'tanggal' => now()->toDateString(),
                    'keterangan' => mb_substr("Otomatis dari pesanan {$pesanan->namaPelanggan}", 0, 100),
                ]);
            }
            $pesanan->save();

            return $pesanan;
        });

        return response()->json(['message' => "Status pesanan diubah menjadi {$status}.", 'pesanan' => $pesanan->load('detail')]);
    }

    /** Order number BR-YYYYMMDD-NNNN, counting up per day. Retries if two checkouts pick the same number. */
    private function simpan(array $kolom, array $detail): Pesanan
    {
        for ($percobaan = 1; ; $percobaan++) {
            try {
                return DB::transaction(function () use ($kolom, $detail) {
                    $awalan = 'BR-'.now()->format('Ymd').'-';
                    $terakhir = Pesanan::where('nomorPesanan', 'like', $awalan.'%')->max('nomorPesanan');
                    $urutan = $terakhir ? ((int) substr($terakhir, -4)) + 1 : 1;

                    $pesanan = Pesanan::create($kolom + ['nomorPesanan' => $awalan.str_pad((string) $urutan, 4, '0', STR_PAD_LEFT)]);
                    $pesanan->detail()->createMany($detail);

                    return $pesanan;
                });
            } catch (QueryException $exception) {
                if ($percobaan >= 3) {
                    throw $exception;
                }
            }
        }
    }

    /** Whole rupiah stay integers in JSON (90000, not 90000.0). */
    private static function angka(int $sen): int|float
    {
        return $sen % 100 === 0 ? intdiv($sen, 100) : $sen / 100;
    }
}
