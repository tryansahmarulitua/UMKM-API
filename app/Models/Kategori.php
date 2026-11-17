<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kategori extends Model
{
    protected $table = 'tb_kategoriproduk';
    protected $primaryKey = 'idKategori';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = ['namaKategori'];

    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class, 'idKategori');
    }
}
