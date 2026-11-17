<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfilUMKM extends Model
{
    protected $table = 'tb_profilumkm';
    protected $primaryKey = 'idProfil';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'idAdmin',
        'namaUMKM',
        'deskripsi',
        'alamat',
        'noTelepon',
        'email',
        'foto',
        'mediaSosial',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'idAdmin');
    }
}
