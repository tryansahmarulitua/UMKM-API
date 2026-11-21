<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Online ordering, payment settings, and the link from automatic income to its order (Project Plan §1.2). */
return new class extends Migration
{
    public function up(): void
    {
        // One row: transfer account and WhatsApp number for orders.
        Schema::create('tb_pengaturan', function (Blueprint $table) {
            $table->id('idPengaturan');
            $table->string('namaBank', 50)->nullable();
            $table->string('nomorRekening', 30)->nullable();
            $table->string('atasNama', 100)->nullable();
            $table->string('nomorWhatsApp', 15)->nullable();
            $table->timestamps();
        });

        Schema::create('tb_pesanan', function (Blueprint $table) {
            $table->id('idPesanan');
            $table->string('nomorPesanan', 20)->unique();
            $table->string('namaPelanggan', 100);
            $table->string('noWhatsApp', 15);
            $table->enum('pengiriman', ['Ambil sendiri', 'Dikirim']);
            $table->decimal('total', 15, 2);
            $table->enum('status', ['Menunggu Pembayaran', 'Lunas', 'Selesai', 'Dibatalkan'])->default('Menunggu Pembayaran');
            $table->timestamp('tanggalLunas')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // Name and price are copied, so later product changes do not change old orders.
        Schema::create('tb_detail_pesanan', function (Blueprint $table) {
            $table->id('idDetail');
            $table->unsignedBigInteger('idPesanan');
            $table->unsignedBigInteger('idProduk')->nullable();
            $table->string('namaProduk', 100);
            $table->decimal('harga', 15, 2);
            $table->unsignedSmallInteger('jumlah');
            $table->decimal('subtotal', 15, 2);

            $table->foreign('idPesanan')->references('idPesanan')->on('tb_pesanan')->cascadeOnDelete();
            $table->foreign('idProduk')->references('idProduk')->on('tb_produk')->nullOnDelete();
        });

        // Unique: each paid order becomes income exactly once.
        Schema::table('tb_laporankeuangan', function (Blueprint $table) {
            $table->unsignedBigInteger('idPesanan')->nullable()->unique()->after('idAdmin');
            $table->foreign('idPesanan')->references('idPesanan')->on('tb_pesanan')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tb_laporankeuangan', function (Blueprint $table) {
            $table->dropForeign(['idPesanan']);
            $table->dropUnique(['idPesanan']);
            $table->dropColumn('idPesanan');
        });
        Schema::dropIfExists('tb_detail_pesanan');
        Schema::dropIfExists('tb_pesanan');
        Schema::dropIfExists('tb_pengaturan');
    }
};
