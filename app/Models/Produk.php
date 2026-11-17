<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produk extends Model
{
    use HasFactory;

    protected $table = 'tb_produk';
    protected $primaryKey = 'idProduk';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'idKategori',
        'idAdmin',
        'namaProduk',
        'deskripsi',
        'harga',
        'foto',
        'tampil',
    ];

    protected $casts = [
        'harga'      => 'decimal:2',
        'tampil'     => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'idKategori');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'idAdmin');
    }
}
