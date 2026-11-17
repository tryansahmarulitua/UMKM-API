<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanKeuangan extends Model
{
    protected $table = 'tb_laporankeuangan';
    protected $primaryKey = 'idLK';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'idAdmin',
        'jenis',
        'namaSumber',
        'jumlah',
        'tanggal',
        'keterangan',
    ];

    protected $casts = [
        'jumlah'   => 'decimal:2',
        'tanggal'  => 'date',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'idAdmin');
    }
}
