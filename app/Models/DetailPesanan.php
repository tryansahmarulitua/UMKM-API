<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailPesanan extends Model
{
    protected $table = 'tb_detail_pesanan';
    protected $primaryKey = 'idDetail';
    public $timestamps = false;

    protected $fillable = ['idPesanan', 'idProduk', 'namaProduk', 'harga', 'jumlah', 'subtotal'];

    protected $casts = [
        'harga' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'jumlah' => 'integer',
    ];
}
