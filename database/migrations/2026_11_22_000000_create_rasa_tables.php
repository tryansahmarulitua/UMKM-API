<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flavour labels (Project Plan §1.2). The five defaults are inserted here rather than by the seeder,
 * so they exist on a server where only the migration SQL is run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_rasa', function (Blueprint $table) {
            $table->id('idRasa');
            $table->string('namaRasa', 20)->unique();
            $table->timestamps();
        });

        Schema::create('tb_produk_rasa', function (Blueprint $table) {
            $table->unsignedBigInteger('idProduk');
            $table->unsignedBigInteger('idRasa');
            $table->primary(['idProduk', 'idRasa']);

            $table->foreign('idProduk')->references('idProduk')->on('tb_produk')->cascadeOnDelete();
            $table->foreign('idRasa')->references('idRasa')->on('tb_rasa')->cascadeOnDelete();
        });

        $now = now();
        DB::table('tb_rasa')->insert(array_map(
            fn (string $nama) => ['namaRasa' => $nama, 'created_at' => $now, 'updated_at' => $now],
            ['Manis', 'Asin', 'Gurih', 'Pedas', 'Renyah'],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_produk_rasa');
        Schema::dropIfExists('tb_rasa');
    }
};
