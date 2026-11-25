<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production legacy databases already had `usuario` before Laravel; add verification column only when absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('legacy.usuario_table', 'usuario');

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'email_verified_at')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->timestamp('email_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        $table = (string) config('legacy.usuario_table', 'usuario');

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'email_verified_at')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropColumn('email_verified_at');
        });
    }
};
