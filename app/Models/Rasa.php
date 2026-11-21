<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Rasa extends Model
{
    protected $table = 'tb_rasa';
    protected $primaryKey = 'idRasa';

    protected $fillable = ['namaRasa'];

    protected $hidden = ['pivot'];

    public function produk(): BelongsToMany
    {
        return $this->belongsToMany(Produk::class, 'tb_produk_rasa', 'idRasa', 'idProduk');
    }
}
