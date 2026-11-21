<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pesanan extends Model
{
    public const MENUNGGU = 'Menunggu Pembayaran';
    public const LUNAS = 'Lunas';
    public const SELESAI = 'Selesai';
    public const DIBATALKAN = 'Dibatalkan';

    /** Valid status changes (Project Plan §4.5): Menunggu -> Lunas -> Selesai, or Menunggu -> Dibatalkan. */
    public const ALUR = [
        self::MENUNGGU => [self::LUNAS, self::DIBATALKAN],
        self::LUNAS => [self::SELESAI],
        self::SELESAI => [],
        self::DIBATALKAN => [],
    ];

    protected $table = 'tb_pesanan';
    protected $primaryKey = 'idPesanan';

    protected $fillable = ['nomorPesanan', 'namaPelanggan', 'noWhatsApp', 'pengiriman', 'total', 'status', 'tanggalLunas'];

    protected $casts = [
        'total' => 'decimal:2',
        'tanggalLunas' => 'datetime',
    ];

    public function detail(): HasMany
    {
        return $this->hasMany(DetailPesanan::class, 'idPesanan');
    }

    /**
     * Orders still unpaid 24 hours after checkout are cancelled. The host has no cron, so this runs
     * whenever order data is opened (Project Plan §4.5, FT12).
     */
    public static function batalkanYangKedaluwarsa(): void
    {
        self::where('status', self::MENUNGGU)
            ->where('created_at', '<=', now()->subDay())
            ->update(['status' => self::DIBATALKAN, 'updated_at' => now()]);
    }

    public function bolehMenjadi(string $status): bool
    {
        return in_array($status, self::ALUR[$this->status] ?? [], true);
    }
}
