<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_laporankeuangan', function (Blueprint $table) {
            $table->enum('jenis', ['Pemasukan', 'Pengeluaran'])->default('Pemasukan')->after('idAdmin');
        });
    }

    public function down(): void
    {
        Schema::table('tb_laporankeuangan', function (Blueprint $table) {
            $table->dropColumn('jenis');
        });
    }
};
