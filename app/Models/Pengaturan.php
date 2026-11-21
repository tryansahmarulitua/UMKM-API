<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Single row: transfer account and WhatsApp number shown at checkout. */
class Pengaturan extends Model
{
    protected $table = 'tb_pengaturan';
    protected $primaryKey = 'idPengaturan';

    protected $fillable = ['namaBank', 'nomorRekening', 'atasNama', 'nomorWhatsApp'];

    public static function aktif(): self
    {
        return self::query()->orderBy('idPengaturan')->first() ?? new self();
    }

    /** Public payment details and the number customers send their order to. */
    public static function untukPembeli(): array
    {
        $pengaturan = self::aktif();

        return [
            'pembayaran' => $pengaturan->only(['namaBank', 'nomorRekening', 'atasNama']),
            // Until the owner fills in Pengaturan, the profile number keeps working.
            'whatsapp' => $pengaturan->nomorWhatsApp ?: ProfilUMKM::latest('updated_at')->value('noTelepon'),
        ];
    }
}
