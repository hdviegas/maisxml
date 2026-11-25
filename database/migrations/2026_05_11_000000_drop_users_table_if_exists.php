<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy Mais XML authenticates against `usuario`, not Laravel's default `users` table.
 * Drop `users` if it was created by an older migration so the schema matches production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('users');
    }

    public function down(): void
    {
        //
    }
};
