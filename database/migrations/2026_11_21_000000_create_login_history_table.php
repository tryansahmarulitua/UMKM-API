<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Login history used to live in the `sessions` table, where Laravel's session cleanup deleted it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idAdmin');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('idAdmin')->references('idAdmin')->on('tb_admin')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_history');
    }
};
