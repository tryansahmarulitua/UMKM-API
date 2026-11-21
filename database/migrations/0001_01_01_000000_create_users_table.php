<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // Kept only because servers already recorded it as run. Login uses tb_admin; the users table was never needed.
    public function up(): void
    {
    }

    public function down(): void
    {
    }
};
