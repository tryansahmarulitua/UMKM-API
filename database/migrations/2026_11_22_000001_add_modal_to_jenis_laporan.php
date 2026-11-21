<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Business capital (modal) as a third record type (Project Plan §1.2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_laporankeuangan', function (Blueprint $table) {
            $table->enum('jenis', ['Pemasukan', 'Pengeluaran', 'Modal'])->default('Pemasukan')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tb_laporankeuangan', function (Blueprint $table) {
            $table->enum('jenis', ['Pemasukan', 'Pengeluaran'])->default('Pemasukan')->change();
        });
    }
};
