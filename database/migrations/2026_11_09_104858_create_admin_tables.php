<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. tb_admin
        Schema::create('tb_admin', function (Blueprint $table) {
            $table->id('idAdmin');
            $table->string('nama', 20);
            $table->string('email', 25)->unique();
            $table->string('password', 255);
            $table->timestamps();
        });

        // 2. tb_kategoriproduk
        Schema::create('tb_kategoriproduk', function (Blueprint $table) {
            $table->id('idKategori');
            $table->string('namaKategori', 20);
            $table->timestamps();
        });

        // 3. tb_produk
        Schema::create('tb_produk', function (Blueprint $table) {
            $table->id('idProduk');
            $table->unsignedBigInteger('idKategori');
            $table->unsignedBigInteger('idAdmin');
            $table->string('namaProduk', 100);
            $table->string('deskripsi', 255);
            $table->decimal('harga', 8, 2);
            $table->string('foto', 255)->nullable();
            $table->boolean('tampil')->default(true);
            $table->timestamps();

            $table->foreign('idKategori')->references('idKategori')->on('tb_kategoriproduk')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('idAdmin')->references('idAdmin')->on('tb_admin')->onUpdate('cascade')->onDelete('restrict');
        });

        // 4. tb_laporankeuangan
        Schema::create('tb_laporankeuangan', function (Blueprint $table) {
            $table->id('idLK');
            $table->unsignedBigInteger('idAdmin');
            $table->string('namaSumber', 100);
            $table->decimal('jumlah', 8, 2);
            $table->date('tanggal');
            $table->string('keterangan', 100)->nullable();
            $table->timestamps();

            $table->foreign('idAdmin')->references('idAdmin')->on('tb_admin')->onUpdate('cascade')->onDelete('restrict');
        });

        // 5. tb_profilumkm
        Schema::create('tb_profilumkm', function (Blueprint $table) {
            $table->id('idProfil');
            $table->unsignedBigInteger('idAdmin')->unique();
            $table->string('namaUMKM', 100);
            $table->text('deskripsi');
            $table->string('alamat', 255);
            $table->string('noTelepon', 15);
            $table->string('email', 100)->nullable();
            $table->string('foto', 255)->nullable();
            $table->string('mediaSosial', 255)->nullable();
            $table->timestamps();

            $table->foreign('idAdmin')->references('idAdmin')->on('tb_admin')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_laporankeuangan');
        Schema::dropIfExists('tb_profilumkm');
        Schema::dropIfExists('tb_produk');
        Schema::dropIfExists('tb_kategoriproduk');
        Schema::dropIfExists('tb_admin');
    }
};
